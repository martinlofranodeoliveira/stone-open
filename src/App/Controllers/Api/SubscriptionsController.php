<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\Stone\StoneApiException;
use App\Infrastructure\Stone\StoneAccount;

final class SubscriptionsController
{
    private static function resolveEnv(array $site): string
    {
        $env = 'sandbox';
        try {
            $acc = StoneAccount::resolveForSite($site);
            $env = strtolower((string)($acc['active_env'] ?? 'sandbox'));
        } catch (\Throwable $e) {
            $env = strtolower((string)($site['active_env'] ?? 'sandbox'));
        }
        if ($env === 'homolog') $env = 'sandbox';
        return in_array($env, ['sandbox','production'], true) ? $env : 'sandbox';
    }
    private static function toMysqlDate(?string $v): ?string
    {
        if (!$v) return null;
        $v = trim($v);
        if ($v === '') return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
        $ts = strtotime($v);
        if ($ts === false) return null;
        return date('Y-m-d', $ts);
    }

    // POST /api/v1/subscriptions
    public function create(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['status' => 'error', 'message' => 'Não autorizado', 'status_code' => 'unauthorized', 'http_status' => 401], 401);
        // Exige conta Pagar.me vinculada para criar assinatura
        try { \App\Infrastructure\Stone\StoneAccount::resolveForSite($site); }
        catch (\App\Infrastructure\Stone\StoneAccountRequiredException $e) {
            return Response::json(['status' => 'error', 'message' => 'Conta Pagar.me não configurada para este site', 'status_code' => 'pagarme_account_required', 'http_status' => 409], 409);
        }
        $env = self::resolveEnv($site);
        $in = $req->json ?? [];
        if (!is_array($in)) return Response::json(['status' => 'error', 'message' => 'JSON inválido', 'status_code' => 'invalid_json', 'http_status' => 400], 400);
        // Mapear PT -> EN e aceitar payload combinado { customer: {...}, card: {...}, plano_id: <local> }
        if (!isset($in['stone_customer_id']) && isset($in['id_cliente_stone'])) $in['stone_customer_id'] = $in['id_cliente_stone'];
        if (!isset($in['stone_plan_id']) && isset($in['id_plano_stone'])) $in['stone_plan_id'] = $in['id_plano_stone'];
        // Não mapear/aceitar amount_cents do payload para assinatura; valor virá do plano
        if (!isset($in['currency']) && isset($in['moeda'])) $in['currency'] = $in['moeda'];

        // Se veio plano local (plano_id), validar existência e conversão para stone_plan_id
        if (isset($in['plano_id'])) {
            $plid = (int)$in['plano_id'];
            if ($plid <= 0) {
                return Response::json([
                    'status' => 'error',
                    'message' => 'plano_id inválido',
                    'status_code' => 'validation_error',
                    'http_status' => 422,
                    'required' => ['plano_id'],
                ], 422);
            }
            $pl = Database::run('SELECT * FROM plans WHERE id = ? AND site_id = ? AND env = ? LIMIT 1', [$plid, $site['id'], $env])->fetch();
            if (!$pl) {
                return Response::json([
                    'status' => 'error',
                    'message' => 'Plano não encontrado para este site',
                    'status_code' => 'plan_not_found',
                    'http_status' => 422,
                ], 422);
            }
            // Se o plano local ainda não possui stone_plan_id, tente criá-lo na Stone agora
            if (empty($pl['stone_plan_id'])) {
                try {
                    $payloadPlan = [
                        'name' => (string)$pl['name'],
                        'description' => (string)($pl['description'] ?? ''),
                        'currency' => (string)$pl['currency'],
                        'interval' => (string)$pl['interval_unit'],
                        'interval_count' => (int)$pl['interval_count'],
                        'items' => [[
                            'name' => (string)$pl['name'],
                            'quantity' => 1,
                            'pricing_scheme' => ['price' => (int)$pl['amount_cents']],
                        ]],
                        'metadata' => ['site_id' => (int)$pl['site_id'], 'local_plan_id' => (int)$pl['id']],
                    ];
                    $client = \App\Infrastructure\Stone\StoneClient::forSite($site);
                    $resPlan = $client->createPlan($payloadPlan);
                    $newId = (string)($resPlan['id'] ?? '');
                    if ($newId === '') throw new \RuntimeException('Resposta sem id do plano');
                    Database::run('UPDATE plans SET stone_plan_id=? WHERE id=?', [$newId, (int)$pl['id']]);
                    $pl['stone_plan_id'] = $newId;
                } catch (\App\Infrastructure\Stone\StoneApiException $e) {
                    return Response::json([
                        'status' => 'error',
                        'message' => 'Erro ao cadastrar o plano no sistema Pagar.me: ' . $e->getErrorMessage(),
                        'status_code' => $e->getErrorCode() ?: 'provider_error',
                        'http_status' => (int)$e->getStatus(),
                        'error_code' => $e->getErrorCode(),
                        'error_message' => $e->getErrorMessage(),
                        'errors' => $e->getErrors(),
                    ], 422);
                } catch (\Throwable $e) {
                    return Response::json([
                        'status' => 'error',
                        'message' => 'Erro ao cadastrar o plano no sistema Pagar.me: ' . $e->getMessage(),
                        'status_code' => 'provider_error',
                        'http_status' => 500,
                    ], 500);
                }
            }
            if (!isset($in['stone_plan_id']) || empty($in['stone_plan_id'])) {
                $in['stone_plan_id'] = $pl['stone_plan_id'];
            }
        }

