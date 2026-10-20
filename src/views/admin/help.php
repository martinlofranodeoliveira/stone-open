<?php $title = 'Ajuda e Integração'; ob_start(); ?>
<h1>Ajuda e Integração</h1>
<style>
  @media (min-width: 992px) {
    body.help-page header.navbar {
      position: sticky;
      top: 0;
      z-index: 1050;
      backdrop-filter: blur(8px);
    }
  }
  .help-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 24px;
  }
  .help-main {
    min-width: 0;
  }
  .help-float-nav {
    display: none;
  }
  .help-float-nav .help-nav-title {
    font-weight: 600;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    font-size: 0.72rem;
    color: #6b7280;
    margin-bottom: 10px;
  }
  .help-float-nav .help-nav-list a {
    display: block;
    padding: 6px 10px;
    border-radius: 10px;
    color: inherit;
    text-decoration: none;
    font-size: 0.92rem;
  }
  .help-float-nav .help-nav-list a.active {
    background: rgba(59, 130, 246, 0.12);
    color: #1d4ed8;
    font-weight: 600;
  }
  .panel h3 {
    scroll-margin-top: 96px;
  }
  @media (min-width: 1200px) {
    .help-layout {
      grid-template-columns: minmax(0, 1fr) 240px;
    }
    .help-float-nav {
      display: block;
      position: sticky;
      top: 96px;
      align-self: start;
      padding: 12px 14px;
      border-radius: 14px;
      border: 1px solid rgba(0, 0, 0, 0.06);
      background: rgba(255, 255, 255, 0.7);
      backdrop-filter: blur(8px);
    }
    [data-theme="dark"] .help-float-nav {
      background: rgba(16, 20, 28, 0.7);
      border-color: rgba(255, 255, 255, 0.08);
    }
  }
</style>
<div class="help-layout">
  <div class="help-main">
<div class="panel help">
  <h3>Resumo</h3>
  <p>Esta página reúne exemplos práticos, payloads e uma classe PHP de cliente para integrar seus sites ao Stone Hub. Você pode copiar e colar os trechos abaixo.</p>
</div>
<div class="panel help">
  <h3>Autenticação</h3>
  <ul>
    <li>Bearer Token (recomendado): envie o header <code>Authorization: Bearer <API_KEY>
    <li>Ou cabeçalhos: <code>X-Client-Id</code> e <code>X-Client-Secret</code></li>
  </ul>
  <p><b>Exceção:</b> <code>POST /api/v1/cards/tokenize</code> é público e usa <code>public_key</code> (app_id).</p>
  <p>Token e segredos são gerados em <a href="<?= $prefix ?>/sites">Sites</a>.</p>
</div>
<div class="panel help">
  <h3>Endpoints principais</h3>
  <ul>
    <li>Criar plano (recorrência): <code>POST <?= htmlspecialchars($appUrl) ?>/api/v1/plans</code></li>
    <li>Listar planos (recorrência): <code>GET <?= htmlspecialchars($appUrl) ?>/api/v1/plans</code></li>
    <li>Criar link de pagamento: <code>POST <?= htmlspecialchars($appUrl) ?>/api/v1/payment-links</code></li>
    <li>Criar pagamento: <code>POST <?= htmlspecialchars($appUrl) ?>/api/v1/payments</code></li>
    <li>Pagamento com cartão + dados completos: <code>POST <?= htmlspecialchars($appUrl) ?>/api/v1/payments/card</code></li>
    <li>Criar pagamento por CPF/Email: <code>POST <?= htmlspecialchars($appUrl) ?>/api/v1/payments/for-customer</code></li>
    <li>Consulta BIN + Parcelas: <code>GET <?= htmlspecialchars($appUrl) ?>/api/v1/cards/quote?amount_cents=10000&amp;bin=411111</code></li>
    <li>Parcela específica (N): <code>GET <?= htmlspecialchars($appUrl) ?>/api/v1/cards/quote/installment?amount_cents=10000&amp;bin=411111&amp;n=3</code></li>
    <li>Apenas marca do cartão: <code>GET <?= htmlspecialchars($appUrl) ?>/api/v1/cards/brand?bin=411111</code></li>
    <li>Tokenizar cartão (público): <code>POST <?= htmlspecialchars($appUrl) ?>/api/v1/cards/tokenize</code> (use <code>public_key</code>)</li>
    <li>Buscar clientes: <code>GET <?= htmlspecialchars($appUrl) ?>/api/v1/customers/search?email=&document=&stone_customer_id=</code></li>
    <li>Webhook público (Stone → Hub): <code>POST <?= htmlspecialchars($appUrl) ?>/webhooks/stone</code></li>
    <li>Métricas (admin): <code>GET <?= htmlspecialchars($appUrl) ?><?= htmlspecialchars($adminPrefix) ?>/metrics</code></li>
    <li>Upsert de cliente: <code>POST <?= htmlspecialchars($appUrl) ?>/api/v1/customers/upsert</code></li>
    <li>Atualizar cliente (lookup): <code>POST <?= htmlspecialchars($appUrl) ?>/api/v1/customers/update</code></li>
    <li>Atualizar cliente por ID: <code>PUT <?= htmlspecialchars($appUrl) ?>/api/v1/customers/{id}</code></li>
    <li>Criar assinatura: <code>POST <?= htmlspecialchars($appUrl) ?>/api/v1/subscriptions</code></li>
    <li>Criar assinatura por CPF/Email: <code>POST <?= htmlspecialchars($appUrl) ?>/api/v1/subscriptions/for-customer</code></li>
  </ul>
