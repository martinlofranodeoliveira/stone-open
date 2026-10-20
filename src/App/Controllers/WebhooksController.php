<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Infrastructure\Http\{Request, Response};
use App\Support\Env;
use App\Support\Security\WebhookSignature;
use App\Infrastructure\DB\Database;
use App\Application\WebhookService;

final class WebhooksController
{
    public function handle(Request $req): Response
    {
        // IP whitelist (optional)
        $whitelist = trim((string)Env::get('STONE_IP_WHITELIST', ''));
        if ($whitelist !== '') {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if (!$this->ipAllowed($ip, $whitelist)) {
                return Response::json(['error' => 'Forbidden'], 403);
            }
        }
        // Configured Basic authentication must succeed; null means not configured.
        $basicAuthenticated = $this->checkOptionalBasicAuth($req);
        if ($basicAuthenticated === false) {
            return new Response(401, [
                'Content-Type' => 'application/json; charset=utf-8',
                'WWW-Authenticate' => 'Basic realm="Stone Webhooks"'
            ], json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE));
        }

        // Signature check (opcional por conta)
        $secret = '';
        $headerNames = [
            'x-hub-signature', 'x-hub-signature-256', 'x-signature', 'pagarme-signature'
        ];
        $provided = null;
        foreach ($headerNames as $h) {
            $val = $req->header($h);
            if ($val) { $provided = $val; break; }
        }

        $payload = json_decode($req->rawBody, true);
        if (!is_array($payload)) {
            return Response::json(['error' => 'Invalid JSON payload'], 400);
        }

        // Descobrir conta pelo payload e obter segredo do webhook (por conta)
        try {
            $accId = null;
            if (isset($payload['account']) && is_array($payload['account']) && !empty($payload['account']['id'])) {
                $accId = (string)$payload['account']['id'];
            } elseif (!empty($payload['account_id'])) {
                $accId = (string)$payload['account_id'];
            }
            if ($accId) {
                $acc = \App\Infrastructure\DB\Database::run('SELECT * FROM pagarme_accounts WHERE stone_account_uid = ? LIMIT 1', [$accId])->fetch();
                if ($acc) {
                    $secret = \App\Infrastructure\Stone\StoneAccount::webhookSecretForAccount($acc) ?? '';
                }
            }
        } catch (\Throwable $e) { /* ignore */ }

        // Never persist or process an unauthenticated webhook when settings are absent.
        if ($secret === '' && $basicAuthenticated !== true) {
            return Response::json(['error' => 'Webhook authentication is not configured'], 503);
        }
        if ($secret !== '' && !WebhookSignature::verify($req->rawBody, $secret, $provided)) {
            return Response::json(['error' => 'Invalid signature'], 400);
        }

        $eventId = (string)($payload['id'] ?? ($payload['event_id'] ?? ''));
        if ($eventId === '') {
            // fallback to hash of body
            $eventId = hash('sha256', $req->rawBody);
        }
        $eventType = (string)($payload['type'] ?? $payload['event'] ?? '');

        // Optional payment/subscription id extraction for quick correlation
        $stonePaymentId = $payload['data']['id'] ?? ($payload['data']['payment']['id'] ?? ($payload['id'] ?? null));
        $stoneSubscriptionId = $payload['data']['subscription']['id'] ?? ($payload['subscription_id'] ?? null);

        // Persist inbound (idempotent unique)
        $requestId = (string)($req->attributes['request_id'] ?? '');
        try {
            Database::run('INSERT INTO inbound_webhooks (provider, provider_event_id, event_type, stone_payment_id, stone_subscription_id, raw_payload, headers, signature, request_id, status) VALUES ("stone", ?, ?, ?, ?, ?, ?, ?, ?, "pending")', [
                $eventId,
                $eventType,
                is_string($stonePaymentId) ? $stonePaymentId : null,
                is_string($stoneSubscriptionId) ? $stoneSubscriptionId : null,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                json_encode($req->headers, JSON_UNESCAPED_UNICODE),
                $provided,
                $requestId,
            ]);
        } catch (\Throwable $e) {
            // duplicate event: ok (or missing column)
            try {
                Database::run('INSERT INTO inbound_webhooks (provider, provider_event_id, event_type, stone_payment_id, stone_subscription_id, raw_payload, headers, signature, status) VALUES ("stone", ?, ?, ?, ?, ?, ?, ?, "pending")', [
                    $eventId,
                    $eventType,
                    is_string($stonePaymentId) ? $stonePaymentId : null,
                    is_string($stoneSubscriptionId) ? $stoneSubscriptionId : null,
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                    json_encode($req->headers, JSON_UNESCAPED_UNICODE),
                    $provided,
                ]);
            } catch (\Throwable $e2) {
                // ignore
            }
        }

        // Load record id
        $row = Database::run('SELECT id FROM inbound_webhooks WHERE provider = "stone" AND provider_event_id = ? LIMIT 1', [$eventId])->fetch();
        $inboundId = (int)($row['id'] ?? 0);
        if ($inboundId > 0) { try { \App\Support\Metrics::inc('webhooks_received_total'); } catch (\Throwable $e) {} }

        // Defer processing to worker/cron for robustness and latency control
        // Além disso, tentamos processar imediatamente (best-effort) para baixa rápida em homolog/ambientes sem worker.
        if ($inboundId > 0) {
            try {
                (new WebhookService())->process($inboundId);
            } catch (\Throwable $e) {
                // Ignorar erros aqui; o worker poderá reprocessar
            }
        }
        return Response::json(['ok' => true, 'queued' => $inboundId > 0]);
    }

    private function checkOptionalBasicAuth(Request $req): ?bool
    {
        // Null distinguishes unconfigured credentials from successfully authenticated requests.
        try {
            $row = \App\Infrastructure\DB\Database::run('SELECT stone_webhook_basic_user, stone_webhook_basic_pass_hash FROM account_settings WHERE id=1 LIMIT 1')->fetch();
        } catch (\Throwable $e) {
            $row = null;
        }
        $user = (string)($row['stone_webhook_basic_user'] ?? '');
        $hash = (string)($row['stone_webhook_basic_pass_hash'] ?? '');
        if ($user === '' || $hash === '') return null;

        $auth = $req->header('authorization');
        if (!$auth || stripos($auth, 'basic ') !== 0) return false;
        $b64 = trim(substr($auth, 6));
        $decoded = base64_decode($b64, true) ?: '';
        if ($decoded === '' || !str_contains($decoded, ':')) return false;
        [$u, $p] = explode(':', $decoded, 2);
        if ($u !== $user) return false;
        return password_verify($p, $hash);
    }

    private function ipAllowed(string $ip, string $list): bool
    {
        $parts = array_filter(array_map('trim', explode(',', $list)));
        foreach ($parts as $item) {
            if ($item === $ip) return true;
            // CIDR IPv4
            if (preg_match('#^\d+\.\d+\.\d+\.\d+/\d+$#', $item)) {
                if ($this->ipv4InCidr($ip, $item)) return true;
            }
        }
        return false;
    }

    private function ipv4InCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr, 2);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) return false;
        $mask = (int)$mask;
        $ipDec = ip2long($ip);
        $subnetDec = ip2long($subnet);
        $maskDec = -1 << (32 - $mask);
        $subnetDec &= $maskDec;
        return ($ipDec & $maskDec) === $subnetDec;
    }
}