        // Validate required fields first
        $v = \App\Support\Validator::subscription($in);
        if (!$v['ok']) {
            $msg = 'Corrija os campos obrigatórios';
            $errs = $v['errors_pt'] ?? ($v['errors_en'] ?? ($v['errors'] ?? []));
            if (is_array($errs) && !empty($errs)) {
                $first = reset($errs);
                if (is_array($first)) {
                    $first = reset($first);
                }
                if (is_string($first) && $first !== '') $msg = $first;
            } elseif (!empty($v['required'])) {
                $msg = 'Campos obrigatórios: ' . implode(', ', (array)$v['required']);
            }
            return Response::json([
                'status' => 'error',
                'message' => $msg,
                'status_code' => 'validation_error',
                'http_status' => 422,
                'required' => $v['required'],
                'errors' => $v['errors_pt'] ?? [],
                'errors_en' => $v['errors_en'] ?? ($v['errors'] ?? []),
                'errors_pt' => $v['errors_pt'] ?? [],
            ], 422);
        }

        // Validar que o plano exista localmente e esteja ativo (obrigatório)
        $stonePlan = trim((string)($in['stone_plan_id'] ?? ''));
        if ($stonePlan !== '') {
            $pl = Database::run('SELECT * FROM plans WHERE stone_plan_id = ? AND site_id = ? AND env = ? LIMIT 1', [$stonePlan, $site['id'], $env])->fetch();
            if (!$pl) {
                return Response::json([
                    'status' => 'error',
                    'message' => 'Plano não encontrado para este site',
                    'status_code' => 'plan_not_found',
                    'http_status' => 422,
                ], 422);
            }
            if (strtolower((string)($pl['status'] ?? '')) !== 'active') {
                return Response::json([
                    'status' => 'error',
                    'message' => 'Plano inativo para este site',
                    'status_code' => 'plan_inactive',
                    'http_status' => 422,
                ], 422);
            }
            // Verificar se o plano existe na Stone; se não, criar automaticamente e prosseguir
            try {
                $client = \App\Infrastructure\Stone\StoneClient::forSite($site);
                $remote = $client->findPlan($stonePlan);
                if (!$remote) {
                    $payloadPlan = [
                        'name' => (string)$pl['name'],
                        'description' => (string)($pl['description'] ?? ''),
                        'currency' => (string)$pl['currency'],
                        'interval' => (string)$pl['interval_unit'],
                        'interval_count' => (int)$pl['interval_count'],
                        'items' => [[
                            'name' => (string)$pl['name'],
                            'quantity' => 1,
                            'pricing_scheme' => ['price' => (int)$pl['amount_cents']],
                        ]],
                        'metadata' => ['site_id' => (int)$pl['site_id'], 'local_plan_id' => (int)$pl['id']],
                    ];
                    $resPlan = $client->createPlan($payloadPlan);
                    $newId = (string)($resPlan['id'] ?? '');
                    if ($newId === '') throw new \RuntimeException('Resposta sem id do plano');
                    Database::run('UPDATE plans SET stone_plan_id=? WHERE id=?', [$newId, (int)$pl['id']]);
                    $stonePlan = $newId; // usar o novo id
                    $in['stone_plan_id'] = $newId;
                }
            } catch (\App\Infrastructure\Stone\StoneApiException $e) {
                return Response::json([
                    'status' => 'error',
                    'message' => 'Erro ao cadastrar o plano no sistema Pagar.me: ' . $e->getErrorMessage(),
                    'status_code' => $e->getErrorCode() ?: 'provider_error',
                    'http_status' => (int)$e->getStatus(),
                    'error_code' => $e->getErrorCode(),
                    'error_message' => $e->getErrorMessage(),
                    'errors' => $e->getErrors(),
                ], 422);
            } catch (\Throwable $e) {
                return Response::json([
                    'status' => 'error',
                    'message' => 'Erro ao cadastrar o plano no sistema Pagar.me: ' . $e->getMessage(),
                    'status_code' => 'provider_error',
                    'http_status' => 500,
                ], 500);
            }
        }

