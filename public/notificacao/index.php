<?php

declare(strict_types=1);

// Legacy forwarding requires a separate, explicit configuration by the user.
if (getenv('STONE_ENABLE_LEGACY_WEBHOOK') !== 'true') {
    http_response_code(503);
    exit('Legacy webhook forwarding is disabled.');
}


$logWebhookFile = __DIR__ . '/webhook_stone.txt';
$logDebugFile = __DIR__ . '/logStone.log';
$logForwardFile = __DIR__ . '/log.log';

$tokenAdesao = '';
$subscriptionId = '';

// Configure os endpoints por siteId aqui.
$siteEndpoints = json_decode((string)getenv('STONE_LEGACY_ENDPOINTS_JSON'), true) ?: [];
function log_append(string $file, string $message): void
{
    file_put_contents($file, PHP_EOL . $message, FILE_APPEND);
}

function normalize_action(?string $action): string
{
    if ($action === null) {
        return '';
    }

    $action = trim($action);
    if ($action === '') {
        return '';
    }

    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($action);
    }

    return strtoupper($action);
}

function normalize_money(?string $value): string
{
    if ($value === null) {
        return '';
    }

    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } else {
        $value = str_replace(',', '.', $value);
    }

    return number_format((float)$value, 2, '.', '');
}

function map_payment_type(?string $method): string
{
    // Ajuste/adicione novos métodos de pagamento aqui.
    if ($method === null) {
        return '';
    }

    $method = normalize_action($method);
    switch ($method) {
        case 'CREDIT_CARD':
        case 'CREDIT':
        case 'CREDITCARD':
        case 'CARTAO':
        case 'CARTAO_CREDITO':
        case 'CARD':
            return '1';
        case 'PIX':
            return '11';
        case 'BOLETO':
        case 'BANK_SLIP':
            return '2';
        default:
            return '';
    }
}

function normalize_payment_method_name(?string $method): string
{
    if ($method === null) {
        return '';
    }

    $method = normalize_action($method);
    if ($method === '') {
        return '';
    }

    $map = [
        'CREDIT' => 'CREDIT_CARD',
        'CREDITCARD' => 'CREDIT_CARD',
        'CARD' => 'CREDIT_CARD',
        'CARTAO' => 'CREDIT_CARD',
        'CARTAO_CREDITO' => 'CREDIT_CARD',
        'BANK_SLIP' => 'BOLETO',
    ];

    return $map[$method] ?? $method;
}

function extract_payment_method(array $payload): string
{
    $candidates = [
        $payload['metodo_pagamento'] ?? null,
        $payload['tipo_metodo_pagamento'] ?? null,
        $payload['summary']['method'] ?? null,
        $payload['pagamento']['metodo_pagamento'] ?? null,
        $payload['pagamento']['tipo_metodo_pagamento'] ?? null,
        $payload['dados']['charges'][0]['payment_method'] ?? null,
        $payload['dados']['charges'][0]['last_transaction']['transaction_type'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return normalize_payment_method_name($candidate);
        }
    }

    return '';
}