</div>

<div class="panel help" style="border-left: 4px solid #ef4444; padding-left: 10px;">
  <h3>Atenção — Juros e Parcelamento</h3>
  <ul>
    <li>Campo opcional <code>juros</code>: <code>C</code> (comprador) ou <code>V</code> (vendedor). Se omitido, usa a configuração do site.</li>
    <li>Para <code>juros=C</code> com cálculo automático, envie <code>bin</code> ou <code>numero_cartao</code> (6 dígitos). Sem BIN, calcule manualmente via <code>/api/v1/cards/quote</code>.</li>
    <li><b>Vendedor assume juros</b>: envie <code>amount_cents</code> = valor base do produto e informe <code>installments</code>.</li>
  </ul>
  <h5>Exemplos de payload</h5>
  <div class="grid-2">
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

<div class="panel help">
  <h3>Planos (Recorrência)</h3>
  <p>Crie planos de assinatura e associe nas assinaturas. A API aceita campos em português e valida antes de enviar ao Pagar.me.</p>
  <p><b>Ciclos de cobrança (ciclos/billing_cycles):</b> você pode definir no plano um padrão de quantos ciclos a assinatura vai renovar antes de encerrar. Quando a assinatura é criada, enviamos <code>billing_cycles</code> no payload. Se não for informado, usamos o padrão do plano; se o plano não tiver, adotamos <code>1</code>.</p>
  <p>Observação: ao criar planos pela API, você pode enviar <code>valor_centavos</code> e omitir <code>itens</code>; o Hub monta um item padrão com o nome do plano e preço informado.</p>
  <h5>Exemplo (JSON)</h5>
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
  <h5>cURL</h5>
  <pre>curl -X POST <?= htmlspecialchars($appUrl) ?>/api/v1/plans \
  -H "Authorization: Bearer <API_KEY>" \
  -H "Content-Type: application/json" \
  --data '{
    "nome":"Plano Gold",
    "moeda":"BRL",
    "intervalo":"month",
    "intervalo_qtd":1,
    "itens":[{"nome":"Musculação","quantidade":1,"preco_centavos":18990}],
    "tipo_cobranca":"prepaid"
  }'</pre>
  <h5>Resposta</h5>
  <pre>{ "plano": { "id": 1, "id_plano_stone": "plan_abc", "nome": "Plano Gold", "codigo": "PLANO-GOLD-001", "valor_centavos": 18990, "moeda": "BRL", "intervalo": "month", "intervalo_qtd": 1, "ciclos": 1, "status": "active" } }</pre>
</div>