        $customerId = trim((string)($in['stone_customer_id'] ?? ''));
        $planId = trim((string)($in['stone_plan_id'] ?? ''));
        // Valor da assinatura deve vir do plano local
        $amount = null;
        $currency = isset($in['currency']) ? (string)$in['currency'] : null; // se ausente, usaremos a moeda do plano
        $localCustomerId = null;

        // Se veio payload combinado, cria/obtém customer e cartão na Stone
        if ($customerId === '' && isset($in['customer']) && is_array($in['customer'])) {
            // Normalizações leves para aumentar compatibilidade e mensagens melhores
            $cust = $in['customer'];
            // document: apenas dígitos
            if (isset($cust['document'])) {
                $cust['document'] = preg_replace('/\D+/', '', (string)$cust['document']);
                if (empty($cust['type'])) {
                    $len = strlen($cust['document']);
                    if ($len === 11) $cust['type'] = 'individual';
                    elseif ($len === 14) $cust['type'] = 'company';
                }
            }
            // address.country: aceitar variações -> BR
            if (isset($cust['address']) && is_array($cust['address']) && isset($cust['address']['country'])) {
                $c = trim((string)$cust['address']['country']);
                $cUp = strtoupper($c);
                if ($cUp !== '' && strlen($cUp) !== 2) {
                    if (in_array($cUp, ['BRASIL', 'BRAZIL', 'BRASÍLIA', 'BR - BRAZIL'], true)) {
                        $cust['address']['country'] = 'BR';
                    } else {
                        $cust['address']['country'] = substr($cUp, 0, 2);
                    }
                } else {
                    $cust['address']['country'] = $cUp;
                }
            }
            // phone string -> phones.mobile_phone quando ausente
            if (!isset($cust['phones']['mobile_phone']) && !empty($cust['phone'])) {
                $digits = preg_replace('/\D+/', '', (string)$cust['phone']);
                if (strlen($digits) >= 10) {
                    $cust['phones']['mobile_phone'] = [
                        'country_code' => substr($digits, 0, 2),
                        'area_code' => substr($digits, 2, 2),
                        'number' => substr($digits, 4),
                    ];
                }
            }
            // Validação guiada antes de chamar a Stone
            $vc = \App\Support\Validator::customerCreate($cust);
            if (!$vc['ok']) {
                // Hints rápidos para erros comuns
                $hints = [];
                $errsAll = array_merge($vc['errors_pt'] ?? [], $vc['errors_en'] ?? [], $vc['errors'] ?? []);
                $addr = $cust['address'] ?? [];
                if (isset($errsAll['document']) || (isset($cust['document']) && !in_array(strlen((string)$cust['document']), [11, 14], true))) {
                    $hints[] = 'document (CPF/CNPJ) deve conter apenas dígitos: CPF 11, CNPJ 14.';
                }
                if (isset($addr['zip_code']) && strlen(preg_replace('/\D+/', '', (string)$addr['zip_code'])) !== 8) {
                    $hints[] = 'address.zip_code (CEP) deve ter 8 dígitos. Ex: 01001000';
                }
                if (isset($addr['country']) && strlen((string)$addr['country']) !== 2) {
                    $hints[] = 'address.country deve ser a sigla com 2 letras (ISO). Ex: BR';
                }
                if (empty($cust['phones']['mobile_phone']) && empty($cust['phones']['home_phone']) && empty($cust['phone'])) {
                    $hints[] = 'Informe ao menos um telefone: phones.mobile_phone { country_code, area_code, number }';
                }
                return Response::json([
                    'error' => 'Falha na validação do cliente',
                    'required' => $vc['required'],
                    'errors' => $vc['errors_pt'] ?? ($vc['errors'] ?? []),
                    'errors_en' => $vc['errors_en'] ?? ($vc['errors'] ?? []),
                    'errors_pt' => $vc['errors_pt'] ?? [],
                    'hints' => $hints,
                    'status' => 422,
                ], 422);
            }
            // Antes de criar, tentar localizar existente por email/document
            try {
                $found = (\App\Infrastructure\Stone\StoneClient::forSite($site))->findCustomer(null, ($cust['email'] ?? null), ($cust['document'] ?? null));
                if (is_array($found) && !empty($found['id'])) {
                    $customerId = (string)$found['id'];
                }
            } catch (\Throwable $e) { /* ignore lookup errors */
            }
            try {
                if ($customerId === '') {
                    $respC = (\App\Infrastructure\Stone\StoneClient::forSite($site))->createCustomer($cust);
                    $customerId = (string)($respC['id'] ?? '');
                } else {
                    $respC = ['id' => $customerId];
                }
                $customerId = (string)($respC['id'] ?? '');
                if ($customerId === '') throw new \RuntimeException('Stone customer id missing');
                // persist local customer
                Database::run('INSERT INTO customers (site_id, stone_customer_id, env, name, email, document, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE env=VALUES(env), name=VALUES(name), email=VALUES(email), document=VALUES(document), updated_at=NOW()', [
                    $site['id'], $customerId, $env, ($cust['name'] ?? null), ($cust['email'] ?? null), ($cust['document'] ?? null),
                ]);
                $cRow = Database::run('SELECT id FROM customers WHERE site_id = ? AND stone_customer_id = ? AND env = ? LIMIT 1', [$site['id'], $customerId, $env])->fetch();
                $localCustomerId = $cRow ? (int)$cRow['id'] : null;
            } catch (\App\Infrastructure\Stone\StoneApiException $e) {
                $errs = $e->getErrors();
                return Response::json([
                    'error' => 'Falha ao criar cliente',
                    'error_code' => $e->getErrorCode(),
                    'error_message' => $e->getErrorMessage(),
                    'errors' => $errs,
                    'errors_en' => $errs,
                    'errors_pt' => $errs,
                    'status' => $e->getStatus(),
                ], 422);
            } catch (\Throwable $e) {
                return Response::json(['error' => 'Falha ao criar cliente', 'message' => $e->getMessage()], 422);
            }
        }

