<?php
declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\DB\Database;
use App\Infrastructure\Stone\StoneClient;
use App\Support\Env;

final class PaymentService
{
    public function create(array $site, array $input): array
    {
        $input = self::normalizeInputPt($input);
        if ((string)Env::get('NORMALIZE_INPUTS', '0') === '1') {
            $input = self::normalizeProviderFields($input);
        }
        $env = self::resolveEnvFromSite($site);
        // Ajusta amount_cents quando comprador arca com juros (campo "juros" ou default do site)
        $input = $this->applyInterestPayerToAmount($site, $input);
        $normalizedExpiresAt = self::normalizeExpiresAt($input['expires_at'] ?? null);
        if ($normalizedExpiresAt) {
            $input['expires_at'] = $normalizedExpiresAt;
        }
        $normalizedDueAt = self::normalizeDueAt($input['due_at'] ?? ($input['boleto']['due_at'] ?? null));
        if ($normalizedDueAt) {
            $input['due_at'] = $normalizedDueAt;
            if (isset($input['boleto']) && is_array($input['boleto'])) {
                $input['boleto']['due_at'] = $normalizedDueAt;
            }
        }
        $amount = (int)($input['amount_cents'] ?? 0);
        $clientRef = (string)($input['client_reference_id'] ?? '');
        $currency = (string)($input['currency'] ?? 'BRL');
        $methodType = $input['payment_method_type'] ?? null;
        $customerName = self::extractCustomerName($input);

        if ($amount <= 0 || $clientRef === '' || !$methodType) {
            throw new \InvalidArgumentException('Missing required fields: amount_cents, client_reference_id, payment_method_type');
        }
        self::validateSimplifiedOrderInput($input);

        // Create payment record first (so we always log attempts), then call Stone APIs
        $localCustomerId = null; $localPaymentMethodId = null; $stoneCustomerId = null; $stoneCardId = null;
        $pdo = Database::pdo();
        $requestedIdempotencyKey = trim((string)($input['idempotency_key'] ?? ''));
        if ($requestedIdempotencyKey !== '' && strlen($requestedIdempotencyKey) > 96) {
            throw new \InvalidArgumentException('idempotency_key deve ter no máximo 96 caracteres');
        }
        $pdo->beginTransaction();
        try {
            $insertedWithIdemColumn = true;
            try {
                // Use '{}' literal instead of JSON_OBJECT() to support older MySQL versions without JSON functions
                $stmt = $pdo->prepare('INSERT INTO payments (site_id, customer_id, payment_method_id, client_reference_id, idempotency_key, amount_cents, currency, payment_method_type, description, customer_name, is_invoice, status, request_payload, env) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, "created", "{}", ?)');
                $stmt->execute([
                    $site['id'],
                    $localCustomerId,
                    $localPaymentMethodId,
                    $clientRef,
                    ($requestedIdempotencyKey !== '' ? $requestedIdempotencyKey : null),
                    $amount,
                    $currency,
                    $methodType,
                    $input['description'] ?? null,
                    $customerName,
                    $env,
                ]);
            } catch (\Throwable $e) {
                // Backward compatibility for databases not migrated with idempotency_key column yet.
                $msg = strtolower($e->getMessage());
                if (str_contains($msg, 'unknown column') && str_contains($msg, 'idempotency_key')) {
                    $insertedWithIdemColumn = false;
                    $stmt = $pdo->prepare('INSERT INTO payments (site_id, customer_id, payment_method_id, client_reference_id, amount_cents, currency, payment_method_type, description, customer_name, is_invoice, status, request_payload, env) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, "created", "{}", ?)');
                    $stmt->execute([
                        $site['id'],
                        $localCustomerId,
                        $localPaymentMethodId,
                        $clientRef,
                        $amount,
                        $currency,
                        $methodType,
                        $input['description'] ?? null,
                        $customerName,
                        $env,
                    ]);
                } else {
                    throw $e;
                }
            }
            $paymentId = (int)$pdo->lastInsertId();

            if ($requestedIdempotencyKey === '') {
                $requestedIdempotencyKey = $clientRef . '-p' . $paymentId;
            }
            $input['idempotency_key'] = $requestedIdempotencyKey;

            // Store request payload
            if ($insertedWithIdemColumn) {
                $stmt2 = $pdo->prepare('UPDATE payments SET request_payload = ?, idempotency_key = COALESCE(idempotency_key, ?) WHERE id = ?');
                $stmt2->execute([json_encode($input, JSON_UNESCAPED_UNICODE), $requestedIdempotencyKey, $paymentId]);
            } else {
                $stmt2 = $pdo->prepare('UPDATE payments SET request_payload = ? WHERE id = ?');
                $stmt2->execute([json_encode($input, JSON_UNESCAPED_UNICODE), $paymentId]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $msg = strtolower($e->getMessage());
            $isDuplicate = str_contains($msg, 'dup') || str_contains($msg, 'duplicate') || (string)$e->getCode() === '23000';
            if ($isDuplicate && $requestedIdempotencyKey !== '') {
                try {
                    $existing = Database::run('SELECT * FROM payments WHERE site_id = ? AND idempotency_key = ? ORDER BY id DESC LIMIT 1', [$site['id'], $requestedIdempotencyKey])->fetch();
                    if ($existing) {
                        return $existing;
                    }
                } catch (\Throwable $ignored) {
                    // ignore: idempotency_key column may not exist yet
                }
            }
            if ($isDuplicate && (str_contains($msg, 'uniq_site_client_ref') || str_contains($msg, 'client_reference_id'))) {
                throw new \InvalidArgumentException('Seu banco ainda exige referência única por site. Rode a migração para permitir múltiplos pagamentos com a mesma referência.');
            }
            throw $e;
        }

        // Optional: handle combined payload { customer: {...}, card: {...} }
        try {
            if (isset($input['customer']) && is_array($input['customer'])) {
                $origCustomer = $input['customer'];
                $stoneClient = \App\Infrastructure\Stone\StoneClient::forSite($site);
                [$stoneCustomerId, $localCustomerId, $docOk] = $this->ensureStoneAndLocalCustomer($site, $origCustomer, $stoneClient, $paymentId);
                $pmType = (string)($input['payment_method_type'] ?? '');
                if ($pmType === 'pix' || $pmType === 'boleto') {
                    // Para PIX/BOLETO, preferir enviar o objeto completo do cliente (evita falhas de customer_id entre ambientes)
                    $cust = $origCustomer;
                    if (empty($cust['type'])) {
                        $doc = preg_replace('/\D+/', '', (string)($cust['document'] ?? ''));
                        if (strlen($doc) === 11) $cust['type'] = 'individual';
                        elseif (strlen($doc) === 14) $cust['type'] = 'company';
                    }
                    $cust = self::ensureCustomerPhones($cust);
                    $input['customer'] = $cust;
                } else {
                    // Cartão: se houver divergência de CPF e não foi possível corrigir na Stone,
                    // enviar o objeto customer completo para que a ordem use os dados corretos
                    if ($docOk === false) {
                        $cust = $origCustomer;
                        if (empty($cust['type'])) {
                            $doc = preg_replace('/\D+/', '', (string)($cust['document'] ?? ''));
                            if (strlen($doc) === 11) $cust['type'] = 'individual';
                            elseif (strlen($doc) === 14) $cust['type'] = 'company';
                        }
                        $cust = self::ensureCustomerPhones($cust);
                        $input['customer'] = $cust;
                    } else {
                        $input['customer'] = ['id' => $stoneCustomerId];
                    }
                }
                Database::run('UPDATE payments SET customer_id = ? WHERE id = ?', [$localCustomerId, $paymentId]);
            }
            // Só processar cartão quando o método de pagamento é cartão (evita tentar criar cartão em fluxos PIX)
            if (in_array((string)$methodType, ['credit_card','debit_card'], true) && isset($input['card']) && is_array($input['card']) && !empty($input['card'])) {
                if (!empty($input['card']['token'])) {
                    $input['card_token'] = $input['card']['token'];
                    $tok = (string)$input['card']['token'];
                    if (strlen($tok) <= 80) {
                        Database::run('INSERT INTO payment_methods (site_id, customer_id, type, stone_token, created_at, updated_at) VALUES (?, ?, "card", ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE updated_at = NOW()', [
                            $site['id'], $localCustomerId, $tok,
                        ]);
                        $pm = Database::run('SELECT * FROM payment_methods WHERE site_id = ? AND stone_token = ? ORDER BY id DESC LIMIT 1', [$site['id'], $tok])->fetch() ?: [];
                        $localPaymentMethodId = (int)($pm['id'] ?? 0) ?: $localPaymentMethodId;
                        if ($localPaymentMethodId) Database::run('UPDATE payments SET payment_method_id = ? WHERE id = ?', [$localPaymentMethodId, $paymentId]);
                    }
                } else {
                    $stoneClient = isset($stoneClient) ? $stoneClient : \App\Infrastructure\Stone\StoneClient::forSite($site);
                    [$stoneCardId, $localPaymentMethodId] = $this->ensureStoneCardAndLocalMethod($site, $stoneCustomerId, $localCustomerId, $input['card'], $stoneClient);
                    if ($stoneCardId) $input['card_id'] = $stoneCardId;
                    if ($localPaymentMethodId) Database::run('UPDATE payments SET payment_method_id = ? WHERE id = ?', [$localPaymentMethodId, $paymentId]);
                }
            }
        } catch (\App\Infrastructure\Stone\StoneApiException $e) {
            Database::run('UPDATE payments SET status = "failed", last_error = ?, response_payload = ?, updated_at = NOW() WHERE id = ?', [
                $e->getMessage(), json_encode($e->getRaw(), JSON_UNESCAPED_UNICODE), $paymentId
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Database::run('UPDATE payments SET status = "failed", last_error = ?, updated_at = NOW() WHERE id = ?', [ $e->getMessage(), $paymentId ]);
            throw $e;
        }

        // Build minimal Order payload if client sent simplified fields
        $orderPayload = $input;
        if (!isset($input['payments']) && $methodType && $amount > 0) {
            $orderPayload = self::buildMinimalOrderPayload($input, $amount, $clientRef, $currency);
        }

        // Attempt to send to Stone (create order) sincronamente
        $stone = \App\Infrastructure\Stone\StoneClient::forSite($site);
        try {
            // A referência de negócio pode repetir; a idempotência técnica é por tentativa/requisição.
            $idemKey = trim((string)($input['idempotency_key'] ?? ''));
            if ($idemKey === '') {
                $idemKey = $clientRef . '-p' . $paymentId;
            }
            $stoneRes = $stone->createOrder($orderPayload, $idemKey);

            $status = self::mapStoneStatus($stoneRes) ?? 'processing';
            $stoneId = $stoneRes['charges'][0]['id'] ?? ($stoneRes['id'] ?? null);
            $providerEventId = (string)($stoneRes['id'] ?? '');
            $metaArr = [];
            if ($methodType === 'pix') {
                $pixInfo = self::extractPixInfo($stoneRes);
                if ($pixInfo) $metaArr['pix'] = $pixInfo;
            } elseif ($methodType === 'boleto') {
                $boletoInfo = self::extractBoletoInfo($stoneRes);
                if ($boletoInfo) $metaArr['boleto'] = $boletoInfo;
            }
            // If the provider returned a business failure inside a 200/201 response, capture messages
            if ($status === 'failed') {
                $provErrs = self::extractProviderErrors($stoneRes);
                if ($provErrs) {
                    $metaArr['provider_errors'] = $provErrs;
                    // Translate to PT for UI
                    $provErrsPt = [];
                    foreach ($provErrs as $e) {
                        $m = (string)($e['message'] ?? '');
                        $provErrsPt[] = ['message' => self::translateProviderMessagePt($m)];
                    }
                    if ($provErrsPt) $metaArr['provider_errors_pt'] = $provErrsPt;
                    $msg = implode('; ', array_filter(array_map(static fn($e)=>$e['message'] ?? null, $provErrs)));
                    if ($msg !== '') {
                        Database::run('UPDATE payments SET last_error = ? WHERE id = ?', [$msg, $paymentId]);
                    }
                }
            }

            if (!empty($metaArr)) {
                try {
                    Database::run('UPDATE payments SET status = ?, stone_payment_id = ?, response_payload = ?, metadata = ?, provider_event_id = COALESCE(provider_event_id, ?), updated_at = NOW() WHERE id = ?', [
                        $status,
                        $stoneId,
                        json_encode($stoneRes, JSON_UNESCAPED_UNICODE),
                        json_encode($metaArr, JSON_UNESCAPED_UNICODE),
                        ($providerEventId !== '' ? $providerEventId : null),
                        $paymentId,
                    ]);
                } catch (\Throwable $e) {
                    // fallback without provider_event_id column
                    Database::run('UPDATE payments SET status = ?, stone_payment_id = ?, response_payload = ?, metadata = ?, updated_at = NOW() WHERE id = ?', [
                        $status,
                        $stoneId,
                        json_encode($stoneRes, JSON_UNESCAPED_UNICODE),
                        json_encode($metaArr, JSON_UNESCAPED_UNICODE),
                        $paymentId,
                    ]);
                }
            } else {
                try {
                    Database::run('UPDATE payments SET status = ?, stone_payment_id = ?, response_payload = ?, provider_event_id = COALESCE(provider_event_id, ?), updated_at = NOW() WHERE id = ?', [
                        $status,
                        $stoneId,
                        json_encode($stoneRes, JSON_UNESCAPED_UNICODE),
                        ($providerEventId !== '' ? $providerEventId : null),
                        $paymentId,
                    ]);
                } catch (\Throwable $e) {
                    // fallback
                    Database::run('UPDATE payments SET status = ?, stone_payment_id = ?, response_payload = ?, updated_at = NOW() WHERE id = ?', [
                        $status,
                        $stoneId,
                        json_encode($stoneRes, JSON_UNESCAPED_UNICODE),
                        $paymentId,
                    ]);
                }
            }
        } catch (\App\Infrastructure\Stone\StoneApiException $e) {
            $detail = ['code' => $e->getErrorCode(), 'message' => $e->getErrorMessage(), 'errors' => $e->getErrors()];
            Database::run('UPDATE payments SET status = "failed", last_error = ?, response_payload = ?, updated_at = NOW() WHERE id = ?', [
                $e->getMessage(), json_encode($e->getRaw(), JSON_UNESCAPED_UNICODE), $paymentId
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Database::run('UPDATE payments SET status = "failed", last_error = ?, updated_at = NOW() WHERE id = ?', [ $e->getMessage(), $paymentId ]);
            throw $e;
        }

        $stmt = Database::run('SELECT * FROM payments WHERE id = ?', [$paymentId]);
        return $stmt->fetch() ?: [];
    }

    private function ensureStoneAndLocalCustomer(array $site, array $customer, ?StoneClient $stone = null, int $paymentId = 0): array
    {
        // Ensure required fields for Stone customer payload
        $payload = $customer;
        if (!isset($payload['type'])) {
            $doc = preg_replace('/\D+/', '', (string)($payload['document'] ?? ''));
            if (strlen($doc) === 11) {
                $payload['type'] = 'individual';
            } elseif (strlen($doc) === 14) {
                $payload['type'] = 'company';
            }
        }
        $payload = self::ensureCustomerPhones($payload);
        $stone = $stone ?: new StoneClient(\App\Infrastructure\Stone\StoneAccount::resolveForSite($site));
        $email = (string)($payload['email'] ?? '');
        $document = preg_replace('/\D+/', '', (string)($payload['document'] ?? '')) ?: null;
        $incomingName = is_string($payload['name'] ?? null) ? trim((string)$payload['name']) : '';
        $env = self::resolveEnvFromSite($site);

        // 1) Tenta resolver via cadastro local
        if ($email !== '' || $document) {
            $cond = 'site_id = ? AND env = ?';
            $params = [$site['id'], $env];
            if ($email !== '') { $cond .= ' AND email = ?'; $params[] = $email; }
            if ($document) { $cond .= ' AND document = ?'; $params[] = $document; }
            $row = Database::run('SELECT * FROM customers WHERE ' . $cond . ' ORDER BY id DESC LIMIT 1', $params)->fetch();
            if ($row && !empty($row['stone_customer_id'])) {
                // docOk desconhecido quando reutiliza cadastro local
                $this->maybeUpdateCustomerName(
                    $site,
                    $stone,
                    $paymentId,
                    (int)$row['id'],
                    (string)$row['stone_customer_id'],
                    (string)($row['name'] ?? ''),
                    $incomingName
                );
                return [(string)$row['stone_customer_id'], (int)$row['id'], null];
            }
        }

        // 2) Tenta localizar na Stone por email/document
        $found = $stone->findCustomer(null, $email ?: null, $document ?: null);
        if (is_array($found) && !empty($found['id'])) {
            $stoneId = (string)$found['id'];
            $name = (string)($payload['name'] ?? ($found['name'] ?? null));
            $email = $email ?: ($found['email'] ?? null);
            $document = $document ?: ($found['document'] ?? null);
            $docOk = null;
            if ($document && isset($payload['document'])) {
                $docOk = preg_replace('/\D+/', '', (string)$payload['document']) === preg_replace('/\D+/', '', (string)$document);
                // Se divergir e o payload tiver CPF/CNPJ válido, tenta atualizar na Stone
                if ($docOk === false) {
                    try {
                        $upd = ['document' => preg_replace('/\D+/', '', (string)$payload['document'])];
                        // mantém name/email se vieram
                        if (!empty($payload['name'])) $upd['name'] = (string)$payload['name'];
                        if (!empty($payload['email'])) $upd['email'] = (string)$payload['email'];
                        $stone->updateCustomer($stoneId, $upd);
                        $document = $upd['document'];
                        $docOk = true;
                    } catch (\Throwable $e) {
                        // Se não conseguir atualizar, segue com docOk=false para enviar customer completo na ordem
                        $docOk = false;
                    }
                }
            }
            Database::run('INSERT INTO customers (site_id, stone_customer_id, env, name, email, document, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name), email=VALUES(email), document=VALUES(document), updated_at=NOW()', [
                $site['id'], $stoneId, $env, $name ?: null, $email ?: null, $document ?: null,
            ]);
            $row = Database::run('SELECT * FROM customers WHERE site_id = ? AND stone_customer_id = ? AND env = ? LIMIT 1', [$site['id'], $stoneId, $env])->fetch() ?: [];
            $this->maybeUpdateCustomerName(
                $site,
                $stone,
                $paymentId,
                (int)($row['id'] ?? 0) ?: null,
                $stoneId,
                (string)($found['name'] ?? ($row['name'] ?? '')),
                $incomingName
            );
            return [$stoneId, (int)($row['id'] ?? 0) ?: null, $docOk];
        }

        // 3) Não existe: criar na Stone e persistir local
        $resp = $stone->createCustomer($payload);
        $stoneId = (string)($resp['id'] ?? '');
        if ($stoneId === '') {
            throw new \RuntimeException('Failed to create customer at Stone');
        }
        $name = (string)($payload['name'] ?? ($resp['name'] ?? null));
        $email = (string)($payload['email'] ?? ($resp['email'] ?? null));
        $document = (string)($payload['document'] ?? ($resp['document'] ?? null));
        Database::run('INSERT INTO customers (site_id, stone_customer_id, env, name, email, document, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name), email=VALUES(email), document=VALUES(document), updated_at=NOW()', [
            $site['id'], $stoneId, $env, $name ?: null, $email ?: null, $document ?: null,
        ]);
        $row = Database::run('SELECT * FROM customers WHERE site_id = ? AND stone_customer_id = ? AND env = ? LIMIT 1', [$site['id'], $stoneId, $env])->fetch() ?: [];
        return [$stoneId, (int)($row['id'] ?? 0) ?: null, true];
    }

    private static function resolveEnvFromSite(array $site): string
    {
        try {
            $acc = \App\Infrastructure\Stone\StoneAccount::resolveForSite($site);
            $env = strtolower((string)($acc['active_env'] ?? 'sandbox'));
        } catch (\Throwable $e) {
            $env = strtolower((string)($site['active_env'] ?? 'sandbox'));
        }
        if ($env === 'homolog') $env = 'sandbox';
        return in_array($env, ['sandbox','production'], true) ? $env : 'sandbox';
    }

    private function maybeUpdateCustomerName(array $site, StoneClient $stone, int $paymentId, ?int $localCustomerId, ?string $stoneCustomerId, ?string $currentName, ?string $incomingName): void
    {
        $newName = is_string($incomingName) ? trim($incomingName) : '';
        if ($newName === '' || $stoneCustomerId === null || $stoneCustomerId === '') return;

        $oldName = is_string($currentName) ? trim($currentName) : '';
        if (self::normalizeNameForCompare($oldName) === self::normalizeNameForCompare($newName)) return;

        if (!$localCustomerId) {
            $env = self::resolveEnvFromSite($site);
            $row = Database::run('SELECT id, name FROM customers WHERE site_id = ? AND stone_customer_id = ? AND env = ? LIMIT 1', [
                $site['id'], $stoneCustomerId, $env,
            ])->fetch();
            if ($row) {
                $localCustomerId = (int)($row['id'] ?? 0) ?: null;
                if ($oldName === '' && isset($row['name'])) $oldName = (string)$row['name'];
            }
        }

        try {
            $stone->updateCustomer($stoneCustomerId, ['name' => $newName]);
        } catch (\Throwable $e) {
            // Best-effort: não interromper pagamento por falha ao atualizar nome
        }

        if ($localCustomerId) {
            Database::run('UPDATE customers SET name = ?, updated_at = NOW() WHERE id = ?', [$newName, $localCustomerId]);
            try {
                Database::run('INSERT INTO customer_name_history (customer_id, payment_id, old_name, new_name, created_at) VALUES (?, ?, ?, ?, NOW())', [
                    $localCustomerId,
                    $paymentId > 0 ? $paymentId : null,
                    $oldName !== '' ? $oldName : null,
                    $newName,
                ]);
            } catch (\Throwable $e) {
                // tabela pode não existir ainda; ignorar
            }
        }
    }

    private static function normalizeNameForCompare(?string $name): string
    {
        $name = is_string($name) ? trim($name) : '';
        if ($name === '') return '';
        $name = preg_replace('/\s+/', ' ', $name);
        if (function_exists('mb_strtoupper')) return mb_strtoupper($name, 'UTF-8');
        return strtoupper($name);
    }

    private function ensureStoneCardAndLocalMethod(array $site, ?string $stoneCustomerId, ?int $localCustomerId, array $card, ?StoneClient $stone = null): array
    {
        $stoneCardId = null; $localMethodId = null;
        $stone = $stone ?: new StoneClient(\App\Infrastructure\Stone\StoneAccount::resolveForSite($site));
        $payload = $card;
        if (!isset($payload['customer_id']) && $stoneCustomerId) $payload['customer_id'] = $stoneCustomerId;
        // normalize token key
        if (!isset($payload['token']) && isset($payload['card_token'])) $payload['token'] = $payload['card_token'];
        try {
            $res = $stone->createCard($payload);
            $stoneCardId = (string)($res['id'] ?? '');
            // Persist local payment method
            $brand = (string)($res['brand'] ?? ($card['brand'] ?? '')) ?: null;
            $last4 = (string)($res['last_four_digits'] ?? ($res['last4'] ?? '')) ?: null;
            $expMonth = isset($res['exp_month']) ? (int)$res['exp_month'] : null;
            $expYear = isset($res['exp_year']) ? (int)$res['exp_year'] : null;
            $holder = (string)($res['holder_name'] ?? ($card['holder_name'] ?? '')) ?: null;
            $storeToken = $stoneCardId ?: ($payload['token'] ?? null);
            if ($storeToken !== null && strlen((string)$storeToken) <= 80) {
                Database::run('INSERT INTO payment_methods (site_id, customer_id, type, stone_token, brand, last4, exp_month, exp_year, holder_name, created_at, updated_at) VALUES (?, ?, "card", ?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE updated_at = NOW()', [
                    $site['id'], $localCustomerId, $storeToken, $brand, $last4, $expMonth, $expYear, $holder,
                ]);
                $pm = Database::run('SELECT * FROM payment_methods WHERE site_id = ? AND stone_token = ? ORDER BY id DESC LIMIT 1', [$site['id'], $storeToken])->fetch() ?: [];
            } else {
                $pm = [];
            }
            $localMethodId = (int)($pm['id'] ?? 0) ?: null;
        } catch (\Throwable $e) {
            // If card creation fails but a token is present, proceed with token-only flow
            if (!empty($payload['token'])) {
                $tok = (string)$payload['token'];
                if (strlen($tok) <= 80) {
                    Database::run('INSERT INTO payment_methods (site_id, customer_id, type, stone_token, created_at, updated_at) VALUES (?, ?, "card", ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE updated_at = NOW()', [
                        $site['id'], $localCustomerId, $tok,
                    ]);
                    $pm = Database::run('SELECT * FROM payment_methods WHERE site_id = ? AND stone_token = ? ORDER BY id DESC LIMIT 1', [$site['id'], $tok])->fetch() ?: [];
                } else {
                    $pm = [];
                }
                $localMethodId = (int)($pm['id'] ?? 0) ?: null;
            } else {
                throw $e;
            }
        }
        return [$stoneCardId, $localMethodId];
    }

    private static function mapStoneStatus(array $stoneRes): ?string
    {
        $status = $stoneRes['charges'][0]['status'] ?? ($stoneRes['status'] ?? null);
        if (!$status) return null;
        $map = [
            'paid' => 'paid',
            'authorized' => 'authorized',
            'processing' => 'processing',
            'pending' => 'processing',
            'canceled' => 'canceled',
            'refunded' => 'refunded',
            'failed' => 'failed',
            'chargeback' => 'chargeback',
        ];
        return $map[strtolower((string)$status)] ?? null;
    }

    private static function extractPixInfo(array $stoneRes): ?array
    {
        $get = static function(array $arr, string $key): mixed {
            return array_key_exists($key, $arr) ? $arr[$key] : null;
        };
        $charge = $get($stoneRes, 'charges');
        if (is_array($charge) && isset($charge[0]) && is_array($charge[0])) {
            $charge = $charge[0];
        } elseif (!is_array($charge)) {
            $charge = $stoneRes;
        }
        $tx = $get($charge, 'last_transaction');
        if (!is_array($tx)) $tx = $get($stoneRes, 'last_transaction');
        if (!is_array($tx)) $tx = $get($charge, 'transaction');
        if (!is_array($tx)) return null;

        $qr = $get($tx, 'qr_code') ?? $get($tx, 'qr_code_emv') ?? $get($tx, 'emv') ?? $get($tx, 'payload') ?? null;
        $qrUrl = $get($tx, 'qr_code_url') ?? $get($tx, 'qr_url') ?? null;
        $expiresAt = $get($tx, 'expires_at') ?? $get($tx, 'expiration_date') ?? null;
        if (!$qr && !$qrUrl && !$expiresAt) return null;
        return [
            'qr_code' => $qr,
            'qr_code_url' => $qrUrl,
            'expires_at' => $expiresAt,
            'transaction_id' => $get($tx, 'id'),
            'status' => $get($tx, 'status'),
        ];
    }

    private static function extractBoletoInfo(array $stoneRes): ?array
    {
        $get = static function(array $arr, string $key): mixed {
            return array_key_exists($key, $arr) ? $arr[$key] : null;
        };
        $charge = $get($stoneRes, 'charges');
        if (is_array($charge) && isset($charge[0]) && is_array($charge[0])) {
            $charge = $charge[0];
        } elseif (!is_array($charge)) {
            $charge = $stoneRes;
        }
        $tx = $get($charge, 'last_transaction');
        if (!is_array($tx)) $tx = $get($stoneRes, 'last_transaction');
        if (!is_array($tx)) $tx = $get($charge, 'transaction');
        if (!is_array($tx)) return null;

        $url = $get($tx, 'url') ?? $get($tx, 'boleto_url') ?? null;
        $pdf = $get($tx, 'pdf') ?? $get($tx, 'pdf_url') ?? null;
        $line = $get($tx, 'line') ?? $get($tx, 'digitable_line') ?? null;
        $barcode = $get($tx, 'barcode') ?? $get($tx, 'bar_code') ?? null;
        $qr = $get($tx, 'qr_code') ?? null;
        $dueAt = $get($tx, 'due_at') ?? $get($tx, 'due_date') ?? null;
        $docNum = $get($tx, 'document_number') ?? null;
        $bank = $get($tx, 'bank') ?? null;
        $type = $get($tx, 'type') ?? null;
        $inst = $get($tx, 'instructions') ?? null;
        $nosso = $get($tx, 'nosso_numero') ?? null;
        if (!$url && !$pdf && !$line && !$barcode && !$qr && !$dueAt && !$docNum) return null;
        return [
            'url' => $url,
            'pdf' => $pdf,
            'line' => $line,
            'barcode' => $barcode,
            'qr_code' => $qr,
            'due_at' => $dueAt,
            'document_number' => $docNum,
            'bank' => $bank,
            'type' => $type,
            'instructions' => $inst,
            'nosso_numero' => $nosso,
            'transaction_id' => $get($tx, 'id'),
            'status' => $get($tx, 'status'),
        ];
    }

    private static function extractProviderErrors(array $stoneRes): ?array
    {
        $errors = [];
        $charges = $stoneRes['charges'] ?? null;
        if (is_array($charges) && isset($charges[0]) && is_array($charges[0])) {
            $charge = $charges[0];
            $tx = $charge['last_transaction'] ?? [];
            if (is_array($tx)) {
                $gw = $tx['gateway_response'] ?? [];
                if (is_array($gw)) {
                    if (isset($gw['errors']) && is_array($gw['errors'])) {
                        foreach ($gw['errors'] as $e) {
                            if (is_array($e)) $errors[] = ['code' => $e['code'] ?? null, 'message' => $e['message'] ?? null];
                        }
                    }
                    if (empty($errors) && isset($gw['message'])) {
                        $errors[] = ['code' => $gw['code'] ?? null, 'message' => $gw['message']];
                    }
                }
                // Some providers return top-level errors too
                if (empty($errors) && isset($tx['failure_reason'])) {
                    $errors[] = ['message' => (string)$tx['failure_reason']];
                }
            }
        }
        return !empty($errors) ? $errors : null;
    }

    private static function translateProviderMessagePt(string $message): string
    {
        return \App\Support\I18n::translateProviderMessagePt($message);
    }

    private static function ensureCustomerPhones(array $cust): array
    {
        // If phones already provided in expected structure, keep as-is
        $phones = $cust['phones'] ?? null;
        $hasHome = is_array($phones) && !empty($phones['home_phone']);
        $hasMobile = is_array($phones) && !empty($phones['mobile_phone']);
        if ($hasHome || $hasMobile) return $cust;

        // Map common aliases to mobile phone
        $country = (string)($cust['country_code'] ?? ($cust['ddi'] ?? '55'));
        $area = (string)($cust['area_code'] ?? ($cust['ddd'] ?? '11'));
        $num = (string)($cust['phone'] ?? ($cust['telefone'] ?? ($cust['celular'] ?? ($cust['mobile'] ?? ''))));
        $country = preg_replace('/\D+/', '', $country) ?: '55';
        $area = preg_replace('/\D+/', '', $area) ?: '11';
        $num = preg_replace('/\D+/', '', $num);
        if ($num === '') {
            // Fallback placeholder for sandbox if phone missing
            $num = '000000000';
        }
        $cust['phones'] = [
            'mobile_phone' => [
                'country_code' => $country,
                'area_code' => $area,
                'number' => $num,
            ],
        ];
        return $cust;
    }

    private static function buildMinimalOrderPayload(array $input, int $amount, string $clientRef, string $currency): array
    {
        $items = $input['items'] ?? [[
            'amount' => $amount,
            'description' => $input['description'] ?? 'Order ' . $clientRef,
            'quantity' => 1,
            'code' => $clientRef,
        ]];

        $payments = [];
        $method = $input['payment_method_type'] ?? '';
        if ($method === 'credit_card' || $method === 'debit_card') {
            $card = [];
            if (!empty($input['card_id'])) $card['id'] = $input['card_id'];
            if (!empty($input['card_token'])) $card['token'] = $input['card_token'];
            // Pass through optional holder_name and billing_address when provided in input.card
            if (isset($input['card']) && is_array($input['card'])) {
                if (!empty($input['card']['holder_name'])) $card['holder_name'] = $input['card']['holder_name'];
                if (!empty($input['card']['billing_address']) && is_array($input['card']['billing_address'])) {
                    $card['billing_address'] = $input['card']['billing_address'];
                }
            }
            $payments[] = [
                'payment_method' => 'credit_card',
                'credit_card' => [
                    'operation_type' => $input['operation_type'] ?? 'auth_and_capture',
                    'installments' => (int)($input['installments'] ?? 1),
                    // Pagar.me v5 usa statement_descriptor; manter compat com soft_descriptor
                    'statement_descriptor' => $input['statement_descriptor'] ?? ($input['soft_descriptor'] ?? null),
                    'card' => $card,
                ],
            ];
        } elseif ($method === 'pix') {
            $pixPay = [];
            $expiresAt = self::normalizeExpiresAt($input['expires_at'] ?? null);
            if ($expiresAt) {
                $pixPay['expires_at'] = $expiresAt;
            } else {
                $pixPay['expires_in'] = (int)($input['expires_in'] ?? 3600);
            }
            if (!empty($input['additional_information']) && is_array($input['additional_information'])) {
                $pixPay['additional_information'] = $input['additional_information'];
            }
            $payments[] = [ 'payment_method' => 'pix', 'pix' => $pixPay ];
        } elseif ($method === 'boleto') {
            $boletoPay = self::buildBoletoPayload($input);
            $payments[] = [ 'payment_method' => 'boleto', 'boleto' => $boletoPay ];
        }

        $order = [
            'closed' => true,
            'code' => $clientRef,
            'items' => $items,
            'payments' => $payments,
            'currency' => $currency,
        ];
        if (isset($input['customer']) && is_array($input['customer'])) {
            $cust = $input['customer'];
            $cid = (string)($cust['id'] ?? '');
            if ($method === 'pix' || $method === 'boleto') {
                // Para PIX/BOLETO, preferir enviar o objeto customer (evita falhas de ID não encontrado entre ambientes/contas)
                $order['customer'] = $cust;
            } else {
                if ($cid !== '') {
                    // Para cartões, customer_id costuma ser aceito de forma mais ampla
                    $order['customer_id'] = $cid;
                } else {
                    $order['customer'] = $cust;
                }
            }
        }
        return $order;
    }

    private static function validateSimplifiedOrderInput(array $input): void
    {
        $method = $input['payment_method_type'] ?? null;
        if ($method === 'credit_card' || $method === 'debit_card') {
            $cardId = $input['card_id'] ?? null; $cardToken = $input['card_token'] ?? null;
            $hasNested = false;
            if (isset($input['card']) && is_array($input['card'])) {
                $card = $input['card'];
                if (!empty($card['token']) || !empty($card['id']) || !empty($card['number'])) $hasNested = true;
            }
            if (!$cardId && !$cardToken && !$hasNested && !isset($input['payments'])) {
                throw new \InvalidArgumentException('For card payments, send card_token or card_id (or full payments.credit_card payload)');
            }
            $inst = (int)($input['installments'] ?? 1);
            if ($inst <= 0) throw new \InvalidArgumentException('installments must be >= 1');
        } elseif ($method === 'pix') {
            if (!empty($input['expires_at'])) {
                $expiresAt = self::normalizeExpiresAt($input['expires_at']);
                if (!$expiresAt) throw new \InvalidArgumentException('expires_at must be a valid date-time');
            } else {
                $exp = (int)($input['expires_in'] ?? 3600);
                if ($exp <= 0) throw new \InvalidArgumentException('expires_in must be > 0');
            }
        } elseif ($method === 'boleto') {
            $dueAt = self::normalizeDueAt($input['due_at'] ?? ($input['boleto']['due_at'] ?? null));
            if (!$dueAt) throw new \InvalidArgumentException('due_at is required for boleto');
        }
    }

    private static function extractCardToken(array $input): string
    {
        $tok = '';
        if (isset($input['card']) && is_array($input['card']) && !empty($input['card']['token'])) {
            $tok = (string)$input['card']['token'];
        } elseif (!empty($input['card_token'])) {
            $tok = (string)$input['card_token'];
        }
        return $tok;
    }

    private static function extractCustomerName(array $input): ?string
    {
        $c = $input['customer'] ?? ($input['cliente'] ?? null);
        if (is_array($c)) {
            $name = $c['name'] ?? ($c['nome'] ?? null);
            $name = is_string($name) ? trim($name) : '';
            if ($name !== '') return $name;
        }
        $name = $input['customer_name'] ?? ($input['nome'] ?? ($input['name'] ?? null));
        $name = is_string($name) ? trim($name) : '';
        return $name !== '' ? $name : null;
    }

    private function applyInterestPayerToAmount(array $site, array $input): array
    {
        $method = strtolower((string)($input['payment_method_type'] ?? ''));
        if (!in_array($method, ['credit_card','debit_card'], true)) return $input;

        $installments = (int)($input['installments'] ?? 1);
        if ($installments <= 1) return $input;

        $payer = self::resolveInterestPayerFromInput($site, $input);
        if ($payer !== 'user') return $input; // vendedor assume: mantém valor base

        $amount = (int)($input['amount_cents'] ?? 0);
        if ($amount <= 0) return $input;

        $bin = self::extractCardBinFromInput($input);
        $installmentsList = null;
        if ($bin) {
            $stone = \App\Infrastructure\Stone\StoneClient::forSite($site);
            $svc = new \App\Application\CardService();
            $quote = $svc->quote($bin, $amount, $installments, null, null, null, $stone, false);
            $installmentsList = $quote['installments'] ?? null;
        } else {
            // Fallback local quando não há BIN
            $installmentsList = \App\Application\CardService::computeInstallmentsLocally($amount, $installments, null, null, null);
        }

        if (!is_array($installmentsList)) {
            throw new \InvalidArgumentException('Não foi possível calcular parcelas para aplicar juros.');
        }
        $total = self::extractInstallmentTotal($installmentsList, $installments);
        if (!$total || $total <= 0) {
            throw new \InvalidArgumentException('Parcela não disponível para aplicar juros.');
        }

        $input['amount_cents'] = $total;
        if (array_key_exists('valor_centavos', $input)) $input['valor_centavos'] = $total;
        return $input;
    }

    private static function resolveInterestPayerFromInput(array $site, array $input): string
    {
        $juros = self::parseJurosFlag($input);
        if ($juros === 'C') return 'user';
        if ($juros === 'V') return 'merchant';

        $def = strtolower((string)($site['interest_payer'] ?? 'user'));
        return in_array($def, ['user','merchant'], true) ? $def : 'user';
    }

    private static function parseJurosFlag(array $input): ?string
    {
        if (!array_key_exists('juros', $input)) return null;
        $j = strtoupper(trim((string)($input['juros'] ?? '')));
        if ($j === '') return null;
        if (!in_array($j, ['C','V'], true)) {
            throw new \InvalidArgumentException('Campo "juros" inválido: use "C" (comprador) ou "V" (vendedor).');
        }
        return $j;
    }

    private static function extractCardBinFromInput(array $input): ?string
    {
        $bin = $input['bin'] ?? null;
        if (!$bin && isset($input['numero_cartao'])) $bin = $input['numero_cartao'];
        if (!$bin && isset($input['card_number'])) $bin = $input['card_number'];
        if (!$bin && isset($input['card']) && is_array($input['card'])) {
            $card = $input['card'];
            if (isset($card['bin'])) $bin = $card['bin'];
            elseif (isset($card['number'])) $bin = $card['number'];
            elseif (isset($card['numero'])) $bin = $card['numero'];
        }
        $digits = preg_replace('/\D+/', '', (string)$bin);
        if (strlen($digits) >= 6) return substr($digits, 0, 6);
        return null;
    }

    private static function extractInstallmentTotal(array $installments, int $n): ?int
    {
        foreach ($installments as $row) {
            if ((int)($row['number'] ?? 0) === $n) {
                $total = (int)($row['total_cents'] ?? 0);
                return $total > 0 ? $total : null;
            }
        }
        return null;
    }

    private static function normalizeInputPt(array $in): array
    {
        // Mapear chaves em PT para o formato interno EN, sem sobrescrever se já veio EN
        $map = [
            'valor_centavos' => 'amount_cents',
            'referencia_cliente' => 'client_reference_id',
            'chave_idempotencia' => 'idempotency_key',
            'moeda' => 'currency',
            'tipo_metodo_pagamento' => 'payment_method_type',
            'token_cartao' => 'card_token',
            'id_cartao' => 'card_id',
            'parcelas' => 'installments',
            'descricao' => 'description',
            'cliente' => 'customer',
        ];
        foreach ($map as $pt => $en) {
            if (!array_key_exists($en, $in) && array_key_exists($pt, $in)) {
                $in[$en] = $in[$pt];
            }
        }
        if (!array_key_exists('expires_at', $in) && array_key_exists('expira_em', $in)) {
            if (is_numeric($in['expira_em'])) {
                $in['expires_in'] = (int)$in['expira_em'];
            } else {
                $in['expires_at'] = $in['expira_em'];
            }
        }
        if (!array_key_exists('due_at', $in)) {
            if (array_key_exists('data_vencimento', $in)) {
                $in['due_at'] = $in['data_vencimento'];
            } elseif (array_key_exists('vencimento', $in)) {
                $in['due_at'] = $in['vencimento'];
            }
        }

        // Se houver card token, garantir billing_address em card a partir de customer.address
        $hasCardToken = false;
        if (!empty($in['card_token'])) $hasCardToken = true;
        if (isset($in['card']) && is_array($in['card'])) {
            if (!empty($in['card']['token']) || !empty($in['card']['id'])) $hasCardToken = true;
        }
        if ($hasCardToken) {
            $ba = null;
            if (isset($in['card']['billing_address']) && is_array($in['card']['billing_address'])) {
                $ba = $in['card']['billing_address'];
            } elseif (isset($in['billing_address']) && is_array($in['billing_address'])) {
                $ba = $in['billing_address'];
            } elseif (isset($in['endereco_cobranca']) && is_array($in['endereco_cobranca'])) {
                $ba = $in['endereco_cobranca'];
            } elseif (isset($in['card']['endereco_cobranca']) && is_array($in['card']['endereco_cobranca'])) {
                $ba = $in['card']['endereco_cobranca'];
            } elseif (isset($in['customer']['billing_address']) && is_array($in['customer']['billing_address'])) {
                $ba = $in['customer']['billing_address'];
            } elseif (isset($in['customer']['address']) && is_array($in['customer']['address'])) {
                $ba = $in['customer']['address'];
            }
            if (is_array($ba)) {
                if (!isset($in['card']) || !is_array($in['card'])) $in['card'] = [];
                $in['card']['billing_address'] = self::normalizeBillingAddress($ba);
            }
        }
        return $in;
    }

    private static function normalizeBillingAddress(array $addr): array
    {
        $map = [
            'linha1' => 'line_1', 'line1' => 'line_1',
            'linha2' => 'line_2', 'line2' => 'line_2',
            'cep' => 'zip_code', 'zip' => 'zip_code',
            'cidade' => 'city', 'municipio' => 'city',
            'estado' => 'state', 'uf' => 'state',
            'pais' => 'country'
        ];
        foreach ($map as $pt => $en) {
            if (!isset($addr[$en]) && isset($addr[$pt])) $addr[$en] = $addr[$pt];
        }
        if (empty($addr['line_1'] ?? '') && !empty($addr['street'] ?? '') && !empty($addr['number'] ?? '')) {
            $addr['line_1'] = (string)$addr['street'] . ', ' . (string)$addr['number'];
        }
        if (isset($addr['zip_code'])) {
            $zip = preg_replace('/\D+/', '', (string)$addr['zip_code']);
            if (strlen($zip) === 7) $zip .= '0';
            $addr['zip_code'] = $zip;
        }
        if (isset($addr['state'])) $addr['state'] = strtoupper((string)$addr['state']);
        if (isset($addr['country'])) {
            $c = strtoupper((string)$addr['country']);
            if ($c === 'BRASIL' || $c === 'BRA') $c = 'BR';
            $addr['country'] = $c;
        }
        return $addr;
    }

    private static function buildBoletoPayload(array $input): array
    {
        $b = [];
        if (isset($input['boleto']) && is_array($input['boleto'])) {
            $b = $input['boleto'];
        }
        // PT aliases (top-level and inside boleto)
        $aliases = [
            'bank' => ['bank','banco'],
            'type' => ['type','tipo','tipo_boleto'],
            'document_number' => ['document_number','numero_documento','numero'],
            'nosso_numero' => ['nosso_numero'],
            'instructions' => ['instructions','instrucoes'],
            'metadata' => ['metadata'],
        ];
        foreach ($aliases as $key => $cands) {
            if (!array_key_exists($key, $b)) {
                foreach ($cands as $cand) {
                    if (array_key_exists($cand, $b)) { $b[$key] = $b[$cand]; break; }
                    if (array_key_exists($cand, $input)) { $b[$key] = $input[$cand]; break; }
                }
            }
        }
        $dueAt = self::normalizeDueAt($b['due_at'] ?? ($input['due_at'] ?? ($input['data_vencimento'] ?? ($input['vencimento'] ?? null))));
        if ($dueAt) $b['due_at'] = $dueAt;
        // Normalize instructions
        if (isset($b['instructions'])) {
            $inst = $b['instructions'];
            if (is_string($inst)) {
                $lines = array_filter(array_map('trim', preg_split('/\r\n|\n|\r/', $inst)));
                $b['instructions'] = $lines ?: [$inst];
            }
        }
        // Remove empty values
        foreach ($b as $k => $v) {
            if ($v === null || $v === '' || (is_array($v) && empty($v))) unset($b[$k]);
        }
        return $b;
    }

    private static function normalizeExpiresAt(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:sP');
        }
        $raw = trim((string)($value ?? ''));
        if ($raw === '' || is_numeric($raw)) return null;
        try {
            $dt = new \DateTimeImmutable($raw);
        } catch (\Throwable $e) {
            return null;
        }
        return $dt->format('Y-m-d\TH:i:sP');
    }

    private static function normalizeDueAt(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:sP');
        }
        $raw = trim((string)($value ?? ''));
        if ($raw === '') return null;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            $raw = $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        try {
            $dt = new \DateTimeImmutable($raw);
        } catch (\Throwable $e) {
            return null;
        }
        return $dt->format('Y-m-d\TH:i:sP');
    }

    private static function normalizeProviderFields(array $in): array
    {
        // Normalize address fields (country/state upper, zip digits) for customer and card.billing_address
        $normAddr = function (array $addr): array {
            if (isset($addr['country'])) {
                $c = strtoupper((string)$addr['country']);
                if ($c === 'BRASIL' || $c === 'BRA') $c = 'BR';
                $addr['country'] = $c;
            }
            if (isset($addr['state'])) {
                $addr['state'] = strtoupper((string)$addr['state']);
            }
            if (isset($addr['zip_code'])) {
                $zip = preg_replace('/\D+/', '', (string)$addr['zip_code']);
                if (strlen($zip) === 7) {
                    // Heurística: CEP com 7 dígitos, completar com zero à direita
                    $zip .= '0';
                }
                $addr['zip_code'] = $zip;
            }
            // Se veio street/number e não veio line_1, montar
            if (empty($addr['line_1'] ?? '') && !empty($addr['street'] ?? '') && !empty($addr['number'] ?? '')) {
                $addr['line_1'] = (string)$addr['street'] . ', ' . (string)$addr['number'];
            }
            return $addr;
        };

        // 1) Normaliza customer.address
        if (isset($in['customer']) && is_array($in['customer']) && isset($in['customer']['address']) && is_array($in['customer']['address'])) {
            $in['customer']['address'] = $normAddr($in['customer']['address']);
        }

        // 2) Mapear aliases para card.token/card.id/card.holder_name (sem criar card vazio à toa)
        if (isset($in['card_token'])) {
            if (!isset($in['card']) || !is_array($in['card'])) $in['card'] = [];
            if (!isset($in['card']['token'])) $in['card']['token'] = $in['card_token'];
        }
        if (isset($in['card_id'])) {
            if (!isset($in['card']) || !is_array($in['card'])) $in['card'] = [];
            if (!isset($in['card']['id'])) $in['card']['id'] = $in['card_id'];
        }
        if (isset($in['holder_name'])) {
            if (!isset($in['card']) || !is_array($in['card'])) $in['card'] = [];
            if (!isset($in['card']['holder_name'])) $in['card']['holder_name'] = $in['holder_name'];
        }

        // 4) Coletar billing address de várias posições e colocar em card.billing_address
        $ba = null;
        if (isset($in['card']['billing_address']) && is_array($in['card']['billing_address'])) {
            $ba = $in['card']['billing_address'];
        } elseif (isset($in['billing_address']) && is_array($in['billing_address'])) {
            $ba = $in['billing_address'];
        } elseif (isset($in['endereco_cobranca']) && is_array($in['endereco_cobranca'])) {
            $ba = $in['endereco_cobranca'];
        } elseif (isset($in['card']['endereco_cobranca']) && is_array($in['card']['endereco_cobranca'])) {
            $ba = $in['card']['endereco_cobranca'];
        } elseif (isset($in['customer']['billing_address']) && is_array($in['customer']['billing_address'])) {
            $ba = $in['customer']['billing_address'];
        } elseif (isset($in['customer']['address']) && is_array($in['customer']['address'])) {
            // Como último recurso, derivar de customer.address
            $ba = $in['customer']['address'];
        }
        if (is_array($ba)) {
            // Mapear aliases PT → EN
            $map = [ 'linha1'=>'line_1','line1'=>'line_1','linha2'=>'line_2','line2'=>'line_2','cep'=>'zip_code','zip'=>'zip_code','cidade'=>'city','municipio'=>'city','estado'=>'state','uf'=>'state','pais'=>'country' ];
            foreach ($map as $pt=>$en) {
                if (!isset($ba[$en]) && isset($ba[$pt])) $ba[$en] = $ba[$pt];
            }
            $ba = $normAddr($ba);
            if (!isset($in['card']) || !is_array($in['card'])) $in['card'] = [];
            $in['card']['billing_address'] = $ba;
            unset($in['billing_address'], $in['endereco_cobranca'], $in['card']['endereco_cobranca'], $in['customer']['billing_address']);
        }
        // Se card.billing_address já existia, normalizar
        if (isset($in['card']['billing_address']) && is_array($in['card']['billing_address'])) {
            $in['card']['billing_address'] = $normAddr($in['card']['billing_address']);
        }
        return $in;
    }
}
