# Stone Open

Código-fonte de uma aplicação PHP de integração de pagamentos, com API, painel administrativo, contas por site, planos/assinaturas, webhooks e ferramentas de administração. Esta edição mantém as camadas, controladores, views e esquema SQL; não inclui dados, contas, chaves, eventos capturados ou histórico da operação privada.

## Configuração própria

Use PHP com PDO MySQL, cURL, OpenSSL, sessão e demais extensões necessárias ao ambiente escolhido, além de um banco MySQL próprio. Copie `.env.example` para `.env` e preencha seus próprios parâmetros. A aplicação recusa iniciar sem banco, usuário e senha explícitos; nenhum banco ou administrador é provisionado pelo release. `Scheme.sql` e migrações contêm estrutura, sem INSERTs de dados operacionais.

Para criar o administrador, configure o banco e execute manualmente `php bin/create_admin.php NOME EMAIL SENHA [PAPEL]` com credenciais próprias. A senha deve ter pelo menos 12 caracteres e não é impressa pelo comando. Contas/chaves de provedores são opcionais para inspecionar o código, mas necessárias para as integrações que o usuário decidir configurar. Não há credencial ou conta de teste compartilhada.

A configuração 2FA gera o QR somente pela biblioteca local. Se ela falhar ou estiver ausente, o modal preserva a chave e a URI para cadastro manual; o segredo não é enviado a um serviço externo de imagens.

O encaminhador legado em `public/notificacao` fica desabilitado por padrão. Só pode ser habilitado mediante `STONE_ENABLE_LEGACY_WEBHOOK=true` e endpoints próprios em `STONE_LEGACY_ENDPOINTS_JSON`. Nenhum endpoint da operação anterior é fornecido. As rotas modernas da API e o painel permanecem no código. Webhooks modernos exigem Basic válido configurado na conta ou segredo HMAC próprio; sem esses mecanismos, retornam 503 antes de persistir ou processar o evento. Um segredo HMAC configurado continua exigindo assinatura válida mesmo com Basic válido.

## Testes e limites

Os scripts antigos de teste que efetuavam chamadas reais de autenticação, cartão e produção não são distribuídos. A validação offline desta preparação inclui sintaxe de todos os PHPs e regressões puras de assinatura HMAC, configuração explícita de banco e exceções estruturadas. Os comandos são `php -n tests/webhook_contract.php`, `php -n tests/release_settings.php`, `php -n tests/webhook_authentication.php` e `node --test tests/twofa_privacy.test.mjs`. A preparação passou 81 verificações públicas (8 de HMAC, 45 de configuração/exceções e 28 de autenticação/persistência de webhooks), mais 4 casos JavaScript de privacidade 2FA e 40 verificações independentes e sintaxe de todos os 120 arquivos PHP, usando PHP 8.5.4 e Node.js 22 sem rede, banco ou credenciais. O helper `scripts/checks-offline.py --php CAMINHO_PHP --node CAMINHO_NODE` executa essa suíte com Bubblewrap, fonte e runtimes somente para leitura, rede isolada, nenhuma capacidade efetiva e bloqueio de novos privilégios. A fonte e os testes foram montados somente para leitura; os arquivos originais privados permaneceram inalterados. A especificação OpenAPI mantém 31 caminhos, sem exemplos de clientes ou cartões da operação anterior.

O teste JavaScript extrai o script real do view e usa DOM/fetch simulados para os três estados de QR local e a política CSP; não substitui uma sessão em navegador. Os testes de webhook usam um substituto de banco em memória e as classes reais de controlador, HTTP e assinatura; verificam que eventos sem autenticação não são persistidos. Esses testes não acionam APIs de provedores, não criam contas, não conectam banco e não fazem transações. Não comprovam o fluxo financeiro completo nem a disponibilidade de serviços externos. Configure e valide uma integração própria separadamente antes de usar dados reais.

## Contexto seguro de logs

O auxiliar puro `App\Support\SafeLogContext::redact(array)` gera uma copia redigida de contexto estruturado para logs, sem alterar a entrada e sem acessar banco, disco, rede, contas ou APIs de pagamento.

Chaves sensiveis (comparacao sem diferenca de caixa) tem o valor trocado por `'[REDACTED]'`: `password`, `authorization`, `api_key`, `token`, `secret`, `card_number` e `cvv`. Demais chaves/valores sao preservados. A redacao e recursiva em arrays/listas ate 8 niveis; objetos ou profundidade maior lancam `InvalidArgumentException('Invalid log context')` sem incluir conteudo original na mensagem.

Exemplo sintetico (sem dados reais):

```php
use App\Support\SafeLogContext;

$redacted = SafeLogContext::redact($context);
// chaves sensiveis passam a '[REDACTED]'; demais valores sao preservados
```

Limites: o exemplo usa apenas valores ficticios. O auxiliar nao promete anonimizacao completa; revise as chaves registradas antes de gravar logs e valide a integracao propria separadamente. Regressao pura em `lab_tests/safe_log_context.php` via `php -n lab_tests/safe_log_context.php`.

## Exportação CSV segura contra fórmulas

O auxiliar puro `App\\Support\\CsvFormulaGuard::protect(string)` prefixa exatamente um apóstrofo ASCII (`'`) antes de todos os bytes originais quando o texto, após pular espaços ASCII `0x20` iniciais, remover no máximo um BOM UTF-8 `EF BB BF` e pular espaços novamente, começa com `=`, `+`, `-`, `@`, TAB, CR, LF ou variantes fullwidth `＝＋－＠`. Todo o resto permanece byte a byte; o apóstrofo faz parte do dado exportado.

Exemplo sintético em memória (sem dados reais):

```php
use App\\Infrastructure\\Http\\Response;

$rows = [['name' => '=1+1', 'balance' => -42, 'code' => '-42']];
$columns = ['name' => 'Name', 'balance' => 'Balance', 'code' => 'Code'];
$safe = Response::csv($rows, $columns);
$raw = Response::csv($rows, $columns, 'export.csv', false);
```

Por padrão (`spreadsheetSafe=true`), rótulos STRING e células cuja entrada original é STRING recebem a proteção; os 15 fluxos atuais herdam esse padrão sem editar controladores. Com opt-out explícito `false`, as strings originais são exportadas sem apóstrofo, útil para consumidor programático que lê CSV como texto. A string `"-42"` é texto literal protegido (`"'-42"`), enquanto o número `-42` (`int`/`float`) mantém seu cast para string sem prefixo; `true` vira `"1"`, `false` vira `""`, e `null`/ausente/arrays mantêm o `json_encode(..., JSON_UNESCAPED_UNICODE)` anterior, sem alterar projeção/ordem de colunas.

Limites: os roteiros não executam planilhas nem ações financeiras. O modo não promete anonimização, proteção universal entre planilhas nem segurança depois de salvar/reabrir; clientes podem reinterpretar dados. XLS/JSON, nomes de arquivo e regras de autorização não ganham garantias novas. Regressão pura em `lab_tests/csv_formula_guard.php` via `php -n lab_tests/csv_formula_guard.php`.

