<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\Stone\StoneAccount;

final class PaymentLinksController
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
    // POST /api/v1/payment-links
    public function create(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['error' => 'Não autorizado'], 401);

        $in = $req->json ?? [];
        if (!is_array($in)) return Response::json(['status' => 'error', 'message' => 'JSON inválido', 'status_code' => 'invalid_json', 'http_status' => 400], 400);

        // Map PT aliases -> EN (orders + checkout payload)
        $payload = [];
        // closed: criar como true por padrão
        $payload['closed'] = (bool)($in['fechado'] ?? $in['closed'] ?? true);

        // code / referência
        $code = (string)($in['codigo'] ?? ($in['referencia'] ?? ($in['code'] ?? '')));
        if ($code !== '') $payload['code'] = $code;

        // items: aceitar itens[] ou valor_centavos + descricao
        $items = [];
        if (isset($in['itens']) && is_array($in['itens'])) {
            foreach ($in['itens'] as $it) {
                if (!is_array($it)) continue;
                $amount = isset($it['valor_centavos']) ? (int)$it['valor_centavos'] : (int)($it['amount'] ?? 0);
                $desc = (string)($it['descricao'] ?? ($it['description'] ?? ''));
                $qty = (int)($it['quantidade'] ?? ($it['quantity'] ?? 1));
                $icode = (string)($it['codigo'] ?? ($it['code'] ?? ''));
                if ($amount <= 0) continue;
                $items[] = [
                    'amount' => $amount,
                    'description' => $desc !== '' ? $desc : 'Item',
                    'quantity' => $qty > 0 ? $qty : 1,
                    'code' => $icode !== '' ? $icode : null,
                ];
            }
        } else {
            $amount = isset($in['valor_centavos']) ? (int)$in['valor_centavos'] : (int)($in['amount_cents'] ?? 0);
            if ($amount <= 0) {
                return Response::json([
                    'status' => 'error',
                    'message' => 'Informe itens ou valor_centavos',
                    'status_code' => 'validation_error',
                    'http_status' => 422,
                    'required' => ['itens[] ou valor_centavos'],
                    'errors' => ['Informe itens ou valor_centavos'],
                    'errors_pt' => ['Informe itens ou valor_centavos'],
                ], 422);
            }
            $desc = (string)($in['descricao'] ?? ($in['description'] ?? 'Pagamento'));
            $items[] = [ 'amount' => $amount, 'description' => $desc, 'quantity' => 1, 'code' => $code ?: 'LINK' ];
        }
        $payload['items'] = $items;

        // customer (opcional para checkout)
        if (isset($in['cliente']) && is_array($in['cliente'])) {
            $c = $in['cliente'];
            $payload['customer'] = [
                'name' => $c['nome'] ?? ($c['name'] ?? null),
                'email' => $c['email'] ?? null,
                'document' => $c['documento'] ?? ($c['document'] ?? null),
                'type' => $c['tipo'] ?? ($c['type'] ?? null),
                'document_type' => $c['documento_tipo'] ?? ($c['document_type'] ?? null),
            ];
        } elseif (isset($in['customer']) && is_array($in['customer'])) {
            $payload['customer'] = $in['customer'];
        }

        // shipping (opcional)
        if (isset($in['frete']) && is_array($in['frete'])) {
            $f = $in['frete'];
            $payload['shipping'] = [
                'amount' => (int)($f['valor_centavos'] ?? ($f['amount'] ?? 0)),
                'description' => $f['descricao'] ?? ($f['description'] ?? null),
                'recipient_name' => $f['nome_destinatario'] ?? ($f['recipient_name'] ?? null),
            ];
        } elseif (isset($in['shipping']) && is_array($in['shipping'])) {
            $payload['shipping'] = $in['shipping'];
        }

        // currency
        $currency = strtoupper((string)($in['moeda'] ?? ($in['currency'] ?? 'BRL')));
        $payload['currency'] = $currency;

        // checkout config
        $checkout = [];
        $expMin = isset($in['expira_em_minutos']) ? (int)$in['expira_em_minutos'] : (int)($in['expires_in'] ?? 0);
        if ($expMin > 0) $checkout['expires_in'] = $expMin; // minutos
        $successUrl = (string)($in['url_sucesso'] ?? ($in['success_url'] ?? ''));
        if ($successUrl !== '') $checkout['success_url'] = $successUrl;

        // métodos aceitos
        $meth = $in['metodos_pagamento'] ?? ($in['accepted_payment_methods'] ?? null);
        if (is_array($meth)) {
            $map = ['cartao' => 'credit_card', 'credito' => 'credit_card', 'pix' => 'pix', 'boleto' => 'boleto'];
            $checkout['accepted_payment_methods'] = array_values(array_unique(array_map(function($m) use ($map){
                $m = strtolower((string)$m);
                return $map[$m] ?? $m;
            }, $meth)));
        } else {
            $checkout['accepted_payment_methods'] = ['credit_card'];
        }
        // bandeiras
        if (isset($in['bandeiras']) && is_array($in['bandeiras'])) {
            $checkout['accepted_brands'] = array_map(fn($b) => strtolower((string)$b), $in['bandeiras']);
        } elseif (isset($in['accepted_brands']) && is_array($in['accepted_brands'])) {
            $checkout['accepted_brands'] = $in['accepted_brands'];
        }
        // cartão (opções)
        if (isset($in['cartao']) && is_array($in['cartao'])) {
            $cc = $in['cartao'];
            $checkout['credit_card'] = [
                'operation_type' => $cc['operacao'] ?? ($cc['operation_type'] ?? 'auth_and_capture'),
                'statement_descriptor' => $cc['descricao_fatura'] ?? ($cc['statement_descriptor'] ?? null),
            ];
        } elseif (isset($in['checkout']['credit_card']) && is_array($in['checkout']['credit_card'])) {
            $checkout['credit_card'] = $in['checkout']['credit_card'];
        }

        $payload['payments'] = [
            [ 'payment_method' => 'checkout', 'checkout' => $checkout ]
        ];

        // Calcular soma para amount_cents local
        $totalCents = 0;
        foreach ($items as $it) { $totalCents += ((int)$it['amount']) * ((int)$it['quantity']); }
        if (isset($payload['shipping']['amount'])) $totalCents += (int)$payload['shipping']['amount'];

        // Idempotency: use code or explicit idempotency_key
        $idem = (string)($in['chave_idempotencia'] ?? ($in['idempotency_key'] ?? ($code ?: '')));

        try {
            $client = \App\Infrastructure\Stone\StoneClient::forSite($site);
            $resp = $client->createOrder($payload, $idem !== '' ? $idem : null);
            $stoneOrderId = (string)($resp['id'] ?? '');
            $status = strtolower((string)($resp['status'] ?? '')) ?: null;
            $checkoutUrl = self::extractCheckoutUrl($resp);
            $env = self::resolveEnv($site);

            // Compute expires_at if not present
            $expiresAt = null;
            if (isset($resp['expires_at'])) {
                $expiresAt = (string)$resp['expires_at'];
            } elseif ($expMin > 0) {
                $expiresAt = date('Y-m-d H:i:s', time() + ($expMin * 60));
            }

            // Customer hints
            $cname = $payload['customer']['name'] ?? null;
            $cemail = $payload['customer']['email'] ?? null;

            Database::run('INSERT INTO payment_links (site_id, stone_order_id, code, env, amount_cents, currency, status, checkout_url, expires_at, expires_in_minutes, customer_name, customer_email, request_payload, response_payload, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', [
                $site['id'], $stoneOrderId ?: null, $code ?: null, $env, $totalCents, $currency, $status, $checkoutUrl, $expiresAt, $expMin ?: null, $cname, $cemail,
                json_encode($payload, JSON_UNESCAPED_UNICODE), json_encode($resp, JSON_UNESCAPED_UNICODE)
            ]);
            $id = (int) Database::pdo()->lastInsertId();

            return Response::json([
                'status' => 'success',
                'message' => 'Link de pagamento criado',
                'status_code' => $status ?: 'pending',
                'id' => $id,
                'link_pagamento_url' => $checkoutUrl,
                'id_pedido_stone' => $stoneOrderId ?: null,
                'valor_centavos' => $totalCents,
                'moeda' => $currency,
                'codigo' => $code ?: null,
                'expira_em_minutos' => $expMin ?: null,
                'expira_em' => $expiresAt,
            ], 201);
        } catch (\App\Infrastructure\Stone\StoneApiException $e) {
            $errs = $e->getErrors();
            $ptMsg = \App\Support\I18n::ptHttpMessage($e->getStatus(), 'Erro ao criar link de pagamento');
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
        } catch (\App\Infrastructure\Stone\StoneAccountRequiredException $e) {
            return Response::json([
                'status' => 'error', 'message' => 'Conta Pagar.me não configurada para este site',
                'status_code' => 'pagarme_account_required', 'http_status' => 409,
                'reason' => $e->getMessage(),
            ], 409);
        } catch (\Throwable $e) {
            return Response::json(['status' => 'error', 'message' => $e->getMessage(), 'status_code' => 'internal_error', 'http_status' => 500], 500);
        }
    }

    private static function extractCheckoutUrl(array $resp): ?string
    {
        // Try common response locations
        if (isset($resp['checkout_url']) && is_string($resp['checkout_url'])) return $resp['checkout_url'];
        if (isset($resp['checkouts']) && is_array($resp['checkouts']) && isset($resp['checkouts'][0]) && is_array($resp['checkouts'][0])) {
            $c0 = $resp['checkouts'][0];
            foreach (['payment_url','url','checkout_url'] as $k) if (!empty($c0[$k]) && is_string($c0[$k])) return (string)$c0[$k];
        }
        // Fallback: search recursively for a URL that looks like a checkout link
        $stack = [$resp];
        while ($stack) {
            $cur = array_pop($stack);
            if (!is_array($cur)) continue;
            foreach ($cur as $k => $v) {
                if (is_string($v) && preg_match('#^https?://#i', $v)) {
                    if (stripos($v, 'checkout') !== false || stripos($v, 'pay') !== false || stripos($v, 'payment') !== false) return $v;
                }
                if (is_array($v)) $stack[] = $v;
            }
        }
        return null;
    }
}