<div class="panel help">
  <h3>Plano – Dia Exato</h3>
  <p>Para cobrança em dia fixo, use <code>tipo_cobranca</code> = <code>exact_day</code> e informe <code>dia_cobranca</code> (1..31).</p>
  <h5>Exemplo (JSON)</h5>
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
  <h5>cURL</h5>
  <pre>curl -X POST <?= htmlspecialchars($appUrl) ?>/api/v1/plans \
  -H "Authorization: Bearer <API_KEY>" \
  -H "Content-Type: application/json" \
  --data '{
    "nome":"Plano Mensal (Dia 10)",
    "moeda":"BRL",
    "intervalo":"month",
    "intervalo_qtd":1,
    "tipo_cobranca":"exact_day",
    "dia_cobranca":10,
    "itens":[{"nome":"Mensalidade","quantidade":1,"preco_centavos":9900},{"nome":"Taxa Administração","quantidade":1,"preco_centavos":500}]
  }'</pre>
</div>

<div class="panel help">
  <h3>Plano – Pós-Pago</h3>
  <p>Cobrança após o período: use <code>tipo_cobranca</code> = <code>postpaid</code>.</p>
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
</div>

<div class="panel help">
  <h3>Plano – Parcelas no Cartão</h3>
  <p>Habilite parcelamento no cartão com <code>parcelas</code> (ex.: [1,2,3,6,12]).</p>
  <pre>{
  "nome": "Plano Parcelado",
  "moeda": "BRL",
  "intervalo": "month",
  "intervalo_qtd": 1,
  "metodos_pagamento": ["cartao"],
  "parcelas": [1,2,3,6,12],
  "itens": [ { "nome": "Mensalidade", "quantidade": 1, "preco_centavos": 15990 } ]
  }</pre>
</div>

<div class="panel help">
  <h3>Plano – Trial e Descriptor (Fatura)</h3>
  <p>Use <code>dias_teste</code> e <code>descricao_fatura</code> (≤13 caracteres) para personalizar o plano.</p>
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
</div>

<div class="panel help">
  <h3>Links de Pagamento (Checkout)</h3>
  <p>Crie um link de pagamento usando checkout do Pagar.me. Envie campos em português; o Hub faz o mapeamento.</p>
  <h5>Exemplo de envio (JSON)</h5>
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
  <h5>Exemplo cURL</h5>
  <pre>curl -X POST <?= htmlspecialchars($appUrl) ?>/api/v1/payment-links \
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
  <h5>Resposta</h5>
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
</div>

<div class="panel help">
  <h3 id="webhooks-basic">Webhooks – Autenticação Basic (opcional)</h3>
  <p>Endpoint público: <code>POST <?= htmlspecialchars($appUrl) ?>/webhooks/stone</code></p>
  <ul>
    <li>Se <strong>desabilitada</strong> no Hub: o endpoint aceita sem Basic e responde 200/queued.</li>
    <li>Se <strong>habilitada</strong> no Hub: o endpoint exige <code>Authorization: Basic</code> com o usuário/senha configurados em <a href="<?= $prefix ?>/account">Admin → Conta</a>.</li>
    <li>Assinatura HMAC (opcional): defina <code>STONE_WEBHOOK_SECRET_SANDBOX</code> e/ou <code>STONE_WEBHOOK_SECRET_PRODUCTION</code> no .env para validar <code>X-Hub-Signature</code> (compatível também com <code>X-Hub-Signature-256</code>, <code>X-Signature</code>, <code>Pagarme-Signature</code>).</li>
  </ul>

  <h5>Como habilitar</h5>
  <ol>
    <li>No Hub, acesse <strong>Admin → Conta</strong> e marque “Habilitar autenticação Basic”, preenchendo Usuário e Senha. Salve.</li>
    <li>No Pagar.me, configure o webhook para <code><?= htmlspecialchars($appUrl) ?>/webhooks/stone</code> e informe as mesmas credenciais (ou use o recurso da sua plataforma para enviar Basic).</li>
  </ol>

  <h5>Exemplos</h5>
  <p><strong>cURL com Basic</strong> (recomendado para testes locais):</p>
  <pre>curl --header @private-auth-headers.txt -X POST \
  -H "Content-Type: application/json" \
  --data '{"type":"charge.paid","data":{}}' \
  <?= htmlspecialchars($appUrl) ?>/webhooks/stone