function extract_subscription_id(array $payload): string
{
    $candidates = [
        $payload['subscription_id'] ?? null,
        $payload['id_assinatura_stone'] ?? null,
        $payload['assinatura']['id'] ?? null,
        $payload['assinatura']['subscription_id'] ?? null,
        $payload['assinatura']['id_assinatura_stone'] ?? null,
        $payload['pagamento']['subscription_id'] ?? null,
        $payload['summary']['subscription_id'] ?? null,
        $payload['summary']['subscription'] ?? null,
        $payload['dados']['subscription_id'] ?? null,
        $payload['dados']['charges'][0]['subscription_id'] ?? null,
        $payload['dados']['charges'][0]['subscription']['id'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate)) {
            $candidate = trim($candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    return '';
}

function map_subscription_status(?string $raw): string
{
    // Ajuste/adicione novos status de assinatura aqui.
    if ($raw === null) {
        return '';
    }

    $raw = normalize_action($raw);
    if ($raw === '') {
        return '';
    }

    $map = [
        'ACTIVE' => 'ATIVA',
        'ATIVA' => 'ATIVA',
        'SUSPENDED' => 'SUSPENSA',
        'SUSPENSA' => 'SUSPENSA',
        'CANCELED' => 'CANCELADA',
        'CANCELADA' => 'CANCELADA',
        'PAUSED' => 'SUSPENSA',
    ];

    return $map[$raw] ?? '';
}

function map_status_from_text(string $text): array
{
    // Ajuste/adicione novos mapeamentos de status aqui.
    $text = normalize_action($text);
    if ($text === '') {
        return ['code' => 1, 'payment' => 'INDEFINIDO', 'subscription' => ''];
    }

    $map = [
        'PAGO' => [3, 'PAGO', ''],
        'APROVADO' => [3, 'PAGO', ''],
        'PAID' => [3, 'PAGO', ''],
        'CAPTURED' => [3, 'PAGO', ''],
        'ESTORNADO' => [0, 'ESTORNADO', ''],
        'REFUNDED' => [0, 'ESTORNADO', ''],
        'CHARGEBACK' => [0, 'CHARGEBACK', ''],
        'CANCELADO' => [0, 'CANCELADO', ''],
        'CANCELED' => [0, 'CANCELADO', ''],
        'FALHOU' => [0, 'FALHOU', ''],
        'FAILED' => [0, 'FALHOU', ''],
        'RECUSADO' => [0, 'RECUSADO', ''],
        'NEGADO' => [0, 'RECUSADO', ''],
        'BLOQUEADO' => [0, 'BLOQUEADO', ''],
        'PENDENTE' => [1, 'PENDENTE', ''],
        'PENDING' => [1, 'PENDENTE', ''],
        'AGUARDANDO' => [1, 'PENDENTE', ''],
        'EM ANALISE' => [1, 'EM_ANALISE', ''],
        'EM_ANALISE' => [1, 'EM_ANALISE', ''],
        'ASSINATURA EFETUADA' => [3, 'ASSINATURA_EFETUADA', 'ATIVA'],
        'ASSINATURA_EFETUADA' => [3, 'ASSINATURA_EFETUADA', 'ATIVA'],
        'ASSINATURA CRIADA' => [3, 'ASSINATURA_EFETUADA', 'ATIVA'],
        'ASSINATURA_CRIADA' => [3, 'ASSINATURA_EFETUADA', 'ATIVA'],
        'ASSINATURA SUSPENSA' => [2, 'ASSINATURA_SUSPENSA', 'SUSPENSA'],
        'ASSINATURA_SUSPENSA' => [2, 'ASSINATURA_SUSPENSA', 'SUSPENSA'],
        'ASSINATURA CANCELADA' => [0, 'ASSINATURA_CANCELADA', 'CANCELADA'],
        'ASSINATURA_CANCELADA' => [0, 'ASSINATURA_CANCELADA', 'CANCELADA'],
        'ASSINATURA REATIVADA' => [3, 'ASSINATURA_REATIVADA', 'ATIVA'],
        'ASSINATURA_REATIVADA' => [3, 'ASSINATURA_REATIVADA', 'ATIVA'],
        'ASSINATURA ATIVA' => [3, 'ASSINATURA_ATIVA', 'ATIVA'],
        'ASSINATURA_ATIVA' => [3, 'ASSINATURA_ATIVA', 'ATIVA'],
    ];

    if (isset($map[$text])) {
        return ['code' => $map[$text][0], 'payment' => $map[$text][1], 'subscription' => $map[$text][2]];
    }

    return ['code' => 1, 'payment' => $text, 'subscription' => ''];
}

function build_query(array $params): string
{
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

$raw = file_get_contents('php://input');
if ($raw !== false && $raw !== '') {
    log_append($logDebugFile, date('d/m/Y H:i:s') . $raw);
}

$payload = null;
if ($raw !== false && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        // Entrada JSON (webhook Stone, etc.)
        $payload = $decoded;
    }
}

$input = array_merge($_GET ?? [], $_POST ?? []);
if ($payload === null && $input === []) {
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'empty_request']);
    return;
}

$siteId = 0;
$referencia = '';
$valor = '';
$ocorrencia = '';
$tipoPagamento = '';
$statusCode = null;
$statusText = '';
$statusAssinatura = '';

