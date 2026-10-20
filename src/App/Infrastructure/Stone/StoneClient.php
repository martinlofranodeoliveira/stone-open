<?php
declare(strict_types=1);

namespace App\Infrastructure\Stone;

use App\Support\Env;

final class StoneClient
{
    private array $account;
    private string $env; // sandbox|production

    public static function forSite(array $site): self
    {
        $acc = StoneAccount::resolveForSite($site);
        return new self($acc);
    }

    public function __construct(array $account)
    {
        $this->account = $account;
        $env = strtolower((string)($account['active_env'] ?? 'sandbox'));
        $this->env = in_array($env, ['sandbox','production'], true) ? $env : 'sandbox';
    }

    private function authHeader(): string
    {
        $token = base64_encode($this->apiKey() . ':');
        return 'Authorization: Basic ' . $token;
    }

    private function baseCoreUrl(): string
    {
        // Default Pagar.me v5 core; allow optional override via env for debugging
        $def = 'https://api.pagar.me/core/v5';
        $envKey = $this->env === 'production' ? 'STONE_BASE_URL_PRODUCTION' : 'STONE_BASE_URL_SANDBOX';
        return rtrim(Env::get($envKey, $def), '/');
    }

    private function apiKey(): string
    {
        $k = $this->env === 'production'
            ? (string)($this->account['api_key_production'] ?? '')
            : (string)($this->account['api_key_sandbox'] ?? '');
        // sanitize: strip spaces and newlines
        $k = preg_replace('/\s+/', '', trim($k)) ?? trim($k);
        return $k;
    }

    private function publicKey(): string
    {
        $k = $this->env === 'production'
            ? (string)($this->account['public_key_production'] ?? '')
            : (string)($this->account['public_key_sandbox'] ?? '');
        $k = preg_replace('/\s+/', '', trim($k)) ?? trim($k);
        return $k;
    }

    private function baseBinUrl(): string
    {
        $def = 'https://api.pagar.me/core/v5';
        $envKey = $this->env === 'production' ? 'STONE_BIN_BASE_URL_PRODUCTION' : 'STONE_BIN_BASE_URL_SANDBOX';
        $fallback = $this->env === 'production' ? Env::get('STONE_BASE_URL_PRODUCTION', $def) : Env::get('STONE_BASE_URL_SANDBOX', $def);
        return rtrim(Env::get($envKey, $fallback), '/');
    }