## Conteúdo excluído e licenças

Excluídos: `.env`, logs, storage/cache da operação, Postman credenciado, webhook capturado, documentação operacional/deploy e marcas de cartão sem licença estabelecida. Ícones genéricos substituem essas marcas; as bibliotecas frontend vendorizadas continuam incluídas com suas licenças preservadas. A edição não contém histórico Git privado.

Código de titularidade autorizada sob MIT. Consulte `LICENSE`, `LICENSES/` e `THIRD_PARTY_NOTICES.md` para direitos de terceiros.


<!-- community-core:plan-interval-tag -->
### Canonical plan interval tag for offline checks

Gives admin plans, subscriptions, invoices and API list offline checks a shared deterministic way to normalize multilingual interval request fields to canonical recurrence tags and cycle lengths without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/plan_interval_tag.php`, API `App\Support\Community\PlanIntervalTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from plan interval request to canonical interval tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and integer assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \stdClass, \InvalidArgumentException, \TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field i0 is payload.interval when payload.interval is a string, else payload.periodo when payload.periodo is a string, else payload.billing_interval when payload.billing_interval is a string, else missing. interval_source is interval when i0 came from payload.interval, periodo when from payload.periodo, billing_interval when from payload.billing_interval, else none. has_interval is true when i0 is present as string else false. raw is i0 when has_interval else empty string. normalized is daily when raw equals daily or diaria, weekly when raw equals weekly or semanal, monthly when raw equals monthly or mensal, yearly when raw equals yearly or anual or annual, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. cycle_days is 1 when normalized is daily, 7 when weekly, 30 when monthly, 365 when yearly, else 0. category is short when normalized is daily or weekly, standard when monthly, long when yearly, else unknown. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of daily, weekly, monthly, yearly or unknown, interval_source one of interval, periodo, billing_interval or none, has_interval boolean, cycle_days one of 0, 1, 7, 30 or 365, category one of short, standard, long or unknown, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "interval": "monthly"
  },
  "output": {
    "category": "standard",
    "cycle_days": 30,
    "has_interval": true,
    "interval_source": "interval",
    "is_known": true,
    "normalized": "monthly",
    "raw": "monthly"
  }
}
```

```json
{
  "input": {
    "periodo": "semanal"
  },
  "output": {
    "category": "short",
    "cycle_days": 7,
    "has_interval": true,
    "interval_source": "periodo",
    "is_known": true,
    "normalized": "weekly",
    "raw": "semanal"
  }
}
```

```json
{
  "input": {},
  "output": {
    "category": "unknown",
    "cycle_days": 0,
    "has_interval": false,
    "interval_source": "none",
    "is_known": false,
    "normalized": "unknown",
    "raw": ""
  }
}
```

Public regression tests: `lab_tests/community_plan_interval_tag.php`.


<!-- community-core:payment-status-tag -->
### Canonical payment status tag for offline checks

Gives admin payments, invoices, subscriptions and API list offline checks a shared deterministic way to normalize multilingual status request fields to canonical payment tags and outcome categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/payment_status_tag.php`, API `App\Support\Community\PaymentStatusTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from payment status request to canonical status tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \stdClass, \InvalidArgumentException, \TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field s0 is payload.status when payload.status is a string, else payload.situacao when payload.situacao is a string, else payload.payment_status when payload.payment_status is a string, else missing. status_source is status when s0 came from payload.status, situacao when from payload.situacao, payment_status when from payload.payment_status, else none. has_status is true when s0 is present as string else false. raw is s0 when has_status else empty string. normalized is pending when raw equals pending or pendente, paid when raw equals paid or pago or paga, failed when raw equals failed or falha, refunded when raw equals refunded or reembolsado, canceled when raw equals canceled or cancelled or cancelado, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. category is open when normalized is pending, success when normalized is paid, failure when normalized is failed, reversal when normalized is refunded or normalized is canceled, else unknown. is_terminal is true when normalized is paid or failed or refunded or canceled else false. is_success is true when normalized is paid else false. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of pending, paid, failed, refunded, canceled or unknown, status_source one of status, situacao, payment_status or none, has_status boolean, category one of open, success, failure, reversal or unknown, is_terminal boolean, is_success boolean, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "status": "paid"
  },
  "output": {
    "category": "success",
    "has_status": true,
    "is_known": true,
    "is_success": true,
    "is_terminal": true,
    "normalized": "paid",
    "raw": "paid",
    "status_source": "status"
  }
}
```

```json
{
  "input": {
    "situacao": "pendente"
  },
  "output": {
    "category": "open",
    "has_status": true,
    "is_known": true,
    "is_success": false,
    "is_terminal": false,
    "normalized": "pending",
    "raw": "pendente",
    "status_source": "situacao"
  }
}
```

```json
{
  "input": {},
  "output": {
    "category": "unknown",
    "has_status": false,
    "is_known": false,
    "is_success": false,
    "is_terminal": false,
    "normalized": "unknown",
    "raw": "",
    "status_source": "none"
  }
}
```

Public regression tests: `lab_tests/community_payment_status_tag.php`.


<!-- community-core:admin-list-window -->
### Canonical admin list window for offline checks

Gives admin customers, payments, invoices, subscriptions, sites, users and API list offline checks a shared deterministic way to normalize pagination and sort request fields to a bounded window without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/admin_list_window.php`, API `App\Support\Community\AdminListWindow\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core: `src/App/Support/Community/admin_list_window.php`, API `App\Support\Community\AdminListWindow\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from list window request to bounded window with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, integer comparisons, integer arithmetic and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \stdClass, \InvalidArgumentException, \TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field p0 is payload.page when payload.page is an integer, else payload.pagina when payload.pagina is an integer, else missing. page_source is page when p0 came from payload.page, pagina when from payload.pagina, else none. has_page is true when p0 is present as integer else false. page is 1 when missing, else 1 when p0 is less than 1, else 1000 when p0 is greater than 1000, else p0. Field pp0 is payload.per_page when payload.per_page is an integer, else payload.por_pagina when payload.por_pagina is an integer, else missing. per_page_source is per_page when pp0 came from payload.per_page, por_pagina when from payload.por_pagina, else none. has_per_page is true when pp0 is present as integer else false. per_page is 20 when missing, else 1 when pp0 is less than 1, else 100 when pp0 is greater than 100, else pp0. Field s0 is payload.sort when payload.sort is a string, else payload.ordenacao when payload.ordenacao is a string, else missing. sort_source is sort when s0 came from payload.sort, ordenacao when from payload.ordenacao, else none. has_sort is true when s0 is present as string else false. raw_sort is s0 when has_sort else empty string. sort is created_at when raw_sort equals created_at or criado_em, amount when raw_sort equals amount or valor, status when raw_sort equals status or situacao, else created_at. Matching is exact case-sensitive with no trimming, folding or pattern calls. Field d0 is payload.direction when payload.direction is a string, else payload.direcao when payload.direcao is a string, else missing. direction_source is direction when d0 came from payload.direction, direcao when from payload.direcao, else none. has_direction is true when d0 is present as string else false. raw_direction is d0 when has_direction else empty string. direction is asc when raw_direction equals asc or crescente, desc when raw_direction equals desc or decrescente, else desc. Matching is exact case-sensitive with no trimming, folding or pattern calls. offset is (page minus 1) multiplied by per_page. Output is an object with page integer 1 to 1000, per_page integer 1 to 100, offset integer 0 to 99900, sort one of created_at, amount or status, sort_source one of sort, ordenacao or none, has_sort boolean, raw_sort string, direction one of asc or desc, direction_source one of direction, direcao or none, has_direction boolean, raw_direction string, page_source one of page, pagina or none, has_page boolean, per_page_source one of per_page, por_pagina or none, and has_per_page boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "direction": "asc",
    "page": 2,
    "per_page": 10,
    "sort": "amount"
  },
  "output": {
    "direction": "asc",
    "direction_source": "direction",
    "has_direction": true,
    "has_page": true,
    "has_per_page": true,
    "has_sort": true,
    "offset": 10,
    "page": 2,
    "page_source": "page",
    "per_page": 10,
    "per_page_source": "per_page",
    "raw_direction": "asc",
    "raw_sort": "amount",
    "sort": "amount",
    "sort_source": "sort"
  }
}
```

