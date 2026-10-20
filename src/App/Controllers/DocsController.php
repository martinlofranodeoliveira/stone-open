<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Infrastructure\Http\{Request, Response};

final class DocsController
{
    // GET /api/openapi.json
    public function openapi(Request $req): Response
    {
        $root = dirname(__DIR__, 3); // project root
        $path = $root . '/docs/openapi.json';
        if (!is_file($path)) {
            return Response::json(['error' => 'OpenAPI spec not found'], 404);
        }
        $json = (string)file_get_contents($path);
        $spec = json_decode($json, true);
        if (is_array($spec)) {
            $this->augmentExamples($spec);
            // Sinaliza no spec que foi enriquecido dinamicamente
            $spec['info']['x-enriched'] = true;
            $json = json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], $json);
    }

    // GET /api/docs
    public function ui(Request $req): Response
    {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Stone Open API – Docs</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css" />
  <style> body { margin:0; } .topbar { display:none; } </style>
  <link rel="icon" href="data:," />
  <!-- Security headers are set by middleware/site; UI only references JS/CSS from CDN. -->
  <script defer src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
  <script>
    window.addEventListener('DOMContentLoaded', function () {
      window.ui = SwaggerUIBundle({
        url: '/api/openapi.json',
        dom_id: '#swagger-ui',
        presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
        layout: 'BaseLayout',
        deepLinking: true,
        requestInterceptor: (r) => r,
      });
    });
  </script>
  </head>
  <body>
    <div id="swagger-ui"></div>
  </body>
</html>
HTML;
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    /** Enriquecer o spec com exemplos de requisição/resposta em endpoints chave */
    private function augmentExamples(array &$spec): void
    {
        // Helper para obter referência segura a um caminho
        $get = function & (&$arr, array $path) {
            $ref =& $arr;
            foreach ($path as $k) {
                if (!is_array($ref)) { $ref = []; }
                if (!array_key_exists($k, $ref) || !is_array($ref[$k])) { $ref[$k] = []; }
                $ref =& $ref[$k];
            }
            return $ref;
        };

        // 1) POST /api/v1/payments — exemplos PIX e Cartão
        if (isset($spec['paths']['/api/v1/payments']['post'])) {
            $reqExamples = [
                'pix' => [
                    'summary' => 'Pagamento PIX',
                    'value' => [
                        'amount_cents' => 18990,
                        'client_reference_id' => 'PED-12345',
                        'payment_method_type' => 'pix',
                        'expires_at' => '2026-12-31T23:59:00-03:00',
                        'customer' => [
                            'name' => 'Maria Silva',
                            'email' => 'maria@example.com',
                            'document' => 'DOCUMENTO_EXEMPLO',
                            'phones' => [
                                'mobile_phone' => [ 'country_code' => '55', 'area_code' => '11', 'number' => 'NUMERO_EXEMPLO' ]
                            ]
                        ]
                    ]
                ],
                'boleto' => [
                    'summary' => 'Pagamento Boleto',
                    'value' => [
                        'amount_cents' => 18990,
                        'client_reference_id' => 'PED-12345',
                        'payment_method_type' => 'boleto',
                        'due_at' => '2026-12-31T23:59:00-03:00',
                        'boleto' => [
                            'document_number' => 'DOCUMENTO_EXEMPLO',
                            'instructions' => ['Pague até o vencimento']
                        ],
                        'customer' => [
                            'name' => 'Maria Silva',
                            'email' => 'maria@example.com',
                            'document' => 'DOCUMENTO_EXEMPLO',
                        ]
                    ]
                ],
                'card' => [
                    'summary' => 'Pagamento Cartão (token + juros)',
                    'value' => [
                        'amount_cents' => 25900,
                        'client_reference_id' => 'PED-987',
                        'payment_method_type' => 'credit_card',
                        'installments' => 3,
                        'juros' => 'C',
                        'bin' => '411111',
                        'card_token' => 'card_tok_abc123',
                        'customer' => [ 'email' => 'carlos@example.com' ]
                    ]
                ],
            ];
            $ref =& $get($spec, ['paths','/api/v1/payments','post','requestBody','content','application/json']);
            $ref['examples'] = $reqExamples;

            $resExamples = [
                'pix' => [
                    'summary' => 'Criado (PIX)',
                    'value' => [
                        'status' => 'success',
                        'message' => 'Pagamento aprovado',
                        'status_code' => 'pending',
                        'summary' => [
                            'status' => 'processing',
                            'reference' => 'PED-12345',
                            'method' => 'pix',
                            'amount_cents' => 18990,
                            'currency' => 'BRL',
                            'pix_qr_code_url' => 'https://example.test/qr.png'
                        ]
                    ]
                ],
                'card' => [
                    'summary' => 'Criado (Cartão)',
                    'value' => [
                        'status' => 'success',
                        'message' => 'Pagamento aprovado',
                        'status_code' => 'paid',
                        'summary' => [
                            'status' => 'paid',
                            'reference' => 'PED-987',
                            'method' => 'credit_card',
                            'amount_cents' => 25900,
                            'currency' => 'BRL'
                        ]
                    ]
                ]
            ];
            $ref201 =& $get($spec, ['paths','/api/v1/payments','post','responses','201','content','application/json']);
            $ref201['examples'] = $resExamples;
        }

        // 2) POST /api/v1/payment-links — exemplo request/response
        if (isset($spec['paths']['/api/v1/payment-links']['post'])) {
            $exReq = [
                'simple' => [
                    'summary' => 'Link simples com valor fixo',
                    'value' => [
                        'codigo' => 'LP-001',
                        'valor_centavos' => 12990,
                        'moeda' => 'BRL',
                        'expira_em_minutos' => 60,
                        'cliente' => [ 'nome' => 'João Souza', 'email' => 'joao@example.com', 'documento' => '98765432100' ]
                    ]
                ]
            ];
            $ref =& $get($spec, ['paths','/api/v1/payment-links','post','requestBody','content','application/json']);
            $ref['examples'] = $exReq;

            $exRes = [
                'created' => [
                    'summary' => 'Criado',
                    'value' => [
                        'status' => 'success',
                        'message' => 'Link de pagamento criado',
                        'status_code' => 'pending',
                        'id' => 42,
                        'codigo' => 'LP-001',
                        'valor_centavos' => 12990,
                        'moeda' => 'BRL',
                        'link_pagamento_url' => 'https://checkout.example/abc123',
                        'expira_em' => '2026-02-23T12:00:00Z'
                    ]
                ]
            ];
            $ref201 =& $get($spec, ['paths','/api/v1/payment-links','post','responses','201','content','application/json']);
            $ref201['examples'] = $exRes;
        }

        // 3) POST /api/v1/subscriptions — exemplo criando customer/card
        if (isset($spec['paths']['/api/v1/subscriptions']['post'])) {
            $ref =& $get($spec, ['paths','/api/v1/subscriptions','post','requestBody','content','application/json']);
            $ref['examples'] = [
                'with_customer' => [
                    'summary' => 'Com customer e cartão',
                    'value' => [
                        'stone_plan_id' => 'pl_123',
                        'payment_method' => 'credit_card',
                        'installments' => 1,
                        'customer' => [
                            'name' => 'Ana Lima',
                            'email' => 'ana@example.com',
                            'document' => 'DOCUMENTO_EXEMPLO',
                            'phones' => [ 'mobile_phone' => [ 'country_code' => '55', 'area_code' => '21', 'number' => 'NUMERO_EXEMPLO' ] ]
                        ],
                        'card' => [ 'token' => 'card_tok_abcd' ]
                    ]
                ]
            ];
        }

        // 4) POST /api/v1/customers/upsert — exemplo request
        if (isset($spec['paths']['/api/v1/customers/upsert']['post'])) {
            $ref =& $get($spec, ['paths','/api/v1/customers/upsert','post','requestBody','content','application/json']);
            $ref['examples'] = [
                'create' => [
                    'summary' => 'Criar cliente',
                    'value' => [
                        'name' => 'Pedro Alves',
                        'email' => 'pedro@example.com',
                        'document' => 'DOCUMENTO_EXEMPLO',
                        'type' => 'individual',
                        'phones' => [ 'mobile_phone' => [ 'country_code' => '55', 'area_code' => '31', 'number' => 'NUMERO_EXEMPLO' ] ],
                        'address' => [ 'zip_code' => '01001000', 'country' => 'BR', 'state' => 'SP', 'city' => 'São Paulo', 'line_1' => 'Praça da Sé, 1' ]
                    ]
                ]
            ];
        }

        // 4b) POST /api/v1/customers/update — exemplo request
        if (isset($spec['paths']['/api/v1/customers/update']['post'])) {
            $ref =& $get($spec, ['paths','/api/v1/customers/update','post','requestBody','content','application/json']);
            $ref['examples'] = [
                'update_by_lookup' => [
                    'summary' => 'Atualizar por stone_customer_id',
                    'value' => [
                        'stone_customer_id' => 'cus_123',
                        'name' => 'Pedro Alves',
                        'email' => 'pedro+edit@example.com',
                        'document' => 'DOCUMENTO_EXEMPLO'
                    ]
                ]
            ];
        }

        // 5) GET /api/v1/cards/quote — exemplo resposta
        if (isset($spec['paths']['/api/v1/cards/quote']['get'])) {
            $ref200 =& $get($spec, ['paths','/api/v1/cards/quote','get','responses','200','content','application/json']);
            $ref200['examples'] = [
                'ok' => [
                    'summary' => 'Exemplo de cotação',
                    'value' => [
                        'status' => 'success',
                        'message' => 'Cotação calculada',
                        'status_code' => 'quoted',
                        'brand' => 'visa',
                        'brand_icon' => '/assets/brands/visa.svg',
                        'interest_payer' => 'user',
                        'installments' => [
                            [ 'number' => 1, 'amount_cents' => 18990, 'total_cents' => 18990, 'interest_rate' => 0, 'is_interest_free' => true, 'label' => '1x de R$ 189,90' ],
                            [ 'number' => 2, 'amount_cents' => 9745, 'total_cents' => 19490, 'interest_rate' => 1.99, 'is_interest_free' => false, 'label' => '2x de R$ 97,45 (1,99% a.m.)' ]
                        ]
                    ]
                ]
            ];
        }
    }
}
