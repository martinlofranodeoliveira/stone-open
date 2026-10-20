<?php
declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\DB\Database;
use App\Infrastructure\Stone\StoneAccount;

final class WebhookService
{
    public function emitSubscriptionEvent(int $subscriptionId, string $eventType, ?array $payloadOverride = null, ?string $occurredAt = null): ?array
    {
        $sub = Database::run('SELECT * FROM subscriptions WHERE id = ? LIMIT 1', [$subscriptionId])->fetch();
        if (!$sub) return null;
        $site = Database::run('SELECT * FROM sites WHERE id = ? LIMIT 1', [$sub['site_id']])->fetch();
        if (!$site) return null;
        $cust = null;
        if (!empty($sub['customer_id'])) {
            $cust = Database::run('SELECT * FROM customers WHERE id = ? LIMIT 1', [$sub['customer_id']])->fetch() ?: null;
        }
        $plan = null;
        if (!empty($sub['plan_id'])) {
            $planEnv = $sub['env'] ?? null;
            if (!$planEnv) $planEnv = self::resolveEnvFromSite($site);
            $plan = Database::run('SELECT * FROM plans WHERE site_id = ? AND stone_plan_id = ? AND env = ? LIMIT 1', [$sub['site_id'], $sub['plan_id'], $planEnv])->fetch() ?: null;
        }
        $nowIso = $occurredAt ?: gmdate('c');
        $payload = $payloadOverride ?: [
            'type' => $eventType,
            'event' => $eventType,
            'occurred_at' => $nowIso,
            'data' => [
                'id' => $sub['stone_subscription_id'] ?? null,
                'status' => $sub['status'] ?? null,
                'plan_id' => $sub['plan_id'] ?? null,
                'start_at' => $sub['start_date'] ?? null,
                'end_at' => $sub['end_date'] ?? null,
                'currency' => $sub['currency'] ?? null,
                'amount' => isset($sub['amount_cents']) ? (int)$sub['amount_cents'] : null,
                'customer' => $cust ? [
                    'id' => $cust['stone_customer_id'] ?? null,
                    'name' => $cust['name'] ?? null,
                    'email' => $cust['email'] ?? null,
                    'document' => $cust['document'] ?? null,
                ] : null,
                'plan' => $plan ? [
                    'id' => $plan['stone_plan_id'] ?? null,
                    'name' => $plan['name'] ?? null,
                    'amount' => isset($plan['amount_cents']) ? (int)$plan['amount_cents'] : null,
                    'currency' => $plan['currency'] ?? null,
                    'interval' => $plan['interval_unit'] ?? null,
                    'interval_count' => isset($plan['interval_count']) ? (int)$plan['interval_count'] : null,
                    'billing_cycles' => isset($plan['billing_cycles']) ? (int)$plan['billing_cycles'] : null,
                ] : null,
            ],
        ];

        $providerEventId = 'manual_sub_' . (int)$subscriptionId . '_' . time() . '_' . random_int(1000, 9999);
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $occurredMysql = date('Y-m-d H:i:s');

        try {
            Database::run('INSERT INTO inbound_webhooks (provider, provider_event_id, event_type, stone_subscription_id, site_id, payment_id, raw_payload, status, processed_at) VALUES ("manual", ?, ?, ?, ?, NULL, ?, "processed", NOW())', [
                $providerEventId,
                $eventType,
                $sub['stone_subscription_id'] ?? null,
                $sub['site_id'],
                $payloadJson,
            ]);
        } catch (\Throwable $e) {
            // If duplicated, try to fetch existing
        }
        $inb = Database::run('SELECT * FROM inbound_webhooks WHERE provider = "manual" AND provider_event_id = ? LIMIT 1', [$providerEventId])->fetch();

        try {
            Database::run('INSERT IGNORE INTO subscription_events (subscription_id, provider_event_id, event_type, raw_payload, occurred_at) VALUES (?, ?, ?, ?, ?)', [
                $subscriptionId,
                $providerEventId,
                $eventType,
                $payloadJson,
                $occurredMysql,
            ]);
        } catch (\Throwable $e) {}

        if ($inb) {
            $this->forwardToSite((int)$sub['site_id'], (int)$inb['id'], null, $payload, 1);
            return ['inbound_id' => (int)$inb['id'], 'provider_event_id' => $providerEventId];
        }
        return null;
    }
    public function drainPending(int $limit = 50, int $budgetMs = 300): int
    {
        $start = (int) (microtime(true) * 1000);
        $processed = 0;
        while ($processed < $limit) {
            $row = Database::run('SELECT id FROM inbound_webhooks WHERE status = "pending" ORDER BY id ASC LIMIT 1')->fetch();
            if (!$row) break;
            $id = (int)$row['id'];
            try {
                $this->process($id);
                $processed++;
            } catch (\Throwable $e) {
                Database::run('UPDATE inbound_webhooks SET status = "failed", error = ? WHERE id = ?', [$e->getMessage(), $id]);
            }
            $elapsed = (int) (microtime(true) * 1000) - $start;
            if ($elapsed >= $budgetMs) break;
        }
        return $processed;
    }
    public function process(int $inboundId): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM inbound_webhooks WHERE id = ? LIMIT 1');
        $stmt->execute([$inboundId]);
        $webhook = $stmt->fetch();
        if (!$webhook) return;

        // Mark processing (idempotent + guard against races)
        $upd = Database::run('UPDATE inbound_webhooks SET status = "processing" WHERE id = ? AND status IN ("pending","failed")', [$inboundId]);
        if (method_exists($upd, 'rowCount')) {
            $aff = (int)$upd->rowCount();
            if ($aff === 0) {
                // Someone else claimed or it was already processed; avoid double work
                return;
            }
        }

        $payload = json_decode($webhook['raw_payload'] ?? '[]', true) ?: [];
        $eventType = (string)($payload['type'] ?? $payload['event'] ?? $webhook['event_type'] ?? '');
        $stonePaymentId = self::extractStonePaymentId($payload) ?? $webhook['stone_payment_id'] ?? null;
        $stoneSubscriptionId = $payload['data']['subscription']['id'] ?? ($payload['subscription_id'] ?? ($webhook['stone_subscription_id'] ?? null));
        $customerName = self::extractCustomerNameFromPayload($payload);
        $isInvoice = self::isInvoicePayload($payload, $eventType, $stoneSubscriptionId);