if ($payload !== null) {
    // Fluxo para webhook JSON.
    $siteId = (int)($payload['id_site'] ?? 0);
    $referencia = (string)($payload['referencia_cliente'] ?? '');
    $ocorrencia = (string)($payload['ocorrencia_assinatura'] ?? '');

    $valorCentavos = $payload['pagamento']['valor_centavos'] ?? $payload['summary']['amount_cents'] ?? null;
    if ($valorCentavos !== null && is_numeric($valorCentavos)) {
        $valor = number_format(((float)$valorCentavos) / 100, 2, '.', '');
    } else {
        $valor = normalize_money($payload['valor'] ?? '');
    }

    $acao = (string)($payload['acao'] ?? '');
    $statusHint = $acao;
    if ($statusHint === '') {
        $statusHint = (string)($payload['pagamento']['status_codigo'] ?? $payload['summary']['status'] ?? $payload['status'] ?? '');
    }

    $statusMapped = map_status_from_text($statusHint);
    $statusCode = $statusMapped['code'];
    $statusText = $statusMapped['payment'];
    $statusAssinatura = $statusMapped['subscription'];

    if ($statusAssinatura === '') {
        $statusAssinatura = map_subscription_status($payload['assinatura']['status'] ?? $payload['assinatura']['status_codigo'] ?? '');
    }

    $subscriptionId = extract_subscription_id($payload);
    if (isset($payload['tokenAdesao']) && is_string($payload['tokenAdesao'])) {
        $tokenAdesao = trim($payload['tokenAdesao']);
    }
    if ($tokenAdesao === '') {
        $tokenAdesao = $subscriptionId;
    }

    if ($statusAssinatura !== '' && $statusCode === 1) {
        switch ($statusAssinatura) {
            case 'ATIVA':
                $statusCode = 3;
                break;
            case 'SUSPENSA':
                $statusCode = 2;
                break;
            case 'CANCELADA':
                $statusCode = 0;
                break;
        }
    }

    $metodo = (string)($payload['metodo_pagamento'] ?? $payload['tipo_metodo_pagamento'] ?? $payload['summary']['method'] ?? '');
    if ($metodo === '') {
        $metodo = extract_payment_method($payload);
    }
    $metodo = normalize_payment_method_name($metodo);
    $tipoPagamento = map_payment_type($metodo);

    if ($tipoPagamento === '' && isset($payload['tpPag'])) {
        $tipoPagamento = (string)$payload['tpPag'];
    }

    $logEntry = "=======================================\n";
    $logEntry .= "ID SITE: " . ($payload['id_site'] ?? 'N/D') . "\n";
    $logEntry .= "Data de Registro: " . ($payload['data_pagamento'] ?? 'N/D') . "\n";
    $logEntry .= "Ação: " . ($payload['acao'] ?? 'N/D') . "\n";
    $logEntry .= "Status do Processamento: " . ($payload['status'] ?? 'N/D') . "\n";
    $logEntry .= "Valor Original: " . $valor . "\n";
    $logEntry .= "Referencia " . ($payload['referencia_cliente'] ?? 'N/D') . "\n";
    $logEntry .= "Forma Pagamento: " . ($payload['metodo_pagamento'] ?? 'N/D') . "\n";
    $logEntry .= "Webhook Stone: " . ($payload['id_evento_provedor'] ?? 'N/D') . "\n";
    $logEntry .= "Ocorrencia: " . ($payload['ocorrencia_assinatura'] ?? 'N/D') . "\n";
    $logEntry .= "Status Normalizado: " . $statusText . "\n";
    if ($subscriptionId !== '') {
        $logEntry .= "Subscription ID: " . $subscriptionId . "\n";
    }
    if ($tokenAdesao !== '') {
        $logEntry .= "Token Adesao: " . $tokenAdesao . "\n";
    }
    if ($statusAssinatura !== '') {
        $logEntry .= "Status Assinatura: " . $statusAssinatura . "\n";
    }
    $logEntry .= "Data do Retorno: " . date('d/m/Y H:i:s') . "\n";
    $logEntry .= "=======================================\n";
    file_put_contents($logWebhookFile, $logEntry, FILE_APPEND);
} else {
    // Fluxo para chamadas diretas via GET/POST (ex.: testes manuais).
    $siteId = (int)($input['siteId'] ?? $input['site_id'] ?? $input['id_site'] ?? 0);
    $referencia = (string)($input['ref'] ?? $input['referencia'] ?? $input['referencia_cliente'] ?? '');
    $valor = normalize_money($input['valor'] ?? '');
    $ocorrencia = (string)($input['ocorrencia'] ?? '');

    if (isset($input['tpPag'])) {
        $tipoPagamento = (string)$input['tpPag'];
    } else {
        $tipoPagamento = map_payment_type($input['metodo_pagamento'] ?? $input['tipo_pagamento'] ?? '');
    }

    if (isset($input['status'])) {
        $statusCode = (int)$input['status'];
    }

    $statusText = (string)($input['statusText'] ?? $input['status_text'] ?? $input['status_pagamento'] ?? $input['acao'] ?? '');
    $statusAssinatura = (string)($input['statusAssinatura'] ?? $input['status_assinatura'] ?? '');
    $subscriptionId = (string)($input['subscription_id'] ?? '');
    $tokenAdesao = (string)($input['tokenAdesao'] ?? '');
    if ($tokenAdesao === '' && $subscriptionId !== '') {
        $tokenAdesao = $subscriptionId;
    }

    if ($statusAssinatura === '') {
        $statusAssinatura = map_subscription_status($input['assinatura_status'] ?? '');
    }
}