        // Se veio card token/dados, opcionalmente cria cartão salvo para o customer (conforme collection, endpoint /customers/{id}/cards)
        $createdCardId = null;
        if (isset($in['card']) && is_array($in['card'])) {
            $cardPayload = $in['card'];
            if (!isset($cardPayload['customer_id']) && $customerId !== '') $cardPayload['customer_id'] = $customerId;
            if (!isset($cardPayload['token']) && isset($cardPayload['card_token'])) $cardPayload['token'] = $cardPayload['card_token'];
            // Se houver token ou dados completos, podemos criar um card_id atrelado ao customer
            if (!empty($cardPayload['token']) || !empty($cardPayload['number'])) {
                try {
                    $resCard = (\App\Infrastructure\Stone\StoneClient::forSite($site))->createCard($cardPayload);
                    $createdCardId = (string)($resCard['id'] ?? '');
                    // persist local payment method (opcional)
                    $brand = (string)($resCard['brand'] ?? ($cardPayload['brand'] ?? '')) ?: null;
                    $last4 = (string)($resCard['last_four_digits'] ?? ($resCard['last4'] ?? '')) ?: null;
                    $expMonth = isset($resCard['exp_month']) ? (int)$resCard['exp_month'] : null;
                    $expYear = isset($resCard['exp_year']) ? (int)$resCard['exp_year'] : null;
                    $holder = (string)($resCard['holder_name'] ?? ($cardPayload['holder_name'] ?? '')) ?: null;
                    Database::run('INSERT INTO payment_methods (site_id, customer_id, type, stone_token, brand, last4, exp_month, exp_year, holder_name, created_at, updated_at) VALUES (?, ?, "card", ?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE updated_at = NOW()', [
                        $site['id'], $localCustomerId, $createdCardId ?: ($cardPayload['token'] ?? null), $brand, $last4, $expMonth, $expYear, $holder,
                    ]);
                } catch (\Throwable $e) {
                    // segue sem cartão salvo localmente
                }
            }
        }