        $payment = null;
        if ($stonePaymentId) {
            $stmt2 = Database::run('SELECT * FROM payments WHERE stone_payment_id = ? LIMIT 1', [$stonePaymentId]);
            $payment = $stmt2->fetch();
        }
        // Fallback: procurar por client_reference_id (code) quando não achar por stone_payment_id
        if (!$payment) {
            $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
            $code = (string)($read($payload, ['data','charge','code'])
                ?? $read($payload, ['data','order','code'])
                ?? $read($payload, ['data','code'])
                ?? '');
            if ($code !== '') {
                $payByCode = Database::run('SELECT * FROM payments WHERE client_reference_id = ? ORDER BY id DESC LIMIT 1', [$code])->fetch();
                if ($payByCode) {
                    $payment = $payByCode;
                    if ($stonePaymentId && empty($payment['stone_payment_id'])) {
                        try { Database::run('UPDATE payments SET stone_payment_id = ?, updated_at = NOW() WHERE id = ?', [$stonePaymentId, $payment['id']]); } catch (\Throwable $e) {}
                    }
                }
            }
        }

        // Resolve site: prefer payment → subscription → heuristics (order/code/unique site)
        $siteId = $payment['site_id'] ?? $webhook['site_id'] ?? null;
        $subscription = null;
        if ($stoneSubscriptionId) {
            $stmt3 = Database::run('SELECT * FROM subscriptions WHERE stone_subscription_id = ? LIMIT 1', [$stoneSubscriptionId]);
            $subscription = $stmt3->fetch();
            if ($subscription && !$siteId) { $siteId = $subscription['site_id']; }
        }
        $siteRow = null;
        if ($siteId) {
            $siteRow = Database::run('SELECT * FROM sites WHERE id = ? LIMIT 1', [$siteId])->fetch() ?: null;
        }
        $env = self::resolveEnvFromSite($siteRow);

        // Create a minimal payment ledger entry for subscription charges when none exists
        if (!$payment && $siteId && $stonePaymentId && $eventType) {
            $mapped = self::mapEventToStatus($eventType, $payload);
            if (!$mapped) {
                $mapped = self::extractPayloadStatus($payload);
            }
            // Only ledger entries for typical charge/order/invoice events
            if (in_array($mapped, ['authorized','paid','processing','failed','canceled','refunded','chargeback'], true)) {
                $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
                $amount = (int)($read($payload, ['data','amount'])
                    ?? $read($payload, ['data','charge','amount'])
                    ?? $read($payload, ['data','charges',0,'amount'])
                    ?? 0);
                $currency = (string)($read($payload, ['data','currency'])
                    ?? $read($payload, ['data','charge','currency'])
                    ?? $read($payload, ['data','charges',0,'currency'])
                    ?? 'BRL');
                $code = (string)($read($payload, ['data','charge','code'])
                    ?? $read($payload, ['data','order','code'])
                    ?? $read($payload, ['data','code'])
                    ?? ('evt_' . ($webhook['provider_event_id'] ?? $inboundId)));
                $method = self::extractPaymentMethod($payload);
                $meta = [ 'source' => 'webhook_ledger' ];
                if ($stoneSubscriptionId) $meta['subscription_stone_id'] = $stoneSubscriptionId;
                $occ = self::extractSubscriptionOccurrence($payload); if ($occ) $meta['occurrence'] = $occ;
                $inst = self::extractCardInstallments($payload); if ($inst) $meta['card_installments'] = $inst;
                try {
                    Database::run('INSERT INTO payments (site_id, customer_id, payment_method_id, stone_payment_id, env, client_reference_id, amount_cents, currency, payment_method_type, description, customer_name, is_invoice, status, request_payload, response_payload, metadata, created_at, updated_at) VALUES (?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', [
                        $siteId,
                        $stonePaymentId,
                        $env,
                        substr($code, 0, 64),
                        $amount,
                        substr($currency ?: 'BRL',0,3),
                        $method ?: null,
                        null,
                        $customerName,
                        $isInvoice ? 1 : 0,
                        $mapped ?: 'processing',
                        json_encode(['source' => 'webhook_ledger'], JSON_UNESCAPED_UNICODE),
                        json_encode($payload, JSON_UNESCAPED_UNICODE),
                        json_encode($meta, JSON_UNESCAPED_UNICODE),
                    ]);
                    $pid = (int)Database::pdo()->lastInsertId();
                    $payment = Database::run('SELECT * FROM payments WHERE id = ?', [$pid])->fetch();
                    // provider_event_id (se coluna existir)
                    try { Database::run('UPDATE payments SET provider_event_id = ? WHERE id = ?', [$webhook['provider_event_id'] ?? null, $pid]); } catch (\Throwable $e) {}
                    // Link inbound to payment for easier correlation
                    if ($payment) {
                        Database::run('UPDATE inbound_webhooks SET payment_id = ?, site_id = COALESCE(site_id, ?) WHERE id = ?', [$pid, $siteId, $inboundId]);
                    }
                } catch (\Throwable $e) {
                    // If duplicate by stone_payment_id, fetch existing
                    $payment = Database::run('SELECT * FROM payments WHERE site_id = ? AND stone_payment_id = ? LIMIT 1', [$siteId, $stonePaymentId])->fetch();
                    if ($payment) {
                        Database::run('UPDATE inbound_webhooks SET payment_id = ?, site_id = COALESCE(site_id, ?) WHERE id = ?', [(int)$payment['id'], $siteId, $inboundId]);
                    }
                }
            }
        }

        // Fallback heuristics to identify site when payment/subscription are absent
        if (!$siteId) {
            $siteId = $this->resolveSiteIdByPayload($payload, $payment, $subscription);
            if ($siteId) {
                // persist correlation for quicker future queries
                Database::run('UPDATE inbound_webhooks SET site_id = ? WHERE id = ?', [$siteId, $inboundId]);
            }
        }