```json
{
  "input": {
    "direcao": "crescente",
    "ordenacao": "criado_em",
    "pagina": 5,
    "por_pagina": 50
  },
  "output": {
    "direction": "asc",
    "direction_source": "direcao",
    "has_direction": true,
    "has_page": true,
    "has_per_page": true,
    "has_sort": true,
    "offset": 200,
    "page": 5,
    "page_source": "pagina",
    "per_page": 50,
    "per_page_source": "por_pagina",
    "raw_direction": "crescente",
    "raw_sort": "criado_em",
    "sort": "created_at",
    "sort_source": "ordenacao"
  }
}
```

```json
{
  "input": {},
  "output": {
    "direction": "desc",
    "direction_source": "none",
    "has_direction": false,
    "has_page": false,
    "has_per_page": false,
    "has_sort": false,
    "offset": 0,
    "page": 1,
    "page_source": "none",
    "per_page": 20,
    "per_page_source": "none",
    "raw_direction": "",
    "raw_sort": "",
    "sort": "created_at",
    "sort_source": "none"
  }
}
```

Public regression tests: `lab_tests/community_admin_list_window.php`.


<!-- community-core:subscription-status-tag -->
### Canonical subscription status tag for offline checks

Gives admin subscriptions, invoices, plans and API list offline checks a shared deterministic way to normalize multilingual subscription status request fields to canonical lifecycle tags and handling categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/subscription_status_tag.php`, API `App\Support\Community\SubscriptionStatusTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core: `src/App/Support/Community/subscription_status_tag.php`, API `App\Support\Community\SubscriptionStatusTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from subscription status request to canonical status tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \stdClass, \InvalidArgumentException, \TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field s0 is payload.status when payload.status is a string, else payload.situacao when payload.situacao is a string, else payload.subscription_status when payload.subscription_status is a string, else missing. status_source is status when s0 came from payload.status, situacao when from payload.situacao, subscription_status when from payload.subscription_status, else none. has_status is true when s0 is present as string else false. raw is s0 when has_status else empty string. normalized is active when raw equals active or ativo or ativa, trialing when raw equals trialing or trial or teste or em_teste, past_due when raw equals past_due or inadimplente or atrasado, suspended when raw equals suspended or suspenso or suspensa, canceled when raw equals canceled or cancelled or cancelado or cancelada, expired when raw equals expired or expirado or expirada, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. category is current when normalized is active or normalized is trialing, attention when normalized is past_due or normalized is suspended, ended when normalized is canceled or normalized is expired, else unknown. is_active is true when normalized is active else false. is_ended is true when normalized is canceled or normalized is expired else false. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of active, trialing, past_due, suspended, canceled, expired or unknown, status_source one of status, situacao, subscription_status or none, has_status boolean, category one of current, attention, ended or unknown, is_active boolean, is_ended boolean, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "status": "active"
  },
  "output": {
    "category": "current",
    "has_status": true,
    "is_active": true,
    "is_ended": false,
    "is_known": true,
    "normalized": "active",
    "raw": "active",
    "status_source": "status"
  }
}
```

```json
{
  "input": {
    "situacao": "inadimplente"
  },
  "output": {
    "category": "attention",
    "has_status": true,
    "is_active": false,
    "is_ended": false,
    "is_known": true,
    "normalized": "past_due",
    "raw": "inadimplente",
    "status_source": "situacao"
  }
}
```

```json
{
  "input": {},
  "output": {
    "category": "unknown",
    "has_status": false,
    "is_active": false,
    "is_ended": false,
    "is_known": false,
    "normalized": "unknown",
    "raw": "",
    "status_source": "none"
  }
}
```

Public regression tests: `lab_tests/community_subscription_status_tag.php`.


<!-- community-core:payment-method-tag -->
### Canonical payment method tag for offline checks

Gives admin payments, payment links, invoices and API query offline checks a shared deterministic way to normalize multilingual method request fields to canonical payment method tags and handling categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/payment_method_tag.php`, API `App\Support\Community\PaymentMethodTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core: `src/App/Support/Community/payment_method_tag.php`, API `App\Support\Community\PaymentMethodTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from payment method request to canonical method tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \stdClass, \InvalidArgumentException, \TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field m0 is payload.method when payload.method is a string, else payload.metodo when payload.metodo is a string, else payload.payment_method when payload.payment_method is a string, else missing. method_source is method when m0 came from payload.method, metodo when from payload.metodo, payment_method when from payload.payment_method, else none. has_method is true when m0 is present as string else false. raw is m0 when has_method else empty string. normalized is pix when raw equals pix, card when raw equals card or cartao, boleto when raw equals boleto or bank_slip, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. category is instant when normalized is pix, card when normalized is card, slip when normalized is boleto, else unknown. is_card is true when normalized is card else false. is_instant is true when normalized is pix else false. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of pix, card, boleto or unknown, method_source one of method, metodo, payment_method or none, has_method boolean, category one of instant, card, slip or unknown, is_card boolean, is_instant boolean, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "method": "pix"
  },
  "output": {
    "category": "instant",
    "has_method": true,
    "is_card": false,
    "is_instant": true,
    "is_known": true,
    "method_source": "method",
    "normalized": "pix",
    "raw": "pix"
  }
}
```

```json
{
  "input": {
    "metodo": "cartao"
  },
  "output": {
    "category": "card",
    "has_method": true,
    "is_card": true,
    "is_instant": false,
    "is_known": true,
    "method_source": "metodo",
    "normalized": "card",
    "raw": "cartao"
  }
}
```

