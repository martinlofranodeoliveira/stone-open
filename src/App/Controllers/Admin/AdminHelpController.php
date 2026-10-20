<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\View\View;
use App\Support\Env;

final class AdminHelpController
{
    public function index(Request $req): Response
    {
        $html = View::render('admin/help', [
            'prefix' => $this->prefix(),
            'appUrl' => rtrim(Env::get('APP_URL', ''), '/'),
            'adminPrefix' => rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/'),
            'clientPhpCode' => $this->clientPhpCode(),
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function downloadClient(Request $req): Response
    {
        $php = $this->clientPhpCode();
        return new Response(200, [
            'Content-Type' => 'application/x-httpd-php; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="StoneHubClient.php"',
        ], $php);
    }

    public function downloadUsage(Request $req): Response
    {
        $base = rtrim(Env::get('APP_URL', ''), '/');
        $usage = <<<MD
# StoneHubCliente – Exemplos de Uso (PT-BR)

> Esta referência mostra, para cada método do cliente, os campos do payload esperados (envio) e os campos comuns da resposta (retorno).
>
> Classe: `StoneHubCliente` (baixe a classe em /_admin/help/client-php)
>
> Collection do Postman (todas as rotas + exemplos): `/_admin/help/postman`

## Configuração de Parcelamento (por Site)
- Em Admin → Sites → Editar, defina:
  - `Quem paga o juro?` → `Usuário (comprador)` ou `Vendedor (site)`
  - `Parcelas sem juros` (válido quando o vendedor subsidia)
- Efeito:
  - A rota `GET /api/v1/cards/quote` refletirá essa configuração ao montar a simulação das parcelas (campo `interest_payer` e `installments`).
  - Você também pode forçar na query `interest_payer=merchant`/`user` para casos pontuais.
  - Atalho: query `juros=C|V` (C=comprador, V=vendedor) — sobrescreve o payer.

> ATENÇÃO — JUROS E PARCELAMENTO
>
> - Campo opcional `juros` no pagamento por cartão: `C` (comprador) ou `V` (vendedor). Se omitido, usa a configuração do site.
> - Para `juros=C` com cálculo automático, envie `bin`/`numero_cartao` (6 dígitos). Sem BIN, calcule manualmente via `/api/v1/cards/quote`.
> - Vendedor assume juros: envie `amount_cents` = valor base do produto e informe `installments`.
> - Comprador assume juros sem `juros`: consulte `/api/v1/cards/quote`, pegue o `total_cents` da parcela N desejada e envie esse valor em `amount_cents` junto com `installments` = N.
>
> Exemplos de payloads:
>
> - Vendedor assume (3x sem juros):
> ```json
> {"client_reference_id":"pedido-001","amount_cents":10000,"currency":"BRL","payment_method_type":"credit_card","installments":3,"juros":"V","card":{"token":"tok_abc123"}}
> ```
> - Comprador assume (3x com juros; total calculado automaticamente):
> ```json
> {"client_reference_id":"pedido-002","amount_cents":10000,"currency":"BRL","payment_method_type":"credit_card","installments":3,"juros":"C","bin":"411111","card":{"token":"tok_def456"}}
> ```

## Autenticação
- Via Bearer: header `Authorization: Bearer <API_KEY>
- Ou via cabeçalhos: `X-Client-Id` + `X-Client-Secret`

```php
\$hub = new StoneHubCliente('{$base}', 'SEU_API_TOKEN');
```

---

## 1) Pagamentos

### 1.1 criarPagamento(array \$dados)
- Envio (campos em português):
  - `valor_centavos` (int)
  - `moeda` (string, ex.: "BRL")
  - `referencia_cliente` (string)
  - `tipo_metodo_pagamento` (string: "pix" | "credit_card" | "boleto")
  - Se cartão: `token_cartao` (string) ou `id_cartao` (string), `parcelas` (int)
  - Se cartão (opcionais): `juros` ("C"|"V"), `bin` (6 dígitos para cálculo automático quando juros=C)
  - Se pix: `expira_em` (date-time) ou `expires_at`
  - Se boleto: `data_vencimento`/`due_at` (date-time) e `boleto` { `bank`, `instructions`, `document_number`, `type` } (opcional)
  - Opcionais: `descricao` (string), `cliente` (objeto com name/email/document)
- Retorno (exemplo):
```json
{
  "payment": {
    "id": 123,
    "site_id": 1,
    "client_reference_id": "pedido-123",
    "amount_cents": 10990,
    "currency": "BRL",
    "payment_method_type": "pix",
    "status": "processing|paid|failed|authorized|...",
    "stone_payment_id": "ch_abc123" | null,
    "created_at": "2025-01-01 12:00:00",
    "updated_at": "2025-01-01 12:00:10"
  }
}
```
- Exemplo PHP:
```php
\$res = \$hub->criarPagamento([
  'valor_centavos' => 10990,
  'moeda' => 'BRL',
  'referencia_cliente' => 'pedido-123',
  'tipo_metodo_pagamento' => 'pix',
  'expira_em' => '2026-12-31T23:59:00-03:00',
]);
```

### 1.2 criarPagamentoPix(array \$dados)
Atalho de criarPagamento com `tipo_metodo_pagamento = 'pix'`.

### 1.3 criarPagamentoCartao(array \$dados)
Atalho de criarPagamento com `tipo_metodo_pagamento = 'credit_card'`.

### 1.4 criarBoleto(array \$dados)
Atalho de criarPagamento com `tipo_metodo_pagamento = 'boleto'`.
Exemplo:
```php
\$res = \$hub->criarBoleto([
  'valor_centavos' => 10990,
  'moeda' => 'BRL',
  'referencia_cliente' => 'pedido-123',
  'data_vencimento' => '2026-12-31T23:59:00-03:00',
  'boleto' => [
    'document_number' => 'DOCUMENTO_EXEMPLO',
    'instructions' => ['Pague até o vencimento'],
  ],
]);
```

### 1.5 Pagamento com payload combinado (customer + card)
- Envie tudo em uma única chamada para `POST {$base}/api/v1/payments`
- Aceita os campos do item 1.1 e, adicionalmente:
  - `customer`: objeto completo (name, email, document, address, phones, ...)
  - `card`: usar preferencialmente `token` (card token). Se precisar (PCI), pode enviar dados completos.
- Exemplo (Card Token):
```json
{
  "client_reference_id": "pedido-123",
  "amount_cents": 10990,
  "currency": "BRL",
  "payment_method_type": "credit_card",
  "customer": { "name": "Fulano", "email": "customer@example.invalid", "document": "DOCUMENTO_EXEMPLO" },
  "card": { "token": "tok_abc123" },
  "installments": 1,
  "description": "Compra X"
}
```
- Resposta (sucesso):
```json
{ "payment": { "id": 124, "status": "processing", "stone_payment_id": "ch_123", "amount_cents": 10990, "currency": "BRL" } }
```
- Resposta (erro de validação):
```json
{ "error": "Missing required fields: amount_cents, client_reference_id, payment_method_type" }
```
- Resposta (erro de processamento):
```json
{ "error": "Payment creation failed", "message": "Stone API error (...detalhes...)" }
```

### 1.5 Pagamento para cliente (CPF/Email/StoneID)
- Rota: `POST {$base}/api/v1/payments/for-customer`
- Exemplo (por email):
```json
{
  "email": "customer@example.invalid",
  "client_reference_id": "pedido-789",
  "amount_cents": 1990,
  "currency": "BRL",
  "payment_method_type": "credit_card",
  "card": { "token": "tok_xyz" }
}
```
- Resposta (sucesso):
```json
{ "payment": { "id": 125, "status": "processing", "stone_payment_id": "ch_999", "amount_cents": 1990, "currency": "BRL" } }
```
- Erro (customer não encontrado):
```json
{ "error": "Customer not found" }
```

### 1.6 Erros comuns em pagamento (exemplos)
- Recusado por fundos insuficientes (Stone):
```json
{ "error": "Payment creation failed", "message": "insufficient_funds" }
```
- Recusado por antifraude:
```json
{ "error": "Payment creation failed", "message": "rejected_by_antifraud" }
```
- Cartão inválido/expirado:
```json
{ "error": "Payment creation failed", "message": "card_expired" }
```

### 1.7 Link de pagamento (checkout)
- Rota: `POST {$base}/api/v1/payment-links`
- Envio (PT): `codigo`, `valor_centavos` ou `itens[]`, `moeda` (BRL), `expira_em_minutos`, `url_sucesso`, `metodos_pagamento` (ex.: ["cartao","pix"]) 
- Retorno (PT): `{ id, link_pagamento_url, id_pedido_stone, status, valor_centavos, moeda, codigo, expira_em_minutos, expira_em }`

Exemplo (request):
```json
{
  "codigo": "pedido-123",
  "valor_centavos": 2990,
  "moeda": "BRL",
  "expira_em_minutos": 120,
  "url_sucesso": "https://merchant.example.invalid/sucesso",
  "metodos_pagamento": ["cartao"],
  "bandeiras": ["visa","mastercard"],
  "cliente": {"nome":"Tony","email":"customer@example.invalid","documento":"03154435026"}
}
```
Exemplo (cURL):
```bash
curl -X POST {$base}/api/v1/payment-links \
  -H "Authorization: Bearer <API_KEY>" \
  -H "Content-Type: application/json" \
  --data '{
    "codigo":"pedido-123",
    "valor_centavos":2990,
    "moeda":"BRL",
    "expira_em_minutos":120,
    "url_sucesso":"https://merchant.example.invalid/sucesso",
    "metodos_pagamento":["cartao"],
    "bandeiras":["visa","mastercard"]
  }'
```
Exemplo (resposta):
```json
{
  "id": 1,
  "link_pagamento_url": "https://checkout...",
  "id_pedido_stone": "or_abc123",
  "status": "open",
  "valor_centavos": 2990,
  "moeda": "BRL",
  "codigo": "pedido-123",
  "expira_em_minutos": 120,
  "expira_em": "2025-01-01 13:00:00"
}
```

---

## 2) Cartões

### 1.x Assinaturas – Faturamento Manual (Pausar/Retomar)

Para pausar/retomar uma assinatura, usamos a configuração de faturamento manual da Pagar.me:

- Pausar = habilitar faturamento manual
  - `POST /subscriptions/{subscription_id}/manual-billing`
- Retomar = desabilitar faturamento manual (voltar ao automático)
  - `DELETE /subscriptions/{subscription_id}/manual-billing`

No Admin, o botão de alternância chama essas rotas via AJAX e, após sucesso, atualiza a assinatura localmente (campo `manual_billing` em `metadata`).

Exemplos de retorno do Admin:
```json
{ "ok": 1, "manual_billing": true }    // pausado (manual)
{ "ok": 1, "manual_billing": false }   // retomado (automático)
```

### 2.1 cotarCartao(int \$valorCentavos, string \$bin, array \$opcoes = [])
- Envio:
  - Obrigatórios: `valor_centavos` (int), `bin` (6 dígitos)
  - Opcionais: `max_parcelas` (int), `parcelas_sem_juros` (int), `taxa_juros` (float, %/mês), `valor_minimo_parcela_centavos` (int), `interest_payer` ("user"|"merchant"), `juros` ("C"|"V")
- Retorno:
```json
{
  "brand": "visa",
  "brand_icon": "/assets/brands/visa.svg",
  "interest_payer": "user",
  "installments": [
    {
      "number": 1,
      "amount_cents": 10990,
      "total_cents": 10990,
      "interest_rate": 0,
      "is_interest_free": true,
      "amount_formatted": "R$ 109,90",
      "total_formatted": "R$ 109,90",
      "label": "1x de R$ 109,90"
    }
  ]
}
```
> Dica: configure no site quem paga o juro (Usuário/Vendedor) e quantas parcelas sem juros quando o vendedor subsidia. Também é possível forçar via query `interest_payer=merchant` ou usar `juros=C|V`.

#### Exemplo (PHP - classe)
```php
// Usuário paga o juro (padrão)
$q1 = $hub->cotarCartao(10990, '411111', ['max_parcelas'=>12]);

// Vendedor paga o juro (site subsidia N parcelas)
$q2 = $hub->cotarCartao(25990, '411111', ['interest_payer'=>'merchant']);

// Atalho: comprador paga o juro (juros=C)
$q3 = $hub->cotarCartao(25990, '411111', ['juros'=>'C']);
```

### 2.2 obterBandeiraCartao(string \$bin)
- Retorno: `{ "brand": "visa|mastercard|..." }`

### 2.3 detectarBandeiraLocal(string \$bin)
- Retorno: `"visa"|"mastercard"|...` ou `null`

---

## 3) Assinaturas (recorrência)

### 3.1 criarAssinatura(array \$dados)
- Envio:
  - `id_cliente_stone` (string) – obrigatório
  - `id_plano_stone` (string) – obrigatório
  - `valor_centavos` (int) – opcional
  - `moeda` (string, ex.: "BRL") – opcional
- Retorno:
```json
{
  "subscription": {
    "id": 10,
    "site_id": 1,
    "stone_subscription_id": "sub_123",
    "plan_id": "plan_abc",
    "amount_cents": 25990,
    "currency": "BRL",
    "status": "active|paused|canceled|...",
    "start_date": "2025-01-01",
    "end_date": null,
    "created_at": "2025-01-01 12:00:00"
  }
}
```

### 3.2 obterAssinaturaPorId(int \$id)
- Retorno: objeto `subscription` (campos acima)

### 3.3 obterAssinaturaPorIdStone(string \$sid)
- Retorno: objeto `subscription`

### 3.4 listarEventosAssinatura(int \$idAssinatura)
- Retorno:
```json
{
  "events": [
    {"id": 1, "event_type": "subscription.activated", "occurred_at": "2025-01-01T12:00:00Z"}
  ]
}
```

### 3.5 listarPagamentosPorAssinatura(string \$idAssinaturaStone)
- Retorno (timeline correlacionada via inbox):
```json
{
  "data": [
    {"notification_id": 123, "event_type": "subscription.activated", "stone_payment_id": null, "payment_id": null, "status": "processed", "received_at": "..."}
  ]
}
```

### 3.6 Assinatura com payload combinado (customer + card + plano)
- Envie tudo em uma única chamada para `POST {$base}/api/v1/subscriptions`
- Aceita:
  - `plano_id` (ID local do plano) ou `stone_plan_id`
  - `customer`: objeto completo (name, email, document, address, phones, ...)
  - `card`: preferencialmente `token`; opcionalmente dados completos (PCI)
- Exemplo (com plano local e card token):
```json
{
  "plano_id": 1,
  "currency": "BRL",
  "amount_cents": 25990,
  "customer": { "name": "Ciclana", "email": "customer@example.invalid", "document": "DOCUMENTO_EXEMPLO" },
  "card": { "token": "tok_def456" },
  "installments": 1
}
```
- Resposta (sucesso):
```json
{ "subscription": { "id": 20, "stone_subscription_id": "sub_123", "status":"active", "amount_cents":25990, "currency":"BRL" } }
```
- Resposta (erro):
```json
{ "error": "stone_plan_id (or plano_id) is required" }
```

### 3.7 Assinatura para cliente (CPF/Email/StoneID)
- Rota: `POST {$base}/api/v1/subscriptions/for-customer`
- Exemplo:
```json
{
  "document": "DOCUMENTO_EXEMPLO",
  "plano_id": 1,
  "amount_cents": 25990,
  "currency": "BRL",
  "card": { "token": "tok_def456" },
  "installments": 1
}
```
- Resposta (sucesso):
```json
{ "subscription": { "id": 21, "stone_subscription_id": "sub_999", "status":"active", "amount_cents":25990, "currency":"BRL" } }
```
- Erro (customer não encontrado):
```json
{ "error": "Customer not found" }
```

### 3.8 Erros comuns em assinatura (exemplos)
- Verificação de cartão falhou (bandeira/banco):
```json
{ "error": "Card verification failed" }
```
- Plano inválido/inexistente:
```json
{ "error": "stone_plan_id (or plano_id) is required" }
```

## Planos (recorrência)

### criarPlano (POST {$base}/api/v1/plans)
- Envio (PT):
  - nome (string), descricao (string, opcional), moeda (BRL),
  - intervalo (day|week|month|year), intervalo_qtd (int>0),
  - metodos_pagamento (ex.: ["cartao","boleto"], opcional), parcelas ([1,2...], opcional),
  - itens: [{ nome, quantidade, preco_centavos, ciclos(opcional) }] OU informe apenas valor_centavos (itens opcional),
  - tipo_cobranca (prepaid|postpaid|exact_day; se exact_day informe dia_cobranca 1..31),
  - descricao_fatura (<=13), dias_teste (int, opcional), codigo (opcional).
- Exemplo simples (sem itens):
```json
{
  "nome": "Plano Gold",
  "descricao": "Descrição do plano",
  "moeda": "BRL",
  "intervalo": "month",
  "intervalo_qtd": 1,
  "valor_centavos": 18990
}
```
- Exemplo completo (com itens):
```json
{
  "nome": "Plano Gold",
  "descricao": "Descrição do plano",
  "moeda": "BRL",
  "intervalo": "month",
  "intervalo_qtd": 1,
  "tipo_cobranca": "prepaid",
  "metodos_pagamento": ["cartao","boleto"],
  "parcelas": [1],
  "itens": [
    { "nome": "Musculação", "quantidade": 1, "preco_centavos": 18990 },
    { "nome": "Matrícula", "quantidade": 1, "preco_centavos": 5990, "ciclos": 1 }
  ],
  "descricao_fatura": "FATURA",
  "dias_teste": 1,
  "codigo": "PLANO-GOLD-001"
}
```
- Resposta (sucesso):
```json
{ "plano": { "id": 1, "id_plano_stone": "plan_abc", "nome": "Plano Gold", "codigo": "PLANO-GOLD-001", "valor_centavos": 24980, "moeda": "BRL", "intervalo": "month", "intervalo_qtd": 1, "status": "active" } }
```

### listarPlanos
- Rota: `GET {$base}/api/v1/plans`
- Filtros (opcional): `status`, `q`, `limit`, `offset`
- Resposta (exemplo):
```json
{ "data": [ { "id": 1, "nome": "Plano Gold", "codigo": "PLANO-GOLD-001", "valor_centavos": 24980, "moeda": "BRL", "intervalo": "month", "intervalo_qtd": 1, "status": "active", "id_plano_stone": "plan_abc" } ] }
```

### criarPlano (modelo dia exato)
- Quando `tipo_cobranca` = `exact_day`, informe `dia_cobranca` (1..31). Exemplo:
```json
{
  "nome": "Plano Mensal (Dia 10)",
  "descricao": "Cobrança no dia 10",
  "moeda": "BRL",
  "intervalo": "month",
  "intervalo_qtd": 1,
  "tipo_cobranca": "exact_day",
  "dia_cobranca": 10,
  "metodos_pagamento": ["cartao"],
  "itens": [
    { "nome": "Mensalidade", "quantidade": 1, "preco_centavos": 9900 },
    { "nome": "Taxa Administração", "quantidade": 1, "preco_centavos": 500 }
  ]
}
```

### criarPlano (pós-pago)
- Quando `tipo_cobranca` = `postpaid`, a cobrança acontece após o período. Exemplo:
```json
{
  "nome": "Plano Pós-Pago",
  "descricao": "Cobra após o período",
  "moeda": "BRL",
  "intervalo": "month",
  "intervalo_qtd": 1,
  "tipo_cobranca": "postpaid",
  "metodos_pagamento": ["cartao","boleto"],
  "itens": [
    { "nome": "Mensalidade", "quantidade": 1, "preco_centavos": 9900 }
  ]
}
```

### criarPlano (parcelas múltiplas no cartão)
- Para permitir parcelamento no cartão, envie `parcelas`: [1,2,3,...]. Exemplo:
```json
{
  "nome": "Plano Parcelado",
  "moeda": "BRL",
  "intervalo": "month",
  "intervalo_qtd": 1,
  "metodos_pagamento": ["cartao"],
  "parcelas": [1,2,3,6,12],
  "itens": [
    { "nome": "Mensalidade", "quantidade": 1, "preco_centavos": 15990 }
  ]
}
```


---

## 4) Consultas de Pagamentos

### 4.1 obterPagamentoPorId(int \$id)
- Retorno: objeto de pagamento (mesmos campos do item 1.1 retorno)

### 4.2 obterPagamentoPorIdStone(string \$idStone)
- Retorno: idem

### 4.3 obterPagamentoPorReferencia(string \$referenciaCliente)
- Retorno: idem

### 4.4 listarPagamentos(array \$filtros)
- Filtros aceitos: `from`, `to`, `customer_id`, `customer_email`, `status`, `subscription_stone_id`, `limit`, `offset`
- Retorno:
```json
{
  "data": [
    {
      "id": 123, "site_id": 1, "client_reference_id": "...", "amount_cents": 10990,
      "currency": "BRL", "payment_method_type": "pix", "status": "paid",
      "stone_payment_id": "ch_...", "created_at": "...", "customer_email": "..."
    }
  ]
}
```

---

## 5) Clientes

### 5.1 obterClientePorId(int \$id)
- Retorno: `{ id, site_id, stone_customer_id, name, email, document, created_at, ... }`

### 5.2 buscarClientes(array \$filtros)
- Filtros: `email`, `stone_customer_id`, `document`
- Retorno: `{ "data": [ { id, name, email, ... } ] }`

### 5.3 buscarClientesAvancado (rota /customers/search)
- Rota: `GET {$base}/api/v1/customers/search?email=&document=&stone_customer_id=`
- Exemplo de retorno:
```json
{ "data": [ { "id": 10, "stone_customer_id": "cus_999", "name": "Fulano", "email": "customer@example.invalid", "document": "DOCUMENTO_EXEMPLO" } ] }
```

### 5.4 upsertCliente (cria/atualiza)
- Rota: `POST {$base}/api/v1/customers/upsert`
- Exemplo de envio:
```json
{ "name": "Nova Pessoa", "email": "customer@example.invalid", "document": "DOCUMENTO_EXEMPLO" }
```
- Resposta (sucesso):
```json
{ "customer": { "id": 12, "stone_customer_id": "cus_abc", "name": "Nova Pessoa", "email": "customer@example.invalid" } }
```

### 5.5 atualizarClientePorId
- Rota: `PUT {$base}/api/v1/customers/{id}`
- Exemplo de envio:
```json
{ "name": "Nome Atualizado" }
```
- Resposta (sucesso):
```json
{ "customer": { "id": 12, "name": "Nome Atualizado", "stone_customer_id": "cus_abc" } }
```

---

## 6) Tarefas HTTP (sem terminal)

### 6.1 tarefasEsvaziarFila(int \$limite, int \$orcamentoMs)
- Envio: QueryString ou header `X-Tasks-Secret` (use `comSegredoTarefas` no cliente)
- Retorno: `{ "drained": <quantidade> }`

### 6.2 tarefasReentregar(int \$limite, int \$maxTentativas)
- Retorno: `{ "retried": <quantidade> }`

---

## 7) Segurança do Forward

### 7.1 verificarAssinaturaForward(string \$corpo, string \$segredo, string \$cabecalho)
- Envio: `corpo` (rawBody), `segredo` (FORWARD_SECRET), `cabecalho` (valor de `X-Hub-Signature`)
- Retorno: `true|false`

Exemplo:
```php
\$ok = StoneHubCliente::verificarAssinaturaForward(\$raw, 'SEU_FORWARD_SECRET', \$_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '');
```

---

## Exemplos completos (PHP)

```php
\$hub = new StoneHubCliente('{$base}', 'SEU_API_TOKEN');

// Link de pagamento (Checkout)
$link = $hub->criarLinkPagamento([
  'codigo' => 'pedido-123',
  'valor_centavos' => 2990,
  'moeda' => 'BRL',
  'metodos_pagamento' => ['cartao'],
  'bandeiras' => ['visa','mastercard'],
  'expira_em_minutos' => 120,
  'url_sucesso' => 'https://merchant.example.invalid/sucesso',
]);

// PIX
\$pix = \$hub->criarPagamento([
  'valor_centavos' => 10990,
  'moeda' => 'BRL',
  'referencia_cliente' => 'pedido-123',
  'tipo_metodo_pagamento' => 'pix',
  'expira_em' => '2026-12-31T23:59:00-03:00',
]);

// Boleto
\$boleto = \$hub->criarBoleto([
  'valor_centavos' => 10990,
  'moeda' => 'BRL',
  'referencia_cliente' => 'pedido-123',
  'data_vencimento' => '2026-12-31T23:59:00-03:00',
  'boleto' => [ 'document_number' => 'DOCUMENTO_EXEMPLO', 'instructions' => ['Pague até o vencimento'] ],
]);

// Cartão (token)
\$cartao = \$hub->criarPagamentoCartao([
  'valor_centavos' => 25990,
  'moeda' => 'BRL',
  'referencia_cliente' => 'pedido-456',
  'token_cartao' => 'tok_abc123',
  'parcelas' => 3,
]);

// Cotar parcelas
\$cotacao = \$hub->cotarCartao(25990, '411111', [ 'max_parcelas' => 12, 'parcelas_sem_juros' => 1 ]);

// Assinatura
\$sub = \$hub->criarAssinatura([
  'id_cliente_stone' => 'cus_999',
  'id_plano_stone' => 'plan_abc',
  'valor_centavos' => 25990,
]);

// Plano (prepaid)
\$planoPre = \$hub->criarPlano([
  'nome' => 'Plano Gold',
  'moeda' => 'BRL',
  'intervalo' => 'month',
  'intervalo_qtd' => 1,
  'metodos_pagamento' => ['cartao','boleto'],
  'itens' => [ ['nome'=>'Mensalidade','quantidade'=>1,'preco_centavos'=>18990] ],
]);

// Plano (exact_day)
\$planoExato = \$hub->criarPlano([
  'nome' => 'Plano Mensal (Dia 10)',
  'moeda' => 'BRL',
  'intervalo' => 'month',
  'intervalo_qtd' => 1,
  'tipo_cobranca' => 'exact_day',
  'dia_cobranca' => 10,
  'itens' => [ ['nome'=>'Mensalidade','quantidade'=>1,'preco_centavos'=>9900] ],
]);

// Plano (parcelas)
\$planoParcelas = \$hub->criarPlano([
  'nome' => 'Plano Parcelado',
  'moeda' => 'BRL',
  'intervalo' => 'month',
  'intervalo_qtd' => 1,
  'metodos_pagamento' => ['cartao'],
  'parcelas' => [1,2,3,6,12],
  'itens' => [ ['nome'=>'Mensalidade','quantidade'=>1,'preco_centavos'=>15990] ],
]);
```

---

## Rotas da API

- POST {$base}/api/v1/plans
- POST {$base}/api/v1/payment-links
- POST {$base}/api/v1/payments
- POST {$base}/api/v1/payments/card
- POST {$base}/api/v1/payments/for-customer
- GET {$base}/api/v1/payments/{id}
- GET {$base}/api/v1/payments/stone/{sid}
- GET {$base}/api/v1/payments/by-ref/{ref}
- GET {$base}/api/v1/payments/by-ref/{ref}/history
- GET {$base}/api/v1/payments?from=&to=&customer_id=&customer_email=&status=&subscription_stone_id=&limit=&offset=
- GET {$base}/api/v1/payments/by-subscription/{sid}
- POST {$base}/api/v1/cards/tokenize
- GET {$base}/api/v1/cards/quote?valor_centavos=&bin=&max_parcelas=&parcelas_sem_juros=&taxa_juros=&valor_minimo_parcela_centavos=&interest_payer=&juros=
- GET {$base}/api/v1/cards/quote/installment?valor_centavos=&bin=&n=&max_parcelas=&parcelas_sem_juros=&taxa_juros=&valor_minimo_parcela_centavos=&interest_payer=&juros=
- GET {$base}/api/v1/cards/brand?bin=
- GET {$base}/api/v1/customers/{id}
- GET {$base}/api/v1/customers?email=&stone_customer_id=&document=
- GET {$base}/api/v1/customers/search?email=&stone_customer_id=&document=
- POST {$base}/api/v1/customers/upsert
- PUT {$base}/api/v1/customers/{id}
- POST {$base}/api/v1/subscriptions
- POST {$base}/api/v1/subscriptions/for-customer
- GET {$base}/api/v1/subscriptions/{id}
- GET {$base}/api/v1/subscriptions/stone/{sid}
- GET {$base}/api/v1/subscriptions/{id}/events
- GET {$base}/tasks/drain?limit=&budget_ms=
- GET {$base}/tasks/retry?limit=&max_attempts=
MD;

        return new Response(200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="StoneHubCliente-usage.md"',
        ], $usage);
    }

    public function downloadUsageHtml(Request $req): Response
    {
        $base = rtrim(Env::get('APP_URL', ''), '/');
        $html = <<<HTML
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>StoneHubCliente – Exemplos de Uso</title>
  <style>
    body{font:15px/1.6 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;color:#0f172a;background:#fff;margin:24px}
    h1,h2,h3{margin:18px 0 8px}
    pre{background:#0b1220;color:#e5e7eb;padding:12px;border-radius:8px;overflow:auto}
    code{background:#f3f4f6;padding:2px 4px;border-radius:4px}
    a{color:#2563eb;text-decoration:none}
    .muted{color:#475569}
    ul{margin:6px 0 12px 18px}
    .toc a{display:inline-block;margin-right:12px}
    hr{border:none;border-top:1px solid #e5e7eb;margin:18px 0}
  </style>
</head>
<body>
  <h1>StoneHubCliente – Exemplos de Uso</h1>
  <div class="muted">Classe cliente para o Stone Hub. Baixe a classe em <code>/_admin/help/client-php</code>.</div>
  <div class="toc" style="margin:12px 0 18px;">
    <a href="#pagamentos">Pagamentos</a>
    <a href="#links">Links de Pagamento</a>
    <a href="#planos">Planos</a>
    <a href="#cartoes">Cartões</a>
    <a href="#assinaturas">Assinaturas</a>
    <a href="#consultas">Consultas de Pagamentos</a>
    <a href="#clientes">Clientes</a>
    <a href="#tarefas">Tarefas HTTP</a>
    <a href="#forward">Segurança do Forward</a>
  </div>
  <div class="panel" style="margin-top:12px; border-left:4px solid #ef4444; padding-left:10px;">
    <h3>Exemplos de payload (juros)</h3>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
      <div>
        <div class="muted"><b>Vendedor assume juros</b> (3x sem juros):</div>
        <pre>{
  "client_reference_id": "pedido-001",
  "amount_cents": 10000,
  "currency": "BRL",
  "payment_method_type": "credit_card",
  "installments": 3,
  "juros": "V",
  "card": { "token": "tok_abc123" }
}</pre>
      </div>
      <div>
        <div class="muted"><b>Comprador assume juros</b> (3x; total calculado automaticamente):</div>
        <pre>{
  "client_reference_id": "pedido-002",
  "amount_cents": 10000,
  "currency": "BRL",
  "payment_method_type": "credit_card",
  "installments": 3,
  "juros": "C",
  "bin": "411111",
  "card": { "token": "tok_def456" }
}</pre>
      </div>
    </div>
  </div>

  <h2>Autenticação</h2>
  <ul>
    <li>Bearer: header <code>Authorization: Bearer <API_KEY>
    <li>Ou <code>X-Client-Id</code> + <code>X-Client-Secret</code></li>
  </ul>
  <pre>\$hub = new StoneHubCliente('{$base}', 'SEU_API_TOKEN');</pre>
  <div class="panel" style="margin-top:12px">
    <h3>Configuração de Parcelamento (por Site)</h3>
    <ul>
      <li>Admin → Sites → Editar: defina <b>Quem paga o juro?</b> (Usuário ou Vendedor) e <b>Parcelas sem juros</b>.</li>
      <li>A rota <code>GET /api/v1/cards/quote</code> refletirá essa configuração (<code>interest_payer</code> e parcelas retornadas).</li>
      <li>Opcional: forçar via query <code>interest_payer=merchant|user</code> ou <code>juros=C|V</code>.</li>
    </ul>
  </div>
  <div class="panel" style="margin-top:12px; border-left:4px solid #ef4444; padding-left:10px;">
    <h3>Atenção — Juros e Parcelamento</h3>
    <ul>
      <li>Campo opcional <code>juros</code>: <code>C</code> (comprador) ou <code>V</code> (vendedor). Se omitido, usa a configuração do site.</li>
      <li>Para <code>juros=C</code> com cálculo automático, envie <code>bin</code> ou <code>numero_cartao</code> (6 dígitos).</li>
      <li><b>Vendedor assume juros</b>: envie <code>amount_cents</code> = valor base do produto e informe <code>installments</code>.</li>
      <li><b>Comprador assume juros</b> sem <code>juros</code>: consulte <code>/api/v1/cards/quote</code>, pegue o <code>total_cents</code> da parcela N e envie em <code>amount_cents</code> com <code>installments</code> = N.</li>
    </ul>
  </div>
  <h3>Como usar a classe (exemplos rápidos)</h3>
  <pre>&lt;?php
$hub = new StoneHubCliente('{$base}', 'SEU_API_TOKEN');

// Upsert de cliente
$up = $hub->upsertCliente(['name'=>'Fulano','email'=>'customer@example.invalid','document'=>'DOCUMENTO_EXEMPLO']);

// Buscar clientes (avançado)
$lista = $hub->buscarClientesAvancado(['email'=>'customer@example.invalid']);

// Pagamento com token
$pag = $hub->criarPagamento([
  'valor_centavos'=>10990,'moeda'=>'BRL','referencia_cliente'=>'pedido-123',
  'tipo_metodo_pagamento'=>'credit_card',
  'customer'=>['name'=>'Fulano','email'=>'customer@example.invalid','document'=>'DOCUMENTO_EXEMPLO'],
  'card'=>['token'=>'tok_abc123']
]);

// Assinatura com plano local + token
$sub = $hub->criarAssinatura([
  'plano_id'=>1,'valor_centavos'=>25990,'moeda'=>'BRL',
  'customer'=>['name'=>'Ciclana','email'=>'customer@example.invalid','document'=>'DOCUMENTO_EXEMPLO'],
  'card'=>['token'=>'tok_def456']
]);
?&gt;</pre>
  <hr/>

  <h2 id="pagamentos">1) Pagamentos</h2>
  <h3>1.1 criarPagamento(array \$dados)</h3>
  <ul>
    <li>Envio: valor_centavos, moeda, referencia_cliente, tipo_metodo_pagamento (pix|credit_card|boleto), token_cartao/id_cartao (se cartão), parcelas, expira_em (date-time, se pix) ou expires_at, data_vencimento/due_at (se boleto) e boleto{bank,instructions,document_number,type}, descricao, cliente</li>
  </ul>
  <pre>{
  "payment": {
    "id": 123, "site_id": 1, "client_reference_id": "pedido-123",
    "amount_cents": 10990, "currency": "BRL", "payment_method_type": "pix",
    "status": "processing|paid|failed|authorized|...",
    "stone_payment_id": "ch_abc123" | null,
    "created_at": "2025-01-01 12:00:00", "updated_at": "2025-01-01 12:00:10"
  }
}</pre>
  <pre>\$res = \$hub->criarPagamento([
  'valor_centavos' => 10990,
  'moeda' => 'BRL',
  'referencia_cliente' => 'pedido-123',
  'tipo_metodo_pagamento' => 'pix',
  'expira_em' => '2026-12-31T23:59:00-03:00',
]);</pre>
  <pre>\$res = \$hub->criarBoleto([
  'valor_centavos' => 10990,
  'moeda' => 'BRL',
  'referencia_cliente' => 'pedido-123',
  'data_vencimento' => '2026-12-31T23:59:00-03:00',
  'boleto' => [ 'document_number' => 'DOCUMENTO_EXEMPLO', 'instructions' => ['Pague até o vencimento'] ],
]);</pre>
  <h3>1.2 criarPagamentoPix</h3>
  <div class="muted">Atalho com tipo_metodo_pagamento = pix</div>
  <h3>1.3 criarPagamentoCartao</h3>
  <div class="muted">Atalho com tipo_metodo_pagamento = credit_card</div>
  <h3>1.4 criarBoleto</h3>
  <div class="muted">Atalho com tipo_metodo_pagamento = boleto</div>
  <h3>1.4 Pagamento (payload combinado)</h3>
  <div class="muted">POST /api/v1/payments com customer + card token</div>
  <pre>{
  "client_reference_id": "pedido-123",
  "amount_cents": 10990,
  "currency": "BRL",
  "payment_method_type": "credit_card",
  "customer": { "name": "Fulano", "email": "customer@example.invalid", "document": "DOCUMENTO_EXEMPLO" },
  "card": { "token": "tok_abc123" },
  "installments": 1,
  "description": "Compra X"
}</pre>
  <h4>Resposta (sucesso)</h4>
  <pre>{ "payment": { "id": 124, "status": "processing", "stone_payment_id": "ch_123", "amount_cents": 10990, "currency": "BRL" } }</pre>
  <h4>Resposta (erro de validação)</h4>
  <pre>{ "error": "Missing required fields: amount_cents, client_reference_id, payment_method_type" }</pre>
  <h4>Resposta (erro de processamento)</h4>
  <pre>{ "error": "Payment creation failed", "message": "Stone API error (...detalhes...)" }</pre>
  <h3>1.5 Pagamento para cliente (CPF/Email/StoneID)</h3>
  <div class="muted">POST /api/v1/payments/for-customer com email/document</div>
  <pre>{
  "email": "customer@example.invalid",
  "client_reference_id": "pedido-789",
  "amount_cents": 1990,
  "currency": "BRL",
  "payment_method_type": "credit_card",
  "card": { "token": "tok_xyz" }
  }</pre>
  <h4>Erros comuns</h4>
  <pre>{ "error": "Customer not found" }</pre>
  <pre>{ "error": "Payment creation failed", "message": "insufficient_funds" }</pre>
  <pre>{ "error": "Payment creation failed", "message": "rejected_by_antifraud" }</pre>
  <hr/>

  <h2 id="links">Links de Pagamento (Checkout)</h2>
  <p>Rota: <code>POST {$base}/api/v1/payment-links</code></p>
  <ul>
    <li>Campos: <code>codigo</code>, <code>valor_centavos</code> ou <code>itens[]</code>, <code>moeda</code> (BRL), <code>expira_em_minutos</code>, <code>url_sucesso</code>, <code>metodos_pagamento</code>, <code>bandeiras</code>, <code>cliente</code> (opcional)</li>
  </ul>
  <pre>{
  "codigo": "pedido-123",
  "valor_centavos": 2990,
  "moeda": "BRL",
  "expira_em_minutos": 120,
  "url_sucesso": "https://merchant.example.invalid/sucesso",
  "metodos_pagamento": ["cartao"],
  "bandeiras": ["visa","mastercard"],
  "cliente": {"nome":"Tony","email":"customer@example.invalid","documento":"03154435026"}
}</pre>
  <h4>cURL</h4>
  <pre>curl -X POST {$base}/api/v1/payment-links \
  -H "Authorization: Bearer <API_KEY>" \
  -H "Content-Type: application/json" \
  --data '{
    "codigo":"pedido-123",
    "valor_centavos":2990,
    "moeda":"BRL",
    "expira_em_minutos":120,
    "url_sucesso":"https://merchant.example.invalid/sucesso",
    "metodos_pagamento":["cartao"],
    "bandeiras":["visa","mastercard"]
  }'</pre>
  <h4>Resposta</h4>
  <pre>{
  "id": 1,
  "link_pagamento_url": "https://checkout...",
  "id_pedido_stone": "or_abc123",
  "status": "open",
  "valor_centavos": 2990,
  "moeda": "BRL",
  "codigo": "pedido-123",
  "expira_em_minutos": 120,
  "expira_em": "2025-01-01 13:00:00"
}</pre>
  <hr/>
  
  <h2 id="planos">Planos (Recorrência)</h2>
  <p>Rota: <code>POST {$base}/api/v1/plans</code></p>
  <ul>
    <li>Campos: <code>nome</code>, <code>descricao</code> (opcional), <code>moeda</code> (BRL), <code>intervalo</code> (day|week|month|year), <code>intervalo_qtd</code> (&gt;0)</li>
    <li><code>metodos_pagamento</code> (ex.: ["cartao","boleto"]) e <code>parcelas</code> (opcional)</li>
    <li><code>itens</code>: [{ <code>nome</code>, <code>quantidade</code>, <code>preco_centavos</code>, <code>ciclos</code> (opcional) }]</li>
    <li><code>tipo_cobranca</code> (prepaid|postpaid|exact_day); se <b>exact_day</b>, informe <code>dia_cobranca</code> (1..31)</li>
    <li><code>descricao_fatura</code> (≤13), <code>dias_teste</code> (opcional), <code>codigo</code> (opcional)</li>
  </ul>
  <pre>{
  "nome": "Plano Gold",
  "descricao": "Descrição do plano",
  "moeda": "BRL",
  "intervalo": "month",
  "intervalo_qtd": 1,
  "tipo_cobranca": "prepaid",
  "metodos_pagamento": ["cartao","boleto"],
  "parcelas": [1],
  "itens": [
    { "nome": "Musculação", "quantidade": 1, "preco_centavos": 18990 },
    { "nome": "Matrícula", "quantidade": 1, "preco_centavos": 5990, "ciclos": 1 }
  ],
  "descricao_fatura": "FATURA",
  "dias_teste": 1,
  "codigo": "PLANO-GOLD-001"
}</pre>
  <h4>Resposta</h4>
  <pre>{ "plano": { "id": 1, "id_plano_stone": "plan_abc", "nome": "Plano Gold", "codigo": "PLANO-GOLD-001", "valor_centavos": 24980, "moeda": "BRL", "intervalo": "month", "intervalo_qtd": 1, "status": "active" } }</pre>
  <h3>Modelo Dia Exato</h3>
  <p>Quando <code>tipo_cobranca</code> = <code>exact_day</code>, informe <code>dia_cobranca</code> (1..31).</p>
  <pre>{
  "nome": "Plano Mensal (Dia 10)",
  "descricao": "Cobrança no dia 10",
  "moeda": "BRL",
  "intervalo": "month",
  "intervalo_qtd": 1,
  "tipo_cobranca": "exact_day",
  "dia_cobranca": 10,
  "metodos_pagamento": ["cartao"],
  "itens": [
    { "nome": "Mensalidade", "quantidade": 1, "preco_centavos": 9900 },
    { "nome": "Taxa Administração", "quantidade": 1, "preco_centavos": 500 }
  ]
}</pre>
  <h4>cURL</h4>
  <pre>curl -X POST {$base}/api/v1/plans \
  -H "Authorization: Bearer <API_KEY>" \
  -H "Content-Type: application/json" \
  --data '{
    "nome":"Plano Mensal (Dia 10)",
    "moeda":"BRL",
    "intervalo":"month",
    "intervalo_qtd":1,
    "tipo_cobranca":"exact_day",
    "dia_cobranca":10,
    "itens":[
      {"nome":"Mensalidade","quantidade":1,"preco_centavos":9900},
      {"nome":"Taxa Administração","quantidade":1,"preco_centavos":500}
    ]
  }'</pre>
  <h3>Pós-Pago</h3>
  <p>Use <code>tipo_cobranca</code> = <code>postpaid</code> para cobrar após o período.</p>
  <pre>{
  "nome": "Plano Pós-Pago",
  "descricao": "Cobra após o período",
  "moeda": "BRL",
  "intervalo": "month",
  "intervalo_qtd": 1,
  "tipo_cobranca": "postpaid",
  "metodos_pagamento": ["cartao","boleto"],
  "itens": [ { "nome": "Mensalidade", "quantidade": 1, "preco_centavos": 9900 } ]
}</pre>
  <h3>Parcelas (cartão)</h3>
  <p>Permitir parcelamento no cartão definindo <code>parcelas</code>.</p>
  <pre>{
  "nome": "Plano Parcelado",
  "moeda": "BRL",
  "intervalo": "month",
  "intervalo_qtd": 1,
  "metodos_pagamento": ["cartao"],
  "parcelas": [1,2,3,6,12],
  "itens": [ { "nome": "Mensalidade", "quantidade": 1, "preco_centavos": 15990 } ]
  }</pre>
  <h3>Trial e Descriptor (Fatura)</h3>
  <p>Use <code>dias_teste</code> para período de avaliação e <code>descricao_fatura</code> (≤13 caracteres) para o texto na fatura.</p>
  <pre>{
  "nome": "Plano com Trial",
  "descricao": "14 dias grátis",
  "moeda": "BRL",
  "intervalo": "month",
  "intervalo_qtd": 1,
  "metodos_pagamento": ["cartao"],
  "itens": [ { "nome": "Mensalidade", "quantidade": 1, "preco_centavos": 12990 } ],
  "dias_teste": 14,
  "descricao_fatura": "MINHALOJA"
}</pre>
  <hr/>

  <h2 id="cartoes">2) Cartões</h2>
  <h3>2.1 cotarCartao</h3>
  <ul><li>Envio: valor_centavos, bin; op: max_parcelas, parcelas_sem_juros, taxa_juros, valor_minimo_parcela_centavos, interest_payer=user|merchant, juros=C|V</li></ul>
  <pre>{
  "brand": "visa",
  "brand_icon": "/assets/brands/visa.svg",
  "interest_payer": "user",
  "installments": [
    {"number":1, "amount_cents":10990, "total_cents":10990, "interest_rate":0, "is_interest_free":true, "amount_formatted":"R$ 109,90", "total_formatted":"R$ 109,90", "label":"1x de R$ 109,90"}
  ]
}</pre>
  <div class="muted">No Admin do Site, configure "Quem paga o juro?" (Usuário ou Vendedor) e "Parcelas sem juros". Também é possível forçar via query <code>interest_payer=merchant</code> ou <code>juros=C|V</code>.</div>
  <h4>Comparativo: usuário vs vendedor</h4>
  <pre>// interest_payer=user
{
  "interest_payer":"user",
  "installments":[{"number":3,"label":"3x de R$ 36,99","is_interest_free":false}]
}

// interest_payer=merchant (parcelas_sem_juros=3 no Site)
{
  "interest_payer":"merchant",
  "installments":[
    {"number":1,"label":"1x de R$ 109,90","is_interest_free":true},
    {"number":2,"label":"2x de R$ 54,95","is_interest_free":true},
    {"number":3,"label":"3x de R$ 36,63","is_interest_free":true},
    {"number":4,"label":"4x de R$ 29,12","is_interest_free":false}
  ]
}</pre>
  <h3>2.2 obterBandeiraCartao</h3>
  <pre>{ "brand": "visa|mastercard|..." }</pre>
  <h3>2.3 detectarBandeiraLocal</h3>
  <pre>"visa" | "mastercard" | ... | null</pre>
  <hr/>

  <h2 id="assinaturas">3) Assinaturas</h2>
  <h3>3.1 criarAssinatura</h3>
  <ul><li>Envio: id_cliente_stone, id_plano_stone, valor_centavos (opcional), moeda</li></ul>
  <pre>{
  "subscription": {
    "id": 10, "site_id": 1, "stone_subscription_id": "sub_123",
    "plan_id": "plan_abc", "amount_cents": 25990, "currency": "BRL",
    "status": "active|paused|canceled|...", "start_date": "2025-01-01", "end_date": null,
    "created_at": "2025-01-01 12:00:00"
  }
}</pre>
  <h3>3.2 obterAssinaturaPorId / 3.3 obterAssinaturaPorIdStone</h3>
  <div class="muted">Retornam objeto subscription</div>
  <h3>3.4 listarEventosAssinatura</h3>
  <pre>{ "events": [ {"id":1, "event_type":"subscription.activated", "occurred_at":"..."} ] }</pre>
  <h3>3.5 listarPagamentosPorAssinatura</h3>
  <pre>{ "data": [ {"notification_id":123, "event_type":"subscription.activated", "stone_payment_id":null, "payment_id":null, "status":"processed", "received_at":"..."} ] }</pre>
  <h3>3.6 Assinatura (payload combinado)</h3>
  <pre>{
  "plano_id": 1,
  "currency": "BRL",
  "amount_cents": 25990,
  "customer": { "name": "Ciclana", "email": "customer@example.invalid", "document": "DOCUMENTO_EXEMPLO" },
  "card": { "token": "tok_def456" },
  "installments": 1
}</pre>
  <h4>Resposta (sucesso)</h4>
  <pre>{ "subscription": { "id": 20, "stone_subscription_id": "sub_123", "status":"active", "amount_cents":25990, "currency":"BRL" } }</pre>
  <h4>Resposta (erro)</h4>
  <pre>{ "error": "stone_plan_id (or plano_id) is required" }</pre>
  <h3>3.7 Assinatura para cliente (CPF/Email/StoneID)</h3>
  <div class="muted">POST /api/v1/subscriptions/for-customer com email/document</div>
  <pre>{
  "document": "DOCUMENTO_EXEMPLO",
  "plano_id": 1,
  "amount_cents": 25990,
  "currency": "BRL",
  "card": { "token": "tok_def456" },
  "installments": 1
  }</pre>
  <h4>Erros comuns</h4>
  <pre>{ "error": "Customer not found" }</pre>
  <pre>{ "error": "Card verification failed" }</pre>
  <hr/>

  <h2 id="consultas">4) Consultas de Pagamentos</h2>
  <ul>
    <li>obterPagamentoPorId</li>
    <li>obterPagamentoPorIdStone</li>
    <li>obterPagamentoPorReferencia</li>
    <li>listarPagamentos (filtros: from, to, customer_id, customer_email, status, subscription_stone_id, limit, offset)</li>
  </ul>
  <pre>{ "data": [ { "id":123, "amount_cents":10990, "status":"paid", ... } ] }</pre>
  <hr/>

  <h2 id="clientes">5) Clientes</h2>
  <ul>
    <li>obterClientePorId → { id, site_id, stone_customer_id, name, email, document, created_at, ... }</li>
    <li>buscarClientes (filtros: email, stone_customer_id, document) → { data: [ ... ] }</li>
  </ul>
  <hr/>

  <h2 id="tarefas">6) Tarefas HTTP</h2>
  <ul>
    <li>tarefasEsvaziarFila → { drained: N }</li>
    <li>tarefasReentregar → { retried: N }</li>
  </ul>
  <hr/>

  <h2 id="forward">7) Segurança do Forward</h2>
  <ul>
    <li>verificarAssinaturaForward(rawBody, FORWARD_SECRET, header X-Hub-Signature) → true|false</li>
  </ul>
  <pre>\$ok = StoneHubCliente::verificarAssinaturaForward(\$raw, 'SEU_FORWARD_SECRET', 
  \$_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '');</pre>
  <hr/>

  <h2 id="rotas">8) Rotas da API</h2>
  <ul>
    <li>GET {$base}/api/v1/plans</li>
    <li>POST {$base}/api/v1/plans</li>
    <li>POST {$base}/api/v1/payment-links</li>
    <li>POST {$base}/api/v1/payments</li>
    <li>POST {$base}/api/v1/payments/card</li>
    <li>POST {$base}/api/v1/payments/for-customer</li>
    <li>GET {$base}/api/v1/payments/{id}</li>
    <li>GET {$base}/api/v1/payments/stone/{sid}</li>
    <li>GET {$base}/api/v1/payments/by-ref/{ref}</li>
    <li>GET {$base}/api/v1/payments/by-ref/{ref}/history</li>
    <li>GET {$base}/api/v1/payments?from=&to=&customer_id=&customer_email=&status=&subscription_stone_id=&limit=&offset=</li>
    <li>GET {$base}/api/v1/payments/by-subscription/{sid}</li>
    <li>POST {$base}/api/v1/cards/tokenize</li>
    <li>GET {$base}/api/v1/cards/quote?valor_centavos=&bin=&max_parcelas=&parcelas_sem_juros=&taxa_juros=&valor_minimo_parcela_centavos=</li>
    <li>GET {$base}/api/v1/cards/quote/installment?valor_centavos=&bin=&n=&max_parcelas=&parcelas_sem_juros=&taxa_juros=&valor_minimo_parcela_centavos=</li>
    <li>GET {$base}/api/v1/cards/brand?bin=</li>
    <li>GET {$base}/api/v1/customers/{id}</li>
    <li>GET {$base}/api/v1/customers?email=&stone_customer_id=&document=</li>
    <li>GET {$base}/api/v1/customers/search?email=&stone_customer_id=&document=</li>
    <li>POST {$base}/api/v1/customers/upsert</li>
    <li>PUT {$base}/api/v1/customers/{id}</li>
    <li>POST {$base}/api/v1/subscriptions</li>
    <li>POST {$base}/api/v1/subscriptions/for-customer</li>
    <li>GET {$base}/api/v1/subscriptions/{id}</li>
    <li>GET {$base}/api/v1/subscriptions/stone/{sid}</li>
    <li>GET {$base}/api/v1/subscriptions/{id}/events</li>
    <li>GET {$base}/tasks/drain?limit=&budget_ms=</li>
    <li>GET {$base}/tasks/retry?limit=&max_attempts=</li>
  </ul>

  <h2>Exemplos de payloads combinados</h2>
  <h3>Pagamento (customer + card token)</h3>
  <pre>{
  "client_reference_id": "pedido-123",
  "amount_cents": 10990,
  "currency": "BRL",
  "payment_method_type": "credit_card",
  "customer": { "name": "Fulano", "email": "customer@example.invalid", "document": "DOCUMENTO_EXEMPLO" },
  "card": { "token": "tok_abc123" },
  "installments": 1,
  "description": "Compra X"
}</pre>

  <h3>Assinatura (plano local + customer + card token)</h3>
  <pre>{
  "plano_id": 1,
  "currency": "BRL",
  "amount_cents": 25990,
  "customer": { "name": "Ciclana", "email": "customer@example.invalid", "document": "DOCUMENTO_EXEMPLO" },
  "card": { "token": "tok_def456" },
  "installments": 1
}</pre>

</body></html>
HTML;
        // Extrai apenas o corpo para renderizar dentro do layout do Admin
        $body = $html;
        if (preg_match('/<body[^>]*>(.*)<\\/body>/is', $html, $m)) {
            $body = $m[1];
        }
        $out = View::render('admin/help_usage_html', [
            'prefix' => $this->prefix(),
            'contentHtml' => $body,
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $out);
    }

    public function downloadPostman(Request $req): Response
    {
        $base = $this->baseUrlForDownload($req);
        $collection = $this->postmanCollection($base);
        $json = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) $json = '{}';
        return new Response(200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="StoneHub.postman_collection.json"',
        ], $json);
    }

    private function prefix(): string
    {
        return rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');
    }

    private function baseUrlForDownload(Request $req): string
    {
        $base = rtrim(Env::get('APP_URL', ''), '/');
        if ($base !== '') return $base;
        $host = (string)($req->header('x-forwarded-host') ?? $req->header('host') ?? 'localhost');
        $proto = (string)($req->header('x-forwarded-proto') ?? '');
        if ($proto === '') {
            $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
            $proto = $https ? 'https' : 'http';
        }
        return $proto . '://' . $host;
    }

    private static function pmHeaders(bool $json, bool $auth, array $extra = []): array
    {
        $headers = [
            ['key' => 'Accept', 'value' => 'application/json'],
        ];
        if ($auth) {
            $headers[] = ['key' => 'Authorization', 'value' => 'Bearer {{API_TOKEN}}'];
            $headers[] = ['key' => 'X-Client-Id', 'value' => '{{CLIENT_ID}}', 'disabled' => true];
            $headers[] = ['key' => 'X-Client-Secret', 'value' => '{{CLIENT_SECRET}}', 'disabled' => true];
        }
        if ($json) {
            $headers[] = ['key' => 'Content-Type', 'value' => 'application/json'];
        }
        foreach ($extra as $h) {
            if (is_array($h) && isset($h['key'])) $headers[] = $h;
        }
        return $headers;
    }

    private static function pmItem(string $name, string $method, string $url, array $headers, ?array $body = null, ?string $description = null): array
    {
        $req = [
            'method' => strtoupper($method),
            'header' => $headers,
            'url' => $url,
        ];
        if ($body !== null) {
            $req['body'] = [
                'mode' => 'raw',
                'raw' => json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ];
        }
        $out = ['name' => $name, 'request' => $req];
        if ($description) $out['description'] = $description;
        return $out;
    }

    private function postmanCollection(string $base): array
    {
        $baseUrl = rtrim($base, '/');
        $api = $baseUrl;

        $vars = [
            ['key' => 'base_url', 'value' => $baseUrl],
            ['key' => 'API_TOKEN', 'value' => ''],
            ['key' => 'CLIENT_ID', 'value' => ''],
            ['key' => 'CLIENT_SECRET', 'value' => ''],
            ['key' => 'TASKS_SECRET', 'value' => ''],
            ['key' => 'payment_id', 'value' => '123'],
            ['key' => 'stone_payment_id', 'value' => 'ch_abc123'],
            ['key' => 'reference_id', 'value' => 'pedido-001'],
            ['key' => 'customer_id', 'value' => '1'],
            ['key' => 'stone_customer_id', 'value' => 'cus_123'],
            ['key' => 'subscription_id', 'value' => '1'],
            ['key' => 'stone_subscription_id', 'value' => 'sub_123'],
            ['key' => 'plan_id', 'value' => '1'],
            ['key' => 'stone_plan_id', 'value' => 'plan_123'],
            ['key' => 'bin', 'value' => '411111'],
            ['key' => 'PUBLIC_KEY', 'value' => 'pk_test_xxx'],
            ['key' => 'email', 'value' => 'customer@example.invalid'],
            ['key' => 'document', 'value' => '12345678909'],
        ];

        $hAuth = self::pmHeaders(false, true);
        $hAuthJson = self::pmHeaders(true, true);
        $hJson = self::pmHeaders(true, false);

        $items = [
            [
                'name' => 'Health & Docs',
                'item' => [
                    self::pmItem('Health check', 'GET', '{{base_url}}/', self::pmHeaders(false, false)),
                    self::pmItem('OpenAPI JSON', 'GET', '{{base_url}}/api/openapi.json', self::pmHeaders(false, false)),
                    self::pmItem('Swagger UI', 'GET', '{{base_url}}/api/docs', self::pmHeaders(false, false)),
                ],
            ],
            [
                'name' => 'Pagamentos',
                'item' => [
                    self::pmItem('Criar pagamento PIX', 'POST', '{{base_url}}/api/v1/payments', $hAuthJson, [
                        'valor_centavos' => 18990,
                        'moeda' => 'BRL',
                        'referencia_cliente' => 'PED-12345',
                        'tipo_metodo_pagamento' => 'pix',
                        'expira_em' => '2026-12-31T23:59:00-03:00',
                        'cliente' => [
                            'nome' => 'Maria Silva',
                            'email' => 'customer@example.invalid',
                            'documento' => '12345678909',
                            'phones' => [
                                'mobile_phone' => [ 'country_code' => '55', 'area_code' => '11', 'number' => 'NUMERO_EXEMPLO' ]
                            ]
                        ],
                    ]),
                    self::pmItem('Criar pagamento Cartão (juros=C)', 'POST', '{{base_url}}/api/v1/payments', $hAuthJson, [
                        'valor_centavos' => 10000,
                        'moeda' => 'BRL',
                        'referencia_cliente' => 'PED-987',
                        'tipo_metodo_pagamento' => 'credit_card',
                        'parcelas' => 3,
                        'juros' => 'C',
                        'bin' => '411111',
                        'token_cartao' => 'tok_abc123',
                        'cliente' => [ 'email' => 'customer@example.invalid' ],
                    ]),
                    self::pmItem('Criar pagamento Cartão (dados completos)', 'POST', '{{base_url}}/api/v1/payments/card', $hAuthJson, [
                        'valor_centavos' => 38700,
                        'moeda' => 'BRL',
                        'referencia_cliente' => 'FAS-PARC-1109322-1',
                        'tipo_metodo_pagamento' => 'credit_card',
                        'parcelas' => 1,
                        'juros' => 'V',
                        'cliente' => [
                            'nome' => 'Cliente Exemplo',
                            'email' => 'customer@example.invalid',
                            'documento' => '11111111111',
                            'phones' => [ 'mobile_phone' => [ 'country_code' => '55', 'area_code' => '35', 'number' => 'NUMERO_EXEMPLO' ] ],
                        ],
                        'card' => [
                            'number' => 'NUMERO_EXEMPLO',
                            'holder_name' => 'Cliente Exemplo',
                            'exp_month' => 6,
                            'exp_year' => 26,
                            'cvv' => 'CVV_EXEMPLO',
                        ],
                    ]),
                    self::pmItem('Criar pagamento Boleto', 'POST', '{{base_url}}/api/v1/payments', $hAuthJson, [
                        'valor_centavos' => 18990,
                        'moeda' => 'BRL',
                        'referencia_cliente' => 'PED-456',
                        'tipo_metodo_pagamento' => 'boleto',
                        'data_vencimento' => '2026-12-31T23:59:00-03:00',
                        'boleto' => [
                            'document_number' => 'DOCUMENTO_EXEMPLO',
                            'instructions' => ['Pague até o vencimento'],
                        ],
                        'cliente' => [
                            'nome' => 'Maria Silva',
                            'email' => 'customer@example.invalid',
                            'documento' => '12345678909',
                        ],
                    ]),
                    self::pmItem('Criar pagamento (para cliente existente)', 'POST', '{{base_url}}/api/v1/payments/for-customer', $hAuthJson, [
                        'valor_centavos' => 10000,
                        'referencia_cliente' => 'PED-100',
                        'tipo_metodo_pagamento' => 'credit_card',
                        'parcelas' => 2,
                        'juros' => 'V',
                        'token_cartao' => 'tok_abc123',
                        'stone_customer_id' => '{{stone_customer_id}}',
                    ]),
                ],
            ],
            [
                'name' => 'Consultas de Pagamentos',
                'item' => [
                    self::pmItem('Listar pagamentos', 'GET', '{{base_url}}/api/v1/payments?from=2026-01-01T00:00:00-03:00&to=2026-01-31T23:59:59-03:00&limit=50&offset=0', $hAuth),
                    self::pmItem('Pagamento por ID', 'GET', '{{base_url}}/api/v1/payments/{{payment_id}}', $hAuth),
                    self::pmItem('Pagamento por ID Stone', 'GET', '{{base_url}}/api/v1/payments/stone/{{stone_payment_id}}', $hAuth),
                    self::pmItem('Pagamento por referência', 'GET', '{{base_url}}/api/v1/payments/by-ref/{{reference_id}}', $hAuth),
                    self::pmItem('Histórico por referência', 'GET', '{{base_url}}/api/v1/payments/by-ref/{{reference_id}}/history', $hAuth),
                    self::pmItem('Pagamentos por assinatura', 'GET', '{{base_url}}/api/v1/payments/by-subscription/{{stone_subscription_id}}', $hAuth),
                ],
            ],
            [
                'name' => 'Boletos',
                'item' => [
                    self::pmItem('Criar boleto', 'POST', '{{base_url}}/api/v1/boletos', $hAuthJson, [
                        'valor_centavos' => 18990,
                        'referencia_cliente' => 'BOL-123',
                        'tipo_metodo_pagamento' => 'boleto',
                        'data_vencimento' => '2026-12-31T23:59:00-03:00',
                        'boleto' => [
                            'document_number' => 'DOCUMENTO_EXEMPLO',
                            'instructions' => ['Pague até o vencimento'],
                        ],
                        'cliente' => [
                            'nome' => 'Maria Silva',
                            'email' => 'customer@example.invalid',
                            'documento' => '12345678909',
                        ],
                    ]),
                    self::pmItem('Listar boletos locais', 'GET', '{{base_url}}/api/v1/boletos?limit=50&offset=0', $hAuth),
                    self::pmItem('Boleto por ID local', 'GET', '{{base_url}}/api/v1/boletos/{{payment_id}}', $hAuth),
                    self::pmItem('Boleto por ID Stone', 'GET', '{{base_url}}/api/v1/boletos/stone/{{stone_payment_id}}', $hAuth),
                    self::pmItem('Boletos direto na Stone', 'GET', '{{base_url}}/api/v1/boletos/remote?status=&page=1&size=20', $hAuth),
                ],
            ],
            [
                'name' => 'Clientes',
                'item' => [
                    self::pmItem('Upsert de cliente', 'POST', '{{base_url}}/api/v1/customers/upsert', $hAuthJson, [
                        'nome' => 'Pedro Alves',
                        'email' => 'customer@example.invalid',
                        'documento' => '11122233344',
                        'tipo' => 'individual',
                        'phones' => [ 'mobile_phone' => [ 'country_code' => '55', 'area_code' => '31', 'number' => 'NUMERO_EXEMPLO' ] ],
                        'address' => [ 'zip_code' => '01001000', 'country' => 'BR', 'state' => 'SP', 'city' => 'São Paulo', 'line_1' => 'Praça da Sé, 1' ],
                    ]),
                    self::pmItem('Atualizar cliente (lookup)', 'POST', '{{base_url}}/api/v1/customers/update', $hAuthJson, [
                        'stone_customer_id' => '{{stone_customer_id}}',
                        'nome' => 'Pedro Alves',
                        'email' => 'customer@example.invalid',
                        'documento' => '11122233344',
                    ]),
                    self::pmItem('Atualizar cliente por ID', 'PUT', '{{base_url}}/api/v1/customers/{{customer_id}}', $hAuthJson, [
                        'nome' => 'Pedro Alves',
                        'email' => 'customer@example.invalid',
                        'documento' => '11122233344',
                    ]),
                    self::pmItem('Buscar clientes (search)', 'GET', '{{base_url}}/api/v1/customers/search?email={{email}}&document={{document}}&stone_customer_id={{stone_customer_id}}', $hAuth),
                    self::pmItem('Listar clientes (filtros)', 'GET', '{{base_url}}/api/v1/customers?email={{email}}&document={{document}}&stone_customer_id={{stone_customer_id}}', $hAuth),
                    self::pmItem('Cliente por ID', 'GET', '{{base_url}}/api/v1/customers/{{customer_id}}', $hAuth),
                ],
            ],
            [
                'name' => 'Assinaturas',
                'item' => [
                    self::pmItem('Criar assinatura', 'POST', '{{base_url}}/api/v1/subscriptions', $hAuthJson, [
                        'plano_id' => '{{plan_id}}',
                        'customer' => [
                            'name' => 'Ana Lima',
                            'email' => 'customer@example.invalid',
                            'document' => 'DOCUMENTO_EXEMPLO',
                            'phones' => [ 'mobile_phone' => [ 'country_code' => '55', 'area_code' => '21', 'number' => 'NUMERO_EXEMPLO' ] ],
                        ],
                        'card' => [ 'token' => 'card_tok_abcd' ],
                    ]),
                    self::pmItem('Criar assinatura (para cliente existente)', 'POST', '{{base_url}}/api/v1/subscriptions/for-customer', $hAuthJson, [
                        'plano_id' => '{{plan_id}}',
                        'stone_customer_id' => '{{stone_customer_id}}',
                        'card' => [ 'token' => 'card_tok_abcd' ],
                    ]),
                    self::pmItem('Assinatura por ID local', 'GET', '{{base_url}}/api/v1/subscriptions/{{subscription_id}}', $hAuth),
                    self::pmItem('Assinatura por ID Stone', 'GET', '{{base_url}}/api/v1/subscriptions/stone/{{stone_subscription_id}}', $hAuth),
                    self::pmItem('Eventos da assinatura', 'GET', '{{base_url}}/api/v1/subscriptions/{{subscription_id}}/events', $hAuth),
                    self::pmItem('Pagamentos por assinatura', 'GET', '{{base_url}}/api/v1/payments/by-subscription/{{stone_subscription_id}}', $hAuth),
                ],
            ],
            [
                'name' => 'Planos',
                'item' => [
                    self::pmItem('Criar plano', 'POST', '{{base_url}}/api/v1/plans', $hAuthJson, [
                        'nome' => 'Plano Gold',
                        'descricao' => 'Descrição do plano',
                        'moeda' => 'BRL',
                        'intervalo' => 'month',
                        'intervalo_qtd' => 1,
                        'metodos_pagamento' => ['cartao','boleto'],
                        'parcelas' => [1,2,3,6,12],
                        'itens' => [
                            [ 'nome' => 'Mensalidade', 'quantidade' => 1, 'preco_centavos' => 18990 ],
                        ],
                    ]),
                    self::pmItem('Listar planos', 'GET', '{{base_url}}/api/v1/plans', $hAuth),
                ],
            ],
            [
                'name' => 'Links de Pagamento',
                'item' => [
                    self::pmItem('Criar link de pagamento', 'POST', '{{base_url}}/api/v1/payment-links', $hAuthJson, [
                        'codigo' => 'LINK-123',
                        'valor_centavos' => 25990,
                        'moeda' => 'BRL',
                        'metodos_pagamento' => ['cartao','pix','boleto'],
                        'bandeiras' => ['visa','mastercard'],
                        'expira_em_minutos' => 120,
                        'url_sucesso' => 'https://merchant.example.invalid/sucesso',
                        'cliente' => [ 'nome' => 'João', 'email' => 'customer@example.invalid', 'documento' => '12345678909' ],
                    ]),
                ],
            ],
            [
                'name' => 'Cartões',
                'item' => [
                    self::pmItem('Tokenizar cartão (público)', 'POST', '{{base_url}}/api/v1/cards/tokenize', $hJson, [
                        'public_key' => '{{PUBLIC_KEY}}',
                        'card' => [
                            'number' => 'NUMERO_EXEMPLO',
                            'holder_name' => 'Maria Silva',
                            'exp_month' => 12,
                            'exp_year' => 30,
                            'cvv' => 'CVV_EXEMPLO',
                        ],
                    ]),
                    self::pmItem('Cotar parcelas', 'GET', '{{base_url}}/api/v1/cards/quote?amount_cents=10000&bin={{bin}}&max_installments=12&juros=C', $hAuth),
                    self::pmItem('Cotar parcela específica', 'GET', '{{base_url}}/api/v1/cards/quote/installment?amount_cents=10000&bin={{bin}}&n=3&juros=V', $hAuth),
                    self::pmItem('Detectar bandeira', 'GET', '{{base_url}}/api/v1/cards/brand?bin={{bin}}', $hAuth),
                ],
            ],
            [
                'name' => 'Webhooks',
                'item' => [
                    self::pmItem('Webhook Stone/Pagar.me', 'POST', '{{base_url}}/webhooks/stone', $hJson, [
                        'type' => 'charge.paid',
                        'data' => [
                            'id' => 'ch_abc123',
                            'status' => 'paid',
                            'amount' => 18990,
                            'customer' => ['id' => 'cus_999', 'name' => 'Cliente Exemplo'],
                        ],
                        'occurred_at' => '2026-01-01T12:00:00Z',
                    ]),
                ],
            ],
            [
                'name' => 'Tarefas HTTP',
                'item' => [
                    self::pmItem('Drain webhooks', 'GET', '{{base_url}}/tasks/drain?limit=100&budget_ms=400', self::pmHeaders(false, false, [
                        ['key' => 'X-Tasks-Secret', 'value' => '{{TASKS_SECRET}}']
                    ])),
                    self::pmItem('Retry forwards', 'GET', '{{base_url}}/tasks/retry?limit=100&max_attempts=5', self::pmHeaders(false, false, [
                        ['key' => 'X-Tasks-Secret', 'value' => '{{TASKS_SECRET}}']
                    ])),
                    self::pmItem('Cleanup logs', 'GET', '{{base_url}}/tasks/cleanup', self::pmHeaders(false, false, [
                        ['key' => 'X-Tasks-Secret', 'value' => '{{TASKS_SECRET}}']
                    ])),
                ],
            ],
        ];

        return [
            'info' => [
                'name' => 'Stone Hub API (dinâmica)',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
                'description' => 'Collection gerada automaticamente com base no domínio atual.',
            ],
            'variable' => $vars,
            'item' => $items,
        ];
    }

    private function clientPhpCode(): string
    {
        return <<<'PHP'
<?php

/**
 * Cliente PHP para o Stone Hub
 * - Autenticação: Bearer (API_TOKEN) ou X-Client-Id/X-Client-Secret
 * - Todos métodos retornam arrays (JSON decodificado)
 *
 * IMPORTANTE — JUROS E PARCELAMENTO
 * - Campo opcional "juros" no pagamento por cartão:
 *   - "C" = comprador assume (ajusta amount_cents automaticamente via cotação).
 *   - "V" = vendedor assume (mantém amount_cents base).
 *   - Se omitido, usa a configuração do site.
 * - Você também pode definir quem arca com o juro pelo valor enviado em amount_cents:
 *   - Vendedor assume juros: enviar amount_cents = valor base do produto e informar parcelas.
 *   - Comprador assume juros: usar o total_cents da cotação para N parcelas em amount_cents e informar parcelas.
 * - Para obter o total com juros por parcela, use cotarCartao() ou cotarParcela().
 * - Para "C" (ou default=usuário), informe bin/numero_cartao (6 dígitos) para cotação.
 */
class StoneHubCliente
{
    private string $urlBase;
    private ?string $tokenApi;
    private ?string $idCliente;
    private ?string $segredoCliente;
    private ?string $segredoTarefas = null;
    private int $tempoLimite;

    /**
     * @param string $urlBase URL base do Hub (ex.: https://stone.example.invalid)
     * @param string|null $tokenApi API_TOKEN do site (opcional se usar ClientId/Secret)
     * @param string|null $idCliente X-Client-Id (opcional)
     * @param string|null $segredoCliente X-Client-Secret (opcional)
     * @param int $tempoLimiteSegundos Timeout de requisição em segundos
     */
    public function __construct(string $urlBase, ?string $tokenApi = null, ?string $idCliente = null, ?string $segredoCliente = null, int $tempoLimiteSegundos = 10)
    {
        $this->urlBase = rtrim($urlBase, '/');
        $this->tokenApi = $tokenApi;
        $this->idCliente = $idCliente;
        $this->segredoCliente = $segredoCliente;
        $this->tempoLimite = $tempoLimiteSegundos;
    }

    /** Define o segredo para endpoints de tarefas HTTP (/tasks) */
    public function comSegredoTarefas(string $segredo): self
    {
        $this->segredoTarefas = $segredo;
        return $this;
    }

    /**
     * Criar pagamento (genérico)
     * Enviar: valor_centavos (int), moeda (string), referencia_cliente (string),
     *  tipo_metodo_pagamento (pix|credit_card|boleto), token_cartao/id_cartao (se cartão), parcelas (int),
     *  juros (C|V, opcional; somente cartão),
     *  expira_em (date-time, se pix) ou expires_at, data_vencimento/due_at (se boleto) e boleto{bank,instructions,document_number,type}
     * Opcional (payload combinado):
     *  - customer: { name, email, document, address, phones, ... }
     *  - card: { token } (preferencial) ou dados completos (PCI)
     * Retorna: { payment: {...} }
     */
    public function criarPagamento(array $dados): array
    {
        // Aplica regra de juros para cartão (C/V/default) quando possível
        $dados = $this->aplicarJurosCartao($dados);
        // Normaliza payload de cartão (garante card.billing_address dentro de card)
        $dados = self::normalizeCardPayload($dados);
        return $this->requisicao('POST', '/api/v1/payments', null, $dados);
    }

    /** Criar pagamento PIX (atalho) */
    public function criarPagamentoPix(array $dados): array
    {
        $dados['tipo_metodo_pagamento'] = 'pix';
        return $this->criarPagamento($dados);
    }

    /** Criar pagamento Cartão (atalho) */
    public function criarPagamentoCartao(array $dados): array
    {
        $dados['tipo_metodo_pagamento'] = 'credit_card';
        return $this->criarPagamento($dados);
    }

    /** Criar pagamento Cartão com dados completos (tokenização no Hub) */
    public function criarPagamentoCartaoComDados(array $dados): array
    {
        $dados['tipo_metodo_pagamento'] = 'credit_card';
        return $this->requisicao('POST', '/api/v1/payments/card', null, $dados);
    }

    /** Criar boleto (atalho) */
    public function criarBoleto(array $dados): array
    {
        $dados['tipo_metodo_pagamento'] = 'boleto';
        return $this->criarPagamento($dados);
    }

    /** Consultar boleto por ID local */
    public function obterBoleto(int $id): array
    {
        return $this->requisicao('GET', '/api/v1/boletos/' . $id);
    }

    /** Consultar boleto por ID (charge) na Stone */
    public function obterBoletoPorIdStone(string $sid): array
    {
        return $this->requisicao('GET', '/api/v1/boletos/stone/' . rawurlencode($sid));
    }

    /** Listar boletos locais */
    public function listarBoletos(array $filtros = []): array
    {
        return $this->requisicao('GET', '/api/v1/boletos', $filtros);
    }

    /** Listar boletos na Stone */
    public function listarBoletosStone(array $filtros = []): array
    {
        return $this->requisicao('GET', '/api/v1/boletos/remote', $filtros);
    }

    /**
     * Criar assinatura (recorrência)
     * Enviar: id_cliente_stone (string), id_plano_stone (string), valor_centavos (int, opcional), moeda (string)
     * Alternativamente, usar payload combinado:
     *  - plano_id (local) ou stone_plan_id
     *  - customer: { ... } (se não enviar id_cliente_stone)
     *  - card: { token } (preferencial) ou dados completos (PCI)
     * Retorna: { subscription: {...} }
     */
    public function criarAssinatura(array $dados): array
    {
        return $this->requisicao('POST', '/api/v1/subscriptions', null, $dados);
    }

    /** Upsert de cliente (cria/atualiza no Hub e Stone) */
    public function upsertCliente(array $dados): array
    {
        return $this->requisicao('POST', '/api/v1/customers/upsert', null, $dados);
    }

    /** Atualizar cliente por stone_customer_id/email/document (sem ID local) */
    public function atualizarCliente(array $dados): array
    {
        return $this->requisicao('POST', '/api/v1/customers/update', null, $dados);
    }

    /** Atualizar cliente local por ID (propaga para Stone se houver stone_customer_id) */
    public function atualizarClientePorId(int $id, array $dados): array
    {
        return $this->requisicao('PUT', '/api/v1/customers/' . $id, null, $dados);
    }

    /** Criar pagamento a partir de CPF/Email/stone_customer_id */
    public function criarPagamentoParaCliente(array $dados): array
    {
        return $this->requisicao('POST', '/api/v1/payments/for-customer', null, $dados);
    }

    /** Criar assinatura a partir de CPF/Email/stone_customer_id */
    public function criarAssinaturaParaCliente(array $dados): array
    {
        return $this->requisicao('POST', '/api/v1/subscriptions/for-customer', null, $dados);
    }

    /**
     * Criar plano de assinatura (recorrência)
     * Exemplo simples (sem itens; usa valor_centavos → monta item padrão):
     *  $hub->criarPlano([
     *    'nome' => 'Plano Gold', 'descricao' => 'Descrição', 'moeda' => 'BRL',
     *    'intervalo' => 'month', 'intervalo_qtd' => 1,
     *    'valor_centavos' => 18990,
     *  ]);
     * Exemplo (prepaid com itens explícitos):
     *  $hub->criarPlano([
     *    'nome' => 'Plano Gold', 'moeda' => 'BRL', 'intervalo' => 'month', 'intervalo_qtd' => 1,
     *    'metodos_pagamento' => ['cartao','boleto'],
     *    'itens' => [ ['nome'=>'Mensalidade','quantidade'=>1,'preco_centavos'=>18990] ],
     *  ]);
     * Exemplo (exact_day):
     *  $hub->criarPlano([
     *    'nome' => 'Plano Mensal (Dia 10)', 'moeda' => 'BRL', 'intervalo' => 'month', 'intervalo_qtd' => 1,
     *    'tipo_cobranca' => 'exact_day', 'dia_cobranca' => 10,
     *    'itens' => [ ['nome'=>'Mensalidade','quantidade'=>1,'preco_centavos'=>9900] ],
     *  ]);
     * Exemplo (parcelas no cartão):
     *  $hub->criarPlano([
     *    'nome' => 'Plano Parcelado', 'moeda' => 'BRL', 'intervalo' => 'month', 'intervalo_qtd' => 1,
     *    'metodos_pagamento' => ['cartao'], 'parcelas' => [1,2,3,6,12],
     *    'itens' => [ ['nome'=>'Mensalidade','quantidade'=>1,'preco_centavos'=>15990] ],
     *  ]);
     */
    public function criarPlano(array $dados): array
    {
        // Compat: permitir omitir itens e enviar valor_centavos
        if (!isset($dados['itens']) && !isset($dados['items'])) {
            $valor = (int)($dados['valor_centavos'] ?? ($dados['amount_cents'] ?? 0));
            $nome = (string)($dados['nome'] ?? ($dados['name'] ?? ''));
            if ($valor > 0 && $nome !== '') {
                $dados['itens'] = [ [ 'nome' => $nome, 'quantidade' => 1, 'preco_centavos' => $valor ] ];
            }
        }
        return $this->requisicao('POST', '/api/v1/plans', null, $dados);
    }

    /** Listar planos do site autenticado (GET /api/v1/plans) */
    public function listarPlanos(array $filtros = []): array
    {
        return $this->requisicao('GET', '/api/v1/plans', $filtros);
    }

    /**
     * Criar link de pagamento (checkout)
     * Exemplo:
     *  $hub->criarLinkPagamento([
     *    'codigo' => 'pedido-123', 'valor_centavos' => 2990, 'moeda' => 'BRL',
     *    'metodos_pagamento' => ['cartao'], 'bandeiras' => ['visa','mastercard'],
     *    'expira_em_minutos' => 120, 'url_sucesso' => 'https://merchant.example.invalid/sucesso',
     *  ]);
     * Enviar campos em PT: codigo, valor_centavos (ou itens[]), moeda (BRL), expira_em_minutos, url_sucesso,
     *  metodos_pagamento ["cartao","pix","boleto"], bandeiras ["visa","mastercard",...], cliente {nome,email,documento,...}
     * Retorna: { id, link_pagamento_url, id_pedido_stone, status, valor_centavos, moeda, codigo, expira_em_minutos, expira_em }
     */
    public function criarLinkPagamento(array $dados): array
    {
        return $this->requisicao('POST', '/api/v1/payment-links', null, $dados);
    }

    /**
     * Cotar parcelas e bandeira
     * Enviar: valor_centavos (int), bin (string com 6 dígitos),
     *  max_parcelas (int), parcelas_sem_juros (int), taxa_juros (% ao mês), valor_minimo_parcela_centavos (int), interest_payer (user|merchant), juros (C|V)
     * Retorna: { brand, brand_icon, interest_payer, installments: [{ number, amount_cents, total_cents, interest_rate, is_interest_free, amount_formatted, total_formatted, label }] }
     */
    public function cotarCartao(int $valorCentavos, string $bin, array $opcoes = []): array
    {
        $q = array_merge(['valor_centavos' => $valorCentavos, 'bin' => $bin], $opcoes);
        return $this->requisicao('GET', '/api/v1/cards/quote', $q);
    }

    /**
     * Cotar apenas uma parcela específica (N)
     * Enviar: valor_centavos (int), bin (string), n (int),
     *  opções: max_parcelas, parcelas_sem_juros, taxa_juros, valor_minimo_parcela_centavos, interest_payer (user|merchant), juros (C|V)
     * Retorna: { status, brand, brand_icon, interest_payer, installment: {...} }
     */
    public function cotarParcela(int $valorCentavos, string $bin, int $n, array $opcoes = []): array
    {
        $q = array_merge(['valor_centavos' => $valorCentavos, 'bin' => $bin, 'n' => $n], $opcoes);
        return $this->requisicao('GET', '/api/v1/cards/quote/installment', $q);
    }

    /** Obter apenas a bandeira do cartão */
    public function obterBandeiraCartao(string $bin): array
    {
        return $this->requisicao('GET', '/api/v1/cards/brand', ['bin' => $bin]);
    }

    /**
     * Tokenizar cartão (público)
     * Enviar: public_key (app_id) e card{number, holder_name, exp_month, exp_year, cvv}
     * Observação: autenticação é opcional neste endpoint.
     */
    public function tokenizarCartao(array $dados): array
    {
        return $this->requisicao('POST', '/api/v1/cards/tokenize', null, $dados);
    }

    /** Consultar pagamento por ID */
    public function obterPagamentoPorId(int $id): array
    {
        return $this->requisicao('GET', '/api/v1/payments/' . $id);
    }

    /** Consultar pagamento por ID da Stone */
    public function obterPagamentoPorIdStone(string $idStone): array
    {
        return $this->requisicao('GET', '/api/v1/payments/stone/' . rawurlencode($idStone));
    }

    /** Consultar pagamento por referência do cliente */
    public function obterPagamentoPorReferencia(string $referenciaCliente): array
    {
        return $this->requisicao('GET', '/api/v1/payments/by-ref/' . rawurlencode($referenciaCliente));
    }

    /** Listar histórico de pagamentos por referência do cliente */
    public function listarHistoricoPagamentosPorReferencia(string $referenciaCliente, array $filtros = []): array
    {
        return $this->requisicao('GET', '/api/v1/payments/by-ref/' . rawurlencode($referenciaCliente) . '/history', $filtros);
    }

    /** Listar pagamentos com filtros (from, to, customer_id, customer_email, status, subscription_stone_id, limit, offset) */
    public function listarPagamentos(array $filtros = []): array
    {
        return $this->requisicao('GET', '/api/v1/payments', $filtros);
    }

    /** Pagamentos/eventos por assinatura */
    public function listarPagamentosPorAssinatura(string $idAssinaturaStone): array
    {
        return $this->requisicao('GET', '/api/v1/payments/by-subscription/' . rawurlencode($idAssinaturaStone));
    }

    /** Consultar cliente por ID */
    public function obterClientePorId(int $id): array
    {
        return $this->requisicao('GET', '/api/v1/customers/' . $id);
    }

    /** Buscar clientes por email/stone_customer_id */
    public function buscarClientes(array $filtros = []): array
    {
        return $this->requisicao('GET', '/api/v1/customers', $filtros);
    }

    /** Buscar clientes avançado (email/document/stone_customer_id) */
    public function buscarClientesAvancado(array $filtros = []): array
    {
        return $this->requisicao('GET', '/api/v1/customers/search', $filtros);
    }

    /** Buscar clientes por e-mail */
    public function buscarClientesPorEmail(string $email): array
    {
        return $this->buscarClientesAvancado(['email' => $email]);
    }

    /** Buscar clientes por documento (CPF) */
    public function buscarClientesPorDocumento(string $documento): array
    {
        return $this->buscarClientesAvancado(['document' => $documento]);
    }

    /** Buscar clientes por Stone Customer ID */
    public function buscarClientesPorStoneId(string $stoneCustomerId): array
    {
        return $this->buscarClientesAvancado(['stone_customer_id' => $stoneCustomerId]);
    }

    /** Consultar assinatura por ID */
    public function obterAssinaturaPorId(int $id): array
    {
        return $this->requisicao('GET', '/api/v1/subscriptions/' . $id);
    }

    /** Consultar assinatura por ID da Stone */
    public function obterAssinaturaPorIdStone(string $sid): array
    {
        return $this->requisicao('GET', '/api/v1/subscriptions/stone/' . rawurlencode($sid));
    }

    /** Listar eventos da assinatura */
    public function listarEventosAssinatura(int $idAssinatura): array
    {
        return $this->requisicao('GET', '/api/v1/subscriptions/' . $idAssinatura . '/events');
    }

    /** Esvaziar fila (HTTP) */
    public function tarefasEsvaziarFila(int $limite = 100, int $orcamentoMs = 400): array
    {
        $h = $this->segredoTarefas ? ['X-Tasks-Secret' => $this->segredoTarefas] : [];
        return $this->requisicao('GET', '/tasks/drain', ['limit' => $limite, 'budget_ms' => $orcamentoMs], null, $h);
    }

    /** Retentar forwards (HTTP) */
    public function tarefasReentregar(int $limite = 100, int $maxTentativas = 5): array
    {
        $h = $this->segredoTarefas ? ['X-Tasks-Secret' => $this->segredoTarefas] : [];
        return $this->requisicao('GET', '/tasks/retry', ['limit' => $limite, 'max_attempts' => $maxTentativas], null, $h);
    }

    /** Validar assinatura HMAC do forward (X-Hub-Signature) */
    public static function verificarAssinaturaForward(string $corpo, string $segredoForward, string $cabecalhoAssinatura): bool
    {
        $prefix = 'sha256=';
        if (stripos($cabecalhoAssinatura, $prefix) !== 0) return false;
        $assinatura = substr($cabecalhoAssinatura, strlen($prefix));
        $calculada = hash_hmac('sha256', $corpo, $segredoForward);
        return hash_equals($calculada, $assinatura);
    }

    /** Detectar bandeira localmente pelo BIN */
    public static function detectarBandeiraLocal(string $bin): ?string
    {
        $bin = preg_replace('/\D/', '', $bin);
        if ($bin === '' || strlen($bin) < 4) return null;
        $patterns = [
            'visa' => '/^4\d{3}/', 'mastercard' => '/^(5[1-5]\d{2}|2[2-7]\d{2})/', 'amex' => '/^3[47]\d{2}/',
            'diners' => '/^3(0[0-5]|[68]\d)\d{1}/', 'discover' => '/^(6011|65\d{2}|64[4-9]\d)/',
            'jcb' => '/^(352[89]|35[3-8]\d)/', 'hipercard' => '/^(606282|3841(0|4|6))/',
            'elo' => '/^(4011(78|79)|431274|438935|451416|457393|504175|627780|636297|636368|650\d{2}|65165\d|65500\d)/', 'aura' => '/^50\d{2}/',
        ];
        foreach ($patterns as $brand => $regex) {
            if (preg_match($regex, $bin)) return $brand;
        }
        return null;
    }

    /** Requisição interna */
    private function requisicao(string $metodo, string $caminho, ?array $query = null, ?array $json = null, array $headersExtras = []): array
    {
        $url = $this->urlBase . $caminho;
        if ($query) $url .= '?' . http_build_query($query);
        $headers = ['Accept: application/json'];
        if ($this->tokenApi) {
            $headers[] = 'Authorization: Bearer ' . $this->tokenApi;
        } elseif ($this->idCliente && $this->segredoCliente) {
            $headers[] = 'X-Client-Id: ' . $this->idCliente;
            $headers[] = 'X-Client-Secret: ' . $this->segredoCliente;
        }
        foreach ($headersExtras as $k => $v) {
            $headers[] = $k . ': ' . $v;
        }
        $ch = curl_init($url);
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => $this->tempoLimite, CURLOPT_CONNECTTIMEOUT => 5];
        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        curl_close($ch);
        if ($body === false || $err) {
            return [
                'status' => 'error',
                'message' => 'HTTP error: ' . $err,
                'status_code' => 'http_error',
                'http_status' => 0,
            ];
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return [
                'status' => 'error',
                'message' => 'Invalid JSON (HTTP ' . $status . ')',
                'status_code' => 'invalid_json',
                'http_status' => $status,
            ];
        }
        if ($status >= 400) {
            $msg = self::montarMensagemErroApi($data, $status);
            if (!isset($data['status'])) $data['status'] = 'error';
            if (!isset($data['message'])) $data['message'] = $msg;
            if (!isset($data['status_code'])) $data['status_code'] = (string)$status;
            if (!isset($data['http_status'])) $data['http_status'] = $status;
            return $data;
        }
        return $data;
    }

    /**
     * Normaliza estrutura de cartão para ficar no padrão Pagar.me v5:
     * - card.token a partir de token_cartao
     * - card.holder_name a partir de nome_portador
     * - card.billing_address (line_1, line_2, zip_code, city, state, country)
     *   a partir de { billing_address | endereco_cobranca | card.endereco_cobranca | customer.billing_address }
     */
    private static function normalizeCardPayload(array $dados): array
    {
        $tipo = $dados['tipo_metodo_pagamento'] ?? ($dados['payment_method_type'] ?? '');
        if (strtolower((string)$tipo) !== 'credit_card') return $dados;

        if (!isset($dados['card']) || !is_array($dados['card'])) $dados['card'] = [];
        if (!isset($dados['card']['token']) && isset($dados['token_cartao'])) $dados['card']['token'] = $dados['token_cartao'];
        if (!isset($dados['card']['holder_name']) && isset($dados['nome_portador'])) $dados['card']['holder_name'] = $dados['nome_portador'];

        $ba = null;
        if (isset($dados['billing_address'])) $ba = $dados['billing_address'];
        elseif (isset($dados['endereco_cobranca'])) $ba = $dados['endereco_cobranca'];
        elseif (isset($dados['card']['endereco_cobranca'])) $ba = $dados['card']['endereco_cobranca'];
        elseif (isset($dados['customer']['billing_address'])) $ba = $dados['customer']['billing_address'];
        if (is_array($ba)) {
            $dados['card']['billing_address'] = self::normalizeAddressKeys($ba);
            unset($dados['billing_address'], $dados['endereco_cobranca'], $dados['card']['endereco_cobranca'], $dados['customer']['billing_address']);
        }
        return $dados;
    }

    /** Converte chaves PT → EN e normaliza CEP/UF/país */
    private static function normalizeAddressKeys(array $addr): array
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
        if (isset($addr['zip_code'])) $addr['zip_code'] = preg_replace('/\D+/', '', (string)$addr['zip_code']);
        if (isset($addr['state'])) $addr['state'] = strtoupper((string)$addr['state']);
        if (isset($addr['country'])) {
            $c = strtoupper((string)$addr['country']);
            if ($c === 'BRASIL' || $c === 'BRA') $c = 'BR';
            $addr['country'] = $c;
        }
        return $addr;
    }

    /**
     * Ajusta amount/valor para pagamentos com cartão baseado no campo "juros" (C|V).
     * - C: comprador assume (ajusta amount_cents via cotação).
     * - V: vendedor assume (mantém amount_cents base).
     * - null: tenta usar o default do site quando possível (cota para descobrir).
     */
    private function aplicarJurosCartao(array $dados): array
    {
        $tipo = $dados['tipo_metodo_pagamento'] ?? ($dados['payment_method_type'] ?? '');
        if (strtolower((string)$tipo) !== 'credit_card') return $dados;

        $jurosRaw = $dados['juros'] ?? null;
        $juros = is_string($jurosRaw) ? strtoupper(trim($jurosRaw)) : null;
        if ($juros !== null && $juros !== '' && !in_array($juros, ['C','V'], true)) {
            throw new \InvalidArgumentException('Campo "juros" inválido: use "C" (comprador) ou "V" (vendedor).');
        }

        $installments = (int)($dados['parcelas'] ?? ($dados['installments'] ?? 1));
        if ($installments <= 1) { unset($dados['juros']); return $dados; }

        // Se vendedor assume, mantém valor base
        if ($juros === 'V') { unset($dados['juros']); return $dados; }

        $amount = $dados['valor_centavos'] ?? ($dados['amount_cents'] ?? null);
        $amount = is_numeric($amount) ? (int)$amount : null;
        if (!$amount || $amount <= 0) { unset($dados['juros']); return $dados; }

        $bin = self::extrairBin($dados);
        if (!$bin) {
            if ($juros === 'C') {
                // Sem BIN: deixa o Hub calcular (não remove juros)
                return $dados;
            }
            // Sem bin e sem juros explícito → não altera
            unset($dados['juros']);
            return $dados;
        }

        // Monta opções para cotação
        $opcoes = [];
        if ($juros === 'C') $opcoes['interest_payer'] = 'user';
        if ($juros === 'V') $opcoes['interest_payer'] = 'merchant';

        $resp = $this->cotarParcela($amount, $bin, $installments, $opcoes);
        if (($resp['status'] ?? null) === 'error') {
            if ($juros === 'C') {
                $msg = (string)($resp['message'] ?? 'Falha ao cotar parcelas');
                throw new \RuntimeException($msg);
            }
            unset($dados['juros']);
            return $dados;
        }

        $payer = $resp['interest_payer'] ?? null;
        if ($juros === 'C') $payer = 'user';
        if ($juros === 'V') $payer = 'merchant';
        if ($payer !== 'user') { unset($dados['juros']); return $dados; }

        $inst = $resp['installment'] ?? null;
        $total = is_array($inst) ? (int)($inst['total_cents'] ?? 0) : 0;
        if ($total > 0) {
            if (array_key_exists('valor_centavos', $dados)) $dados['valor_centavos'] = $total;
            if (array_key_exists('amount_cents', $dados)) $dados['amount_cents'] = $total;
            if (!array_key_exists('valor_centavos', $dados) && !array_key_exists('amount_cents', $dados)) {
                $dados['valor_centavos'] = $total;
            }
        }

        unset($dados['juros']);
        return $dados;
    }

    /** Extrai BIN (6 dígitos) de campos comuns do payload */
    private static function extrairBin(array $dados): ?string
    {
        $bin = $dados['bin'] ?? null;
        if (!$bin && isset($dados['numero_cartao'])) $bin = $dados['numero_cartao'];
        if (!$bin && isset($dados['card_number'])) $bin = $dados['card_number'];
        if (!$bin && isset($dados['card']) && is_array($dados['card'])) {
            if (isset($dados['card']['bin'])) $bin = $dados['card']['bin'];
            elseif (isset($dados['card']['number'])) $bin = $dados['card']['number'];
            elseif (isset($dados['card']['numero'])) $bin = $dados['card']['numero'];
        }
        $digits = preg_replace('/\D+/', '', (string)$bin);
        if (strlen($digits) >= 6) return substr($digits, 0, 6);
        return null;
    }
    private static function montarMensagemErroApi(array $data, int $status): string
    {
        // Preferir mensagem em PT-Br se disponível, depois EN
        $msg = (string)($data['error_message'] ?? ($data['message'] ?? ($data['error'] ?? '')));
        $errs = $data['errors_pt'] ?? ($data['errors_en'] ?? ($data['errors'] ?? null));
        $flat = '';
        if (is_array($errs)) {
            $pairs = [];
            foreach ($errs as $k => $v) {
                if (is_array($v)) $v = implode('; ', array_map('strval', $v));
                $pairs[] = (string)$k . ': ' . (string)$v;
            }
            $flat = implode(' | ', $pairs);
        }
        // Traduzir mensagens base comuns para PT
        $mapBase = [
            'Validation failed' => 'Falha na validação',
            'Payment creation failed' => 'Falha ao criar pagamento',
            'Customer upsert failed' => 'Falha ao gravar cliente',
            'Customer update failed' => 'Falha ao atualizar cliente',
            'Subscription creation failed' => 'Falha ao criar assinatura',
            'The request is invalid.' => 'A requisição é inválida.'
        ];
        $base = $msg !== '' ? ($mapBase[$msg] ?? $msg) : ('Erro de API (' . $status . ')');
        return $flat !== '' ? ($base . ': ' . $flat) : $base;
    }

    /**
     * Obter métricas (admin) em formato Prometheus (texto)
     * Observação: requer autenticação de admin. Se o Hub estiver configurado com
     * ADMIN_BASIC_USER/ADMIN_BASIC_PASS, informe aqui para autenticar por Basic.
     */
    public function obterMetricasAdmin(?string $usuarioBasic = null, ?string $senhaBasic = null): string
    {
        $url = $this->urlBase . '/_admin/metrics';
        $headers = ['Accept: text/plain'];
        if ($usuarioBasic !== null && $senhaBasic !== null) {
            $headers[] = 'Authorization: Basic ' . base64_encode($usuarioBasic . ':' . $senhaBasic);
        }
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->tempoLimite,
            CURLOPT_CONNECTTIMEOUT => 5,
        ];
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        curl_close($ch);
        if ($body === false || $err) throw new \RuntimeException('HTTP error: ' . $err);
        if ($status >= 400) throw new \RuntimeException('HTTP ' . $status);
        return (string)$body;
    }
}
PHP;
    }
}
