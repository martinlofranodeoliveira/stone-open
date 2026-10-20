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