```json
{
  "input": {},
  "output": {
    "category": "unknown",
    "has_method": false,
    "is_card": false,
    "is_instant": false,
    "is_known": false,
    "method_source": "none",
    "normalized": "unknown",
    "raw": ""
  }
}
```

Public regression tests: `lab_tests/community_payment_method_tag.php`.


<!-- community-core:list-sort-tag -->
### Canonical list sort tag for offline checks

Gives admin payments, customers, invoices, subscriptions and API list offline checks a shared deterministic way to normalize multilingual sort request fields to canonical list sort tags and field/direction categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/list_sort_tag.php`, API `App\Support\Community\ListSortTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core: `src/App/Support/Community/list_sort_tag.php`, API `App\Support\Community\ListSortTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from list sort request to canonical sort tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \stdClass, \InvalidArgumentException, \TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field s0 is payload.sort when payload.sort is a string, else payload.ordenacao when payload.ordenacao is a string, else payload.order_by when payload.order_by is a string, else missing. sort_source is sort when s0 came from payload.sort, ordenacao when from payload.ordenacao, order_by when from payload.order_by, else none. has_sort is true when s0 is present as string else false. raw is s0 when has_sort else empty string. normalized is recent when raw equals recent or recentes or newest, oldest when raw equals oldest or mais_antigos, amount_high when raw equals amount_desc or valor_desc or maior_valor, amount_low when raw equals amount_asc or valor_asc or menor_valor, name_az when raw equals name_asc or nome_asc or nome_az, name_za when raw equals name_desc or nome_desc or nome_za, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. field is date when normalized is recent or normalized is oldest, amount when normalized is amount_high or normalized is amount_low, name when normalized is name_az or normalized is name_za, else unknown. direction is descending when normalized is recent or normalized is amount_high or normalized is name_za, ascending when normalized is oldest or normalized is amount_low or normalized is name_az, else unknown. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of recent, oldest, amount_high, amount_low, name_az, name_za or unknown, sort_source one of sort, ordenacao, order_by or none, has_sort boolean, field one of date, amount, name or unknown, direction one of descending, ascending or unknown, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not paginate, redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "sort": "recent"
  },
  "output": {
    "direction": "descending",
    "field": "date",
    "has_sort": true,
    "is_known": true,
    "normalized": "recent",
    "raw": "recent",
    "sort_source": "sort"
  }
}
```

```json
{
  "input": {
    "ordenacao": "valor_desc"
  },
  "output": {
    "direction": "descending",
    "field": "amount",
    "has_sort": true,
    "is_known": true,
    "normalized": "amount_high",
    "raw": "valor_desc",
    "sort_source": "ordenacao"
  }
}
```

```json
{
  "input": {},
  "output": {
    "direction": "unknown",
    "field": "unknown",
    "has_sort": false,
    "is_known": false,
    "normalized": "unknown",
    "raw": "",
    "sort_source": "none"
  }
}
```

Public regression tests: `lab_tests/community_list_sort_tag.php`.


<!-- community-core:user-role-tag -->
### Canonical user role tag for offline checks

Gives admin users, auth, access logs and API list offline checks a shared deterministic way to normalize multilingual role request fields to canonical user role tags and privilege categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/user_role_tag.php`, API `App\Support\Community\UserRoleTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core: `src/App/Support/Community/user_role_tag.php`, API `App\Support\Community\UserRoleTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from user role request to canonical role tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \stdClass, \InvalidArgumentException, \TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field r0 is payload.role when payload.role is a string, else payload.papel when payload.papel is a string, else payload.user_role when payload.user_role is a string, else missing. role_source is role when r0 came from payload.role, papel when from payload.papel, user_role when from payload.user_role, else none. has_role is true when r0 is present as string else false. raw is r0 when has_role else empty string. normalized is admin when raw equals admin or administrador, manager when raw equals manager or gerente, operator when raw equals operator or operador, viewer when raw equals viewer or leitor or visualizador, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. category is full when normalized is admin, elevated when normalized is manager, operational when normalized is operator, readonly when normalized is viewer, else unknown. is_privileged is true when normalized is admin or normalized is manager else false. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of admin, manager, operator, viewer or unknown, role_source one of role, papel, user_role or none, has_role boolean, category one of full, elevated, operational, readonly or unknown, is_privileged boolean, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not paginate, redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "role": "admin"
  },
  "output": {
    "category": "full",
    "has_role": true,
    "is_known": true,
    "is_privileged": true,
    "normalized": "admin",
    "raw": "admin",
    "role_source": "role"
  }
}
```

```json
{
  "input": {
    "papel": "operador"
  },
  "output": {
    "category": "operational",
    "has_role": true,
    "is_known": true,
    "is_privileged": false,
    "normalized": "operator",
    "raw": "operador",
    "role_source": "papel"
  }
}
```

```json
{
  "input": {},
  "output": {
    "category": "unknown",
    "has_role": false,
    "is_known": false,
    "is_privileged": false,
    "normalized": "unknown",
    "raw": "",
    "role_source": "none"
  }
}
```

Public regression tests: `lab_tests/community_user_role_tag.php`.


<!-- community-core:customer-document-tag -->
### Canonical customer document tag for offline checks

Gives admin customers, verification, reports and API query offline checks a shared deterministic way to normalize multilingual document request fields to canonical customer document tags and holder categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/customer_document_tag.php`, API `App\Support\Community\CustomerDocumentTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core: `src/App/Support/Community/customer_document_tag.php`, API `App\Support\Community\CustomerDocumentTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from customer document request to canonical document tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \stdClass, \InvalidArgumentException, \TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field d0 is payload.document when payload.document is a string, else payload.documento when payload.documento is a string, else payload.customer_document when payload.customer_document is a string, else missing. document_source is document when d0 came from payload.document, documento when from payload.documento, customer_document when from payload.customer_document, else none. has_document is true when d0 is present as string else false. raw is d0 when has_document else empty string. normalized is cpf when raw equals cpf or CPF, cnpj when raw equals cnpj or CNPJ, passport when raw equals passport or passaporte, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. category is individual when normalized is cpf, company when normalized is cnpj, foreign when normalized is passport, else unknown. is_brazilian is true when normalized is cpf or normalized is cnpj else false. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of cpf, cnpj, passport or unknown, document_source one of document, documento, customer_document or none, has_document boolean, category one of individual, company, foreign or unknown, is_brazilian boolean, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not validate document numbers, verify identity, redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "document": "cpf"
  },
  "output": {
    "category": "individual",
    "document_source": "document",
    "has_document": true,
    "is_brazilian": true,
    "is_known": true,
    "normalized": "cpf",
    "raw": "cpf"
  }
}
```

```json
{
  "input": {
    "documento": "passaporte"
  },
  "output": {
    "category": "foreign",
    "document_source": "documento",
    "has_document": true,
    "is_brazilian": false,
    "is_known": true,
    "normalized": "passport",
    "raw": "passaporte"
  }
}
```