        // Update payment status based on event type
        if ($payment && $eventType) {
            $eventStatus = self::mapEventToStatus($eventType, $payload);
            $payloadStatus = self::extractPayloadStatus($payload);
            $newStatus = $eventStatus ?? $payloadStatus ?? 'processing';
            if ($payment['status'] === 'paid' && in_array($newStatus, ['processing','authorized','queued','created','pending'], true)) {
                $newStatus = 'paid';
            }
            if ($newStatus !== $payment['status']) {
                $ctx = [
                    'event' => $eventType,
                    'provider_event_id' => $webhook['provider_event_id'] ?? null,
                    'stone_payment_id' => $webhook['stone_payment_id'] ?? null,
                ];
                Database::run('INSERT INTO payment_status_history (payment_id, old_status, new_status, reason, context) VALUES (?, ?, ?, ?, ?)', [
                    $payment['id'], $payment['status'], $newStatus, 'webhook', json_encode($ctx, JSON_UNESCAPED_UNICODE)
                ]);
                // Atualiza status e registra o id do evento do provedor
                try { Database::run('UPDATE payments SET status = ?, provider_event_id = COALESCE(provider_event_id, ?), updated_at = NOW() WHERE id = ?', [$newStatus, ($webhook['provider_event_id'] ?? null), $payment['id']]); }
                catch (\Throwable $e) { Database::run('UPDATE payments SET status = ?, updated_at = NOW() WHERE id = ?', [$newStatus, $payment['id']]); }
            }
            // Mesmo sem mudança de status, preenche provider_event_id se ainda não houver
            elseif (($webhook['provider_event_id'] ?? null) && empty($payment['provider_event_id'] ?? null)) {
                try { Database::run('UPDATE payments SET provider_event_id = ? WHERE id = ?', [$webhook['provider_event_id'], $payment['id']]); } catch (\Throwable $e) {}
            }
        }

        // Preencher nome do cliente e flag de fatura se necessário
        if ($payment) {
            if ($customerName && empty($payment['customer_name'] ?? null)) {
                try { Database::run('UPDATE payments SET customer_name = ? WHERE id = ? AND (customer_name IS NULL OR customer_name = "")', [$customerName, $payment['id']]); } catch (\Throwable $e) {}
            }
            if ($isInvoice && empty($payment['is_invoice'] ?? null)) {
                try { Database::run('UPDATE payments SET is_invoice = 1 WHERE id = ? AND (is_invoice IS NULL OR is_invoice = 0)', [$payment['id']]); } catch (\Throwable $e) {}
            }
        }

        