</pre>
  <p><strong>Header explícito</strong> (se já tiver o valor Base64):</p>
  <pre>curl -X POST \
  -H "Content-Type: application/json" \
  --header @private-auth-headers.txt \
  --data '{"type":"charge.paid","data":{}}' \
  <?= htmlspecialchars($appUrl) ?>/webhooks/stone
</pre>
  <p class="muted">Observação: se Basic estiver habilitado e ausente/incorreto, o Hub retorna 401. Se o segredo HMAC estiver configurado no .env e o header de assinatura não bater, retorna 400.</p>
</div>

<div class="panel help">
  <h3>Forward para o seu site (Hub → Seu Site)</h3>
  <p>Para cada evento recebido da Stone e processado pelo Hub, enviamos um <strong>forward</strong> (POST JSON) para o <b>Callback URL</b> do Site. O corpo é 100% em português e assinado via HMAC.</p>
  <p class="muted">Roteamento: se o payload contiver <code>account.id</code>, o Hub roteia pelo <b>Stone Account ID</b> configurado no Site. Alternativamente, tenta correlacionar por <code>charge.id</code> (ch_...), <code>order.code</code>/<code>charge.code</code> (sua referência) ou, na ausência, usa o único site ativo.</p>
  <h5>Cabeçalhos</h5>
  <ul>
    <li><code>Content-Type: application/json</code></li>
    <li><code>X-Hub-Signature: sha256=&lt;hex&gt;</code> (HMAC-SHA256 do corpo usando o <b>FORWARD_SECRET</b> do Site)</li>
  </ul>
  <h5>Exemplo de payload (PT‑BR)</h5>
  <pre>{
  "provedor": "stone",
  "id_notificacao": 123,
  "evento": "payment.refunded",
  "id_evento_provedor": "evt_abc123",
  "id_pagamento_stone": "ch_123",
  "id_assinatura_stone": null,
  "id_conta_stone": "acc_3Xzx14QuGf5Lmp4Q",
  "referencia_cliente": "REF-001",
  "metodo_pagamento": "credit_card",
  "ocorrencia_assinatura": 1,
  "parcelas_cartao": 1,
  "status": "success",
  "message": "Pagamento estornado",
  "status_code": "refunded",
  "pagamento": {
    "id": 55,
    "referencia_cliente": "REF-001",
    "valor_centavos": 12900,
    "moeda": "BRL",
    "status": "Estornado",
    "status_codigo": "refunded"
  },
  "assinatura": null,
  "descricao": "Pagamento estornado",
  "dados": { /* payload original da Stone (JSON) */ }
}</pre>

  <h5>Validação da assinatura (PHP)</h5>
  <pre>
$raw = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';
$expected = 'sha256=' . hash_hmac('sha256', $raw, 'SEU_FORWARD_SECRET');
if (!hash_equals($expected, $sig)) {
    http_response_code(401);
    exit('assinatura inválida');
}
// OK: processe o JSON em $raw (PT‑BR)
</pre>

  <p class="muted">O Hub reentrega automaticamente em caso de falha (backoff). Consulte <a href="<?= $prefix ?>/forward-logs">Forward Logs</a> para histórico de tentativas.</p>
</div>
<div class="panel help">
  <h3>Workers HTTP (sem terminal)</h3>
  <p>Use um agendador externo para chamar periodicamente:</p>
  <ul>
    <li>Drain webhooks pendentes: <code>GET <?= htmlspecialchars($appUrl) ?>/tasks/drain?token=SEU_TASKS_SECRET&amp;limit=100&amp;budget_ms=400</code></li>
    <li>Retry de forwards vencidos: <code>GET <?= htmlspecialchars($appUrl) ?>/tasks/retry?token=SEU_TASKS_SECRET&amp;limit=100&amp;max_attempts=5</code></li>
  </ul>
  <p>Defina <code>TASKS_SECRET</code> (e opcional <code>TASKS_IP_WHITELIST</code>) no .env.</p>