```json
{
  "input": {},
  "output": {
    "category": "unknown",
    "document_source": "none",
    "has_document": false,
    "is_brazilian": false,
    "is_known": false,
    "normalized": "unknown",
    "raw": ""
  }
}
```

Public regression tests: `lab_tests/community_customer_document_tag.php`.


<!-- community-core:environment-tag -->
### Canonical account environment tag for offline checks

Gives admin sites, accounts, diagnostics and API query offline checks a shared deterministic way to normalize multilingual account environment request fields to canonical environment tags and live categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/environment_tag.php`, API `App\Support\Community\EnvironmentTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core: `src/App/Support/Community/environment_tag.php`, API `App\Support\Community\EnvironmentTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from account environment request to canonical environment tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \stdClass, \InvalidArgumentException, \TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field e0 is payload.environment when payload.environment is a string, else payload.ambiente when payload.ambiente is a string, else payload.account_mode when payload.account_mode is a string, else missing. environment_source is environment when e0 came from payload.environment, ambiente when from payload.ambiente, account_mode when from payload.account_mode, else none. has_environment is true when e0 is present as string else false. raw is e0 when has_environment else empty string. normalized is test when raw equals test or teste or sandbox or homologacao, live when raw equals live or production or producao, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. category is non_live when normalized is test, live when normalized is live, else unknown. is_live is true when normalized is live else false. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of test, live or unknown, environment_source one of environment, ambiente, account_mode or none, has_environment boolean, category one of non_live, live or unknown, is_live boolean, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "environment": "sandbox"
  },
  "output": {
    "category": "non_live",
    "environment_source": "environment",
    "has_environment": true,
    "is_known": true,
    "is_live": false,
    "normalized": "test",
    "raw": "sandbox"
  }
}
```

```json
{
  "input": {
    "ambiente": "producao"
  },
  "output": {
    "category": "live",
    "environment_source": "ambiente",
    "has_environment": true,
    "is_known": true,
    "is_live": true,
    "normalized": "live",
    "raw": "producao"
  }
}
```

```json
{
  "input": {},
  "output": {
    "category": "unknown",
    "environment_source": "none",
    "has_environment": false,
    "is_known": false,
    "is_live": false,
    "normalized": "unknown",
    "raw": ""
  }
}
```

Public regression tests: `lab_tests/community_environment_tag.php`.


<!-- community-core:export-format-tag -->
### Canonical export format tag for offline checks

Gives admin reports, payments, invoices, subscriptions and API list offline checks a shared deterministic way to normalize multilingual export format request fields to canonical format tags and content categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/export_format_tag.php`, API `App\Support\Community\ExportFormatTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core: `src/App/Support/Community/export_format_tag.php`, API `App\Support\Community\ExportFormatTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from export format request to canonical export tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \stdClass, \InvalidArgumentException, \TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field f0 is payload.format when payload.format is a string, else payload.formato when payload.formato is a string, else payload.export_format when payload.export_format is a string, else missing. format_source is format when f0 came from payload.format, formato when from payload.formato, export_format when from payload.export_format, else none. has_format is true when f0 is present as string else false. raw is f0 when has_format else empty string. normalized is csv when raw equals csv, json when raw equals json, xls when raw equals xls or xlsx or excel or planilha, pdf when raw equals pdf, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. category is tabular when normalized is csv or normalized is xls, structured when normalized is json, document when normalized is pdf, else unknown. is_tabular is true when category is tabular else false. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of csv, json, xls, pdf or unknown, format_source one of format, formato, export_format or none, has_format boolean, category one of tabular, structured, document or unknown, is_tabular boolean, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "format": "csv"
  },
  "output": {
    "category": "tabular",
    "format_source": "format",
    "has_format": true,
    "is_known": true,
    "is_tabular": true,
    "normalized": "csv",
    "raw": "csv"
  }
}
```

```json
{
  "input": {
    "formato": "planilha"
  },
  "output": {
    "category": "tabular",
    "format_source": "formato",
    "has_format": true,
    "is_known": true,
    "is_tabular": true,
    "normalized": "xls",
    "raw": "planilha"
  }
}
```

```json
{
  "input": {},
  "output": {
    "category": "unknown",
    "format_source": "none",
    "has_format": false,
    "is_known": false,
    "is_tabular": false,
    "normalized": "unknown",
    "raw": ""
  }
}
```

Public regression tests: `lab_tests/community_export_format_tag.php`.


<!-- community-core:admin-log-action-tag -->
### Canonical admin log action tag for offline checks

Gives admin access logs, api logs, forward logs and diagnostics offline checks a shared deterministic way to normalize multilingual log action request fields to canonical action tags and review categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/admin_log_action_tag.php`, API `App\Support\Community\AdminLogActionTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core: `src/App/Support/Community/admin_log_action_tag.php`, API `App\Support\Community\AdminLogActionTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from admin log action request to canonical action tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \stdClass, \InvalidArgumentException, \TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field a0 is payload.action when payload.action is a string, else payload.acao when payload.acao is a string, else payload.log_action when payload.log_action is a string, else missing. action_source is action when a0 came from payload.action, acao when from payload.acao, log_action when from payload.log_action, else none. has_action is true when a0 is present as string else false. raw is a0 when has_action else empty string. normalized is login when raw equals login or entrar, logout when raw equals logout or sair, create when raw equals create or criar, update when raw equals update or atualizar, delete when raw equals delete or excluir, view when raw equals view or visualizar, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. category is auth when normalized is login or normalized is logout, mutation when normalized is create or normalized is update or normalized is delete, read when normalized is view, else unknown. is_mutating is true when category is mutation else false. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of login, logout, create, update, delete, view or unknown, action_source one of action, acao, log_action or none, has_action boolean, category one of auth, mutation, read or unknown, is_mutating boolean, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "action": "criar"
  },
  "output": {
    "action_source": "action",
    "category": "mutation",
    "has_action": true,
    "is_known": true,
    "is_mutating": true,
    "normalized": "create",
    "raw": "criar"
  }
}
```

```json
{
  "input": {
    "acao": "sair"
  },
  "output": {
    "action_source": "acao",
    "category": "auth",
    "has_action": true,
    "is_known": true,
    "is_mutating": false,
    "normalized": "logout",
    "raw": "sair"
  }
}
```

```json
{
  "input": {},
  "output": {
    "action_source": "none",
    "category": "unknown",
    "has_action": false,
    "is_known": false,
    "is_mutating": false,
    "normalized": "unknown",
    "raw": ""
  }
}
```

Public regression tests: `lab_tests/community_admin_log_action_tag.php`.


<!-- community-core:utils-tool-tag -->
### Canonical admin utils tool tag for offline checks

