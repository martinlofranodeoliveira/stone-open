<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Infrastructure\Http\{Request, Response};
use App\Application\CardService;
use App\Infrastructure\DB\Database;
use App\Infrastructure\Stone\StoneAccount;
use App\Support\Env;
use App\Support\I18n;

final class CardsController
{
    // POST /api/v1/cards/tokenize (público)
    public function tokenize(Request $req): Response
    {
        $data = $req->json ?? null;
        if (!is_array($data)) {
            $data = [];
            if (is_string($req->rawBody) && $req->rawBody !== '') {
                parse_str($req->rawBody, $tmp);
                if (is_array($tmp) && !empty($tmp)) $data = $tmp;
            }
        }

        $publicKey = (string)($data['public_key'] ?? $data['publicKey'] ?? $data['app_id'] ?? $data['appId'] ?? ($req->header('X-Public-Key') ?? ''));
        $authz = (string)($req->header('Authorization') ?? '');
        $clientId = (string)($req->header('X-Client-Id') ?? '');
        $clientSecret = (string)($req->header('X-Client-Secret') ?? '');
        if ($publicKey === '') {
            $site = null;
            if (stripos($authz, 'bearer ') === 0) {
                $token = trim(substr($authz, 7));
                if ($token !== '') {
                    $site = Database::run('SELECT * FROM sites WHERE api_token = ? AND status = "active" LIMIT 1', [$token])->fetch();
                }
                if (!$site) {
                    return Response::json([
                        'status' => 'error',
                        'message' => 'Não autorizado',
                        'status_code' => 'unauthorized',
                        'http_status' => 401,
                    ], 401);
                }
            } elseif ($clientId !== '' || $clientSecret !== '') {
                if ($clientId === '' || $clientSecret === '') {
                    return Response::json([
                        'status' => 'error',
                        'message' => 'Não autorizado',
                        'status_code' => 'unauthorized',
                        'http_status' => 401,
                    ], 401);
                }
                $site = Database::run('SELECT * FROM sites WHERE client_id = ? AND status = "active" LIMIT 1', [$clientId])->fetch();
                if (!$site || !password_verify($clientSecret, (string)($site['client_secret_hash'] ?? ''))) {
                    return Response::json([
                        'status' => 'error',
                        'message' => 'Não autorizado',
                        'status_code' => 'unauthorized',
                        'http_status' => 401,
                    ], 401);
                }
            }

            if ($site) {
                try {
                    $acc = StoneAccount::resolveForSite($site);
                    $env = strtolower((string)($acc['active_env'] ?? 'sandbox'));
                    $publicKey = $env === 'production'
                        ? (string)($acc['public_key_production'] ?? '')
                        : (string)($acc['public_key_sandbox'] ?? '');
                } catch (\Throwable $e) {
                    return Response::json([
                        'status' => 'error',
                        'message' => 'Conta Pagar.me não configurada para este site',
                        'status_code' => 'pagarme_account_required',
                        'http_status' => 409,
                        'reason' => $e->getMessage(),
                    ], 409);
                }
            }
        }

        if ($publicKey === '') {
            return Response::json([
                'status' => 'error',
                'message' => 'Informe public_key (ou app_id) para tokenizar o cartão.',
                'status_code' => 'validation_error',
                'http_status' => 422,
                'required' => ['public_key'],
                'errors' => ['public_key' => 'public_key é obrigatório'],
                'errors_en' => ['public_key' => 'public_key is required'],
                'errors_pt' => ['public_key' => 'public_key é obrigatório'],
            ], 422);
        }

        $card = [];
        if (isset($data['card']) && is_array($data['card'])) $card = $data['card'];
        $pick = static function(array $candidates): ?string {
            foreach ($candidates as $v) {
                if (is_string($v) || is_numeric($v)) {
                    $s = trim((string)$v);
                    if ($s !== '') return $s;
                }
            }
            return null;
        };
        $card['number'] = $pick([
            $card['number'] ?? null,
            $data['number'] ?? null,
            $data['card_number'] ?? null,
            $data['numero_cartao'] ?? null,
        ]) ?? '';
        $card['holder_name'] = $pick([
            $card['holder_name'] ?? null,
            $data['holder_name'] ?? null,
            $data['card_holder'] ?? null,
            $data['nome_portador'] ?? null,
            $data['nomeTitular'] ?? null,
        ]) ?? '';
        $card['exp_month'] = $pick([
            $card['exp_month'] ?? null,
            $data['exp_month'] ?? null,
            $data['card_exp_month'] ?? null,
            $data['mes_validade'] ?? null,
            $data['mesValidade'] ?? null,
        ]) ?? '';
        $card['exp_year'] = $pick([
            $card['exp_year'] ?? null,
            $data['exp_year'] ?? null,
            $data['card_exp_year'] ?? null,
            $data['ano_validade'] ?? null,
            $data['anoValidade'] ?? null,
        ]) ?? '';
        $card['cvv'] = $pick([
            $card['cvv'] ?? null,
            $data['cvv'] ?? null,
            $data['card_cvv'] ?? null,
            $data['cvc'] ?? null,
        ]) ?? '';

        $card['number'] = preg_replace('/\D+/', '', (string)$card['number']);
        $card['cvv'] = preg_replace('/\D+/', '', (string)$card['cvv']);
        $card['exp_month'] = (int)$card['exp_month'];
        $card['exp_year'] = (int)$card['exp_year'];
        if ($card['exp_year'] > 999) $card['exp_year'] = (int)($card['exp_year'] % 100);

        $errorsPt = [];
        $errorsEn = [];
        if ($card['number'] === '') {
            $errorsPt['card.number'] = 'Número do cartão é obrigatório.';
            $errorsEn['card.number'] = 'Card number is required.';
        } elseif (strlen($card['number']) < 13 || strlen($card['number']) > 19) {
            $errorsPt['card.number'] = 'Número do cartão inválido (13 a 19 dígitos).';
            $errorsEn['card.number'] = 'Invalid card number length (13 to 19 digits).';
        }
        if (trim((string)$card['holder_name']) === '') {
            $errorsPt['card.holder_name'] = 'Nome do titular é obrigatório.';
            $errorsEn['card.holder_name'] = 'Card holder name is required.';
        }
        if ($card['exp_month'] < 1 || $card['exp_month'] > 12) {
            $errorsPt['card.exp_month'] = 'Mês de validade inválido (1-12).';
            $errorsEn['card.exp_month'] = 'Invalid exp_month (1-12).';
        }
        if ($card['exp_year'] <= 0) {
            $errorsPt['card.exp_year'] = 'Ano de validade inválido.';
            $errorsEn['card.exp_year'] = 'Invalid exp_year.';
        }
        if ($card['cvv'] === '') {
            $errorsPt['card.cvv'] = 'CVV é obrigatório.';
            $errorsEn['card.cvv'] = 'CVV is required.';
        } elseif (strlen($card['cvv']) < 3 || strlen($card['cvv']) > 4) {
            $errorsPt['card.cvv'] = 'CVV inválido (3 ou 4 dígitos).';
            $errorsEn['card.cvv'] = 'Invalid CVV length (3 or 4 digits).';
        }

        if (!empty($errorsPt)) {
            return Response::json([
                'status' => 'error',
                'message' => 'Corrija os campos obrigatórios',
                'status_code' => 'validation_error',
                'http_status' => 422,
                'required' => array_keys($errorsPt),
                'errors' => $errorsPt,
                'errors_en' => $errorsEn,
                'errors_pt' => $errorsPt,
            ], 422);
        }

        $tokenize = $this->tokenizeWithPublicKey($publicKey, $card);
        if (!$tokenize['ok']) {
            $status = (int)($tokenize['status'] ?? 502);
            $msg = (string)($tokenize['message'] ?? 'Erro ao tokenizar o cartão');
            $ptMsg = I18n::translateProviderMessagePt($msg);
            $errorsRaw = $tokenize['errors'] ?? null;
            $errorsPt2 = $this->translateErrorsToPt($errorsRaw);
            return Response::json([
                'status' => 'error',
                'message' => $ptMsg,
                'status_code' => 'tokenize_error',
                'http_status' => $status,
                'errors' => $errorsPt2 ?? [],
                'errors_en' => $errorsRaw ?? [],
                'errors_pt' => $errorsPt2 ?? [],
            ], $status);
        }

        $payload = $tokenize['data'] ?? [];
        $token = (string)($payload['id'] ?? ($payload['token'] ?? ''));
        if ($token === '') {
            return Response::json([
                'status' => 'error',
                'message' => 'Falha ao tokenizar o cartão',
                'status_code' => 'tokenize_error',
                'http_status' => 502,
            ], 502);
        }
        $cardInfo = is_array($payload['card'] ?? null) ? $payload['card'] : [];
        return Response::json([
            'status' => 'success',
            'message' => 'Token gerado',
            'status_code' => 'tokenized',
            'token' => $token,
            'card_token' => $token,
            'id' => $token,
            'card' => [
                'brand' => $cardInfo['brand'] ?? null,
                'last4' => $cardInfo['last_four_digits'] ?? ($cardInfo['last4'] ?? null),
                'exp_month' => $cardInfo['exp_month'] ?? null,
                'exp_year' => $cardInfo['exp_year'] ?? null,
                'holder_name' => $cardInfo['holder_name'] ?? null,
            ],
        ], 200);
    }