        if ($planId === '' || $customerId === '') {
            return Response::json(['error' => 'stone_plan_id and stone_customer_id are required'], 422);
        }
        $payload = ['plan_id' => $planId];
        // Billing cycles: default 1 if not provided; accept 'billing_cycles' or 'ciclos'
        $cycles = (int)($in['billing_cycles'] ?? ($in['ciclos'] ?? 0));
        if ($cycles <= 0) {
            // try from local plan
            $pl = Database::run('SELECT billing_cycles FROM plans WHERE site_id=? AND stone_plan_id=? AND env = ? LIMIT 1', [$site['id'], $planId, $env])->fetch();
            $cycles = (int)($pl['billing_cycles'] ?? 1);
        }
        if ($cycles <= 0) $cycles = 1;
        $payload['billing_cycles'] = $cycles;
        // Segue a collection: permitir customer inline ou via customer_id
        if (isset($in['customer']) && is_array($in['customer']) && $customerId === '') {
            $payload['customer'] = $in['customer'];
        } else {
            $payload['customer_id'] = $customerId;
        }
        // Payment method conforme collection
        if (isset($in['payment_method'])) {
            $payload['payment_method'] = $in['payment_method'];
        } else {
            $payload['payment_method'] = 'credit_card';
        }
        if (isset($in['installments'])) $payload['installments'] = (int)$in['installments'];
        if ($createdCardId) {
            $payload['card_id'] = $createdCardId;
        } elseif (isset($in['card']) && is_array($in['card'])) {
            // Aceitar card token conforme collection (evitar trafegar dados abertos)
            $payload['card'] = $in['card'];
        }
        // Não enviar amount custom no payload; usar preço do plano na Stone
        if ($currency) $payload['currency'] = $currency;
        try {
            $res = (\App\Infrastructure\Stone\StoneClient::forSite($site))->createSubscription($payload);
            $startDate = self::toMysqlDate((string)($res['start_at'] ?? ($res['start_date'] ?? '')));
            // Resolver amount e currency a partir do plano local (caso currency não venha no payload)
            $planRow = Database::run('SELECT amount_cents, currency FROM plans WHERE site_id = ? AND stone_plan_id = ? AND env = ? LIMIT 1', [$site['id'], $planId, $env])->fetch();
            $amountPlan = isset($planRow['amount_cents']) ? (int)$planRow['amount_cents'] : null;
            $currencyFinal = $currency ?: (string)($planRow['currency'] ?? 'BRL');
            Database::run('INSERT INTO subscriptions (site_id, customer_id, stone_subscription_id, env, plan_id, amount_cents, currency, status, start_date, metadata, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', [
                $site['id'],
                $localCustomerId,
                $res['id'] ?? null,
                $env,
                $planId,
                $amountPlan,
                $currencyFinal,
                strtolower((string)($res['status'] ?? 'active')),
                $startDate,
                json_encode($res, JSON_UNESCAPED_UNICODE),
            ]);
            $newId = (int)Database::pdo()->lastInsertId();
            if ($newId > 0) {
                $evt = strtolower((string)($res['status'] ?? 'active')) === 'active' ? 'subscription.activated' : 'subscription.created';
                try { (new \App\Application\WebhookService())->emitSubscriptionEvent($newId, $evt); } catch (\Throwable $e) {}
            }
            $row = Database::run('SELECT * FROM subscriptions WHERE stone_subscription_id = ? AND site_id = ? ORDER BY id DESC LIMIT 1', [$res['id'] ?? null, $site['id']])->fetch();
            return Response::json(['subscription' => $row ?: $res], 201);
        } catch (StoneApiException $e) {
            $errs = $e->getErrors();
            $ptMsg = \App\Support\I18n::ptHttpMessage($e->getStatus(), 'Erro ao criar assinatura');
            return Response::json([
                'status' => 'error',
                'message' => $ptMsg,
                'status_code' => $e->getErrorCode() ?: (string)$e->getStatus(),
                'http_status' => $e->getStatus(),
                'provider_message' => $e->getErrorMessage(),
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getErrorMessage(),
                'errors' => $errs,
                'errors_en' => $errs,
                'errors_pt' => $errs,
            ], 422);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    // GET /api/v1/subscriptions/{id}
    public function show(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['error' => 'Não autorizado'], 401);
        $id = (int)($req->attributes['routeParams']['id'] ?? 0);
        $row = Database::run('SELECT * FROM subscriptions WHERE id = ? AND site_id = ? LIMIT 1', [$id, $site['id']])->fetch();
        if (!$row) return Response::json(['error' => 'Not found'], 404);
        return Response::json($row);
    }