Gives admin utils, help, diagnostics and API query offline checks a shared deterministic way to normalize multilingual utils tool request fields to canonical tool tags and review categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/utils_tool_tag.php`, API `App\Support\Community\UtilsToolTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core, API runFeature(payload). This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from admin utils tool request to canonical tool tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \stdClass, \InvalidArgumentException, \TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field t0 is payload.tool when payload.tool is a string, else payload.ferramenta when payload.ferramenta is a string, else payload.utils_tool when payload.utils_tool is a string, else missing. tool_source is tool when t0 came from payload.tool, ferramenta when from payload.ferramenta, utils_tool when from payload.utils_tool, else none. has_tool is true when t0 is present as string else false. raw is t0 when has_tool else empty string. normalized is boleto when raw equals boleto or boletos, card when raw equals card or cartao or cards, pix when raw equals pix, link when raw equals link or links or payment_link, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. category is slip when normalized is boleto, card when normalized is card, instant when normalized is pix, shareable when normalized is link, else unknown. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of boleto, card, pix, link or unknown, tool_source one of tool, ferramenta, utils_tool or none, has_tool boolean, category one of slip, card, instant, shareable or unknown, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "tool": "boleto"
  },
  "output": {
    "category": "slip",
    "has_tool": true,
    "is_known": true,
    "normalized": "boleto",
    "raw": "boleto",
    "tool_source": "tool"
  }
}
```

```json
{
  "input": {
    "ferramenta": "pix"
  },
  "output": {
    "category": "instant",
    "has_tool": true,
    "is_known": true,
    "normalized": "pix",
    "raw": "pix",
    "tool_source": "ferramenta"
  }
}
```

```json
{
  "input": {},
  "output": {
    "category": "unknown",
    "has_tool": false,
    "is_known": false,
    "normalized": "unknown",
    "raw": "",
    "tool_source": "none"
  }
}
```

Public regression tests: `lab_tests/community_utils_tool_tag.php`.


<!-- community-core:webhook-event-tag -->
### Canonical webhook event tag for offline checks

Gives webhooks, forward logs, subscriptions and API query offline checks a shared deterministic way to normalize multilingual webhook event request fields to canonical event tags and domain categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/webhook_event_tag.php`, API `App\Support\Community\WebhookEventTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core: `src/App/Support/Community/webhook_event_tag.php`, API `App\Support\Community\WebhookEventTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from webhook event request to canonical event tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \stdClass, \InvalidArgumentException, \TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field e0 is payload.event when payload.event is a string, else payload.evento when payload.evento is a string, else payload.webhook_event when payload.webhook_event is a string, else missing. event_source is event when e0 came from payload.event, evento when from payload.evento, webhook_event when from payload.webhook_event, else none. has_event is true when e0 is present as string else false. raw is e0 when has_event else empty string. normalized is payment_created when raw equals payment_created or pagamento_criado, payment_paid when raw equals payment_paid or pagamento_pago, payment_failed when raw equals payment_failed or pagamento_falhou, subscription_created when raw equals subscription_created or assinatura_criada, subscription_canceled when raw equals subscription_canceled or subscription_cancelled or assinatura_cancelada, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. category is payment when normalized is payment_created or normalized is payment_paid or normalized is payment_failed, subscription when normalized is subscription_created or normalized is subscription_canceled, else unknown. is_payment is true when category is payment else false. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of payment_created, payment_paid, payment_failed, subscription_created, subscription_canceled or unknown, event_source one of event, evento, webhook_event or none, has_event boolean, category one of payment, subscription or unknown, is_payment boolean, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "event": "payment_paid"
  },
  "output": {
    "category": "payment",
    "event_source": "event",
    "has_event": true,
    "is_known": true,
    "is_payment": true,
    "normalized": "payment_paid",
    "raw": "payment_paid"
  }
}
```

```json
{
  "input": {
    "evento": "assinatura_cancelada"
  },
  "output": {
    "category": "subscription",
    "event_source": "evento",
    "has_event": true,
    "is_known": true,
    "is_payment": false,
    "normalized": "subscription_canceled",
    "raw": "assinatura_cancelada"
  }
}
```

```json
{
  "input": {},
  "output": {
    "category": "unknown",
    "event_source": "none",
    "has_event": false,
    "is_known": false,
    "is_payment": false,
    "normalized": "unknown",
    "raw": ""
  }
}
```

Public regression tests: `lab_tests/community_webhook_event_tag.php`.


<!-- community-core:verify-target-tag -->
### Canonical verification target tag for offline checks

Gives admin verify, dashboard and API query offline checks a shared deterministic way to normalize multilingual verification target request fields to canonical verify tags and domain categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/verify_target_tag.php`, API `App\Support\Community\VerifyTargetTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core: `src/App/Support/Community/verify_target_tag.php`, API `App\Support\Community\VerifyTargetTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from verification target request to canonical verify target tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \stdClass, \InvalidArgumentException, \TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field t0 is payload.target when payload.target is a string, else payload.alvo when payload.alvo is a string, else payload.verify_target when payload.verify_target is a string, else missing. target_source is target when t0 came from payload.target, alvo when from payload.alvo, verify_target when from payload.verify_target, else none. has_target is true when t0 is present as string else false. raw is t0 when has_target else empty string. normalized is payments when raw equals payments or pagamentos or pagamento, invoices when raw equals invoices or invoice or faturas or fatura, subscriptions when raw equals subscriptions or subscription or assinaturas or assinatura, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. category is transaction when normalized is payments, billing when normalized is invoices, recurring when normalized is subscriptions, else unknown. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of payments, invoices, subscriptions or unknown, target_source one of target, alvo, verify_target or none, has_target boolean, category one of transaction, billing, recurring or unknown, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "target": "payments"
  },
  "output": {
    "category": "transaction",
    "has_target": true,
    "is_known": true,
    "normalized": "payments",
    "raw": "payments",
    "target_source": "target"
  }
}
```

```json
{
  "input": {
    "alvo": "faturas"
  },
  "output": {
    "category": "billing",
    "has_target": true,
    "is_known": true,
    "normalized": "invoices",
    "raw": "faturas",
    "target_source": "alvo"
  }
}
```

```json
{
  "input": {},
  "output": {
    "category": "unknown",
    "has_target": false,
    "is_known": false,
    "normalized": "unknown",
    "raw": "",
    "target_source": "none"
  }
}
```

Public regression tests: `lab_tests/community_verify_target_tag.php`.


<!-- community-core:account-provider-tag -->
### Canonical account provider tag for offline checks

Gives admin accounts, sites, diagnostics and API query offline checks a shared deterministic way to normalize multilingual provider request fields to canonical account provider tags and handling categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/account_provider_tag.php`, API `App\Support\Community\AccountProviderTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core: `src/App/Support/Community/account_provider_tag.php`, API `App\Support\Community\AccountProviderTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from account provider request to canonical provider tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \stdClass, \InvalidArgumentException, \TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field p0 is payload.provider when payload.provider is a string, else payload.provedor when payload.provedor is a string, else payload.account_provider when payload.account_provider is a string, else missing. provider_source is provider when p0 came from payload.provider, provedor when from payload.provedor, account_provider when from payload.account_provider, else none. has_provider is true when p0 is present as string else false. raw is p0 when has_provider else empty string. normalized is stone when raw equals stone or stone_pagamentos, pagarme when raw equals pagarme, manual when raw equals manual or interno, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. category is external when normalized is stone or normalized is pagarme, internal when normalized is manual, else unknown. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of stone, pagarme, manual or unknown, provider_source one of provider, provedor, account_provider or none, has_provider boolean, category one of external, internal or unknown, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "provider": "stone"
  },
  "output": {
    "category": "external",
    "has_provider": true,
    "is_known": true,
    "normalized": "stone",
    "provider_source": "provider",
    "raw": "stone"
  }
}
```

```json
{
  "input": {
    "provedor": "interno"
  },
  "output": {
    "category": "internal",
    "has_provider": true,
    "is_known": true,
    "normalized": "manual",
    "provider_source": "provedor",
    "raw": "interno"
  }
}
```

```json
{
  "input": {},
  "output": {
    "category": "unknown",
    "has_provider": false,
    "is_known": false,
    "normalized": "unknown",
    "provider_source": "none",
    "raw": ""
  }
}
```

Public regression tests: `lab_tests/community_account_provider_tag.php`.


<!-- community-core:forward-outcome-tag -->
### Canonical forward outcome tag for offline checks

Gives admin forward logs, retry, webhooks and diagnostics offline checks a shared deterministic way to normalize multilingual forward outcome request fields to canonical delivery tags and handling categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/forward_outcome_tag.php`, API `App\Support\Community\ForwardOutcomeTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core: `src/App/Support/Community/forward_outcome_tag.php`, API `App\\Support\\Community\\ForwardOutcomeTag\\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from forward outcome request to canonical outcome tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \\stdClass, \\InvalidArgumentException, \\TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field f0 is payload.outcome when payload.outcome is a string, else payload.resultado when payload.resultado is a string, else payload.forward_status when payload.forward_status is a string, else missing. outcome_source is outcome when f0 came from payload.outcome, resultado when from payload.resultado, forward_status when from payload.forward_status, else none. has_outcome is true when f0 is present as string else false. raw is f0 when has_outcome else empty string. normalized is delivered when raw equals delivered or entregue, pending when raw equals pending or pendente, failed when raw equals failed or falha, retrying when raw equals retrying or retry or tentando_novamente, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. category is success when normalized is delivered, open when normalized is pending or normalized is retrying, failure when normalized is failed, else unknown. is_terminal is true when normalized is delivered or normalized is failed else false. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of delivered, pending, failed, retrying or unknown, outcome_source one of outcome, resultado, forward_status or none, has_outcome boolean, category one of success, open, failure or unknown, is_terminal boolean, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "outcome": "delivered"
  },
  "output": {
    "category": "success",
    "has_outcome": true,
    "is_known": true,
    "is_terminal": true,
    "normalized": "delivered",
    "outcome_source": "outcome",
    "raw": "delivered"
  }
}
```

```json
{
  "input": {
    "resultado": "pendente"
  },
  "output": {
    "category": "open",
    "has_outcome": true,
    "is_known": true,
    "is_terminal": false,
    "normalized": "pending",
    "outcome_source": "resultado",
    "raw": "pendente"
  }
}
```

```json
{
  "input": {},
  "output": {
    "category": "unknown",
    "has_outcome": false,
    "is_known": false,
    "is_terminal": false,
    "normalized": "unknown",
    "outcome_source": "none",
    "raw": ""
  }
}
```

Public regression tests: `lab_tests/community_forward_outcome_tag.php`.


<!-- community-core:card-brand-tag -->
### Canonical card brand tag for offline checks

Gives admin payments, verify payments, card utilities and API query offline checks a shared deterministic way to normalize multilingual card brand request fields to canonical brand tags and handling categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/card_brand_tag.php`, API `App\Support\Community\CardBrandTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core: `src/App/Support/Community/card_brand_tag.php`, API `App\Support\Community\CardBrandTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from card brand request to canonical brand tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \stdClass, \InvalidArgumentException, \TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field b0 is payload.brand when payload.brand is a string, else payload.bandeira when payload.bandeira is a string, else payload.card_brand when payload.card_brand is a string, else missing. brand_source is brand when b0 came from payload.brand, bandeira when from payload.bandeira, card_brand when from payload.card_brand, else none. has_brand is true when b0 is present as string else false. raw is b0 when has_brand else empty string. normalized is visa when raw equals visa, mastercard when raw equals mastercard or master, elo when raw equals elo, amex when raw equals amex or american_express, hipercard when raw equals hipercard, generic when raw equals generic or generica or generico, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. category is international when normalized is visa or normalized is mastercard or normalized is amex, domestic when normalized is elo or normalized is hipercard, fallback when normalized is generic, else unknown. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of visa, mastercard, elo, amex, hipercard, generic or unknown, brand_source one of brand, bandeira, card_brand or none, has_brand boolean, category one of international, domestic, fallback or unknown, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "brand": "visa"
  },
  "output": {
    "brand_source": "brand",
    "category": "international",
    "has_brand": true,
    "is_known": true,
    "normalized": "visa",
    "raw": "visa"
  }
}
```

```json
{
  "input": {
    "bandeira": "elo"
  },
  "output": {
    "brand_source": "bandeira",
    "category": "domestic",
    "has_brand": true,
    "is_known": true,
    "normalized": "elo",
    "raw": "elo"
  }
}
```

```json
{
  "input": {},
  "output": {
    "brand_source": "none",
    "category": "unknown",
    "has_brand": false,
    "is_known": false,
    "normalized": "unknown",
    "raw": ""
  }
}
```

Public regression tests: `lab_tests/community_card_brand_tag.php`.


<!-- community-core:admin-report-tag -->
### Canonical admin report tag for offline checks

Gives admin reports, accounts reports, metrics and API list offline checks a shared deterministic way to normalize multilingual report request fields to canonical report tags and handling categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/admin_report_tag.php`, API `App\Support\Community\AdminReportTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core: `src/App/Support/Community/admin_report_tag.php`, API `App\\Support\\Community\\AdminReportTag\\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from admin report request to canonical report tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \\stdClass, \\InvalidArgumentException, \\TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field r0 is payload.report when payload.report is a string, else payload.relatorio when payload.relatorio is a string, else payload.report_type when payload.report_type is a string, else missing. report_source is report when r0 came from payload.report, relatorio when from payload.relatorio, report_type when from payload.report_type, else none. has_report is true when r0 is present as string else false. raw is r0 when has_report else empty string. normalized is payments when raw equals payments or pagamentos, subscriptions when raw equals subscriptions or assinaturas, invoices when raw equals invoices or faturas, accounts when raw equals accounts or contas, forwards when raw equals forwards or encaminhamentos, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. category is financial when normalized is payments or normalized is invoices, recurring when normalized is subscriptions, operational when normalized is accounts or normalized is forwards, else unknown. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of payments, subscriptions, invoices, accounts, forwards or unknown, report_source one of report, relatorio, report_type or none, has_report boolean, category one of financial, recurring, operational or unknown, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "report": "payments"
  },
  "output": {
    "category": "financial",
    "has_report": true,
    "is_known": true,
    "normalized": "payments",
    "raw": "payments",
    "report_source": "report"
  }
}
```

```json
{
  "input": {
    "relatorio": "assinaturas"
  },
  "output": {
    "category": "recurring",
    "has_report": true,
    "is_known": true,
    "normalized": "subscriptions",
    "raw": "assinaturas",
    "report_source": "relatorio"
  }
}
```

```json
{
  "input": {},
  "output": {
    "category": "unknown",
    "has_report": false,
    "is_known": false,
    "normalized": "unknown",
    "raw": "",
    "report_source": "none"
  }
}
```

Public regression tests: `lab_tests/community_admin_report_tag.php`.


<!-- community-core:site-status-tag -->
### Canonical site status tag for offline checks

Gives admin sites, accounts, users and API list offline checks a shared deterministic way to normalize multilingual site status request fields to canonical site tags and availability categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/site_status_tag.php`, API `App\Support\Community\SiteStatusTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core: `src/App/Support/Community/site_status_tag.php`, API `App\Support\Community\SiteStatusTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from site status request to canonical status tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \stdClass, \InvalidArgumentException, \TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field s0 is payload.status when payload.status is a string, else payload.situacao when payload.situacao is a string, else payload.site_status when payload.site_status is a string, else missing. site_source is status when s0 came from payload.status, situacao when from payload.situacao, site_status when from payload.site_status, else none. has_site_status is true when s0 is present as string else false. raw is s0 when has_site_status else empty string. normalized is active when raw equals active or ativo or ativa, inactive when raw equals inactive or inativo or inativa, suspended when raw equals suspended or suspenso or suspensa, archived when raw equals archived or arquivado or arquivada, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. category is live when normalized is active, dormant when normalized is inactive or normalized is archived, blocked when normalized is suspended, else unknown. is_active is true when normalized is active else false. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of active, inactive, suspended, archived or unknown, site_source one of status, situacao, site_status or none, has_site_status boolean, category one of live, dormant, blocked or unknown, is_active boolean, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "status": "active"
  },
  "output": {
    "category": "live",
    "has_site_status": true,
    "is_active": true,
    "is_known": true,
    "normalized": "active",
    "raw": "active",
    "site_source": "status"
  }
}
```

```json
{
  "input": {
    "situacao": "suspensa"
  },
  "output": {
    "category": "blocked",
    "has_site_status": true,
    "is_active": false,
    "is_known": true,
    "normalized": "suspended",
    "raw": "suspensa",
    "site_source": "situacao"
  }
}
```

```json
{
  "input": {},
  "output": {
    "category": "unknown",
    "has_site_status": false,
    "is_active": false,
    "is_known": false,
    "normalized": "unknown",
    "raw": "",
    "site_source": "none"
  }
}
```

Public regression tests: `lab_tests/community_site_status_tag.php`.


<!-- community-core:admin-task-tag -->
### Canonical admin task tag for offline checks

Gives admin tasks, diagnostics, health and retention offline checks a shared deterministic way to normalize multilingual task request fields to canonical task tags and domain categories without database, network, filesystem writes or provider calls and without deciding delivery, authorization, pricing, billing, settlement or financial outcome.

Reusable deterministic JSON core: `src/App/Support/Community/admin_task_tag.php`, API `App\Support\Community\AdminTaskTag\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change.

