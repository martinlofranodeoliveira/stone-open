<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\View\View;
use App\Support\Env;
use App\Application\CardService;

final class AdminUtilsController
{
    // Base URL helper (not credentials). Allows optional override via env only for host selection.
    private function baseCoreUrlForEnv(string $env): string
    {
        $env = strtolower($env);
        $def = 'https://api.pagar.me/core/v5';
        if ($env === 'production') return rtrim(Env::get('STONE_BASE_URL_PRODUCTION', $def), '/');
        return rtrim(Env::get('STONE_BASE_URL_SANDBOX', $def), '/');
    }

    // Fetch pagarme_accounts row for a given site
    private function accountForSite(array $site): ?array
    {
        try {
            \App\Infrastructure\Stone\StoneAccount::ensureSchema();
            $accId = (int)($site['pagarme_account_id'] ?? 0);
            if ($accId <= 0) return null;
            $acc = \App\Infrastructure\DB\Database::run('SELECT * FROM pagarme_accounts WHERE id = ? LIMIT 1', [$accId])->fetch();
            return $acc ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function maskKey(?string $key): string
    {
        $k = (string)($key ?? '');
        if ($k === '') return '(vazio)';
        $len = strlen($k);
        return $len <= 8 ? str_repeat('*', $len) : (substr($k, 0, 3) . str_repeat('*', max(0, $len - 7)) . substr($k, -4));
    }

    // Environment info driven by the site's linked pagarme_account
    private function envInfoForSite(array $site): ?array
    {
        $acc = $this->accountForSite($site);
        if (!$acc) return null;
        $env = strtolower((string)($acc['active_env'] ?? 'sandbox'));
        if (!in_array($env, ['sandbox','production'], true)) $env = 'sandbox';
        $api = $env === 'production' ? (string)($acc['api_key_production'] ?? '') : (string)($acc['api_key_sandbox'] ?? '');
        return [
            'env' => $env,
            'api_key_masked' => $this->maskKey($api),
            'base_url' => $this->baseCoreUrlForEnv($env),
        ];
    }

    private function debugLog(string $feature, array $data): void
    {
        try {
            if ((string)Env::get('LOG_LOCAL_DEBUG', '0') !== '1') return;
            $root = dirname(__DIR__, 4);
            $dir = $root . '/storage/logs';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $file = $dir . '/local-debug-' . date('Y-m-d') . '.log';
            $row = ['time' => date('Y-m-d H:i:s'), 'feature' => $feature] + $data;
            @file_put_contents($file, json_encode($row, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    // Quick auth check for selected site's account (GET /customers)
    private function authQuickCheckForSite(array $site): ?array
    {
        $acc = $this->accountForSite($site);
        if (!$acc) return null;
        $env = strtolower((string)($acc['active_env'] ?? 'sandbox'));
        if (!in_array($env, ['sandbox','production'], true)) $env = 'sandbox';
        $key = $env === 'production' ? (string)($acc['api_key_production'] ?? '') : (string)($acc['api_key_sandbox'] ?? '');
        $base = $this->baseCoreUrlForEnv($env);
        $url = $base . '/customers?limit=1';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Basic ' . base64_encode($key . ':')],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch) ?: null;
        $code = (int)(curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0);
        curl_close($ch);
        return ['http' => $code, 'error' => $err, 'ok' => ($code >= 200 && $code < 300)];
    }
    public function index(Request $req): Response
    {
        $html = View::render('admin/utils', [
            'prefix' => $this->prefix(),
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    // GET /_admin/utils/cards/quote?amount_cents=...&bin=...&max_parcelas=...
    public function cardsQuote(Request $req): Response
    {
        $q = $req->query;
        $amount = (int)($q['amount_cents'] ?? 0);
        $bin = (string)($q['bin'] ?? '');
        $max = isset($q['max_parcelas']) ? (int)$q['max_parcelas'] : null;
        $payer = strtolower((string)($q['payer'] ?? 'user'));
        if (!in_array($payer, ['user','merchant'], true)) $payer = 'user';
        $free = isset($q['free_installments']) ? (int)$q['free_installments'] : null;
        if ($payer === 'merchant') {
            if (!$free || $free < 1) $free = 1; // padrão: pelo menos 1 sem juros quando vendedor paga
            if ($max !== null && $max > 0 && $free > 0 && $max > $free) {
                $max = $free; // evita parcelas com juros quando vendedor subsidia
            }
        } else {
            $free = null; // ignorar free_installments quando comprador paga
        }
        $rate = null;
        if (isset($q['interest_rate']) && $q['interest_rate'] !== '') {
            $rateStr = str_replace(['%',' '], '', (string)$q['interest_rate']);
            $rateStr = str_replace(',', '.', $rateStr);
            $rate = is_numeric($rateStr) ? (float)$rateStr : null; // % ao mês
        }
        $minInstallment = isset($q['min_installment_cents']) ? (int)$q['min_installment_cents'] : null;
        $bin = preg_replace('/\D+/', '', $bin);
        if (strlen($bin) < 6 || $amount < 1) {
            return Response::json(['error' => 'Parâmetros inválidos: informe valor e BIN (6 dígitos).'], 400);
        }
        try {
            $svc = new CardService();
            $forceNoInterest = ($payer === 'merchant');
            $res = $svc->quote($bin, $amount, $max, $free, $rate, $minInstallment, null, $forceNoInterest);
            // Anexa metadados do cálculo
            $res['interest_payer'] = $payer;
            if ($payer === 'merchant') $res['free_installments'] = $free ?? 1;
            return Response::json($res, 200);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    // PIX helper (form)
    public function pixForm(Request $req): Response
    {
        $sites = \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll();
        $html = \App\Infrastructure\View\View::render('admin/utils_pix', [
            'prefix' => $this->prefix(),
            'sites' => $sites,
            'csrf' => \App\Support\Security\Csrf::token(),
            'result' => null,
            // No site escolhido ainda → não exibir credenciais de .env
            'env_info' => null,
            'auth_check' => null,
        ]);
        return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
    }

    // PIX helper (submit)
    public function pixCreate(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return new Response(400, ['Content-Type'=>'text/plain'], 'Invalid CSRF');
        $siteId = (int)($post['site_id'] ?? 0);
        $site = \App\Infrastructure\DB\Database::run('SELECT * FROM sites WHERE id = ? LIMIT 1', [$siteId])->fetch();
        if (!$site) return new Response(404, ['Content-Type'=>'text/plain'], 'Site not found');

        // Build simplified payload
        $amount = (int)($post['amount_cents'] ?? 0);
        $ref = (string)($post['client_reference_id'] ?? 'ref_' . date('YmdHis'));
        $expiresAtRaw = trim((string)($post['expires_at'] ?? ''));
        $expiresIn = (int)($post['expires_in'] ?? 0); // legacy fallback
        $expiresAt = null;
        if ($expiresAtRaw !== '') {
            try {
                $expiresAt = (new \DateTimeImmutable($expiresAtRaw))->format('Y-m-d\TH:i:sP');
            } catch (\Throwable $e) {
                $expiresAt = null;
            }
            if (!$expiresAt) {
                $html = \App\Infrastructure\View\View::render('admin/utils_pix', [
                    'prefix' => $this->prefix(), 'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(), 'csrf' => \App\Support\Security\Csrf::token(),
                    'error' => 'Expiração inválida. Informe um date-time válido.',
                    'env_info' => $this->envInfoForSite($site),
                ]);
                return new Response(400, ['Content-Type'=>'text/html; charset=utf-8'], $html);
            }
        }
        if (!$expiresAt) {
            $seconds = $expiresIn > 0 ? $expiresIn : 3600;
            if ($seconds < 60) {
                $html = \App\Infrastructure\View\View::render('admin/utils_pix', [
                    'prefix' => $this->prefix(), 'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(), 'csrf' => \App\Support\Security\Csrf::token(),
                    'error' => 'Expiração deve ser no mínimo 60 segundos.',
                    'env_info' => $this->envInfoForSite($site),
                ]);
                return new Response(400, ['Content-Type'=>'text/html; charset=utf-8'], $html);
            }
            $seconds = max(60, $seconds);
            $expiresAt = (new \DateTimeImmutable('now'))->modify('+' . $seconds . ' seconds')->format('Y-m-d\TH:i:sP');
        }
        $customer = [
            'name' => (string)($post['customer_name'] ?? ''),
            'email' => (string)($post['customer_email'] ?? ''),
            'document' => (string)($post['customer_document'] ?? ''),
        ];
        // Definir type conforme documento (CPF=individual, CNPJ=company)
        $docDigits = preg_replace('/\D+/', '', (string)$customer['document']);
        if ($docDigits !== '') {
            if (strlen($docDigits) === 11) $customer['type'] = 'individual';
            elseif (strlen($docDigits) === 14) $customer['type'] = 'company';
        }
        $cc = preg_replace('/\D+/', '', (string)($post['customer_phone_cc'] ?? '55')) ?: '55';
        $aa = preg_replace('/\D+/', '', (string)($post['customer_phone_area'] ?? '11')) ?: '11';
        $nn = preg_replace('/\D+/', '', (string)($post['customer_phone_number'] ?? ''));
        if ($nn !== '') {
            $customer['phones'] = [ 'mobile_phone' => [ 'country_code' => $cc, 'area_code' => $aa, 'number' => $nn ] ];
        }
        $input = [
            'amount_cents' => $amount,
            'client_reference_id' => $ref,
            'currency' => 'BRL',
            'payment_method_type' => 'pix',
            'expires_at' => $expiresAt,
            'customer' => $customer,
        ];
        if ($amount <= 0) {
            $html = \App\Infrastructure\View\View::render('admin/utils_pix', [
                'prefix' => $this->prefix(), 'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(), 'csrf' => \App\Support\Security\Csrf::token(),
                'error' => 'Informe um valor válido (> R$ 0,00).',
                'env_info' => $this->envInfoForSite($site),
            ]);
            return new Response(400, ['Content-Type'=>'text/html; charset=utf-8'], $html);
        }
        if (!$expiresAt) {
            $html = \App\Infrastructure\View\View::render('admin/utils_pix', [
                'prefix' => $this->prefix(), 'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(), 'csrf' => \App\Support\Security\Csrf::token(),
                'error' => 'Expiração inválida. Informe um date-time válido.',
                'env_info' => $this->envInfoForSite($site),
            ]);
            return new Response(400, ['Content-Type'=>'text/html; charset=utf-8'], $html);
        }
        // Local debug
        $this->debugLog('utils_pix_pre', ['env'=>$this->envInfoForSite($site) ?? [],'request'=>$input,'site_id'=>(int)($site['id'] ?? 0)]);
        try {
            $svc = new \App\Application\PaymentService();
            $payment = $svc->create($site, $input);
            $this->debugLog('utils_pix_post', ['env'=>$this->envInfoForSite($site) ?? [],'response'=> json_decode((string)($payment['response_payload'] ?? ''), true),'site_id'=>(int)($site['id'] ?? 0)]);
            $html = \App\Infrastructure\View\View::render('admin/utils_pix', [
                'prefix' => $this->prefix(),
                'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(),
                'csrf' => \App\Support\Security\Csrf::token(),
                'result' => $payment,
                'env_info' => $this->envInfoForSite($site),
                'auth_check' => $this->authQuickCheckForSite($site),
                'debug' => ['request'=>$input,'response'=> json_decode((string)($payment['response_payload'] ?? ''), true)],
            ]);
            return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
        } catch (\Throwable $e) {
            $this->debugLog('utils_pix_error', ['env'=>$this->envInfoForSite($site) ?? [],'error'=>$e->getMessage(),'request'=>$input,'site_id'=>(int)($site['id'] ?? 0)]);
            $html = \App\Infrastructure\View\View::render('admin/utils_pix', [
                'prefix' => $this->prefix(),
                'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(),
                'csrf' => \App\Support\Security\Csrf::token(),
                'error' => $e->getMessage(),
                'env_info' => $this->envInfoForSite($site),
                'auth_check' => $this->authQuickCheckForSite($site),
                'debug' => ['request'=>$input],
            ]);
            return new Response(500, ['Content-Type'=>'text/html; charset=utf-8'], $html);
        }
    }

    // Boleto helper (form)
    public function boletoForm(Request $req): Response
    {
        $sites = \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll();
        $html = \App\Infrastructure\View\View::render('admin/utils_boleto', [
            'prefix' => $this->prefix(),
            'sites' => $sites,
            'csrf' => \App\Support\Security\Csrf::token(),
            'result' => null,
            'env_info' => null,
            'auth_check' => null,
        ]);
        return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
    }

    // Boleto helper (submit)
    public function boletoCreate(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return new Response(400, ['Content-Type'=>'text/plain'], 'Invalid CSRF');
        $siteId = (int)($post['site_id'] ?? 0);
        $site = \App\Infrastructure\DB\Database::run('SELECT * FROM sites WHERE id = ? LIMIT 1', [$siteId])->fetch();
        if (!$site) return new Response(404, ['Content-Type'=>'text/plain'], 'Site not found');

        $amount = (int)($post['amount_cents'] ?? 0);
        $ref = (string)($post['client_reference_id'] ?? 'ref_' . date('YmdHis'));
        $dueAtRaw = trim((string)($post['due_at'] ?? ''));
        $dueAt = null;
        if ($dueAtRaw !== '') {
            try {
                $dueAt = (new \DateTimeImmutable($dueAtRaw))->format('Y-m-d\TH:i:sP');
            } catch (\Throwable $e) {
                $dueAt = null;
            }
        }
        if (!$dueAt) {
            $html = \App\Infrastructure\View\View::render('admin/utils_boleto', [
                'prefix' => $this->prefix(), 'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(), 'csrf' => \App\Support\Security\Csrf::token(),
                'error' => 'Informe uma data de vencimento válida.',
                'env_info' => $this->envInfoForSite($site),
            ]);
            return new Response(400, ['Content-Type'=>'text/html; charset=utf-8'], $html);
        }

        $customer = [
            'name' => (string)($post['customer_name'] ?? ''),
            'email' => (string)($post['customer_email'] ?? ''),
            'document' => (string)($post['customer_document'] ?? ''),
        ];
        $docDigits = preg_replace('/\D+/', '', (string)$customer['document']);
        if ($docDigits !== '') {
            if (strlen($docDigits) === 11) $customer['type'] = 'individual';
            elseif (strlen($docDigits) === 14) $customer['type'] = 'company';
        }

        $instructionsRaw = trim((string)($post['instructions'] ?? ''));
        $instructions = [];
        if ($instructionsRaw !== '') {
            foreach (preg_split('/\r\n|\n|\r/', $instructionsRaw) as $line) {
                $line = trim((string)$line);
                if ($line !== '') $instructions[] = $line;
            }
        }

        $boleto = [
            'due_at' => $dueAt,
            'document_number' => trim((string)($post['document_number'] ?? '')),
            'bank' => trim((string)($post['bank'] ?? '')),
            'type' => trim((string)($post['type'] ?? '')),
        ];
        if (!empty($instructions)) $boleto['instructions'] = $instructions;
        // remove empty
        foreach ($boleto as $k => $v) { if ($v === '' || $v === null) unset($boleto[$k]); }

        $input = [
            'amount_cents' => $amount,
            'client_reference_id' => $ref,
            'currency' => 'BRL',
            'payment_method_type' => 'boleto',
            'due_at' => $dueAt,
            'customer' => $customer,
            'boleto' => $boleto,
        ];
        if ($amount <= 0) {
            $html = \App\Infrastructure\View\View::render('admin/utils_boleto', [
                'prefix' => $this->prefix(), 'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(), 'csrf' => \App\Support\Security\Csrf::token(),
                'error' => 'Informe um valor válido (> R$ 0,00).',
                'env_info' => $this->envInfoForSite($site),
            ]);
            return new Response(400, ['Content-Type'=>'text/html; charset=utf-8'], $html);
        }
        $this->debugLog('utils_boleto_pre', ['env'=>$this->envInfoForSite($site) ?? [],'request'=>$input,'site_id'=>(int)($site['id'] ?? 0)]);
        try {
            $svc = new \App\Application\PaymentService();
            $payment = $svc->create($site, $input);
            $this->debugLog('utils_boleto_post', ['env'=>$this->envInfoForSite($site) ?? [],'response'=> json_decode((string)($payment['response_payload'] ?? ''), true),'site_id'=>(int)($site['id'] ?? 0)]);
            $html = \App\Infrastructure\View\View::render('admin/utils_boleto', [
                'prefix' => $this->prefix(),
                'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(),
                'csrf' => \App\Support\Security\Csrf::token(),
                'result' => $payment,
                'env_info' => $this->envInfoForSite($site),
                'auth_check' => $this->authQuickCheckForSite($site),
                'debug' => ['request'=>$input,'response'=> json_decode((string)($payment['response_payload'] ?? ''), true)],
            ]);
            return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
        } catch (\Throwable $e) {
            $this->debugLog('utils_boleto_error', ['env'=>$this->envInfoForSite($site) ?? [],'error'=>$e->getMessage(),'request'=>$input,'site_id'=>(int)($site['id'] ?? 0)]);
            $html = \App\Infrastructure\View\View::render('admin/utils_boleto', [
                'prefix' => $this->prefix(),
                'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(),
                'csrf' => \App\Support\Security\Csrf::token(),
                'error' => $e->getMessage(),
                'env_info' => $this->envInfoForSite($site),
                'auth_check' => $this->authQuickCheckForSite($site),
                'debug' => ['request'=>$input],
            ]);
            return new Response(500, ['Content-Type'=>'text/html; charset=utf-8'], $html);
        }
    }

    // Credit card helper (form)
    public function cardForm(Request $req): Response
    {
        $sites = \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll();
        $html = \App\Infrastructure\View\View::render('admin/utils_card', [
            'prefix' => $this->prefix(),
            'sites' => $sites,
            'csrf' => \App\Support\Security\Csrf::token(),
            'result' => null,
            'env_info' => null,
        ]);
        return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
    }

    public function cardCreate(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return new Response(400, ['Content-Type'=>'text/plain'], 'Invalid CSRF');
        $siteId = (int)($post['site_id'] ?? 0);
        $site = \App\Infrastructure\DB\Database::run('SELECT * FROM sites WHERE id = ? LIMIT 1', [$siteId])->fetch();
        if (!$site) return new Response(404, ['Content-Type'=>'text/plain'], 'Site not found');

        $amount = (int)($post['amount_cents'] ?? 0);
        $ref = (string)($post['client_reference_id'] ?? 'ref_' . date('YmdHis'));
        $installments = max(1, (int)($post['installments'] ?? 1));
        $opType = (string)($post['operation_type'] ?? 'auth_and_capture');

        // Tokenize card
        $card = [
            'number' => preg_replace('/\D+/', '', (string)$post['card_number']),
            'holder_name' => (string)$post['card_holder'],
            'exp_month' => (int)$post['card_exp_month'],
            'exp_year' => (int)$post['card_exp_year'],
            'cvv' => (string)$post['card_cvv'],
        ];
        if ($amount <= 0) {
            $html = \App\Infrastructure\View\View::render('admin/utils_card', [
                'prefix' => $this->prefix(), 'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(), 'csrf' => \App\Support\Security\Csrf::token(),
                'error' => 'Informe um valor válido (> R$ 0,00).', 'env_info' => $this->envInfoForSite($site),
            ]);
            return new Response(400, ['Content-Type'=>'text/html; charset=utf-8'], $html);
        }
        if (strlen($card['number']) < 13 || strlen($card['number']) > 19 || $card['exp_month'] < 1 || $card['exp_month'] > 12 || $card['exp_year'] < 24 || $card['exp_year'] > 99 || strlen(preg_replace('/\D/','',$card['cvv'])) < 3) {
            $html = \App\Infrastructure\View\View::render('admin/utils_card', [
                'prefix' => $this->prefix(), 'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(), 'csrf' => \App\Support\Security\Csrf::token(),
                'error' => 'Dados do cartão inválidos. Verifique número, validade e CVV.', 'env_info' => $this->envInfoForSite($site),
            ]);
            return new Response(400, ['Content-Type'=>'text/html; charset=utf-8'], $html);
        }
        $stone = \App\Infrastructure\Stone\StoneClient::forSite($site);
        try { $tokRes = $stone->tokenizeCard($card); } catch (\Throwable $e) { $tokRes = []; }
        $token = (string)($tokRes['id'] ?? ($tokRes['token'] ?? ''));
        if ($token === '') {
            $html = \App\Infrastructure\View\View::render('admin/utils_card', [
                'prefix' => $this->prefix(), 'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(), 'csrf' => \App\Support\Security\Csrf::token(),
                'error' => 'Falha ao tokenizar o cartão',
            ]);
            return new Response(400, ['Content-Type'=>'text/html; charset=utf-8'], $html);
        }

        $customer = [
            'name' => (string)($post['customer_name'] ?? ''),
            'email' => (string)($post['customer_email'] ?? ''),
            'document' => (string)($post['customer_document'] ?? ''),
        ];
        $input = [
            'amount_cents' => $amount,
            'client_reference_id' => $ref,
            'currency' => 'BRL',
            'payment_method_type' => 'credit_card',
            'installments' => $installments,
            'operation_type' => $opType,
            'card_token' => $token,
            'customer' => $customer,
        ];
        $this->debugLog('utils_card_pre', ['env'=>$this->envInfoForSite($site) ?? [],'request'=>$input,'site_id'=>(int)($site['id'] ?? 0)]);
        try {
            $svc = new \App\Application\PaymentService();
            $payment = $svc->create($site, $input);
            $html = \App\Infrastructure\View\View::render('admin/utils_card', [
                'prefix' => $this->prefix(),
                'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(),
                'csrf' => \App\Support\Security\Csrf::token(),
                'result' => $payment, 'env_info' => $this->envInfoForSite($site),
                'debug' => ['request'=>$input,'response'=> json_decode((string)($payment['response_payload'] ?? ''), true)],
            ]);
            return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
        } catch (\Throwable $e) {
            $this->debugLog('utils_card_error', ['env'=>$this->envInfoForSite($site) ?? [],'error'=>$e->getMessage(),'request'=>$input,'site_id'=>(int)($site['id'] ?? 0)]);
            $html = \App\Infrastructure\View\View::render('admin/utils_card', [
                'prefix' => $this->prefix(),
                'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(),
                'csrf' => \App\Support\Security\Csrf::token(),
                'error' => $e->getMessage(), 'env_info' => $this->envInfoForSite($site), 'debug'=>['request'=>$input],
            ]);
            return new Response(500, ['Content-Type'=>'text/html; charset=utf-8'], $html);
        }
    }

    // Payment link helper
    public function linkForm(Request $req): Response
    {
        $sites = \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll();
        $html = \App\Infrastructure\View\View::render('admin/utils_link', [
            'prefix' => $this->prefix(), 'sites' => $sites, 'csrf' => \App\Support\Security\Csrf::token(), 'result' => null, 'env_info' => null,
        ]);
        return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
    }

    public function linkCreate(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return new Response(400, ['Content-Type'=>'text/plain'], 'Invalid CSRF');
        $siteId = (int)($post['site_id'] ?? 0);
        $site = \App\Infrastructure\DB\Database::run('SELECT * FROM sites WHERE id = ? LIMIT 1', [$siteId])->fetch();
        if (!$site) return new Response(404, ['Content-Type'=>'text/plain'], 'Site not found');

        // Build payload compatible with PaymentLinksController
        $amount = (int)($post['amount_cents'] ?? 0);
        $code = trim((string)($post['code'] ?? ''));
        $desc = trim((string)($post['description'] ?? ''));
        $expires = (int)($post['expires_in'] ?? 0);
        $methods = (array)($post['methods'] ?? []);
        if ($amount <= 0) {
            $html = \App\Infrastructure\View\View::render('admin/utils_link', [
                'prefix' => $this->prefix(), 'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(), 'csrf' => \App\Support\Security\Csrf::token(),
                'error' => 'Informe um valor válido (> R$ 0,00).',
            ]);
            return new Response(400, ['Content-Type'=>'text/html; charset=utf-8'], $html);
        }
        if (empty($methods)) $methods = ['cartao'];
        $payload = [
            'valor_centavos' => $amount,
            'descricao' => $desc ?: 'Pagamento',
            'codigo' => $code,
            'moeda' => 'BRL',
            'expira_em_minutos' => $expires > 0 ? $expires : null,
            'metodos_pagamento' => $methods ?: ['cartao'],
        ];

        // Forge Request to reuse PaymentLinksController
        $apiReq = new \App\Infrastructure\Http\Request();
        $apiReq->method = 'POST';
        $apiReq->path = '/api/v1/payment-links';
        $apiReq->headers = ['content-type' => 'application/json'];
        $apiReq->rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $apiReq->parsedBody = null; $apiReq->attributes = ['site' => $site];

        $this->debugLog('utils_link_pre', ['env'=>$this->envInfoForSite($site) ?? [],'request'=>$payload,'site_id'=>(int)($site['id'] ?? 0)]);
        $controller = new \App\Controllers\Api\PaymentLinksController();
        $resp = $controller->create($apiReq);
        if ($resp->status >= 400) {
            $html = \App\Infrastructure\View\View::render('admin/utils_link', [
                'prefix' => $this->prefix(), 'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(), 'csrf' => \App\Support\Security\Csrf::token(),
                'error' => $resp->body ?: 'Falha ao criar link', 'env_info' => $this->envInfoForSite($site), 'debug'=>['request'=>$payload,'response'=>$resp->body],
            ]);
            return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
        }
        $data = json_decode($resp->body, true) ?: [];
        $this->debugLog('utils_link_post', ['env'=>$this->envInfoForSite($site) ?? [],'response'=>$data,'site_id'=>(int)($site['id'] ?? 0)]);
        $html = \App\Infrastructure\View\View::render('admin/utils_link', [
            'prefix' => $this->prefix(), 'sites' => \App\Infrastructure\DB\Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll(), 'csrf' => \App\Support\Security\Csrf::token(),
            'result' => $data, 'env_info' => $this->envInfoForSite($site), 'debug'=>['request'=>$payload,'response'=>$data],
        ]);
        return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
    }

    private function prefix(): string
    {
        return rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');
    }
}