</div>
<div class="panel help">
  <h3>Métodos disponíveis (cliente PHP)</h3>
  <ul>
    <li><code>listarPlanos(array $filtros = [])</code> – planos do site autenticado</li>
    <li><code>criarPlano(array $dados)</code> – cria um plano de recorrência</li>
    <li><code>criarLinkPagamento(array $dados)</code> – link de pagamento (checkout)</li>
    <li><code>criarPagamentoPix(array $dados)</code> – compra via PIX</li>
    <li><code>criarPagamentoCartao(array $dados)</code> – compra via Cartão (token/id)</li>
    <li><code>criarPagamentoCartaoComDados(array $dados)</code> – compra via Cartão (dados completos + tokenização)</li>
    <li><code>criarBoleto(array $dados)</code> – gera boleto</li>
    <li><code>criarAssinatura(array $dados)</code> – pagamento recorrente (assinatura)</li>
    <li><code>cotarCartao(int $valorCentavos, string $bin, array $opcoes = [])</code> – parcelas + bandeira</li>
    <li><code>obterBandeiraCartao(string $bin)</code> – bandeira do cartão</li>
    <li><code>detectarBandeiraLocal(string $bin6)</code> – detectar bandeira localmente</li>
    <li><code>obterPagamentoPorId(int $id)</code> – consultar status da transação</li>
    <li><code>obterPagamentoPorIdStone(string $idStone)</code> – consultar por ID da Stone</li>
    <li><code>obterPagamentoPorReferencia(string $referenciaCliente)</code> – consultar por referência do cliente</li>
    <li><code>listarPagamentos(array $filtros)</code> – consultar por período, cliente e status</li>
    <li><code>obterBoleto(int $id)</code> – consultar boleto por ID local</li>
    <li><code>obterBoletoPorIdStone(string $idStone)</code> – consultar boleto por ID Stone</li>
    <li><code>listarBoletos(array $filtros = [])</code> – listar boletos locais</li>
    <li><code>listarBoletosStone(array $filtros = [])</code> – listar boletos na Stone</li>
    <li><code>obterClientePorId(int $id)</code> – consultar cliente</li>
    <li><code>buscarClientes(array $filtros)</code> – consultar por email/stone_customer_id/document</li>
    <li><code>upsertCliente(array $dados)</code> – criar/atualizar cliente (Stone + local)</li>
    <li><code>atualizarCliente(array $dados)</code> – atualizar por stone_customer_id/email/document</li>
    <li><code>atualizarClientePorId(int $id, array $dados)</code> – atualizar cliente pelo ID local</li>
    <li><code>obterAssinaturaPorId(int $id)</code> – consultar assinatura</li>
    <li><code>obterAssinaturaPorIdStone(string $idStone)</code> – consultar assinatura por Stone</li>
    <li><code>listarEventosAssinatura(int $idAssinatura)</code> – eventos da assinatura</li>
    <li><code>listarPagamentosPorAssinatura(string $idAssinaturaStone)</code> – consultar pagamentos por assinatura</li>
    <li><code>tarefasEsvaziarFila(int $limite, int $orcamentoMs)</code> – esvaziar fila (HTTP)</li>
    <li><code>tarefasReentregar(int $limite, int $maxTentativas)</code> – retentar forwards (HTTP)</li>
    <li><code>verificarAssinaturaForward(string $corpo, string $segredo, string $cabecalho)</code> – validar HMAC</li>
    <li><code>obterMetricasAdmin(?string $usuarioBasic, ?string $senhaBasic)</code> – retorna métricas (texto Prometheus) do Admin</li>
  </ul>
</div>