Reusable deterministic JSON core: `src/App/Support/Community/admin_task_tag.php`, API `App\\Support\\Community\\AdminTaskTag\\runFeature(payload)`. This module is independently tested; application/UI integration is outside this change. Exposes runFeature(payload) as a pure deterministic JSON-to-JSON transform from admin task request to canonical task tag with no side effects, I/O, env, processes, time, randomness, callbacks or dependencies. PHP uses global function runFeature with stdClass for objects and arrays for arrays using only foreach, get_object_vars and fixed property reads plus strict string equality, comparisons and boolean assignment and no use/imports/classes/static/global variables, variable/parenthesized function calls, callback builtins, object methods, I/O or extensions. Uses fully qualified \\stdClass, \\InvalidArgumentException, \\TypeError only when needed. All mutable state local; preserve input. Input payload when not an object behaves as empty object. Field t0 is payload.task when payload.task is a string, else payload.tarefa when payload.tarefa is a string, else payload.admin_task when payload.admin_task is a string, else missing. task_source is task when t0 came from payload.task, tarefa when from payload.tarefa, admin_task when from payload.admin_task, else none. has_task is true when t0 is present as string else false. raw is t0 when has_task else empty string. normalized is process_webhooks when raw equals process_webhooks or processar_webhooks, retry_forwards when raw equals retry_forwards or reenviar_forwards, health when raw equals health or saude, diagnostics when raw equals diagnostics or diagnostico, retention when raw equals retention or retencao, else unknown. Matching is exact case-sensitive with no trimming, folding or pattern calls. category is delivery when normalized is process_webhooks or normalized is retry_forwards, system when normalized is health or normalized is diagnostics, maintenance when normalized is retention, else unknown. is_delivery is true when category is delivery else false. is_system is true when category is system else false. is_known is true when normalized is not unknown else false. Output is an object with raw string, normalized one of process_webhooks, retry_forwards, health, diagnostics, retention or unknown, task_source one of task, tarefa, admin_task or none, has_task boolean, category one of delivery, system, maintenance or unknown, is_delivery boolean, is_system boolean, and is_known boolean. Extra input fields are ignored. Input is never mutated. Same input always yields identical output. Does not query database, network or providers and does not decide delivery, authorization, pricing, billing, settlement or financial outcome. Does not redact secrets, prefix formulas, verify signatures, persist events or process payments.

Examples (JSON input and expected output):

```json
{
  "input": {
    "task": "process_webhooks"
  },
  "output": {
    "category": "delivery",
    "has_task": true,
    "is_delivery": true,
    "is_known": true,
    "is_system": false,
    "normalized": "process_webhooks",
    "raw": "process_webhooks",
    "task_source": "task"
  }
}
```

```json
{
  "input": {
    "tarefa": "diagnostico"
  },
  "output": {
    "category": "system",
    "has_task": true,
    "is_delivery": false,
    "is_known": true,
    "is_system": true,
    "normalized": "diagnostics",
    "raw": "diagnostico",
    "task_source": "tarefa"
  }
}
```

```json
{
  "input": {},
  "output": {
    "category": "unknown",
    "has_task": false,
    "is_delivery": false,
    "is_known": false,
    "is_system": false,
    "normalized": "unknown",
    "raw": "",
    "task_source": "none"
  }
}
```

Public regression tests: `lab_tests/community_admin_task_tag.php`.