    // GET /api/v1/subscriptions/stone/{sid}
    public function showByStone(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['error' => 'Não autorizado'], 401);
        $sid = (string)($req->attributes['routeParams']['sid'] ?? '');
        $row = Database::run('SELECT * FROM subscriptions WHERE stone_subscription_id = ? AND site_id = ? LIMIT 1', [$sid, $site['id']])->fetch();
        if (!$row) return Response::json(['error' => 'Not found'], 404);
        return Response::json($row);
    }

    // GET /api/v1/subscriptions/{id}/events
    public function events(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['error' => 'Unauthorized'], 401);
        $id = (int)($req->attributes['routeParams']['id'] ?? 0);
        $sub = Database::run('SELECT * FROM subscriptions WHERE id = ? AND site_id = ? LIMIT 1', [$id, $site['id']])->fetch();
        if (!$sub) return Response::json(['error' => 'Not found'], 404);
        $rows = Database::run('SELECT * FROM subscription_events WHERE subscription_id = ? ORDER BY COALESCE(occurred_at, created_at) ASC, id ASC LIMIT 500', [$id])->fetchAll();
        return Response::json(['events' => $rows]);
    }

    // POST /api/v1/subscriptions/for-customer
    public function createForCustomer(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['error' => 'Unauthorized'], 401);
        // Exige conta Pagar.me vinculada para criar assinatura
        try { \App\Infrastructure\Stone\StoneAccount::resolveForSite($site); }
        catch (\App\Infrastructure\Stone\StoneAccountRequiredException $e) {
            return Response::json(['status' => 'error', 'message' => 'Conta Pagar.me não configurada para este site', 'status_code' => 'pagarme_account_required', 'http_status' => 409], 409);
        }
        $env = self::resolveEnv($site);
        $in = $req->json ?? [];
        if (!is_array($in)) return Response::json(['error' => 'Invalid JSON'], 400);

        // Validate minimal requirements: plan id and some way to resolve customer
        $vin = $in;
        if (!isset($vin['stone_plan_id']) && isset($vin['plano_id'])) $vin['stone_plan_id'] = $vin['plano_id'];
        $errsEn = [];
        $errsPt = [];
        $reqd = [];
        if (trim((string)($vin['stone_plan_id'] ?? '')) === '') {
            $reqd[] = 'stone_plan_id|plano_id';
            $errsEn['stone_plan_id'] = 'stone_plan_id (ou plano_id) is required';
            $errsPt['stone_plan_id'] = 'stone_plan_id (ou plano_id) é obrigatório';
        }
        // Normaliza documento para lookup
        if (isset($in['document'])) $in['document'] = preg_replace('/\D+/', '', (string)$in['document']);
        if (isset($in['cpf'])) $in['cpf'] = preg_replace('/\D+/', '', (string)$in['cpf']);
        $hasLookup = trim((string)($in['stone_customer_id'] ?? '')) !== '' || trim((string)($in['email'] ?? ($in['customer_email'] ?? ''))) !== '' || trim((string)($in['document'] ?? ($in['cpf'] ?? ''))) !== '';
        if (!$hasLookup) {
            $reqd[] = 'stone_customer_id|email|document (cpf)';
            $errsEn['customer_lookup'] = 'Provide stone_customer_id or email or document (cpf)';
            $errsPt['customer_lookup'] = 'Informe stone_customer_id ou email ou document (cpf)';
        }
        if (!empty($errsEn) || !empty($errsPt)) {
            $hints = [];
            if (in_array('stone_plan_id|plano_id', $reqd, true)) $hints[] = 'Informe o plano pelo stone_plan_id ou plano_id.';
            $hints[] = 'Para criar automaticamente, use POST /api/v1/subscriptions com o objeto customer completo.';
            return Response::json(['error' => 'Falha na validação', 'required' => $reqd, 'errors' => $errsPt, 'errors_en' => $errsEn, 'errors_pt' => $errsPt, 'hints' => $hints, 'status' => 422], 422);
        }

        $stoneCid = (string)($in['stone_customer_id'] ?? '');
        $email = (string)($in['email'] ?? ($in['customer_email'] ?? ''));
        $document = (string)($in['document'] ?? ($in['cpf'] ?? ''));
        $row = null;
        if ($stoneCid !== '') {
            $row = Database::run('SELECT * FROM customers WHERE site_id = ? AND stone_customer_id = ? AND env = ? LIMIT 1', [$site['id'], $stoneCid, $env])->fetch();
        } elseif ($email !== '') {
            $row = Database::run('SELECT * FROM customers WHERE site_id = ? AND email = ? AND env = ? LIMIT 1', [$site['id'], $email, $env])->fetch();
        } elseif ($document !== '') {
            $row = Database::run('SELECT * FROM customers WHERE site_id = ? AND document = ? AND env = ? LIMIT 1', [$site['id'], $document, $env])->fetch();
        }
        if (!$row || empty($row['stone_customer_id'])) return Response::json([
            'status' => 'error',
            'message' => 'Customer not found',
            'status_code' => 'not_found',
            'http_status' => 404,
            'hints' => [
                'Confirme stone_customer_id/email/document (cpf) cadastrados para este site.',
                'Ou envie POST /api/v1/subscriptions com { customer: {...} } para criarmos o cliente automaticamente.'
            ]
        ], 404);

        // Reaproveitar criação já existente: plan mapping e card token suportados
        $in['stone_customer_id'] = (string)$row['stone_customer_id'];
        unset($in['email'], $in['customer_email'], $in['document'], $in['cpf']);

        // Map PT -> EN shortcuts (reuse existing code path)
        if (!isset($in['stone_plan_id']) && isset($in['plano_id'])) {
            $plid = (int)$in['plano_id'];
            if ($plid <= 0) return Response::json(['status' => 'error', 'message' => 'plano_id inválido', 'status_code' => 'validation_error', 'http_status' => 422], 422);
            $pl = Database::run('SELECT * FROM plans WHERE id = ? AND site_id = ? AND env = ? LIMIT 1', [$plid, $site['id'], $env])->fetch();
            if (!$pl) return Response::json(['status' => 'error', 'message' => 'Plano não encontrado para este site', 'status_code' => 'plan_not_found', 'http_status' => 422], 422);
            if (empty($pl['stone_plan_id'])) {
                try {
                    $payloadPlan = [
                        'name' => (string)$pl['name'],
                        'description' => (string)($pl['description'] ?? ''),
                        'currency' => (string)$pl['currency'],
                        'interval' => (string)$pl['interval_unit'],
                        'interval_count' => (int)$pl['interval_count'],
                        'items' => [[
                            'name' => (string)$pl['name'],
                            'quantity' => 1,
                            'pricing_scheme' => ['price' => (int)$pl['amount_cents']],
                        ]],
                        'metadata' => ['site_id' => (int)$pl['site_id'], 'local_plan_id' => (int)$pl['id']],
                    ];
                    $client = \App\Infrastructure\Stone\StoneClient::forSite($site);
                    $resPlan = $client->createPlan($payloadPlan);
                    $newId = (string)($resPlan['id'] ?? '');
                    if ($newId === '') throw new \RuntimeException('Resposta sem id do plano');
                    Database::run('UPDATE plans SET stone_plan_id=? WHERE id=?', [$newId, (int)$pl['id']]);
                    $pl['stone_plan_id'] = $newId;
                } catch (\App\Infrastructure\Stone\StoneApiException $e) {
                    return Response::json([
                        'status' => 'error',
                        'message' => 'Erro ao cadastrar o plano no sistema Pagar.me: ' . $e->getErrorMessage(),
                        'status_code' => $e->getErrorCode() ?: 'provider_error',
                        'http_status' => (int)$e->getStatus(),
                        'error_code' => $e->getErrorCode(),
                        'error_message' => $e->getErrorMessage(),
                        'errors' => $e->getErrors(),
                    ], 422);
                } catch (\Throwable $e) {
                    return Response::json([
                        'status' => 'error',
                        'message' => 'Erro ao cadastrar o plano no sistema Pagar.me: ' . $e->getMessage(),
                        'status_code' => 'provider_error',
                        'http_status' => 500,
                    ], 500);
                }
            }
            $in['stone_plan_id'] = $pl['stone_plan_id'];
        }
        if (!isset($in['amount_cents']) && isset($in['valor_centavos'])) $in['amount_cents'] = $in['valor_centavos'];
        if (!isset($in['currency']) && isset($in['moeda'])) $in['currency'] = $in['moeda'];

        $planId = trim((string)($in['stone_plan_id'] ?? ''));
        if ($planId === '') return Response::json(['status' => 'error', 'message' => 'stone_plan_id (or plano_id) is required', 'status_code' => 'validation_error', 'http_status' => 422], 422);
        // Validar plano local e ativo; e garantir que exista na Stone
        $pl = Database::run('SELECT * FROM plans WHERE stone_plan_id = ? AND site_id = ? AND env = ? LIMIT 1', [$planId, $site['id'], $env])->fetch();
        if (!$pl) return Response::json(['status' => 'error', 'message' => 'Plano não encontrado para este site', 'status_code' => 'plan_not_found', 'http_status' => 422], 422);
        if (strtolower((string)($pl['status'] ?? '')) !== 'active') return Response::json(['status' => 'error', 'message' => 'Plano inativo para este site', 'status_code' => 'plan_inactive', 'http_status' => 422], 422);
        try {
            $client = \App\Infrastructure\Stone\StoneClient::forSite($site);
            $remote = $client->findPlan($planId);
            if (!$remote) {
                $payloadPlan = [
                    'name' => (string)$pl['name'],
                    'description' => (string)($pl['description'] ?? ''),
                    'currency' => (string)$pl['currency'],
                    'interval' => (string)$pl['interval_unit'],
                    'interval_count' => (int)$pl['interval_count'],
                    'items' => [[
                        'name' => (string)$pl['name'],
                        'quantity' => 1,
                        'pricing_scheme' => ['price' => (int)$pl['amount_cents']],
                    ]],
                    'metadata' => ['site_id' => (int)$pl['site_id'], 'local_plan_id' => (int)$pl['id']],
                ];
                $resPlan = $client->createPlan($payloadPlan);
                $newId = (string)($resPlan['id'] ?? '');
                if ($newId === '') throw new \RuntimeException('Resposta sem id do plano');
                Database::run('UPDATE plans SET stone_plan_id=? WHERE id=?', [$newId, (int)$pl['id']]);
                $planId = $newId; // use new id
            }
        } catch (\App\Infrastructure\Stone\StoneApiException $e) {
            return Response::json([
                'status' => 'error',
                'message' => 'Erro ao cadastrar o plano no sistema Pagar.me: ' . $e->getErrorMessage(),
                'status_code' => $e->getErrorCode() ?: 'provider_error',
                'http_status' => (int)$e->getStatus(),
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getErrorMessage(),
                'errors' => $e->getErrors(),
            ], 422);
        } catch (\Throwable $e) {
            return Response::json(['status' => 'error', 'message' => 'Erro ao cadastrar o plano no sistema Pagar.me: ' . $e->getMessage(), 'status_code' => 'provider_error', 'http_status' => 500], 500);
        }

        // Build payload similar to create()
        $payload = ['plan_id' => $planId, 'customer_id' => (string)$row['stone_customer_id']];
        // Billing cycles: default 1 if not provided
        $cycles = (int)($in['billing_cycles'] ?? ($in['ciclos'] ?? 0));
        if ($cycles <= 0) {
            $pl = Database::run('SELECT billing_cycles FROM plans WHERE site_id=? AND stone_plan_id=? AND env = ? LIMIT 1', [$site['id'], $planId, $env])->fetch();
            $cycles = (int)($pl['billing_cycles'] ?? 1);
        }
        if ($cycles <= 0) $cycles = 1;
        $payload['billing_cycles'] = $cycles;
        if (isset($in['payment_method'])) $payload['payment_method'] = $in['payment_method']; else $payload['payment_method'] = 'credit_card';
        if (isset($in['installments'])) $payload['installments'] = (int)$in['installments'];
        if (isset($in['card_id'])) $payload['card_id'] = (string)$in['card_id'];
        if (isset($in['card']) && is_array($in['card'])) $payload['card'] = $in['card'];
        // Não enviar amount custom; usar valor do plano. Se moeda vier no payload, repassar; caso contrário, usaremos a do plano ao persistir
        if (isset($in['currency'])) $payload['currency'] = (string)$in['currency'];

        try {
            $res = (\App\Infrastructure\Stone\StoneClient::forSite($site))->createSubscription($payload);
            $startDate = self::toMysqlDate((string)($res['start_at'] ?? ($res['start_date'] ?? '')));
            // Amount e currency devem vir do plano local (currency se ausente no payload)
            $plRow = Database::run('SELECT amount_cents, currency FROM plans WHERE site_id=? AND stone_plan_id=? AND env = ? LIMIT 1', [$site['id'], $planId, $env])->fetch();
            $amountFromPlan = isset($plRow['amount_cents']) ? (int)$plRow['amount_cents'] : null;
            $currencyFinal = isset($in['currency']) ? (string)$in['currency'] : (string)($plRow['currency'] ?? 'BRL');
            Database::run('INSERT INTO subscriptions (site_id, customer_id, stone_subscription_id, env, plan_id, amount_cents, currency, status, start_date, metadata, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', [
                $site['id'],
                (int)($row['id'] ?? 0) ?: null,
                $res['id'] ?? null,
                $env,
                $planId,
                $amountFromPlan,
                $currencyFinal,
                strtolower((string)($res['status'] ?? 'active')),
                $startDate,
                json_encode($res, JSON_UNESCAPED_UNICODE),
            ]);
            $out = Database::run('SELECT * FROM subscriptions WHERE stone_subscription_id = ? AND site_id = ? ORDER BY id DESC LIMIT 1', [$res['id'] ?? null, $site['id']])->fetch();
            return Response::json([
                'status' => 'success',
                'message' => 'Assinatura criada',
                'status_code' => strtolower((string)($res['status'] ?? 'active')),
                'subscription' => $out ?: $res
            ], 201);
        } catch (StoneApiException $e) {
            $ptMsg = \App\Support\I18n::ptHttpMessage($e->getStatus(), 'Erro ao criar assinatura');
            return Response::json([
                'status' => 'error',
                'message' => $ptMsg,
                'status_code' => $e->getErrorCode() ?: (string)$e->getStatus(),
                'http_status' => $e->getStatus(),
                'provider_message' => $e->getErrorMessage(),
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getErrorMessage(),
                'errors' => $e->getErrors(),
            ], 422);
        } catch (\Throwable $e) {
            return Response::json(['status' => 'error', 'message' => $e->getMessage(), 'status_code' => 'internal_error', 'http_status' => 500], 500);
        }
    }
}