    public function createOrder(array $payload, ?string $idempotencyKey = null): array
    {
        $url = $this->baseCoreUrl() . '/orders';
        $headers = [ 'Content-Type: application/json', 'Accept: application/json', $this->authHeader() ];
        if ($idempotencyKey) {
            $headers[] = (Env::get('STONE_IDEMPOTENCY_HEADER', 'Idempotency-Key') . ': ' . $idempotencyKey);
        }

        $resp = $this->request('POST', $url, $headers, json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $resp;
    }

    // Customers
    public function createCustomer(array $payload): array
    {
        $url = $this->baseCoreUrl() . '/customers';
        $headers = [ 'Content-Type: application/json', 'Accept: application/json', $this->authHeader() ];
        return $this->request('POST', $url, $headers, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    public function updateCustomer(string $customerId, array $payload): array
    {
        $url = $this->baseCoreUrl() . '/customers/' . rawurlencode($customerId);
        $headers = [ 'Content-Type: application/json', 'Accept: application/json', $this->authHeader() ];
        return $this->request('PUT', $url, $headers, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Find a customer on Stone by id, email or document.
     * Returns the customer array or null if not found.
     */
    public function findCustomer(?string $id = null, ?string $email = null, ?string $document = null): ?array
    {
        $headers = [ 'Accept: application/json', $this->authHeader() ];
        if ($id) {
            try {
                $url = $this->baseCoreUrl() . '/customers/' . rawurlencode($id);
                $res = $this->request('GET', $url, $headers);
                if (is_array($res) && !isset($res['errors'])) return $res;
            } catch (\Throwable $e) { /* ignore */ }
        }
        $q = [];
        if ($email) $q['email'] = $email;
        if ($document) $q['document'] = preg_replace('/\D+/', '', (string)$document);
        if (!$q) return null;
        try {
            $url = $this->baseCoreUrl() . '/customers?' . http_build_query($q);
            $res = $this->request('GET', $url, $headers);
            // Some APIs return { data: [...] }, others a list directly
            if (isset($res['data']) && is_array($res['data'])) $list = $res['data'];
            elseif (is_array($res)) $list = $res; else $list = [];
            $first = null;
            if (isset($list[0]) && is_array($list[0])) $first = $list[0];
            return $first ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // Cards (store card for a customer). Accepts ['customer_id'=>..., 'token'=>..., ...]
    public function createCard(array $payload): array
    {
        $customerId = (string)($payload['customer_id'] ?? '');
        if ($customerId === '') {
            throw new \InvalidArgumentException('customer_id is required to create a card');
        }
        unset($payload['customer_id']);
        $url = $this->baseCoreUrl() . '/customers/' . rawurlencode($customerId) . '/cards';
        $headers = [ 'Content-Type: application/json', 'Accept: application/json', $this->authHeader() ];
        return $this->request('POST', $url, $headers, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    // Optional: unified endpoint if provider supports returning brand + installments by BIN + amount
    public function cardInfoAndInstallments(?string $bin, ?int $amountCents): ?array
    {
        $path = Env::get('STONE_CARD_INFO_PATH', '');
        if ($path === '') return null;
        $url = $this->baseCoreUrl() . '/' . ltrim($path, '/');
        $query = [];
        if ($bin) $query['bin'] = $bin;
        if ($amountCents) $query['amount'] = $amountCents;
        if ($query) $url .= '?' . http_build_query($query);
        $headers = [ $this->authHeader(), 'Accept: application/json' ];
        return $this->request('GET', $url, $headers);
    }

    public function lookupCardBin(string $bin): array
    {
        $template = Env::get('STONE_CARD_BIN_PATH', 'bin/v1/%s');
        $path = sprintf($template, urlencode($bin));
        $base = $this->baseBinUrl();
        // If base ends with /core/v5, strip to domain root for bin service
        $base = preg_replace('#/core/[^/]+$#','',$base);
        $url = rtrim($base, '/') . '/' . ltrim($path, '/');
        $headers = [ $this->authHeader(), 'Accept: application/json' ];
        return $this->request('GET', $url, $headers);
    }

    public function getInstallments(int $amountCents, ?int $maxInstallments, ?int $freeInstallments, ?float $interestRate, ?int $minInstallmentCents, ?string $brand = null, ?string $bin = null): ?array
    {
        $query = [ 'amount' => $amountCents ];
        if ($maxInstallments) $query['max_installments'] = $maxInstallments;
        if ($freeInstallments !== null) $query['free_installments'] = $freeInstallments;
        if ($interestRate !== null) $query['interest_rate'] = $interestRate; // % per month
        if ($minInstallmentCents !== null) $query['min_installment_cents'] = $minInstallmentCents;
        if ($brand) $query['brand'] = $brand;
        if ($bin) $query['bin'] = $bin;

        $headers = [ $this->authHeader(), 'Accept: application/json' ];

        // If a path is configured, try it first
        $configured = trim((string)Env::get('STONE_INSTALLMENTS_PATH', ''));
        $candidates = [];
        if ($configured !== '') $candidates[] = ltrim($configured, '/');
        // Try common Pagar.me v5 patterns so .env is optional
        $candidates = array_merge($candidates, [
            'installments',
            'payments/credit-card/installments',
            'credit-cards/installments',
        ]);

        foreach ($candidates as $path) {
            try {
                $url = $this->baseCoreUrl() . '/' . $path . '?' . http_build_query($query);
                $resp = $this->request('GET', $url, $headers);
                if (isset($resp['installments']) && is_array($resp['installments'])) return $resp['installments'];
                if (is_array($resp) && isset($resp[0])) return $resp; // already list
            } catch (\Throwable $e) {
                // try next candidate
            }
        }
        return null; // caller will fallback to local computation
    }

    public function tokenizeCard(array $cardPayload): array
    {
        $url = $this->baseCoreUrl() . '/tokens?appId=' . urlencode($this->publicKey());
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        return $this->request('POST', $url, $headers, json_encode(['type'=>'card','card'=>$cardPayload], JSON_UNESCAPED_UNICODE));
    }

    // Charges
    public function getCharge(string $chargeId): array
    {
        $url = $this->baseCoreUrl() . '/charges/' . rawurlencode($chargeId);
        $headers = [ 'Accept: application/json', $this->authHeader() ];
        return $this->request('GET', $url, $headers);
    }

    public function listCharges(array $query = []): array
    {
        $url = $this->baseCoreUrl() . '/charges';
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        $headers = [ 'Accept: application/json', $this->authHeader() ];
        return $this->request('GET', $url, $headers);
    }

    public function updateChargeDueDate(string $chargeId, string $dueAt): array
    {
        $url = $this->baseCoreUrl() . '/charges/' . rawurlencode($chargeId) . '/due-date';
        $headers = [ 'Content-Type: application/json', 'Accept: application/json', $this->authHeader() ];
        $payload = [ 'due_at' => $dueAt ];
        return $this->request('PATCH', $url, $headers, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    public function updateChargePaymentMethod(string $chargeId, array $payload): array
    {
        $url = $this->baseCoreUrl() . '/charges/' . rawurlencode($chargeId) . '/payment-method';
        $headers = [ 'Content-Type: application/json', 'Accept: application/json', $this->authHeader() ];
        return $this->request('PATCH', $url, $headers, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    private function request(string $method, string $url, array $headers, ?string $body = null): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => (int)Env::get('HTTP_TIMEOUT', '10'),
            CURLOPT_CONNECTTIMEOUT => (int)Env::get('HTTP_CONNECT_TIMEOUT', '5'),
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $opts);
        $respBody = curl_exec($ch);
        $err = curl_error($ch);
        $errno = curl_errno($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        curl_close($ch);

        // Log HTTP exchange (sem vazar Authorization)
        $this->logHttp($method, $url, $status, $body, $respBody, $err);

        if ($respBody === false || $err) {
            $errMsg = $err ?: 'Erro de conexão com a Stone';
            // curl timeout: treat as gateway timeout (504)
            if ($errno === CURLE_OPERATION_TIMEDOUT || str_contains(strtolower($errMsg), 'timed out')) {
                throw new StoneApiException(504, 'timeout', 'Stone API timeout: ' . $errMsg, null, [
                    'curl_errno' => $errno,
                    'curl_error' => $errMsg,
                ]);
            }
            // other transport errors: treat as bad gateway (502)
            throw new StoneApiException(502, 'gateway_error', 'Stone API error: ' . $errMsg, null, [
                'curl_errno' => $errno,
                'curl_error' => $errMsg,
            ]);
        }
        if ($status >= 400) {
            // Parse error payload if possible; tolerate non-JSON bodies
            $json = json_decode($respBody, true);
            $msg = '';
            if (is_array($json) && isset($json['message'])) {
                $msg = is_string($json['message']) ? $json['message'] : json_encode($json['message']);
            }
            $code = null;
            if (is_array($json) && isset($json['errors']) && is_array($json['errors']) && isset($json['errors'][0])) {
                $e0 = $json['errors'][0];
                if (is_array($e0) && isset($e0['code'])) $code = (string)$e0['code'];
                elseif (is_string($e0)) $code = $e0;
                if ($msg === '' && is_array($e0) && isset($e0['message'])) $msg = (string)$e0['message'];
            }
            if (!$code && is_array($json) && isset($json['error'])) {
                $er = $json['error'];
                if (is_string($er)) $code = $er;
                elseif (is_array($er) && isset($er['code'])) $code = (string)$er['code'];
            }
            if (!$code && is_array($json) && isset($json['code'])) $code = is_scalar($json['code']) ? (string)$json['code'] : null;
            if ($msg === '' && is_string($respBody) && trim($respBody) !== '') $msg = trim($respBody);
            if ($msg === '') $msg = 'Stone API error';
            // Ajuste de códigos para erros comuns de autorização
            if (in_array((int)$status, [401,403], true)) {
                $code = $code ?: 'unauthorized';
                // Normalizar mensagem em casos comuns
                if (stripos($msg, 'Authorization has been denied') !== false) {
                    $msg = 'Authorization has been denied for this request.';
                }
            }
            $code = $code ?: 'unknown_error';
            $raw = is_array($json) ? $json : ['body' => (string)$respBody];
            throw new \App\Infrastructure\Stone\StoneApiException($status, $code, $msg, (is_array($json) && isset($json['errors']) ? $json['errors'] : null), $raw);
        }
        $json = json_decode($respBody, true);
        if (!is_array($json)) {
            throw new \RuntimeException('Invalid Stone API response (HTTP ' . $status . ')');
        }
        return $json;
    }

    private function logHttp(string $method, string $url, int $status, ?string $reqBody, $respBody, ?string $err): void
    {
        try {
            if ((string)Env::get('STONE_HTTP_LOG', '1') !== '1') return;
            // Redact credentials
            $safeUrl = preg_replace('#://([^@]+)@#', '://***@', $url);
            $row = [
                'time' => date('Y-m-d H:i:s'),
                'method' => $method,
                'url' => $safeUrl,
                'status' => $status,
                'request' => $this->truncate($reqBody ?? ''),
                'response' => $this->truncate((string)$respBody),
                'error' => $err ?: null,
                'account' => [
                    'id' => $this->account['id'] ?? null,
                    'env' => $this->env,
                    'api_key_masked' => $this->maskKey($this->apiKey()),
                ],
            ];
            $root = dirname(__DIR__, 4);
            $dir = $root . '/storage/logs/stone';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $file = $dir . '/stone-http-' . date('Y-m-d') . '.log';
            @file_put_contents($file, json_encode($row, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        } catch (\Throwable $e) {
            // ignore logging errors
        }
    }

    private function maskKey(string $key): string
    {
        if ($key === '') return '';
        $len = strlen($key);
        if ($len <= 8) return str_repeat('*', $len);
        return substr($key, 0, 3) . str_repeat('*', max(0, $len - 7)) . substr($key, -4);
    }

    private function truncate(string $text, int $limit = 8000): string
    {
        if (strlen($text) > $limit) return substr($text, 0, $limit) . '... (truncated)';
        return $text;
    }

    public function captureCharge(string $chargeId, ?int $amountCents = null): array
    {
        $url = $this->baseCoreUrl() . '/charges/' . rawurlencode($chargeId) . '/capture';
        $headers = [ 'Content-Type: application/json', 'Accept: application/json', $this->authHeader() ];
        $body = $amountCents !== null ? json_encode(['amount' => $amountCents], JSON_UNESCAPED_UNICODE) : json_encode(new \stdClass());
        return $this->request('POST', $url, $headers, $body);
    }

    public function retryCharge(string $chargeId): array
    {
        $url = $this->baseCoreUrl() . '/charges/' . rawurlencode($chargeId) . '/retry';
        $headers = [ 'Content-Type: application/json', 'Accept: application/json', $this->authHeader() ];
        return $this->request('POST', $url, $headers, json_encode(new \stdClass()));
    }

    public function cancelCharge(string $chargeId): array
    {
        // Try POST /charges/{id}/cancel; fallback to DELETE /charges/{id}; if 404, tentar tratar id como order e cancelar suas charges
        $cancelUrl = $this->baseCoreUrl() . '/charges/' . rawurlencode($chargeId) . '/cancel';
        $headers = [ 'Content-Type: application/json', 'Accept: application/json', $this->authHeader() ];
        try {
            return $this->request('POST', $cancelUrl, $headers, json_encode(new \stdClass()));
        } catch (\App\Infrastructure\Stone\StoneApiException $e) {
            // Se não for 404, tentar DELETE direto na charge
            if ((int)$e->getStatus() !== 404) {
                $delUrl = $this->baseCoreUrl() . '/charges/' . rawurlencode($chargeId);
                return $this->request('DELETE', $delUrl, [ 'Accept: application/json', $this->authHeader() ], null);
            }
            // 404: talvez seja um order_id, tentar localizar charges pelo order e cancelar
            $order = $this->findOrder($chargeId);
            if (is_array($order) && isset($order['charges']) && is_array($order['charges'])) {
                foreach ($order['charges'] as $ch) {
                    $cid = (string)($ch['id'] ?? ''); if ($cid === '') continue;
                    try {
                        $u = $this->baseCoreUrl() . '/charges/' . rawurlencode($cid) . '/cancel';
                        return $this->request('POST', $u, [ 'Content-Type: application/json', 'Accept: application/json', $this->authHeader() ], json_encode(new \stdClass()));
                    } catch (\App\Infrastructure\Stone\StoneApiException $e2) {
                        // tentar DELETE
                        $u2 = $this->baseCoreUrl() . '/charges/' . rawurlencode($cid);
                        try { return $this->request('DELETE', $u2, [ 'Accept: application/json', $this->authHeader() ], null); }
                        catch (\Throwable $e3) { /* next charge */ }
                    } catch (\Throwable $e2) { /* next charge */ }
                }
            }
            // Como último recurso, relançar o erro original 404
            throw $e;
        }
    }

    public function refundCharge(string $chargeId, ?int $amountCents = null): array
    {
        $url = $this->baseCoreUrl() . '/charges/' . rawurlencode($chargeId) . '/refund';
        $headers = [ 'Content-Type: application/json', 'Accept: application/json', $this->authHeader() ];
        $body = $amountCents !== null ? json_encode(['amount' => $amountCents], JSON_UNESCAPED_UNICODE) : json_encode(new \stdClass());
        return $this->request('POST', $url, $headers, $body);
    }

    // Retrieve order by id (or null if not found)
    public function findOrder(string $orderId): ?array
    {
        $url = $this->baseCoreUrl() . '/orders/' . rawurlencode($orderId);
        $headers = [ 'Accept: application/json', $this->authHeader() ];
        try {
            $res = $this->request('GET', $url, $headers, null);
            return is_array($res) ? $res : null;
        } catch (\App\Infrastructure\Stone\StoneApiException $e) {
            if ((int)$e->getStatus() === 404) return null;
            throw $e;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function createSubscription(array $payload): array
    {
        $url = $this->baseCoreUrl() . '/subscriptions';
        $headers = [ 'Content-Type: application/json', 'Accept: application/json', $this->authHeader() ];
        return $this->request('POST', $url, $headers, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        $url = $this->baseCoreUrl() . '/subscriptions/' . rawurlencode($subscriptionId);
        $headers = [ $this->authHeader() ];
        return $this->request('DELETE', $url, $headers, null);
    }

    // Plans
    public function createPlan(array $payload): array
    {
        $url = $this->baseCoreUrl() . '/plans';
        $headers = [ 'Content-Type: application/json', $this->authHeader() ];
        return $this->request('POST', $url, $headers, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    public function updatePlan(string $planId, array $payload): array
    {
        $url = $this->baseCoreUrl() . '/plans/' . rawurlencode($planId);
        $headers = [ 'Content-Type: application/json', 'Accept: application/json', $this->authHeader() ];
        // According to docs (Editar plano), the method is PUT
        return $this->request('PUT', $url, $headers, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Retrieve a plan by id from Stone. Returns array or null if not found (404).
     */
    public function findPlan(string $planId): ?array
    {
        $url = $this->baseCoreUrl() . '/plans/' . rawurlencode($planId);
        $headers = [ 'Accept: application/json', $this->authHeader() ];
        try {
            $res = $this->request('GET', $url, $headers);
            if (is_array($res) && !isset($res['errors'])) return $res;
            return null;
        } catch (\App\Infrastructure\Stone\StoneApiException $e) {
            if ((int)$e->getStatus() === 404) return null;
            throw $e;
        }
    }

    public function updateSubscriptionStatus(string $subscriptionId, string $status): array
    {
        $url = $this->baseCoreUrl() . '/subscriptions/' . rawurlencode($subscriptionId);
        $headers = [ 'Content-Type: application/json', $this->authHeader() ];
        return $this->request('PATCH', $url, $headers, json_encode(['status'=>$status], JSON_UNESCAPED_UNICODE));
    }

    public function pauseSubscription(string $subscriptionId): array
    {
        // Deprecated in this hub: prefer manual billing enable
        return $this->enableManualBilling($subscriptionId);
    }

    public function resumeSubscription(string $subscriptionId): array
    {
        // Deprecated in this hub: prefer manual billing disable
        return $this->disableManualBilling($subscriptionId);
    }

    // Manual billing (subscriptions/{id}/manual-billing)
    public function enableManualBilling(string $subscriptionId): array
    {
        $url = $this->baseCoreUrl() . '/subscriptions/' . rawurlencode($subscriptionId) . '/manual-billing';
        $headers = [ 'Content-Type: application/json', 'Accept: application/json', $this->authHeader() ];
        return $this->request('POST', $url, $headers, json_encode(new \stdClass()));
    }

    public function disableManualBilling(string $subscriptionId): array
    {
        $url = $this->baseCoreUrl() . '/subscriptions/' . rawurlencode($subscriptionId) . '/manual-billing';
        $headers = [ $this->authHeader() ];
        return $this->request('DELETE', $url, $headers, null);
    }
}