if ($siteId <= 0 || !isset($siteEndpoints[$siteId])) {
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'invalid_site', 'site_id' => $siteId]);
    return;
}

if ($referencia === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'missing_reference']);
    return;
}

if ($statusCode === null) {
    $statusMapped = map_status_from_text($statusText);
    $statusCode = $statusMapped['code'];
    if ($statusText === '') {
        $statusText = $statusMapped['payment'];
    }
    if ($statusAssinatura === '') {
        $statusAssinatura = $statusMapped['subscription'];
    }
}

$params = [
    'status' => $statusCode,
    'ref' => $referencia,
    'valor' => $valor,
    'tpPag' => $tipoPagamento,
    'ocorrencia' => $ocorrencia,
    'tipoPagamento' => $tipoPagamento,
    'valorPagamento' => $valor,
];

if ($statusText !== '') {
    $params['status_pagamento'] = $statusText;
}

if ($statusAssinatura !== '') {
    $params['status_assinatura'] = $statusAssinatura;
}

if ($subscriptionId !== '') {
    $params['subscription_id'] = $subscriptionId;
}

if ($tokenAdesao !== '') {
    $params['tokenAdesao'] = $tokenAdesao;
}

if ($payload !== null) {
    $metodoParam = $payload['metodo_pagamento'] ?? $payload['tipo_metodo_pagamento'] ?? '';
    if ($metodoParam === '') {
        $metodoParam = extract_payment_method($payload);
    }
    $metodoParam = normalize_payment_method_name($metodoParam);
    if ($metodoParam !== '') {
        $params['metodo_pagamento'] = $metodoParam;
    }
} else {
    $metodoParam = $input['metodo_pagamento'] ?? $input['tipo_pagamento'] ?? '';
    $metodoParam = normalize_payment_method_name($metodoParam);
    if ($metodoParam !== '') {
        $params['metodo_pagamento'] = $metodoParam;
    }
}

$targetUrl = $siteEndpoints[$siteId] . '?' . build_query($params);

log_append($logForwardFile, date('d/m/Y H:i:s') . ' **** ' . $targetUrl);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$responseBody = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlInfo = curl_getinfo($ch);
curl_close($ch);

$logResponse = $responseBody === false ? '' : (string)$responseBody;
if (strlen($logResponse) > 1500) {
    $logResponse = substr($logResponse, 0, 1500) . '...';
}

$logForwardDetails = date('d/m/Y H:i:s') . ' **** CURL';
$logForwardDetails .= ' http_code=' . $httpCode;
if ($curlError !== '') {
    $logForwardDetails .= ' error=' . $curlError;
}
if (!empty($curlInfo['primary_ip'])) {
    $logForwardDetails .= ' ip=' . $curlInfo['primary_ip'];
}
if (isset($curlInfo['total_time'])) {
    $logForwardDetails .= ' total_time=' . $curlInfo['total_time'];
}
if ($logResponse !== '') {
    $logForwardDetails .= ' response=' . $logResponse;
}
log_append($logForwardFile, $logForwardDetails);

/*// *********************************************************
// enviar para CGO
$targetUrl = $siteEndpoints[2] . '?' . build_query($params);

log_append($logForwardFile, date('d/m/Y H:i:s') . ' **** ' . $targetUrl);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$responseBody = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
// **********************************************************/



header('Content-Type: application/json; charset=UTF-8');
if ($curlError !== '') {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'curl_error',
        'message' => $curlError,
        'url' => $targetUrl,
    ]);
    return;
}

http_response_code(200);
echo json_encode([
    'ok' => true,
    'site_id' => $siteId,
    'url' => $targetUrl,
    'http_code' => $httpCode,
    'response' => $responseBody,
]);