        // Update subscription status and log event
        if ($subscription && $eventType) {
            $subNew = self::mapSubscriptionEventToStatus($eventType, $payload) ?? null;
            if ($subNew && $subNew !== $subscription['status']) {
                Database::run('UPDATE subscriptions SET status = ?, updated_at = NOW() WHERE id = ?', [$subNew, $subscription['id']]);
            }
            $occ = self::extractSubscriptionOccurrence($payload);
            $inst = self::extractCardInstallments($payload);
            // ensure extra columns exist (best-effort)
            $this->ensureSubscriptionEventsExtraColumns();
            try {
                Database::run('INSERT IGNORE INTO subscription_events (subscription_id, provider_event_id, event_type, occurrence, card_installments, raw_payload, occurred_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [
                    $subscription['id'],
                    $webhook['provider_event_id'],
                    $eventType,
                    $occ,
                    $inst,
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                    $payload['occurred_at'] ?? null,
                ]);
            } catch (\Throwable $e) {
                // fallback without occurrence column
                Database::run('INSERT IGNORE INTO subscription_events (subscription_id, provider_event_id, event_type, raw_payload, occurred_at) VALUES (?, ?, ?, ?, ?)', [
                    $subscription['id'],
                    $webhook['provider_event_id'],
                    $eventType,
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                    $payload['occurred_at'] ?? null,
                ]);
            }
        }

        // Forward to site
        if ($siteId) {
            $this->forwardToSite((int)$siteId, $inboundId, $payment ? (int)$payment['id'] : null, $payload, 1);
        }

        Database::run('UPDATE inbound_webhooks SET status = "processed", processed_at = NOW(), payment_id = ?, site_id = ? WHERE id = ?', [
            $payment['id'] ?? null,
            $siteId,
            $inboundId,
        ]);
        try { \App\Support\Metrics::inc('webhooks_processed_total'); } catch (\Throwable $e) {}
    }

    public function forwardToSite(int $siteId, int $notificationId, ?int $paymentId, array $payload, int $attempt = 1): void
    {
        $site = Database::run('SELECT * FROM sites WHERE id = ? LIMIT 1', [$siteId])->fetch();
        if (!$site) return;
        $url = $site['callback_url'];
        if (!$url) return;

        // Tolerar bancos antigos sem a coluna request_id (ou outras colunas recém adicionadas)
        try {
            $inb = Database::run('SELECT provider_event_id, stone_payment_id, stone_subscription_id, request_id FROM inbound_webhooks WHERE id = ? LIMIT 1', [$notificationId])->fetch() ?: [];
        } catch (\Throwable $e) {
            try {
                $inb = Database::run('SELECT provider_event_id, stone_payment_id, stone_subscription_id FROM inbound_webhooks WHERE id = ? LIMIT 1', [$notificationId])->fetch() ?: [];
            } catch (\Throwable $e2) {
                try {
                    $inb = Database::run('SELECT provider_event_id, stone_payment_id FROM inbound_webhooks WHERE id = ? LIMIT 1', [$notificationId])->fetch() ?: [];
                } catch (\Throwable $e3) {
                    $inb = [];
                }
            }
        }
        $payment = null;
        if ($paymentId) {
            $payment = Database::run('SELECT client_reference_id, amount_cents, currency, status FROM payments WHERE id = ? LIMIT 1', [$paymentId])->fetch() ?: [];
        }
        // Fallbacks when payment row is not available (ex.: reenvio manual)
        $read = function(array $a, array $path){
            $cur = $a;
            foreach ($path as $k) {
                if (is_array($cur) && array_key_exists($k, $cur)) { $cur = $cur[$k]; }
                else { return null; }
            }
            return $cur;
        };
        $reference = $payment['client_reference_id'] ?? null;
        if (!$reference) {
            $reference = $read($payload, ['data','code']) ?? $read($payload, ['data','order','code']) ?? ($payload['code'] ?? null);
        }
        $amountCents = isset($payment['amount_cents']) ? (int)$payment['amount_cents'] : null;
        if ($amountCents === null) {
            $amountCents = $read($payload, ['amount']) ?? $read($payload, ['amount_cents']) ?? $read($payload, ['data','amount']);
            if (is_numeric($amountCents)) $amountCents = (int)$amountCents; else $amountCents = null;
        }
        $currency = $payment['currency'] ?? ($read($payload, ['currency']) ?? $read($payload, ['data','currency']) ?? null);

        $event = $payload['type'] ?? $payload['event'] ?? null;
        $normalizedPaymentStatus = self::mapEventToStatus((string)$event, $payload);
        $normalizedSubStatus = self::mapSubscriptionEventToStatus((string)$event, $payload);
        if (!$normalizedPaymentStatus) {
            $st = strtolower((string)($payload['status'] ?? $payload['data']['status'] ?? ''));
            if (in_array($st, ['paid','authorized','processing','pending','canceled','refunded','failed','chargeback'], true)) {
                $normalizedPaymentStatus = $st;
            }
        }

        $stoneSubscriptionId = $inb['stone_subscription_id'] ?? ($payload['data']['subscription']['id'] ?? ($payload['subscription_id'] ?? null));
        $subscription = null;
        if ($stoneSubscriptionId) {
            $subscription = Database::run('SELECT id, stone_subscription_id, status FROM subscriptions WHERE stone_subscription_id = ? LIMIT 1', [$stoneSubscriptionId])->fetch() ?: [];
        }

        $statusPt = $normalizedPaymentStatus ? self::statusPtLabel($normalizedPaymentStatus) : null;
        $subStatusPt = $normalizedSubStatus ? self::subscriptionStatusPtLabel($normalizedSubStatus) : null;
        $descricao = self::describeEventPt((string)$event, (string)($statusPt ?? $subStatusPt ?? ''), (bool)$subscription);
        // Ação (em caixa alta) para o consumidor final
        $acaoBase = $statusPt ?? $subStatusPt ?? ($descricao ?: 'Atualização de status');
        try { $acao = mb_strtoupper((string)$acaoBase, 'UTF-8'); } catch (\Throwable $e) { $acao = strtoupper((string)$acaoBase); }

        // Extrair método de pagamento, se possível
        $method = self::extractPaymentMethod($payload);
        $occurrence = self::extractSubscriptionOccurrence($payload);
        $cardInstallments = self::extractCardInstallments($payload);

        // Corpo do forward 100% em PT-BR
        $forwardBody = [
            'provedor' => 'stone',
            'request_id' => $inb['request_id'] ?? null,
            'id_site' => (int)$siteId,
            'id_notificacao' => $notificationId,
            'evento' => $event,
            'id_evento_provedor' => $inb['provider_event_id'] ?? null,
            'id_pagamento_stone' => $inb['stone_payment_id'] ?? null,
            'id_assinatura_stone' => $stoneSubscriptionId ?? null,
            'id_conta_stone' => $payload['account']['id'] ?? null,
            'referencia_cliente' => $reference,
            'metodo_pagamento' => $method,
            // Alias explícito para clientes que esperam "tipo_metodo_pagamento"
            'tipo_metodo_pagamento' => $method,
            'ocorrencia_assinatura' => $occurrence,
            'parcelas_cartao' => $cardInstallments,
            'acao' => $acao,
            'pagamento' => $paymentId ? [
                'id' => (int)$paymentId,
                'referencia_cliente' => $reference,
                'valor_centavos' => $amountCents,
                'moeda' => $currency ?? 'BRL',
                'status' => $statusPt ?? null,
                'status_codigo' => $normalizedPaymentStatus,
            ] : null,
            'assinatura' => $stoneSubscriptionId ? [
                'id_assinatura_stone' => $stoneSubscriptionId,
                'status' => $subStatusPt ?? null,
                'status_codigo' => $normalizedSubStatus,
            ] : null,
            'descricao' => $descricao,
            'dados' => $payload,
        ];

        // Padrão amigável para o site: status ('success'|'error'), message e status_code
        $acqMsg = self::extractAcquirerMessage($payload);
        $acqCode = self::extractAcquirerReturnCode($payload);
        $txStatus = self::extractTransactionStatus($payload);
        $norm = $normalizedPaymentStatus ?: $normalizedSubStatus;
        $isError = in_array((string)$norm, ['failed','canceled','chargeback'], true);
        $forwardBody['status'] = $isError ? 'error' : 'success';
        // Mensagem raiz sempre em PT para consumidores do site
        $forwardBody['message'] = $descricao ?? null;
        // status_code prioriza acquirer_return_code; fallback para status da transação; por fim, status normalizado
        $forwardBody['status_code'] = $acqCode ?: ($txStatus ?: $norm);
        // Expor mensagem do adquirente separadamente para depuração (pode vir em EN)
        if (is_string($acqMsg) && $acqMsg !== '') {
            $forwardBody['acquirer_message'] = $acqMsg;
        }

        // Summary padronizado (para compatibilidade com respostas da API)
        $forwardBody['summary'] = [
            'reference' => $reference ?? ($forwardBody['referencia_cliente'] ?? null),
            'status' => $norm ?: null,
            'stone_payment_id' => $payment['stone_payment_id'] ?? ($forwardBody['id_pagamento_stone'] ?? null),
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'method' => $method,
        ];

        // Rate limit per site (per minute)
        $this->ensureRateCountersTable();
        $limitPerMin = isset($site['rate_limit_per_minute']) ? (int)$site['rate_limit_per_minute'] : 0;
        if ($limitPerMin > 0 && !$this->rateLimitAllow((int)$siteId, $limitPerMin)) {
            // Log as rate_limited (no HTTP attempt); will be retried pelo worker
            $this->insertForwardLog([
                'notification_id' => $notificationId,
                'payment_id' => $paymentId,
                'site_id' => $siteId,
                'target_url' => $url,
                'attempt' => $attempt,
                'request_body' => json_encode($forwardBody, JSON_UNESCAPED_UNICODE),
                'response_status' => 0,
                'response_body' => null,
                'success' => 0,
                'error' => 'rate_limited',
                'request_id' => $inb['request_id'] ?? null,
            ]);
            try { \App\Support\Metrics::inc('forward_failure_total'); } catch (\Throwable $e) {}
            return;
        }

        // Circuit breaker por site
        $this->ensureSiteBreakerColumns();
        $openUntil = $site['breaker_open_until'] ?? null;
        if ($openUntil && strtotime((string)$openUntil) > time()) {
            $this->insertForwardLog([
                'notification_id' => $notificationId,
                'payment_id' => $paymentId,
                'site_id' => $siteId,
                'target_url' => $url,
                'attempt' => $attempt,
                'request_body' => json_encode($forwardBody, JSON_UNESCAPED_UNICODE),
                'response_status' => 0,
                'response_body' => null,
                'success' => 0,
                'error' => 'circuit_open',
                'request_id' => $inb['request_id'] ?? null,
            ]);
            try { \App\Support\Metrics::inc('forward_failure_total'); } catch (\Throwable $e) {}
            return;
        }

        $secret = $site['webhook_forward_secret'] ?? '';
        $bodyJson = json_encode($forwardBody, JSON_UNESCAPED_UNICODE);
        $sig = hash_hmac('sha256', $bodyJson, $secret ?: '');
        $headers = [
            'Content-Type: application/json',
            'X-Hub-Signature: sha256=' . $sig,
            // Idempotent-friendly headers (não alteram o corpo PT)
            'X-Event-Id: ' . (string)($inb['provider_event_id'] ?? ''),
            'X-Notification-Id: ' . (string)$notificationId,
            'X-Event-Attempt: ' . (string)$attempt,
        ];
        $reqId = (string)($inb['request_id'] ?? '');
        if ($reqId !== '') $headers[] = 'X-Request-Id: ' . $reqId;

        $ch = curl_init($url);
        $t0 = microtime(true);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_POSTFIELDS => $bodyJson,
        ]);
        $respBody = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        curl_close($ch);
        $t1 = microtime(true);
        $latMs = (int) round(($t1 - $t0) * 1000);

        $this->insertForwardLog([
            'notification_id' => $notificationId,
            'payment_id' => $paymentId,
            'site_id' => $siteId,
            'target_url' => $url,
            'attempt' => $attempt,
            'request_body' => json_encode($forwardBody, JSON_UNESCAPED_UNICODE),
            'response_status' => $status,
            'response_body' => $respBody,
            'success' => (int)($status >= 200 && $status < 300),
            'error' => $err ?: null,
            'request_id' => $inb['request_id'] ?? null,
        ]);

        // Metrics: count success/failure
        try {
            \App\Support\Metrics::inc(($status >= 200 && $status < 300) ? 'forward_success_total' : 'forward_failure_total');
            // Latency buckets via sum/count (avg = sum/count)
            \App\Support\Metrics::inc('forward_latency_ms_sum', max(0, $latMs));
            \App\Support\Metrics::inc('forward_latency_ms_count', 1);
        } catch (\Throwable $e) {}

        // Circuit breaker accounting
        try {
            $isSuccess = ($status >= 200 && $status < 300);
            $this->updateBreakerState((int)$siteId, $isSuccess);
        } catch (\Throwable $e) { /* ignore */ }
    }

    private static function extractAcquirerMessage(array $payload): ?string
    {
        $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
        $candidates = [
            $read($payload, ['data','charge','last_transaction','acquirer_message']),
            $read($payload, ['data','charges',0,'last_transaction','acquirer_message']),
            $read($payload, ['data','last_transaction','acquirer_message']),
        ];
        foreach ($candidates as $c) {
            if (is_string($c) && $c !== '') return $c;
        }
        return null;
    }

    private static function extractAcquirerReturnCode(array $payload): ?string
    {
        $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
        $candidates = [
            $read($payload, ['data','charge','last_transaction','acquirer_return_code']),
            $read($payload, ['data','charges',0,'last_transaction','acquirer_return_code']),
            $read($payload, ['data','last_transaction','acquirer_return_code']),
        ];
        foreach ($candidates as $c) {
            if (is_string($c) && $c !== '') return $c;
            if (is_numeric($c)) return (string)$c;
        }
        return null;
    }

    private static function extractTransactionStatus(array $payload): ?string
    {
        $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
        $candidates = [
            $read($payload, ['data','charge','last_transaction','status']),
            $read($payload, ['data','charges',0,'last_transaction','status']),
            $read($payload, ['data','last_transaction','status']),
        ];
        foreach ($candidates as $c) {
            if (is_string($c) && $c !== '') return $c;
        }
        return null;
    }

    private static function extractPayloadStatus(array $payload): ?string
    {
        $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
        $candidates = [
            $payload['status'] ?? null,
            $read($payload, ['data','status']),
            $read($payload, ['data','charge','status']),
            $read($payload, ['data','charges',0,'status']),
            $read($payload, ['data','payment','status']),
            $read($payload, ['data','charge','last_transaction','status']),
            $read($payload, ['data','charges',0,'last_transaction','status']),
            $read($payload, ['data','last_transaction','status']),
        ];
        foreach ($candidates as $c) {
            if (!is_string($c)) continue;
            $norm = strtolower(trim($c));
            if ($norm === '') continue;
            if (in_array($norm, ['paid','captured'], true)) return 'paid';
            if (in_array($norm, ['authorized','authorised'], true)) return 'authorized';
            if (in_array($norm, ['processing','pending'], true)) return 'processing';
            if (in_array($norm, ['canceled','cancelled','voided'], true)) return 'canceled';
            if (in_array($norm, ['refunded','refund','reversed'], true)) return 'refunded';
            if (in_array($norm, ['failed','refused','denied','error'], true)) return 'failed';
            if (in_array($norm, ['chargeback','dispute'], true)) return 'chargeback';
        }
        return null;
    }

    private static function extractStonePaymentId(array $payload): ?string
    {
        // Preferir IDs de cobrança (charge) como "ch_..."; ignorar invoice/order ids (in_/or_)
        $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
        $candidates = [];
        // Formatos comuns
        $candidates[] = $read($payload, ['data','charge','id']);
        $candidates[] = $read($payload, ['data','charges',0,'id']);
        $candidates[] = $read($payload, ['data','payment','id']);
        $candidates[] = $read($payload, ['data','last_transaction','charge_id']); // se presente
        // Fallbacks genéricos
        $candidates[] = $read($payload, ['payment_id']);
        $candidates[] = $read($payload, ['id']);
        $candidates[] = $read($payload, ['data','id']);
        foreach ($candidates as $c) {
            if (!is_string($c) || $c === '') continue;
            // Priorizar charge id (padrão Pagar.me: ch_...)
            if (str_starts_with($c, 'ch_')) return $c;
        }
        // Se nada com ch_ encontrado, retorne um id plausível que não seja invoice/order
        foreach ($candidates as $c) {
            if (!is_string($c) || $c === '') continue;
            if (preg_match('/^(in_|or_)/', $c)) continue; // ignore invoice/order ids
            return $c;
        }
        return null;
    }

    private static function extractCustomerNameFromPayload(array $payload): ?string
    {
        $name = self::findCustomerName($payload);
        return $name !== '' ? $name : null;
    }

    private static function findCustomerName(array $data, int $depth = 0): string
    {
        if ($depth > 6) return '';
        if (isset($data['customer_name']) && is_string($data['customer_name']) && trim($data['customer_name']) !== '') {
            return trim($data['customer_name']);
        }
        foreach (['customer','cliente','buyer','payer'] as $k) {
            if (isset($data[$k]) && is_array($data[$k])) {
                $c = $data[$k];
                $n = $c['name'] ?? ($c['nome'] ?? ($c['full_name'] ?? null));
                if (is_string($n) && trim($n) !== '') return trim($n);
            }
        }
        foreach (['charges','charge','transactions','transaction','last_transaction','order','subscription','invoice','data','payment','payments'] as $k) {
            if (!isset($data[$k])) continue;
            $v = $data[$k];
            if (is_array($v)) {
                if (array_is_list($v)) {
                    foreach ($v as $item) {
                        if (is_array($item)) {
                            $n = self::findCustomerName($item, $depth + 1);
                            if ($n !== '') return $n;
                        }
                    }
                } else {
                    $n = self::findCustomerName($v, $depth + 1);
                    if ($n !== '') return $n;
                }
            }
        }
        foreach ($data as $v) {
            if (is_array($v)) {
                $n = self::findCustomerName($v, $depth + 1);
                if ($n !== '') return $n;
            }
        }
        return '';
    }

    private static function isInvoicePayload(array $payload, ?string $eventType, ?string $stoneSubscriptionId): bool
    {
        if ($stoneSubscriptionId) return true;
        $e = strtolower((string)($eventType ?? ''));
        if (str_contains($e, 'invoice')) return true;
        if (isset($payload['data']['subscription']['id']) || isset($payload['subscription_id'])) return true;
        if (isset($payload['data']['invoice']) || isset($payload['invoice'])) return true;
        if (isset($payload['metadata']['subscription_stone_id']) || isset($payload['metadata']['webhook_ledger'])) return true;
        return false;
    }

    private static function mapEventToStatus(string $event, array $payload): ?string
    {
        $e = strtolower($event);
        if (str_contains($e, 'paid')) return 'paid';
        if (str_contains($e, 'authorized') || str_contains($e, 'authoriz')) return 'authorized';
        if (str_contains($e, 'refunded') || str_contains($e, 'refund') || str_contains($e, 'reversed') || str_contains($e, 'voided')) return 'refunded';
        if (str_contains($e, 'canceled') || str_contains($e, 'cancelled') || str_contains($e, 'void')) return 'canceled';
        if (str_contains($e, 'failed') || str_contains($e, 'denied') || str_contains($e, 'refused')) return 'failed';
        if (str_contains($e, 'chargeback') || str_contains($e, 'dispute')) return 'chargeback';
        if (str_contains($e, 'protest')) return 'failed'; // protesto (boletos)
        if (str_contains($e, 'processing') || str_contains($e, 'pending')) return 'processing';
        return null;
    }

    private function resolveSiteIdByPayload(array $payload, ?array $payment, ?array $subscription): ?int
    {
        // 1) Se já tiver pagamento/assinatura, use site_id deles
        if ($payment && isset($payment['site_id'])) return (int)$payment['site_id'];
        if ($subscription && isset($subscription['site_id'])) return (int)$subscription['site_id'];

        // 1b) Por account.id (mapeamento direto para Site)
        try {
            $accountId = null;
            if (isset($payload['account']['id']) && is_string($payload['account']['id'])) {
                $accountId = $payload['account']['id'];
            }
            if ($accountId) {
                $row = Database::run('SELECT id FROM sites WHERE stone_account_id = ? LIMIT 1', [$accountId])->fetch();
                if ($row && isset($row['id'])) return (int)$row['id'];
            }
        } catch (\Throwable $e) { /* coluna pode não existir; ignorar */ }

        // 2) Tentar por código (client_reference_id) em possíveis caminhos
        $paths = [
            ['data','code'],
            ['data','charge','code'],
            ['data','order','code'],
        ];
        $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
        foreach ($paths as $p) {
            $code = $read($payload, $p);
            if (is_string($code) && $code !== '') {
                $row = Database::run('SELECT site_id FROM payments WHERE client_reference_id = ? ORDER BY id DESC LIMIT 1', [$code])->fetch();
                if ($row && isset($row['site_id'])) return (int)$row['site_id'];
            }
        }

        // 3) Tentar por charge id em charges[] (order.paid) ou data.charge
        $chargeIds = [];
        $charge = $read($payload, ['data','charge']); if (is_array($charge) && !empty($charge['id'])) $chargeIds[] = $charge['id'];
        $charges = $read($payload, ['data','charges']); if (is_array($charges)) { foreach ($charges as $c) { if (is_array($c) && !empty($c['id'])) $chargeIds[] = $c['id']; } }
        foreach ($chargeIds as $cid) {
            $row = Database::run('SELECT site_id FROM payments WHERE stone_payment_id = ? LIMIT 1', [$cid])->fetch();
            if ($row && isset($row['site_id'])) return (int)$row['site_id'];
        }

        // 4) Fallback: se apenas um site ativo existe, use-o
        try {
            $row = Database::run('SELECT id FROM sites WHERE status = "active" LIMIT 2')->fetchAll();
            if (count($row) === 1) return (int)$row[0]['id'];
        } catch (\Throwable $e) {}

        return null;
    }

    private static function statusPtLabel(string $status): string
    {
        return [
            'created' => 'Criado',
            'queued' => 'Na fila',
            'processing' => 'Processando',
            'authorized' => 'Autorizado',
            'paid' => 'Pago',
            'failed' => 'Falhou',
            'canceled' => 'Cancelado',
            'refunded' => 'Estornado',
            'chargeback' => 'Chargeback',
        ][$status] ?? $status;
    }

    private static function subscriptionStatusPtLabel(string $status): string
    {
        return [
            'active' => 'Ativa',
            'trialing' => 'Em teste',
            'past_due' => 'Em atraso',
            'paused' => 'Pausada',
            'canceled' => 'Cancelada',
            'expired' => 'Expirada',
            'deleted' => 'Deletada',
        ][$status] ?? $status;
    }

    private static function describeEventPt(string $event, string $statusLabelPt, bool $hasSubscription): string
    {
        $e = strtolower($event);
        if ($hasSubscription) {
            if (str_contains($e, 'cancel')) return 'Assinatura cancelada';
            if (str_contains($e, 'pause')) return 'Assinatura pausada';
            if (str_contains($e, 'resume') || str_contains($e, 'activate')) return 'Assinatura ativada';
            if (str_contains($e, 'expire')) return 'Assinatura expirada';
            if (str_contains($e, 'overdue') || str_contains($e, 'past_due')) return 'Assinatura em atraso';
        }
        if (str_contains($e, 'chargeback') || str_contains($e, 'dispute')) return 'Chargeback registrado';
        if (str_contains($e, 'protest')) return 'Boleto protestado';
        if (str_contains($e, 'refund')) return 'Pagamento estornado';
        if (str_contains($e, 'paid')) return 'Pagamento aprovado';
        if (str_contains($e, 'authorized')) return 'Pagamento autorizado';
        if (str_contains($e, 'fail') || str_contains($e, 'deny') || str_contains($e, 'refuse')) return 'Pagamento negado';
        if (str_contains($e, 'cancel')) return 'Pagamento cancelado';
        if ($statusLabelPt !== '') return $statusLabelPt;
        return 'Atualização de status';
    }

    private static function mapSubscriptionEventToStatus(string $event, array $payload): ?string
    {
        $e = strtolower($event);
        if (str_contains($e, 'subscription') || str_contains($e, 'plan')) {
            if (str_contains($e, 'activated') || str_contains($e, 'active')) return 'active';
            if (str_contains($e, 'canceled') || str_contains($e, 'cancelled')) return 'canceled';
            if (str_contains($e, 'paused')) return 'paused';
            if (str_contains($e, 'expired')) return 'expired';
            if (str_contains($e, 'past_due') || str_contains($e, 'overdue')) return 'past_due';
        }
        return null;
    }

    public function retryDueForwards(int $limit = 50, int $maxAttempts = 5): int
    {
        // Pick latest attempt per notification that failed
        $sql = 'SELECT f.* FROM webhook_forward_logs f
                JOIN (
                    SELECT notification_id, MAX(attempt) AS last_attempt
                    FROM webhook_forward_logs
                    GROUP BY notification_id
                ) t ON t.notification_id = f.notification_id AND t.last_attempt = f.attempt
                WHERE f.success = 0
                ORDER BY f.sent_at ASC
                LIMIT ' . (int)$limit;
        $rows = Database::run($sql)->fetchAll();
        $count = 0;
        foreach ($rows as $r) {
            $attempt = (int)$r['attempt'];
            if ($attempt >= $maxAttempts) {
                // exhausted, optionally mark a DLQ log entry once
                continue;
            }
            $delay = $this->backoffDelaySeconds($attempt + 1);
            $sentAt = strtotime((string)$r['sent_at'] ?? 'now');
            if (time() < ($sentAt + $delay)) {
                continue; // not due yet
            }
            // Load inbound payload and payment id
            $inb = Database::run('SELECT id, site_id, payment_id, raw_payload FROM inbound_webhooks WHERE id = ? LIMIT 1', [$r['notification_id']])->fetch();
            if (!$inb) continue;
            $payload = json_decode($inb['raw_payload'] ?? '[]', true) ?: [];
            $this->forwardToSite((int)$inb['site_id'], (int)$inb['id'], $inb['payment_id'] ? (int)$inb['payment_id'] : null, $payload, $attempt + 1);
            $count++;
        }
        return $count;
    }

    private function backoffDelaySeconds(int $attempt): int
    {
        // Attempt 1 already sent; compute delay for next attempt number
        return match ($attempt) {
            2 => 60,          // 1m
            3 => 5 * 60,      // 5m
            4 => 30 * 60,     // 30m
            5 => 2 * 3600,    // 2h
            default => 24 * 3600, // beyond cap
        };
    }

    private function insertForwardLog(array $data): void
    {
        try {
            Database::run('INSERT INTO webhook_forward_logs (notification_id, payment_id, site_id, target_url, attempt, request_body, response_status, response_body, success, error, request_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $data['notification_id'] ?? null,
                $data['payment_id'] ?? null,
                $data['site_id'] ?? null,
                $data['target_url'] ?? null,
                $data['attempt'] ?? 1,
                $data['request_body'] ?? null,
                $data['response_status'] ?? null,
                $data['response_body'] ?? null,
                $data['success'] ?? 0,
                $data['error'] ?? null,
                $data['request_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            try {
                Database::run('INSERT INTO webhook_forward_logs (notification_id, payment_id, site_id, target_url, attempt, request_body, response_status, response_body, success, error) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                    $data['notification_id'] ?? null,
                    $data['payment_id'] ?? null,
                    $data['site_id'] ?? null,
                    $data['target_url'] ?? null,
                    $data['attempt'] ?? 1,
                    $data['request_body'] ?? null,
                    $data['response_status'] ?? null,
                    $data['response_body'] ?? null,
                    $data['success'] ?? 0,
                    $data['error'] ?? null,
                ]);
            } catch (\Throwable $e2) {
                // ignore
            }
        }
    }

    // -------- Rate limit & Circuit breaker helpers --------
    private function ensureRateCountersTable(): void
    {
        static $done = false; if ($done) return;
        try {
            Database::run('CREATE TABLE IF NOT EXISTS site_rate_counters (
  site_id BIGINT UNSIGNED NOT NULL,
  window_start_minute DATETIME NOT NULL,
  `count` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (site_id, window_start_minute)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        } catch (\Throwable $e) { /* ignore */ }
        $done = true;
    }

    private function rateLimitAllow(int $siteId, int $limitPerMinute): bool
    {
        $minute = date('Y-m-d H:i:00');
        try {
            // Seed row if not exists
            Database::run('INSERT INTO site_rate_counters (site_id, window_start_minute, `count`) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE `count`=`count`', [$siteId, $minute]);
            // Try to increment only if below limit
            $stmt = Database::run('UPDATE site_rate_counters SET `count` = `count` + 1 WHERE site_id = ? AND window_start_minute = ? AND `count` < ?', [$siteId, $minute, $limitPerMinute]);
            if (method_exists($stmt, 'rowCount') && (int)$stmt->rowCount() === 1) return true;
        } catch (\Throwable $e) { /* on error allow to avoid blocking */ return true; }
        return false;
    }

    private function ensureSiteBreakerColumns(): void
    {
        static $done = false; if ($done) return;
        try {
            $sql = 'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "sites" AND COLUMN_NAME = "breaker_open_until"';
            $row = Database::run($sql)->fetch();
            if (!$row) {
                Database::run('ALTER TABLE sites ADD COLUMN breaker_open_until TIMESTAMP NULL DEFAULT NULL AFTER rate_limit_per_minute');
            }
        } catch (\Throwable $e) { /* ignore */ }
        try {
            $sql = 'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "sites" AND COLUMN_NAME = "breaker_failure_count"';
            $row = Database::run($sql)->fetch();
            if (!$row) {
                Database::run('ALTER TABLE sites ADD COLUMN breaker_failure_count INT NOT NULL DEFAULT 0 AFTER breaker_open_until');
            }
        } catch (\Throwable $e) { /* ignore */ }
        try {
            $sql = 'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "sites" AND COLUMN_NAME = "breaker_window_start"';
            $row = Database::run($sql)->fetch();
            if (!$row) {
                Database::run('ALTER TABLE sites ADD COLUMN breaker_window_start TIMESTAMP NULL DEFAULT NULL AFTER breaker_failure_count');
            }
        } catch (\Throwable $e) { /* ignore */ }
        $done = true;
    }

    private function updateBreakerState(int $siteId, bool $success): void
    {
        // Parameters: window 60s, threshold 10 failures; open for 60s
        $windowSec = 60; $threshold = 10; $openSec = 60;
        $row = Database::run('SELECT breaker_open_until, breaker_failure_count, breaker_window_start FROM sites WHERE id = ? LIMIT 1', [$siteId])->fetch() ?: [];
        $openUntil = $row['breaker_open_until'] ?? null;
        $now = time();
        // If success, reset counters
        if ($success) {
            Database::run('UPDATE sites SET breaker_failure_count = 0, breaker_window_start = NULL WHERE id = ?', [$siteId]);
            return;
        }
        // Failure path
        $winStart = isset($row['breaker_window_start']) ? (int)strtotime((string)$row['breaker_window_start']) : 0;
        $cnt = (int)($row['breaker_failure_count'] ?? 0);
        if ($winStart > 0 && ($now - $winStart) <= $windowSec) {
            $cnt++;
        } else {
            $cnt = 1;
            $winStart = $now;
        }
        if ($cnt >= $threshold) {
            $open = date('Y-m-d H:i:s', $now + $openSec);
            Database::run('UPDATE sites SET breaker_open_until = ?, breaker_failure_count = 0, breaker_window_start = NULL WHERE id = ?', [$open, $siteId]);
        } else {
            Database::run('UPDATE sites SET breaker_failure_count = ?, breaker_window_start = FROM_UNIXTIME(?) WHERE id = ?', [$cnt, $winStart, $siteId]);
        }
    }

    private static function extractPaymentMethod(array $payload): ?string
    {
        $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
        $candidates = [
            $read($payload, ['data','payment_method']),
            $read($payload, ['data','charge','payment_method']),
            $read($payload, ['data','charges',0,'payment_method']),
            $read($payload, ['data','last_transaction','transaction_type']),
        ];
        foreach ($candidates as $c) {
            if (is_string($c) && $c !== '') return strtolower($c);
        }
        return null;
    }

    private static function extractSubscriptionOccurrence(array $payload): ?int
    {
        // Prefer explicit cycle number when present
        $cycle = null;
        if (isset($payload['data']['cycle']['cycle']) && is_numeric($payload['data']['cycle']['cycle'])) {
            $cycle = (int)$payload['data']['cycle']['cycle'];
            if ($cycle > 0) return $cycle;
        }
        // Try invoice/charge/order codes that end with -NN
        $codes = [];
        $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
        foreach ([['data','invoice','code'], ['data','charge','code'], ['data','order','code'], ['data','code']] as $p) {
            $v = $read($payload, $p);
            if (is_string($v) && $v !== '') $codes[] = $v;
        }
        foreach ($codes as $c) {
            if (preg_match('/-(\d{1,3})$/', $c, $m)) {
                $n = (int)$m[1];
                if ($n > 0) return $n;
            }
        }
        // Map textual recurrence_cycle to 1st occurrence if available
        $rec = $read($payload, ['data','recurrence_cycle']);
        if (is_string($rec) && strtolower($rec) === 'first') return 1;
        return null;
    }

    private static function extractCardInstallments(array $payload): ?int
    {
        $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
        $candidates = [
            $read($payload, ['data','last_transaction','installments']),
            $read($payload, ['data','charge','last_transaction','installments']),
            $read($payload, ['data','charges',0,'last_transaction','installments']),
        ];
        foreach ($candidates as $c) {
            if (is_numeric($c)) return (int)$c;
        }
        return null;
    }

    private static function resolveEnvFromSite(?array $site): string
    {
        if (!$site) return 'sandbox';
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

    private function ensureSubscriptionEventsExtraColumns(): void
    {
        static $done = false;
        if ($done) return;
        try {
            $rowOcc = Database::run('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "subscription_events" AND COLUMN_NAME = "occurrence"')->fetch();
            if (!$rowOcc) { Database::run('ALTER TABLE subscription_events ADD COLUMN occurrence INT UNSIGNED NULL AFTER event_type'); }
            $rowInst = Database::run('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "subscription_events" AND COLUMN_NAME = "card_installments"')->fetch();
            if (!$rowInst) { Database::run('ALTER TABLE subscription_events ADD COLUMN card_installments INT UNSIGNED NULL AFTER occurrence'); }
        } catch (\Throwable $e) {
            // ignore
        }
        $done = true;
    }
}