    // GET /api/v1/cards/quote?amount_cents=10000&bin=411111
    public function quote(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['status' => 'error', 'message' => 'Não autorizado', 'status_code' => 'unauthorized', 'http_status' => 401], 401);

        $max = isset($req->query['max_installments']) ? (int)$req->query['max_installments'] : (isset($req->query['max_parcelas']) ? (int)$req->query['max_parcelas'] : null);
        $free = isset($req->query['free_installments']) ? (int)$req->query['free_installments'] : (isset($req->query['parcelas_sem_juros']) ? (int)$req->query['parcelas_sem_juros'] : null);
        $rate = isset($req->query['interest_rate']) ? (float)$req->query['interest_rate'] : (isset($req->query['taxa_juros']) ? (float)$req->query['taxa_juros'] : null); // % per month
        $min  = isset($req->query['min_installment_cents']) ? (int)$req->query['min_installment_cents'] : (isset($req->query['valor_minimo_parcela_centavos']) ? (int)$req->query['valor_minimo_parcela_centavos'] : null);

        $val = \App\Support\Validator::cardQuote($req->query ?? []);
        if (!$val['ok']) {
            $msg = 'Corrija os campos obrigatórios';
            $errs = $val['errors_pt'] ?? ($val['errors_en'] ?? ($val['errors'] ?? []));
            if (is_array($errs) && !empty($errs)) {
                $first = reset($errs);
                if (is_array($first)) { $first = reset($first); }
                if (is_string($first) && $first !== '') $msg = $first;
            } elseif (!empty($val['required'])) {
                $msg = 'Campos obrigatórios: ' . implode(', ', (array)$val['required']);
            }
            return Response::json([
                'status' => 'error',
                'message' => $msg,
                'status_code' => 'validation_error',
                'http_status' => 422,
                'required' => $val['required'],
                'errors' => $val['errors_pt'] ?? [],
                'errors_en' => $val['errors_en'] ?? ($val['errors'] ?? []),
                'errors_pt' => $val['errors_pt'] ?? [],
            ], 422);
        }
        $amount = (int) ($req->query['amount_cents'] ?? ($req->query['valor_centavos'] ?? 0));
        $bin = (string) ($req->query['bin'] ?? '');
        if ($bin === '' && !empty($req->query['numero_cartao'])) {
            $digits = preg_replace('/\D/', '', (string)$req->query['numero_cartao']);
            if (strlen($digits) >= 6) $bin = substr($digits, 0, 6);
        }