<div class="panel help">
  <h3>Exemplos de Payload</h3>
  <h5>1) Criar pagamento (PIX)</h5>
  <pre>{
  "valor_centavos": 10990,
  "moeda": "BRL",
  "referencia_cliente": "pedido-123",
  "tipo_metodo_pagamento": "pix",
  "expira_em": "2026-12-31T23:59:00-03:00",
  "cliente": {
    "name": "Fulano de Tal",
    "email": "customer@example.invalid",
    "document": "DOCUMENTO_EXEMPLO"
  },
  "descricao": "Compra Loja X"
}</pre>

  <h5>2) Criar pagamento (Boleto)</h5>
  <pre>{
  "valor_centavos": 10990,
  "moeda": "BRL",
  "referencia_cliente": "pedido-123",
  "tipo_metodo_pagamento": "boleto",
  "data_vencimento": "2026-12-31T23:59:00-03:00",
  "boleto": {
    "document_number": "DOCUMENTO_EXEMPLO",
    "instructions": ["Pague até o vencimento"]
  },
  "cliente": {
    "name": "Fulano de Tal",
    "email": "customer@example.invalid",
    "document": "DOCUMENTO_EXEMPLO"
  }
}</pre>

  <h5>3) Criar pagamento (Cartão por token)</h5>
  <pre>{
  "valor_centavos": 25990,
  "moeda": "BRL",
  "referencia_cliente": "pedido-456",
  "tipo_metodo_pagamento": "credit_card",
  "token_cartao": "tok_abc123",
  "parcelas": 3,
  "operation_type": "auth_and_capture",
  "cliente": {
    "name": "Ciclana",
    "email": "customer@example.invalid"
  },
  "descricao": "Assinatura Plano Ouro"
}</pre>

  <h5>4) Criar pagamento (Cartão com dados completos)</h5>
  <pre>{
  "valor_centavos": 38700,
  "moeda": "BRL",
  "referencia_cliente": "FAS-PARC-1109322-1",
  "tipo_metodo_pagamento": "credit_card",
  "parcelas": 1,
  "juros": "V",
  "cliente": {
    "name": "Cliente Exemplo",
    "email": "customer@example.invalid",
    "document": "DOCUMENTO_EXEMPLO"
  },
  "card": {
    "number": "NUMERO_EXEMPLO",
    "holder_name": "Cliente Exemplo",
    "exp_month": 6,
    "exp_year": 26,
    "cvv": "CVV_EXEMPLO"
  }
}</pre>

  <h5>4) Criar assinatura (recorrência)</h5>
  <p class="muted">Observação: o valor cobrado é o do plano (plans.amount_cents). O campo de valor não é aceito no payload da assinatura.</p>
  <pre>{
  "id_cliente_stone": "cus_999",
  "id_plano_stone": "plan_abc",
  "moeda": "BRL",
  "billing_cycles": 1
}</pre>

  <h5>6) Pagamento com payload combinado (customer + card token)</h5>
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

  <h5>7) Assinatura com payload combinado (plano local + customer + card token)</h5>
  <p class="muted">Observação: o valor da assinatura é sempre o valor do plano; não envie amount_cents.</p>
  <pre>{
  "plano_id": 1,
  "currency": "BRL",
  "customer": { "name": "Ciclana", "email": "customer@example.invalid", "document": "DOCUMENTO_EXEMPLO" },
  "card": { "token": "tok_def456" },
  "installments": 1,
  "billing_cycles": 1
}</pre>

  <h5>4) Forward recebido no seu site (pagamento avulso)</h5>
  <pre>{
  "provedor": "stone",
  "id_notificacao": 123,
  "id_pagamento": 987,
  "evento": "charge.paid",
  "id_evento_provedor": "wh_123abc",
  "id_pagamento_stone": "ch_abc123",
  "referencia_cliente": "pedido-456",
  "dados": { /* JSON original da Stone */ }
}</pre>
  <p>Valide a assinatura HMAC no header <code>X-Hub-Signature: sha256=...</code> com o <code>FORWARD_SECRET</code> do Site.</p>

  <h5>5) Forward recebido (recorrência)</h5>
  <pre>{
  "provedor": "stone",
  "id_notificacao": 124,
  "id_pagamento": null,
  "evento": "subscription.activated",
  "id_evento_provedor": "wh_789xyz",
  "id_pagamento_stone": null,
  "referencia_cliente": null,
  "dados": {
    "type": "subscription.activated",
    "data": {
      "subscription": {
        "id": "sub_123",
        "status": "active",
        "plan_id": "plan_abc",
        "amount": 25990,
        "customer": {"id": "cus_999", "name": "Ciclana"}
      },
      "occurred_at": "2025-01-01T12:00:00Z"
    }
  }
}</pre>
</div>

