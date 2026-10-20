<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;

final class QueriesController
{
    // GET /api/v1/payments/{id}
    public function paymentById(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['status' => 'error', 'message' => 'Não autorizado', 'status_code' => 'unauthorized', 'http_status' => 401], 401);
        $id = (int)($req->attributes['routeParams']['id'] ?? 0);
        $row = Database::run('SELECT p.*, c.email AS customer_email FROM payments p LEFT JOIN customers c ON c.id=p.customer_id WHERE p.id = ? AND p.site_id = ? LIMIT 1', [$id, $site['id']])->fetch();
        if (!$row) return Response::json(['status' => 'error', 'message' => 'Not found', 'status_code' => 'not_found', 'http_status' => 404], 404);
        $summary = self::buildPaymentSummary($row);
        $resp = ['payment' => $row, 'summary' => $summary];
        $norm = strtolower((string)($row['status'] ?? ''));
        $isError = in_array($norm, ['failed','canceled','chargeback'], true);
        $acq = self::extractAcquirerMessageFromPayment($row);
        $txStatus = self::extractTxStatusFromPayment($row);
        $code = self::extractAcquirerReturnCodeFromPayment($row) ?? ($txStatus ?? $norm ?: null);
        // Padronizar mensagem raiz em PT; detalhes do adquirente vão em acquirer_message
        $msg = self::friendlyMessageFromStatus($norm);
        $resp['status'] = $isError ? 'error' : 'success';
        $resp['message'] = $msg;
        $resp['status_code'] = $code;
        // Informação adicional opcional para depuração
        if ($acq !== null && $acq !== '') $resp['acquirer_message'] = $acq;
        return Response::json($resp);
    }

    // GET /api/v1/payments/stone/{sid}
    public function paymentByStoneId(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['status' => 'error', 'message' => 'Não autorizado', 'status_code' => 'unauthorized', 'http_status' => 401], 401);
        $sid = (string)($req->attributes['routeParams']['sid'] ?? '');
        $row = Database::run('SELECT p.*, c.email AS customer_email FROM payments p LEFT JOIN customers c ON c.id=p.customer_id WHERE p.stone_payment_id = ? AND p.site_id = ? LIMIT 1', [$sid, $site['id']])->fetch();
        if (!$row) return Response::json(['status' => 'error', 'message' => 'Not found', 'status_code' => 'not_found', 'http_status' => 404], 404);
        $summary = self::buildPaymentSummary($row);
        $resp = ['payment' => $row, 'summary' => $summary];
        $norm = strtolower((string)($row['status'] ?? ''));
        $isError = in_array($norm, ['failed','canceled','chargeback'], true);
        $acq = self::extractAcquirerMessageFromPayment($row);
        $txStatus = self::extractTxStatusFromPayment($row);
        $code = self::extractAcquirerReturnCodeFromPayment($row) ?? ($txStatus ?? $norm ?: null);
        // Padronizar mensagem raiz em PT; detalhes do adquirente vão em acquirer_message
        $msg = self::friendlyMessageFromStatus($norm);
        $resp['status'] = $isError ? 'error' : 'success';
        $resp['message'] = $msg;
        $resp['status_code'] = $code;
        if ($acq !== null && $acq !== '') $resp['acquirer_message'] = $acq;
        return Response::json($resp);
    }

    // GET /api/v1/payments/by-ref/{ref}
    public function paymentByRef(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['status' => 'error', 'message' => 'Não autorizado', 'status_code' => 'unauthorized', 'http_status' => 401], 401);
        $ref = (string)($req->attributes['routeParams']['ref'] ?? '');
        // Referência pode repetir; por compatibilidade, esta rota retorna o registro mais recente.
        $row = Database::run('SELECT p.*, c.email AS customer_email FROM payments p LEFT JOIN customers c ON c.id=p.customer_id WHERE p.client_reference_id = ? AND p.site_id = ? ORDER BY p.id DESC LIMIT 1', [$ref, $site['id']])->fetch();
        if (!$row) return Response::json(['status' => 'error', 'message' => 'Not found', 'status_code' => 'not_found', 'http_status' => 404], 404);
        $summary = self::buildPaymentSummary($row);
        $resp = ['payment' => $row, 'summary' => $summary];
        $norm = strtolower((string)($row['status'] ?? ''));
        $isError = in_array($norm, ['failed','canceled','chargeback'], true);
        $acq = self::extractAcquirerMessageFromPayment($row);
        $txStatus = self::extractTxStatusFromPayment($row);
        $code = self::extractAcquirerReturnCodeFromPayment($row) ?? ($txStatus ?? $norm ?: null);
        // Padronizar mensagem raiz em PT; detalhes do adquirente vão em acquirer_message
        $msg = self::friendlyMessageFromStatus($norm);
        $resp['status'] = $isError ? 'error' : 'success';
        $resp['message'] = $msg;
        $resp['status_code'] = $code;
        if ($acq !== null && $acq !== '') $resp['acquirer_message'] = $acq;
        return Response::json($resp);
    }

    // GET /api/v1/payments/by-ref/{ref}/history
    public function paymentsByRefHistory(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['status' => 'error', 'message' => 'Não autorizado', 'status_code' => 'unauthorized', 'http_status' => 401], 401);
        $ref = (string)($req->attributes['routeParams']['ref'] ?? '');
        $limit = min(200, max(1, (int)($req->query['limit'] ?? 50)));
        $offset = max(0, (int)($req->query['offset'] ?? 0));

        $rows = Database::run(
            'SELECT p.*, c.email AS customer_email
             FROM payments p
             LEFT JOIN customers c ON c.id = p.customer_id
             WHERE p.client_reference_id = ? AND p.site_id = ?
             ORDER BY p.id DESC
             LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset,
            [$ref, $site['id']]
        )->fetchAll();

        return Response::json(['reference' => $ref, 'data' => $rows]);
    }

    private static function buildPaymentSummary(array $payment): array
    {
        $status = strtolower((string)($payment['status'] ?? ''));
        $error = in_array($status, ['failed','canceled','chargeback'], true);
        $summary = [
            'error' => $error,
            'status' => $status ?: null,
            'reference' => $payment['client_reference_id'] ?? null,
            'stone_payment_id' => $payment['stone_payment_id'] ?? null,
            'amount_cents' => isset($payment['amount_cents']) ? (int)$payment['amount_cents'] : null,
            'currency' => $payment['currency'] ?? null,
            'created_at' => $payment['created_at'] ?? null,
            'updated_at' => $payment['updated_at'] ?? null,
            'method' => $payment['payment_method_type'] ?? null,
        ];
        // Parse provider-specific details from response_payload/metadata
        $rp = (string)($payment['response_payload'] ?? '');
        $rpJ = $rp !== '' ? json_decode($rp, true) : null;
        $charges = is_array($rpJ) ? ($rpJ['charges'] ?? null) : null;
        $tx = null;
        if (is_array($charges) && isset($charges[0]) && is_array($charges[0])) {
            $tx = $charges[0]['last_transaction'] ?? null;
        }
        $meta = (string)($payment['metadata'] ?? '');
        $metaJ = $meta !== '' ? json_decode($meta, true) : null;
        // PIX extras
        if (($payment['payment_method_type'] ?? null) === 'pix') {
            $pix = is_array($metaJ) ? ($metaJ['pix'] ?? null) : null;
            if (is_array($pix)) {
                $summary['pix_expires_at'] = $pix['expires_at'] ?? null;
                $summary['pix_qr_code_url'] = $pix['qr_code_url'] ?? null;
                $summary['pix_qr_code'] = $pix['qr_code'] ?? null;
                $summary['pix_code'] = $summary['pix_qr_code'];
                $summary['pix_qrcode_url'] = $summary['pix_qr_code_url'];
            } elseif (is_array($tx)) {
                $summary['pix_expires_at'] = $tx['expires_at'] ?? ($tx['expiration_date'] ?? null);
                $summary['pix_qr_code_url'] = $tx['qr_code_url'] ?? null;
                $summary['pix_qr_code'] = $tx['qr_code'] ?? ($tx['qr_code_emv'] ?? ($tx['emv'] ?? ($tx['payload'] ?? null)));
                $summary['pix_code'] = $summary['pix_qr_code'];
                $summary['pix_qrcode_url'] = $summary['pix_qr_code_url'];
            }
        } elseif (($payment['payment_method_type'] ?? null) === 'boleto') {
            $boleto = is_array($metaJ) ? ($metaJ['boleto'] ?? null) : null;
            if (is_array($boleto)) {
                $summary['boleto_url'] = $boleto['url'] ?? null;
                $summary['boleto_pdf'] = $boleto['pdf'] ?? null;
                $summary['boleto_line'] = $boleto['line'] ?? null;
                $summary['boleto_barcode'] = $boleto['barcode'] ?? null;
                $summary['boleto_qr_code'] = $boleto['qr_code'] ?? null;
                $summary['boleto_due_at'] = $boleto['due_at'] ?? null;
                $summary['boleto_document_number'] = $boleto['document_number'] ?? null;
                $summary['boleto_bank'] = $boleto['bank'] ?? null;
                $summary['boleto_type'] = $boleto['type'] ?? null;
            } elseif (is_array($tx)) {
                $summary['boleto_url'] = $tx['url'] ?? ($tx['boleto_url'] ?? null);
                $summary['boleto_pdf'] = $tx['pdf'] ?? ($tx['pdf_url'] ?? null);
                $summary['boleto_line'] = $tx['line'] ?? ($tx['digitable_line'] ?? null);
                $summary['boleto_barcode'] = $tx['barcode'] ?? ($tx['bar_code'] ?? null);
                $summary['boleto_qr_code'] = $tx['qr_code'] ?? null;
                $summary['boleto_due_at'] = $tx['due_at'] ?? ($tx['due_date'] ?? null);
                $summary['boleto_document_number'] = $tx['document_number'] ?? null;
                $summary['boleto_bank'] = $tx['bank'] ?? null;
                $summary['boleto_type'] = $tx['type'] ?? null;
            }
        }
        // Card extras
        if (($payment['payment_method_type'] ?? null) === 'credit_card' && is_array($tx)) {
            $card = $tx['card'] ?? [];
            if (is_array($card)) {
                $summary['card_brand'] = $card['brand'] ?? null;
                $summary['card_last4'] = $card['last_four_digits'] ?? ($card['last4'] ?? null);
            }
            $summary['installments'] = $tx['installments'] ?? ($payment['installments'] ?? null);
        }
        return $summary;
    }

    private static function extractAcquirerMessageFromPayment(array $payment): ?string
    {
        $rp = (string)($payment['response_payload'] ?? '');
        if ($rp === '') return null;
        $j = json_decode($rp, true);
        if (!is_array($j)) return null;
        $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
        $candidates = [];
        $candidates[] = $read($j, ['charges', 0, 'last_transaction', 'acquirer_message']);
        $candidates[] = $read($j, ['last_transaction', 'acquirer_message']);
        foreach ($candidates as $c) {
            if (is_string($c) && $c !== '') return $c;
        }
        return null;
    }

    private static function extractAcquirerReturnCodeFromPayment(array $payment): ?string
    {
        $rp = (string)($payment['response_payload'] ?? ''); if ($rp === '') return null; $j = json_decode($rp, true); if (!is_array($j)) return null;
        $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
        foreach ([['charges',0,'last_transaction','acquirer_return_code'], ['last_transaction','acquirer_return_code']] as $p) {
            $v = $read($j, $p);
            if (is_string($v) && $v !== '') return $v; if (is_numeric($v)) return (string)$v;
        }
        return null;
    }

    private static function extractTxStatusFromPayment(array $payment): ?string
    {
        $rp = (string)($payment['response_payload'] ?? ''); if ($rp === '') return null; $j = json_decode($rp, true); if (!is_array($j)) return null;
        $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
        foreach ([['charges',0,'last_transaction','status'], ['last_transaction','status']] as $p) {
            $v = $read($j, $p); if (is_string($v) && $v !== '') return $v;
        }
        return null;
    }

    private static function friendlyMessageFromStatus(?string $status): ?string
    {
        $s = strtolower((string)($status ?? ''));
        return match ($s) {
            'paid' => 'Pagamento aprovado',
            'authorized' => 'Pagamento autorizado',
            'processing', 'pending' => 'Pagamento em processamento',
            'canceled' => 'Pagamento cancelado',
            'refunded' => 'Pagamento estornado',
            'chargeback' => 'Pagamento em chargeback',
            'failed' => 'Pagamento não autorizado',
            default => ($s !== '' ? ucfirst($s) : null),
        };
    }

    // GET /api/v1/payments  (filter by from,to,customer_id,customer_email,status)
    public function paymentsSearch(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['error' => 'Não autorizado'], 401);
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $customerId = isset($req->query['customer_id']) ? (int)$req->query['customer_id'] : null;
        $customerEmail = (string)($req->query['customer_email'] ?? '');
        $status = (string)($req->query['status'] ?? '');
        $subscriptionStoneId = (string)($req->query['subscription_stone_id'] ?? '');
        $limit = min(200, max(1, (int)($req->query['limit'] ?? 50)));
        $offset = max(0, (int)($req->query['offset'] ?? 0));

        $sql = 'FROM payments p LEFT JOIN customers c ON c.id=p.customer_id WHERE p.site_id = ?';
        $params = [$site['id']];
        if ($from !== '') { $sql .= ' AND p.created_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND p.created_at <= ?'; $params[] = $to; }
        if ($customerId) { $sql .= ' AND p.customer_id = ?'; $params[] = $customerId; }
        if ($customerEmail !== '') { $sql .= ' AND c.email = ?'; $params[] = $customerEmail; }
        if ($status !== '') { $sql .= ' AND p.status = ?'; $params[] = $status; }
        if ($subscriptionStoneId !== '') {
            $sql .= ' AND EXISTS (SELECT 1 FROM inbound_webhooks w WHERE (w.payment_id = p.id OR w.stone_payment_id = p.stone_payment_id) AND w.stone_subscription_id = ? AND (w.site_id = p.site_id OR w.site_id IS NULL))';
            $params[] = $subscriptionStoneId;
        }

        $rows = Database::run('SELECT p.*, c.email AS customer_email ' . $sql . ' ORDER BY p.id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset, $params)->fetchAll();
        return Response::json(['data' => $rows]);
    }

    // GET /api/v1/payments/by-subscription/{sid}
    public function paymentsBySubscription(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null; if (!$site) return Response::json(['error' => 'Não autorizado'], 401);
        $sid = (string)($req->attributes['routeParams']['sid'] ?? '');
        if ($sid === '') return Response::json(['error' => 'sid required'], 422);
        $rows = Database::run('SELECT w.id AS notification_id, w.provider_event_id, w.event_type, w.stone_payment_id, w.payment_id, w.status, w.received_at, w.processed_at FROM inbound_webhooks w WHERE w.stone_subscription_id = ? AND (w.site_id IS NULL OR w.site_id = ?) ORDER BY w.id DESC LIMIT 500', [$sid, $site['id']])->fetchAll();
        return Response::json(['data' => $rows]);
    }

    // GET /api/v1/customers/{id}
    public function customerById(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['error' => 'Não autorizado'], 401);
        $id = (int)($req->attributes['routeParams']['id'] ?? 0);
        $row = Database::run('SELECT * FROM customers WHERE id = ? AND site_id = ? LIMIT 1', [$id, $site['id']])->fetch();
        if (!$row) return Response::json(['error' => 'Not found'], 404);
        return Response::json($row);
    }

    // GET /api/v1/customers (search by email, document or stone_customer_id)
    public function customersSearch(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['error' => 'Não autorizado'], 401);
        $email = (string)($req->query['email'] ?? '');
        $stoneCid = (string)($req->query['stone_customer_id'] ?? '');
        $document = (string)($req->query['document'] ?? ($req->query['cpf'] ?? ''));
        $sql = 'SELECT * FROM customers WHERE site_id = ?';
        $params = [$site['id']];
        if ($email !== '') { $sql .= ' AND email = ?'; $params[] = $email; }
        if ($document !== '') { $sql .= ' AND document = ?'; $params[] = $document; }
        if ($stoneCid !== '') { $sql .= ' AND stone_customer_id = ?'; $params[] = $stoneCid; }
        $sql .= ' ORDER BY id DESC LIMIT 100';
        $rows = Database::run($sql, $params)->fetchAll();
        return Response::json(['data' => $rows]);
    }
}