        // Interest payer: default user; optionally site or query can override
        $payer = strtolower((string)($req->query['interest_payer'] ?? ($site['interest_payer'] ?? 'user')));
        $juros = strtoupper(trim((string)($req->query['juros'] ?? '')));
        $jurosProvided = ($juros !== '');
        if ($jurosProvided) {
            if ($juros === 'C') $payer = 'user';
            elseif ($juros === 'V') $payer = 'merchant';
            else {
                return Response::json([
                    'status' => 'error',
                    'message' => 'Campo "juros" inválido: use "C" (comprador) ou "V" (vendedor).',
                    'status_code' => 'validation_error',
                    'http_status' => 422,
                ], 422);
            }
        }
        if ($payer === 'merchant') {
            $siteFree = isset($site['installments_free']) ? (int)$site['installments_free'] : null;
            if ($jurosProvided && $juros === 'V' && $max !== null && $max > 0) {
                $free = $max; // vendedor paga juros: todas as parcelas devem ser sem juros
            } elseif ($siteFree && $siteFree > 0) {
                $free = $siteFree;
            } elseif ($max !== null && $max > 0) {
                $free = $max;
            }
            if ($max !== null && $max > 0 && $free !== null && $free > 0 && $max > $free) {
                $max = $free; // evita parcelas com juros quando vendedor subsidia
            }
        }