<div class="panel help">
  <div class="flex" style="justify-content: space-between; align-items: baseline;">
    <h3 style="margin:0">Classe PHP de Cliente (copie e use)</h3>
    <a class="btn" href="<?= $prefix ?>/help/client-php">
      <svg class="ico"><use href="/assets/icons.svg#download"></use></svg> Baixar classe PHP
    </a>
    <a class="btn secondary" href="<?= $prefix ?>/help/usage">
      <svg class="ico"><use href="/assets/icons.svg#download"></use></svg> Baixar exemplos (usage)
    </a>
    <a class="btn secondary" href="<?= $prefix ?>/help/usage-html">
      <svg class="ico"><use href="/assets/icons.svg#download"></use></svg> Baixar exemplos (HTML)
    </a>
    <a class="btn secondary" href="<?= $prefix ?>/help/postman">
      <svg class="ico"><use href="/assets/icons.svg#download"></use></svg> Baixar collection (Postman)
    </a>
  </div>
  <p>Use esta classe nos seus sites para chamar a API do Hub. Suporta Bearer Token (<code>API_TOKEN</code>) ou <code>X-Client-Id</code>/<code>X-Client-Secret</code>. Inclui validação da assinatura HMAC do forward.</p>
  <p class="muted">Dica: a collection do Postman já vem com todas as rotas e exemplos prontos, usando o domínio atual.</p>
  <pre><?= htmlspecialchars($clientPhpCode) ?></pre>

  <h5>Uso rápido (PHP)</h5>
  <pre>&lt;?php
$hub = new StoneHubCliente('<?= htmlspecialchars($appUrl) ?>', 'SEU_API_TOKEN');
$res = $hub->criarPagamento([
  'valor_centavos' => 10990,
  'moeda' => 'BRL',
  'referencia_cliente' => 'pedido-123',
  'tipo_metodo_pagamento' => 'pix',
  'expira_em' => '2026-12-31T23:59:00-03:00',
]);
?&gt;</pre>

  <h5>Validando forward no seu site (PHP)</h5>
  <pre>&lt;?php
$raw = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';
$ok = StoneHubCliente::verificarAssinaturaForward($raw, 'SEU_FORWARD_SECRET', $sig);
if (!$ok) { http_response_code(400); exit; }
// processar JSON
?&gt;</pre>
</div>

<div class="panel">
  <h3>Boas práticas</h3>
  <ul>
    <li>Guarde <code>API_TOKEN</code> e <code>FORWARD_SECRET</code> com segurança.</li>
    <li>Valide assinatura <code>X-Hub-Signature</code> em todos os forwards recebidos.</li>
    <li>Use <code>client_reference_id</code> único por pedido para idempotência.</li>
    <li>Agende os endpoints <em>tasks</em> se não puder usar workers via terminal.</li>
  </ul>
</div>

  </div>
  <aside class="help-float-nav">
    <div class="help-nav-title">Navegação</div>
    <nav id="helpNavList" class="help-nav-list"></nav>
  </aside>
</div>
<script>
  document.body.classList.add('help-page');
  (function () {
    var nav = document.getElementById('helpNavList');
    if (!nav) return;
    var headings = Array.prototype.slice.call(document.querySelectorAll('.panel h3'));
    if (!headings.length) return;
    var used = {};
    var slugify = function (text) {
      var slug = (text || '').toString().trim().toLowerCase();
      slug = slug.normalize ? slug.normalize('NFD').replace(/[\\u0300-\\u036f]/g, '') : slug;
      slug = slug.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
      if (slug === '') slug = 'secao';
      var base = slug;
      var i = 2;
      while (used[slug]) { slug = base + '-' + i++; }
      used[slug] = true;
      return slug;
    };
    var links = [];
    headings.forEach(function (h) {
      if (!h.id) h.id = slugify(h.textContent || 'secao');
      var a = document.createElement('a');
      a.href = '#' + h.id;
      a.textContent = h.textContent || 'Seção';
      nav.appendChild(a);
      links.push(a);
    });

    var linkById = {};
    links.forEach(function (a) { linkById[a.getAttribute('href').slice(1)] = a; });
    if ('IntersectionObserver' in window) {
      var obs = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          var id = entry.target.id;
          Object.values(linkById).forEach(function (el) { el.classList.remove('active'); });
          if (linkById[id]) linkById[id].classList.add('active');
        });
      }, { rootMargin: '-35% 0px -55% 0px', threshold: 0.01 });
      headings.forEach(function (h) { obs.observe(h); });
    }
  })();
</script>

<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