        // Exige conta Pagar.me vinculada para consultas
        try {
            $stone = \App\Infrastructure\Stone\StoneClient::forSite($site);
        } catch (\App\Infrastructure\Stone\StoneAccountRequiredException $e) {
            return Response::json([
                'status' => 'error',
                'message' => 'Conta Pagar.me não configurada para este site',
                'status_code' => 'pagarme_account_required',
                'http_status' => 409,
                'reason' => $e->getMessage(),
            ], 409);
        }

        $service = new CardService();
        $forceNoInterest = ($payer === 'merchant');
        $data = $service->quote($bin, $amount, $max, $free, $rate, $min, $stone, $forceNoInterest);
        $data['interest_payer'] = in_array($payer, ['merchant','user']) ? $payer : 'user';
        return Response::json(array_merge([
            'status' => 'success',
            'message' => 'Cotação calculada',
            'status_code' => 'quoted',
        ], $data), 200);
    }

    // GET /api/v1/cards/quote/installment?amount_cents=10000&bin=411111&n=3
    // Retorna apenas a opção da parcela N (com juros/total formatados)
    public function quoteInstallment(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['status' => 'error', 'message' => 'Não autorizado', 'status_code' => 'unauthorized', 'http_status' => 401], 401);

        // Validação básica
        $amount = (int) ($req->query['amount_cents'] ?? ($req->query['valor_centavos'] ?? 0));
        $bin = (string) ($req->query['bin'] ?? '');
        if ($bin === '' && !empty($req->query['numero_cartao'])) {
            $digits = preg_replace('/\D/', '', (string)$req->query['numero_cartao']);
            if (strlen($digits) >= 6) $bin = substr($digits, 0, 6);
        }
        $n = isset($req->query['n']) ? (int)$req->query['n'] : (isset($req->query['installment']) ? (int)$req->query['installment'] : (isset($req->query['parcela']) ? (int)$req->query['parcela'] : (isset($req->query['numero']) ? (int)$req->query['numero'] : null)));
        if ($amount <= 0 || $bin === '' || !$n || $n <= 0) {
            return Response::json([
                'status' => 'error',
                'message' => 'Campos obrigatórios: amount_cents (ou valor_centavos), bin, n (parcela)',
                'status_code' => 'validation_error',
                'http_status' => 422,
                'required' => ['amount_cents', 'bin', 'n'],
            ], 422);
        }

        // Opções avançadas
        $max = isset($req->query['max_installments']) ? (int)$req->query['max_installments'] : (isset($req->query['max_parcelas']) ? (int)$req->query['max_parcelas'] : null);
        $free = isset($req->query['free_installments']) ? (int)$req->query['free_installments'] : (isset($req->query['parcelas_sem_juros']) ? (int)$req->query['parcelas_sem_juros'] : null);
        $rate = isset($req->query['interest_rate']) ? (float)$req->query['interest_rate'] : (isset($req->query['taxa_juros']) ? (float)$req->query['taxa_juros'] : null);
        $min  = isset($req->query['min_installment_cents']) ? (int)$req->query['min_installment_cents'] : (isset($req->query['valor_minimo_parcela_centavos']) ? (int)$req->query['valor_minimo_parcela_centavos'] : null);

        // Interest payer: padrão user; pode ser sobrescrito pelo site ou query
        $payer = strtolower((string)($req->query['interest_payer'] ?? ($site['interest_payer'] ?? 'user')));
        $juros = strtoupper(trim((string)($req->query['juros'] ?? '')));
        $jurosProvided = ($juros !== '');
        if ($jurosProvided) {
            if ($juros === 'C') $payer = 'user';
            elseif ($juros === 'V') $payer = 'merchant';
            else {
                return Response::json([
                    'status' => 'error',
                    'message' => 'Campo "juros" inválido: use "C" (comprador) ou "V" (vendedor).',
                    'status_code' => 'validation_error',
                    'http_status' => 422,
                ], 422);
            }
        }
        if ($payer === 'merchant') {
            $siteFree = isset($site['installments_free']) ? (int)$site['installments_free'] : null;
            if ($jurosProvided && $juros === 'V' && $n > 0) {
                $free = $n; // parcela solicitada deve ser sem juros
                if ($max === null || $max < $n) $max = $n;
            } elseif ($siteFree && $siteFree > 0) {
                $free = $siteFree;
            } elseif ($max !== null && $max > 0) {
                $free = $max;
            }
            if ($max !== null && $max > 0 && $free !== null && $free > 0 && $max > $free) {
                $max = $free;
            }
        }

        // Exige conta Pagar.me vinculada para consultas
        try {
            $stone = \App\Infrastructure\Stone\StoneClient::forSite($site);
        } catch (\App\Infrastructure\Stone\StoneAccountRequiredException $e) {
            return Response::json([
                'status' => 'error',
                'message' => 'Conta Pagar.me não configurada para este site',
                'status_code' => 'pagarme_account_required',
                'http_status' => 409,
                'reason' => $e->getMessage(),
            ], 409);
        }
        $service = new CardService();
        $forceNoInterest = ($payer === 'merchant');
        $data = $service->quote($bin, $amount, $max, $free, $rate, $min, $stone, $forceNoInterest);
        $data['interest_payer'] = in_array($payer, ['merchant','user']) ? $payer : 'user';

        // Encontrar a parcela solicitada
        $chosen = null;
        foreach ((array)($data['installments'] ?? []) as $row) {
            if ((int)($row['number'] ?? 0) === $n) { $chosen = $row; break; }
        }
        if (!$chosen) {
            return Response::json([
                'status' => 'error',
                'message' => 'Parcela não disponível para os parâmetros informados',
                'status_code' => 'installment_not_available',
                'http_status' => 404,
            ], 404);
        }

        return Response::json([
            'status' => 'success',
            'message' => 'Cotação calculada (parcela específica)',
            'status_code' => 'quoted_one',
            'brand' => $data['brand'] ?? null,
            'brand_icon' => $data['brand_icon'] ?? null,
            'interest_payer' => $data['interest_payer'],
            'installment' => $chosen,
        ], 200);
    }

    // GET /api/v1/cards/brand?bin=411111
    public function brand(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['status' => 'error', 'message' => 'Não autorizado', 'status_code' => 'unauthorized', 'http_status' => 401], 401);
        $val = \App\Support\Validator::cardBrand($req->query ?? []);
        if (!$val['ok']) {
            $msg = 'Corrija os campos obrigatórios';
            $errs = $val['errors_pt'] ?? ($val['errors_en'] ?? ($val['errors'] ?? []));
            if (is_array($errs) && !empty($errs)) {
                $first = reset($errs);
                if (is_array($first)) { $first = reset($first); }
                if (is_string($first) && $first !== '') $msg = $first;
            } elseif (!empty($val['required'])) {
                $msg = 'Campos obrigatórios: ' . implode(', ', (array)$val['required']);
            }
            return Response::json([
                'status' => 'error',
                'message' => $msg,
                'status_code' => 'validation_error',
                'http_status' => 422,
                'required' => $val['required'],
                'errors' => $val['errors_pt'] ?? [],
                'errors_en' => $val['errors_en'] ?? ($val['errors'] ?? []),
                'errors_pt' => $val['errors_pt'] ?? [],
            ], 422);
        }
        $bin = (string) ($req->query['bin'] ?? '');
        if ($bin === '' && !empty($req->query['numero_cartao'])) {
            $digits = preg_replace('/\D/', '', (string)$req->query['numero_cartao']);
            if (strlen($digits) >= 6) $bin = substr($digits, 0, 6);
        }
        // Exige conta Pagar.me vinculada para consultas
        try {
            $stone = \App\Infrastructure\Stone\StoneClient::forSite($site);
        } catch (\App\Infrastructure\Stone\StoneAccountRequiredException $e) {
            return Response::json([
                'status' => 'error',
                'message' => 'Conta Pagar.me não configurada para este site',
                'status_code' => 'pagarme_account_required',
                'http_status' => 409,
                'reason' => $e->getMessage(),
            ], 409);
        }
        // Try provider then fallback to local
        $brand = null;
        try {
            $info = $stone->lookupCardBin($bin);
            $brand = $info['brand'] ?? $info['card_brand'] ?? null;
        } catch (\Throwable $e) {
            // ignore
        }
        if (!$brand) {
            $brand = CardService::detectBrandLocally($bin);
        }
        return Response::json([
            'status' => 'success',
            'message' => 'OK',
            'status_code' => 'ok',
            'brand' => $brand,
            'brand_icon' => CardService::brandIcon($brand)
        ]);
    }

    private function tokenizeWithPublicKey(string $publicKey, array $cardPayload): array
    {
        $base = $this->baseCoreUrlForPublicKey($publicKey);
        $url = rtrim($base, '/') . '/tokens?appId=' . urlencode($publicKey);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $body = json_encode(['type' => 'card', 'card' => $cardPayload], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => (int)Env::get('HTTP_TIMEOUT', '10'),
            CURLOPT_CONNECTTIMEOUT => (int)Env::get('HTTP_CONNECT_TIMEOUT', '5'),
            CURLOPT_POSTFIELDS => $body,
        ];
        curl_setopt_array($ch, $opts);
        $respBody = curl_exec($ch);
        $err = curl_error($ch);
        $errno = curl_errno($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        curl_close($ch);

        if ($respBody === false || $err) {
            $msg = $err ?: 'Erro de conexão com a Stone';
            $code = ($errno === CURLE_OPERATION_TIMEDOUT || str_contains(strtolower($msg), 'timed out')) ? 504 : 502;
            return ['ok' => false, 'status' => $code, 'message' => $msg];
        }

        $json = json_decode((string)$respBody, true);
        if (!is_array($json)) {
            return ['ok' => false, 'status' => 502, 'message' => 'Resposta inválida da Stone'];
        }
        if ($status >= 400) {
            $msg = '';
            if (isset($json['message']) && is_string($json['message'])) $msg = $json['message'];
            if ($msg === '' && isset($json['errors'][0])) {
                $e0 = $json['errors'][0];
                if (is_array($e0) && isset($e0['message'])) $msg = (string)$e0['message'];
                elseif (is_string($e0)) $msg = $e0;
            }
            if ($msg === '') $msg = 'Stone API error';
            return ['ok' => false, 'status' => $status, 'message' => $msg, 'errors' => ($json['errors'] ?? null)];
        }
        return ['ok' => true, 'status' => $status, 'data' => $json];
    }

    private function baseCoreUrlForPublicKey(string $publicKey): string
    {
        $env = $this->inferEnvFromPublicKey($publicKey);
        $def = 'https://api.pagar.me/core/v5';
        $envKey = $env === 'production' ? 'STONE_BASE_URL_PRODUCTION' : 'STONE_BASE_URL_SANDBOX';
        return rtrim(Env::get($envKey, $def), '/');
    }

    private function inferEnvFromPublicKey(string $publicKey): string
    {
        $k = strtolower($publicKey);
        if (str_contains($k, 'live') || str_contains($k, 'prod')) return 'production';
        if (str_contains($k, 'test') || str_contains($k, 'sandbox')) return 'sandbox';
        $env = strtolower((string)Env::get('STONE_ENV', 'sandbox'));
        if ($env === 'homolog') $env = 'sandbox';
        return in_array($env, ['sandbox', 'production'], true) ? $env : 'sandbox';
    }

    private function translateErrorsToPt($errors)
    {
        if (!is_array($errors)) return $errors;
        $out = [];
        foreach ($errors as $k => $v) {
            if (is_string($v)) {
                $out[$k] = I18n::translateProviderMessagePt($v);
                continue;
            }
            if (is_array($v)) {
                $tmp = $v;
                if (isset($tmp['message']) && is_string($tmp['message'])) {
                    $tmp['message'] = I18n::translateProviderMessagePt($tmp['message']);
                }
                foreach ($tmp as $kk => $vv) {
                    if ($kk === 'code') continue;
                    if (is_array($vv) || is_string($vv)) $tmp[$kk] = $this->translateErrorsToPt($vv);
                }
                $out[$k] = $tmp;
                continue;
            }
            $out[$k] = $v;
        }
        return $out;
    }
}
