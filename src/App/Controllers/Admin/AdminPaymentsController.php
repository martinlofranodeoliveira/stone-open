<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Admin\Concerns\PaymentAdminFiltersTrait;
use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\View\View;
use App\Support\Env;
use App\Support\I18n;

final class AdminPaymentsController
{
    use PaymentAdminFiltersTrait;

    private function extractNameFromPayload(?string $json): string
    {
        $json = (string)($json ?? '');
        if ($json === '') return '';
        $data = json_decode($json, true);
        if (!is_array($data)) return '';
        return $this->findCustomerName($data);
    }

    private function findCustomerName(array $data, int $depth = 0): string
    {
        if ($depth > 6) return '';
        if (isset($data['customer_name']) && is_string($data['customer_name']) && trim($data['customer_name']) !== '') {
            return trim($data['customer_name']);
        }
        foreach (['customer','cliente','buyer','payer'] as $k) {
            if (isset($data[$k]) && is_array($data[$k])) {
                $c = $data[$k];
                $n = $c['name'] ?? ($c['nome'] ?? ($c['full_name'] ?? null));
                if (is_string($n) && trim($n) !== '') return trim($n);
            }
        }
        foreach (['charges','charge','transactions','transaction','last_transaction','order','subscription','invoice','data','payment','payments'] as $k) {
            if (!isset($data[$k])) continue;
            $v = $data[$k];
            if (is_array($v)) {
                if (array_is_list($v)) {
                    foreach ($v as $item) {
                        if (is_array($item)) {
                            $n = $this->findCustomerName($item, $depth + 1);
                            if ($n !== '') return $n;
                        }
                    }
                } else {
                    $n = $this->findCustomerName($v, $depth + 1);
                    if ($n !== '') return $n;
                }
            }
        }
        foreach ($data as $v) {
            if (is_array($v)) {
                $n = $this->findCustomerName($v, $depth + 1);
                if ($n !== '') return $n;
            }
        }
        return '';
    }

    private function resolveCustomerName(array $row): ?string
    {
        $local = trim((string)($row['customer_name'] ?? ($row['customer_name_local'] ?? '')));
        if ($local !== '') return $local;
        $join = trim((string)($row['customer_name_join'] ?? ''));
        if ($join !== '') {
            $this->persistCustomerName((int)($row['id'] ?? 0), $join);
            return $join;
        }
        $name = $this->extractNameFromPayload($row['request_payload'] ?? null);
        if ($name !== '') {
            $this->persistCustomerName((int)($row['id'] ?? 0), $name);
            return $name;
        }
        $name = $this->extractNameFromPayload($row['response_payload'] ?? null);
        if ($name !== '') {
            $this->persistCustomerName((int)($row['id'] ?? 0), $name);
            return $name;
        }
        return null;
    }

    private function extractBoletoInfo(array $row): ?array
    {
        $metaRaw = (string)($row['metadata'] ?? '');
        if ($metaRaw !== '') {
            $meta = json_decode($metaRaw, true);
            if (is_array($meta) && isset($meta['boleto']) && is_array($meta['boleto'])) {
                return $meta['boleto'];
            }
        }
        $respRaw = (string)($row['response_payload'] ?? '');
        if ($respRaw === '') return null;
        $resp = json_decode($respRaw, true);
        if (!is_array($resp)) return null;
        $charge = $resp['charges'] ?? null;
        if (is_array($charge) && isset($charge[0]) && is_array($charge[0])) {
            $charge = $charge[0];
        } elseif (!is_array($charge)) {
            $charge = $resp;
        }
        $tx = $charge['last_transaction'] ?? ($resp['last_transaction'] ?? ($charge['transaction'] ?? null));
        if (!is_array($tx)) return null;
        return [
            'url' => $tx['url'] ?? ($tx['boleto_url'] ?? null),
            'pdf' => $tx['pdf'] ?? ($tx['pdf_url'] ?? null),
            'line' => $tx['line'] ?? ($tx['digitable_line'] ?? null),
            'barcode' => $tx['barcode'] ?? ($tx['bar_code'] ?? null),
            'qr_code' => $tx['qr_code'] ?? null,
            'due_at' => $tx['due_at'] ?? ($tx['due_date'] ?? null),
            'document_number' => $tx['document_number'] ?? null,
            'bank' => $tx['bank'] ?? null,
            'type' => $tx['type'] ?? null,
            'instructions' => $tx['instructions'] ?? null,
            'nosso_numero' => $tx['nosso_numero'] ?? null,
        ];
    }

    private function persistCustomerName(int $paymentId, string $name): void
    {
        if ($paymentId <= 0 || trim($name) === '') return;
        try {
            Database::run('UPDATE payments SET customer_name = ? WHERE id = ? AND (customer_name IS NULL OR customer_name = "")', [$name, $paymentId]);
        } catch (\Throwable $e) {}
    }

    private function normalizeDateStart(?string $v): ?string
    {
        $v = trim((string)($v ?? ''));
        if ($v === '') return null;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $v, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
        // Unknown format, return as-is to let DB try to parse
        return $v;
    }

    private function normalizeDateEnd(?string $v): ?string
    {
        $start = $this->normalizeDateStart($v);
        if ($start === null) return null;
        // If already contains time, keep as is
        if (preg_match('/\d{2}:\d{2}:\d{2}$/', (string)$v)) return (string)$v;
        return $start . ' 23:59:59';
    }

    private function paymentBaseFromSql(): string
    {
        return ' FROM payments p JOIN sites s ON s.id=p.site_id LEFT JOIN customers c ON c.id=p.customer_id WHERE p.status <> "deleted"';
    }

    private function paymentListSelectSql(): string
    {
        return 'SELECT p.*, s.name AS site_name, p.customer_name AS customer_name_local, c.name AS customer_name_join, c.email AS customer_email_join, c.document AS customer_document_join' . $this->paymentBaseFromSql();
    }

    private function paymentDataSelectSql(): string
    {
        return 'SELECT p.id, s.name AS site_name, p.env, p.client_reference_id, p.stone_payment_id, p.customer_name AS customer_name_local, c.name AS customer_name_join, c.email AS customer_email_join, c.document AS customer_document_join, p.request_payload, p.response_payload, p.metadata, p.amount_cents, p.payment_method_type, p.status, p.created_at';
    }

    public function list(Request $req): Response
    {
        $filter = $this->paymentAdminFiltersFromRequest($req);

        $sql = $this->paymentListSelectSql();
        $params = [];
        $sql = $this->applyPaymentAdminFilters($sql, $params, $filter, false);
        $page = max(1, (int)($req->query['page'] ?? 1));
        $perPage = min(100, max(10, (int)($req->query['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $sqlCount = 'SELECT COUNT(*) c FROM (' . $sql . ') t';
        $total = (int) Database::run($sqlCount, $params)->fetch()['c'];
        $sql .= ' ORDER BY p.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        $rows = Database::run($sql, $params)->fetchAll();
        foreach ($rows as &$r) {
            $r['customer_name'] = $this->resolveCustomerName($r);
            if (($r['payment_method_type'] ?? '') === 'boleto') {
                $b = $this->extractBoletoInfo($r);
                if (is_array($b)) {
                    $r['boleto_url'] = $b['url'] ?? null;
                    $r['boleto_pdf'] = $b['pdf'] ?? null;
                    $r['boleto_line'] = $b['line'] ?? null;
                    $r['boleto_due_at'] = $b['due_at'] ?? null;
                }
            }
        }
        unset($r);

        $sites = Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll();
        $html = View::render('admin/payments_list', [
            'prefix' => $this->prefix(),
            'payments' => $rows,
            'sites' => $sites,
            'filter' => $filter,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function data(Request $req): Response
    {
        $draw = (int)($req->query['draw'] ?? 1);
        $start = max(0, (int)($req->query['start'] ?? 0));
        $length = min(200, max(10, (int)($req->query['length'] ?? 25)));
        $search = trim((string)($req->query['search']['value'] ?? ''));

        $filter = $this->paymentAdminFiltersFromRequest($req);

        $base = $this->paymentBaseFromSql();
        $params = [];
        $base = $this->applyPaymentAdminFilters($base, $params, $filter, false);
        $total = (int) Database::run('SELECT COUNT(*) c ' . $base, $params)->fetch()['c'];
        $filtered = $total;
        $rowBase = $base;
        $rowParams = $params;
        if ($search !== '') {
            $rowBase = $this->applyPaymentAdminDataTableSearch($base, $rowParams, $search);
            $filtered = (int) Database::run('SELECT COUNT(*) c ' . $rowBase, $rowParams)->fetch()['c'];
        }

        $orderColIdx = (int)($req->query['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string)($req->query['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $columns = ['p.id','s.name','p.env','p.client_reference_id','p.customer_name','p.amount_cents','p.payment_method_type','p.status','p.created_at'];
        $orderBy = $columns[$orderColIdx] ?? 'p.id';
        if (!$orderBy) $orderBy = 'p.id';

        $sql = $this->paymentDataSelectSql() . $rowBase . ' ORDER BY ' . $orderBy . ' ' . $orderDir . ' LIMIT ' . (int)$length . ' OFFSET ' . (int)$start;
        $rows = Database::run($sql, $rowParams)->fetchAll();
        foreach ($rows as &$r) {
            $r['customer_name'] = $this->resolveCustomerName($r);
            if (($r['payment_method_type'] ?? '') === 'boleto') {
                $b = $this->extractBoletoInfo($r);
                if (is_array($b)) {
                    $r['boleto_url'] = $b['url'] ?? null;
                    $r['boleto_pdf'] = $b['pdf'] ?? null;
                    $r['boleto_line'] = $b['line'] ?? null;
                    $r['boleto_due_at'] = $b['due_at'] ?? null;
                }
            }
            unset($r['request_payload'], $r['response_payload'], $r['metadata']);
        }
        unset($r);
        return Response::json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows,
        ]);
    }

    public function exportCsv(Request $req): Response
    {
        $filter = $this->paymentAdminFiltersFromRequest($req);

        $sql = $this->paymentListSelectSql();
        $params = [];
        $sql = $this->applyPaymentAdminFilters($sql, $params, $filter, false);
        $sql .= ' ORDER BY p.id DESC LIMIT 20000';
        $rows = Database::run($sql, $params)->fetchAll();
        foreach ($rows as &$r) {
            $r['customer_name'] = $this->resolveCustomerName($r);
        }
        unset($r);
        $cols = [
            'id' => 'ID',
            'site_name' => 'Site',
            'env' => 'Ambiente',
            'client_reference_id' => 'Ref Cliente',
            'customer_name' => 'Nome',
            'stone_payment_id' => 'Stone ID',
            'amount_cents' => 'Valor (centavos)',
            'currency' => 'Moeda',
            'payment_method_type' => 'Tipo',
            'status' => 'Status',
            'created_at' => 'Criado em',
        ];
        return Response::csv($rows, $cols, 'payments.csv');
    }

    public function exportXls(Request $req): Response
    {
        $filter = $this->paymentAdminFiltersFromRequest($req);

        $sql = $this->paymentListSelectSql();
        $params = [];
        $sql = $this->applyPaymentAdminFilters($sql, $params, $filter, false);
        $sql .= ' ORDER BY p.id DESC LIMIT 20000';
        $rows = Database::run($sql, $params)->fetchAll();
        foreach ($rows as &$r) {
            $r['customer_name'] = $this->resolveCustomerName($r);
        }
        unset($r);
        $cols = [
            'id' => 'ID','site_name' => 'Site','env'=>'Ambiente','client_reference_id' => 'Ref Cliente','customer_name' => 'Nome','stone_payment_id' => 'Stone ID','amount_cents' => 'Valor (centavos)','currency'=>'Moeda','payment_method_type' => 'Tipo','status' => 'Status','created_at' => 'Criado em'
        ];
        return Response::xls($rows, $cols, 'payments.xls');
    }

    public function capture(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return Response::json(['error'=>'Invalid CSRF'], 400);
        $chargeId = (string)($post['stone_charge_id'] ?? '');
        $amount = isset($post['amount_cents']) ? (int)$post['amount_cents'] : null;
        if ($chargeId === '') return Response::json(['error'=>'Missing charge id'], 422);
        try {
            // find site to resolve account
            $row = \App\Infrastructure\DB\Database::run('SELECT * FROM payments WHERE stone_payment_id = ? LIMIT 1', [$chargeId])->fetch();
            $site = $row ? (\App\Infrastructure\DB\Database::run('SELECT * FROM sites WHERE id=? LIMIT 1', [$row['site_id']])->fetch() ?: null) : null;
            $client = $site ? \App\Infrastructure\Stone\StoneClient::forSite($site) : null;
            if (!$client) throw new \RuntimeException('Site/payment não encontrado');
            $res = $client->captureCharge($chargeId, $amount);
            $status = $res['status'] ?? null;
            if ($row) {
                if ($status && $status !== $row['status']) {
                    \App\Infrastructure\DB\Database::run('INSERT INTO payment_status_history (payment_id, old_status, new_status, reason) VALUES (?, ?, ?, ?)', [$row['id'], $row['status'], strtolower($status), 'admin_capture']);
                }
                \App\Infrastructure\DB\Database::run('UPDATE payments SET status=?, response_payload=?, updated_at=NOW() WHERE id=?', [strtolower((string)$status), json_encode($res, JSON_UNESCAPED_UNICODE), $row['id']]);
            }
            return new Response(302, ['Location' => $this->prefix() . '/payments'], '');
        } catch (\Throwable $e) {
            return Response::json(['error'=>'Capture failed','message'=>$e->getMessage()], 500);
        }
    }

    public function retry(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return Response::json(['error'=>'Invalid CSRF'], 400);
        $chargeId = (string)($post['stone_charge_id'] ?? '');
        if ($chargeId === '') return Response::json(['error'=>'Missing charge id'], 422);
        try {
            $row = \App\Infrastructure\DB\Database::run('SELECT * FROM payments WHERE stone_payment_id = ? LIMIT 1', [$chargeId])->fetch();
            $site = $row ? (\App\Infrastructure\DB\Database::run('SELECT * FROM sites WHERE id=? LIMIT 1', [$row['site_id']])->fetch() ?: null) : null;
            $client = $site ? \App\Infrastructure\Stone\StoneClient::forSite($site) : null;
            if (!$client) throw new \RuntimeException('Site/payment não encontrado');
            $res = $client->retryCharge($chargeId);
            $status = $res['status'] ?? null;
            if ($row) {
                if ($status && $status !== $row['status']) {
                    \App\Infrastructure\DB\Database::run('INSERT INTO payment_status_history (payment_id, old_status, new_status, reason) VALUES (?, ?, ?, ?)', [$row['id'], $row['status'], strtolower($status), 'admin_retry']);
                }
                \App\Infrastructure\DB\Database::run('UPDATE payments SET status=?, response_payload=?, updated_at=NOW() WHERE id=?', [strtolower((string)$status), json_encode($res, JSON_UNESCAPED_UNICODE), $row['id']]);
            }
            if (($req->query['ajax'] ?? '') === '1' || str_contains(strtolower((string)($req->header('accept') ?? '')), 'application/json')) {
                return Response::json(['ok'=>1, 'status'=>strtolower((string)$status)]);
            }
            return new Response(302, ['Location' => $this->prefix() . '/payments'], '');
        } catch (\Throwable $e) {
            return Response::json(['error'=>'Retry failed','message'=>$e->getMessage()], 500);
        }
    }

    public function cancel(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return Response::json(['error'=>'Invalid CSRF'], 400);
        $chargeId = (string)($post['stone_charge_id'] ?? '');
        $reason = trim((string)($post['reason'] ?? ''));
        if ($chargeId === '') return Response::json(['error'=>'Missing charge id'], 422);
        if ($reason === '') return Response::json(['error'=>'Missing cancel reason'], 422);
        try {
            $row = \App\Infrastructure\DB\Database::run('SELECT * FROM payments WHERE stone_payment_id = ? LIMIT 1', [$chargeId])->fetch();
            $site = $row ? (\App\Infrastructure\DB\Database::run('SELECT * FROM sites WHERE id=? LIMIT 1', [$row['site_id']])->fetch() ?: null) : null;
            $client = $site ? \App\Infrastructure\Stone\StoneClient::forSite($site) : null;
            if (!$client) throw new \RuntimeException('Site/payment não encontrado');
            $res = $client->cancelCharge($chargeId);
            $status = strtolower((string)($res['status'] ?? 'canceled'));
            if ($row) {
                if ($status && $status !== $row['status']) {
                    $ctx = json_encode(['action' => 'cancel', 'reason' => $reason], JSON_UNESCAPED_UNICODE);
                    \App\Infrastructure\DB\Database::run(
                        'INSERT INTO payment_status_history (payment_id, old_status, new_status, reason, context) VALUES (?, ?, ?, ?, ?)',
                        [$row['id'], $row['status'], strtolower((string)$status), $reason, $ctx]
                    );
                }
                \App\Infrastructure\DB\Database::run(
                    'UPDATE payments SET status=?, cancel_reason=?, response_payload=?, updated_at=NOW() WHERE id=?',
                    [strtolower((string)$status), $reason, json_encode($res, JSON_UNESCAPED_UNICODE), $row['id']]
                );
            }
            if (($req->query['ajax'] ?? '') === '1' || str_contains(strtolower((string)($req->header('accept') ?? '')), 'application/json')) {
                return Response::json(['ok'=>1, 'status'=>strtolower((string)$status)]);
            }
            return new Response(302, ['Location' => $this->prefix() . '/payments'], '');
        } catch (\App\Infrastructure\Stone\StoneApiException $e) {
            // Tratar 404 como "já cancelado / não encontrado" e efetivar cancelamento local
            if ((int)$e->getStatus() === 404) {
                try {
                    $row = \App\Infrastructure\DB\Database::run('SELECT * FROM payments WHERE stone_payment_id = ? LIMIT 1', [$chargeId])->fetch();
                    if ($row) {
                        if ((string)$row['status'] !== 'canceled') {
                            $ctx = json_encode(['action' => 'cancel', 'reason' => $reason, 'provider_status' => $e->getStatus()], JSON_UNESCAPED_UNICODE);
                            \App\Infrastructure\DB\Database::run(
                                'INSERT INTO payment_status_history (payment_id, old_status, new_status, reason, context) VALUES (?, ?, ?, ?, ?)',
                                [$row['id'], $row['status'], 'canceled', $reason, $ctx]
                            );
                            \App\Infrastructure\DB\Database::run(
                                'UPDATE payments SET status=?, cancel_reason=?, response_payload=?, updated_at=NOW() WHERE id=?',
                                ['canceled', $reason, json_encode(['error'=>'not_found','message'=>$e->getErrorMessage(),'provider_status'=>$e->getStatus()], JSON_UNESCAPED_UNICODE), $row['id']]
                            );
                        }
                    }
                } catch (\Throwable $e2) { /* ignore local update failure */ }
                return Response::json(['ok'=>1, 'status'=>'canceled', 'provider_status'=>404]);
            }
            // Outros erros: reportar JSON (sem redirecionar)
            return Response::json([
                'error' => 'Cancel failed',
                'message' => $e->getErrorMessage(),
                'provider_status' => $e->getStatus(),
                'status_code' => $e->getErrorCode(),
                'errors' => $e->getErrors(),
            ], 422);
        } catch (\Throwable $e) {
            return Response::json(['error'=>'Cancel failed','message'=>$e->getMessage()], 500);
        }
    }

    public function refund(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return Response::json(['error'=>'Invalid CSRF'], 400);
        $chargeId = (string)($post['stone_charge_id'] ?? '');
        $reason = trim((string)($post['reason'] ?? ''));
        if ($chargeId === '') return Response::json(['error'=>'Missing charge id'], 422);
        if ($reason === '') return Response::json(['error'=>'Missing refund reason'], 422);
        try {
            $row = \App\Infrastructure\DB\Database::run('SELECT * FROM payments WHERE stone_payment_id = ? LIMIT 1', [$chargeId])->fetch();
            $site = $row ? (\App\Infrastructure\DB\Database::run('SELECT * FROM sites WHERE id=? LIMIT 1', [$row['site_id']])->fetch() ?: null) : null;
            $client = $site ? \App\Infrastructure\Stone\StoneClient::forSite($site) : null;
            if (!$client) throw new \RuntimeException('Site/payment não encontrado');
            $res = $client->refundCharge($chargeId);
            $status = strtolower((string)($res['status'] ?? 'refunded'));
            if ($row) {
                if ($status && $status !== $row['status']) {
                    $ctx = json_encode(['action' => 'refund', 'reason' => $reason], JSON_UNESCAPED_UNICODE);
                    \App\Infrastructure\DB\Database::run(
                        'INSERT INTO payment_status_history (payment_id, old_status, new_status, reason, context) VALUES (?, ?, ?, ?, ?)',
                        [$row['id'], $row['status'], $status, $reason, $ctx]
                    );
                }
                \App\Infrastructure\DB\Database::run(
                    'UPDATE payments SET status=?, cancel_reason=?, response_payload=?, updated_at=NOW() WHERE id=?',
                    [$status, $reason, json_encode($res, JSON_UNESCAPED_UNICODE), $row['id']]
                );
            }
            if (($req->query['ajax'] ?? '') === '1' || str_contains(strtolower((string)($req->header('accept') ?? '')), 'application/json')) {
                return Response::json(['ok'=>1, 'status'=>$status]);
            }
            return new Response(302, ['Location' => $this->prefix() . '/payments'], '');
        } catch (\App\Infrastructure\Stone\StoneApiException $e) {
            return Response::json([
                'error' => 'Refund failed',
                'message' => $e->getErrorMessage(),
                'provider_status' => $e->getStatus(),
                'status_code' => $e->getErrorCode(),
                'errors' => $e->getErrors(),
            ], 422);
        } catch (\Throwable $e) {
            return Response::json(['error'=>'Refund failed','message'=>$e->getMessage()], 500);
        }
    }

    public function resend(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return Response::json(['error'=>'Invalid CSRF'], 400);
        $pid = (int)($post['payment_id'] ?? 0);
        if ($pid <= 0) return Response::json(['error'=>'Missing payment id'], 422);
        $ok=0; $fail=0; $skip=0;
        $log = null;
        try {
            $p = \App\Infrastructure\DB\Database::run('SELECT * FROM payments WHERE id = ? LIMIT 1', [$pid])->fetch();
            if (!$p) { $skip++; }
            else {
                $inb = \App\Infrastructure\DB\Database::run('SELECT * FROM inbound_webhooks WHERE payment_id = ? ORDER BY id DESC LIMIT 1', [$pid])->fetch();
                if (!$inb) {
                    // Fallback: criar inbound_webhook sintético a partir do response_payload (ou request_payload)
                    $payload = json_decode((string)($p['response_payload'] ?? ''), true);
                    if (!is_array($payload) || empty($payload)) {
                        $payload = json_decode((string)($p['request_payload'] ?? ''), true);
                    }
                    if (!is_array($payload)) $payload = [];
                    $providerEventId = 'manual_' . (int)$p['id'] . '_' . time() . '_' . random_int(1000,9999);
                    $eventType = $payload['type'] ?? ($payload['event'] ?? 'manual.resend');
                    \App\Infrastructure\DB\Database::run('INSERT INTO inbound_webhooks (provider, provider_event_id, event_type, stone_payment_id, stone_subscription_id, site_id, payment_id, raw_payload, headers, signature, status, processed_at) VALUES ("manual", ?, ?, ?, ?, ?, ?, ?, NULL, NULL, "processed", NOW())', [
                        $providerEventId,
                        $eventType,
                        $p['stone_payment_id'] ?? null,
                        $payload['data']['subscription']['id'] ?? null,
                        $p['site_id'],
                        $p['id'],
                        json_encode($payload, JSON_UNESCAPED_UNICODE),
                    ]);
                    $inb = \App\Infrastructure\DB\Database::run('SELECT * FROM inbound_webhooks WHERE provider = "manual" AND provider_event_id = ? LIMIT 1', [$providerEventId])->fetch();
                }
                if ($inb) {
                    $payload = json_decode($inb['raw_payload'] ?? '[]', true) ?: [];
                    $last = \App\Infrastructure\DB\Database::run('SELECT MAX(attempt) a FROM webhook_forward_logs WHERE notification_id = ?', [$inb['id']])->fetch();
                    $attempt = ((int)($last['a'] ?? 0)) + 1;
                    (new \App\Application\WebhookService())->forwardToSite((int)$p['site_id'], (int)$inb['id'], (int)$p['id'], $payload, $attempt);
                    $log = \App\Infrastructure\DB\Database::run('SELECT response_status, success, error, target_url FROM webhook_forward_logs WHERE notification_id = ? AND attempt = ? ORDER BY id DESC LIMIT 1', [$inb['id'], $attempt])->fetch() ?: null;
                    $ok++;
                } else {
                    $skip++;
                }
            }
        } catch (\Throwable $e) { $fail++; }
        if (($req->query['ajax'] ?? '') === '1' || strpos($req->header('Accept',''), 'application/json') !== false) {
            return Response::json(['resent'=>$ok,'failed'=>$fail,'skipped'=>$skip,'log'=>$log]);
        }
        return new Response(302, ['Location' => $this->prefix() . '/payments?resent='.$ok.'&failed='.$fail.'&skipped='.$skip], '');
    }

    public function resendBatch(Request $req): Response
    {
        if (($req->query['ajax'] ?? '') === '1' || strpos($req->header('Accept',''), 'application/json') !== false) {
            return Response::json(['error'=>'Batch resend disabled'], 403);
        }
        return new Response(403, ['Content-Type'=>'text/plain'], 'Batch resend disabled');
    }

    // POST /_admin/payments/force-settle
    // Força a baixa por referência + status e encaminha o payload ao cliente (forward)
    public function forceSettle(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return Response::json(['error'=>'Invalid CSRF'], 400);
        $reference = trim((string)($post['reference'] ?? ''));
        $newStatus = strtolower(trim((string)($post['status'] ?? '')));
        $stoneChargeId = trim((string)($post['stone_payment_id'] ?? ''));
        $providerEventId = trim((string)($post['provider_event_id'] ?? ''));
        if ($reference === '' || $newStatus === '') return Response::json(['error'=>'Missing reference or status'], 422);

        // Map pretty statuses to internal ones
        $allowed = ['created','queued','processing','authorized','paid','failed','canceled','refunded','chargeback'];
        if (!in_array($newStatus, $allowed, true)) return Response::json(['error'=>'Invalid status'], 422);

        $p = \App\Infrastructure\DB\Database::run('SELECT * FROM payments WHERE client_reference_id = ? ORDER BY id DESC LIMIT 1', [$reference])->fetch();
        if (!$p) return Response::json(['error'=>'Payment not found'], 404);

        // Update local status (+ opcionalmente stone_charge_id/provider_event_id)
        $old = (string)$p['status'];
        if ($stoneChargeId !== '' && (string)($p['stone_payment_id'] ?? '') === '') {
            \App\Infrastructure\DB\Database::run('UPDATE payments SET stone_payment_id = ? WHERE id = ?', [$stoneChargeId, $p['id']]);
            $p['stone_payment_id'] = $stoneChargeId;
        }
        if ($providerEventId !== '') {
            try { \App\Infrastructure\DB\Database::run('UPDATE payments SET provider_event_id = ? WHERE id = ?', [$providerEventId, $p['id']]); } catch (\Throwable $e) {}
        }
        if ($newStatus !== $old) {
            \App\Infrastructure\DB\Database::run('INSERT INTO payment_status_history (payment_id, old_status, new_status, reason) VALUES (?, ?, ?, ?)', [$p['id'], $old, $newStatus, 'admin_force_settle']);
            \App\Infrastructure\DB\Database::run('UPDATE payments SET status = ?, updated_at = NOW() WHERE id = ?', [$newStatus, $p['id']]);
            $p['status'] = $newStatus;
        }

        // Build minimal provider-like payload and forward to the client
        $event = match ($newStatus) {
            'paid' => 'charge.paid',
            'authorized' => 'transaction.authorized',
            'canceled' => 'charge.canceled',
            'refunded' => 'charge.refunded',
            'failed' => 'charge.failed',
            'chargeback' => 'charge.chargeback',
            'processing','queued','created' => 'order.processing',
            default => 'order.updated',
        };
        $payload = [
            'type' => $event,
            'data' => [
                'code' => $reference,
                'id' => $p['stone_payment_id'] ?? null,
                'payment_method' => $p['payment_method_type'] ?? null,
                'status' => $newStatus,
                'charges' => [[
                    'id' => $p['stone_payment_id'] ?? null,
                    'payment_method' => $p['payment_method_type'] ?? null,
                    'last_transaction' => [ 'status' => $newStatus ],
                ]],
            ],
        ];
        try {
            (new \App\Application\WebhookService())->forwardToSite((int)$p['site_id'], 0, (int)$p['id'], $payload, 1);
        } catch (\Throwable $e) {
            // Forward falhou: seguimos com a baixa local mesmo assim
        }

        if (($req->query['ajax'] ?? '') === '1' || str_contains(strtolower((string)($req->header('accept') ?? '')), 'application/json')) {
            return Response::json(['ok'=>1, 'id'=>(int)$p['id'], 'reference'=>$reference, 'status'=>$newStatus]);
        }
        return new Response(302, ['Location' => $this->prefix() . '/payments?forced=1'], '');
    }

    private function prefix(): string
    {
        return rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');
    }

    // GET /_admin/payments/json?id=123
    public function json(Request $req): Response
    {
        $id = (int)($req->query['id'] ?? 0);
        if ($id <= 0) return Response::json(['error'=>'Invalid id'], 422);
        $p = \App\Infrastructure\DB\Database::run('SELECT * FROM payments WHERE id = ? LIMIT 1', [$id])->fetch();
        if (!$p) return Response::json(['error'=>'Not found'], 404);
        $reqJ = $p['request_payload'] ? json_decode((string)$p['request_payload'], true) : null;
        $resJ = $p['response_payload'] ? json_decode((string)$p['response_payload'], true) : null;
        return Response::json([
            'payment' => [
                'id' => (int)$p['id'],
                'site_id' => (int)$p['site_id'],
                'reference' => $p['client_reference_id'],
                'status' => $p['status'],
                'amount_cents' => (int)$p['amount_cents'],
                'currency' => $p['currency'],
                'stone_payment_id' => $p['stone_payment_id'],
                'created_at' => $p['created_at'],
            ],
            'request' => $reqJ,
            'response' => $resJ,
            'metadata' => $p['metadata'] ? json_decode((string)$p['metadata'], true) : null,
        ]);
    }

    // POST /_admin/payments/delete
    public function delete(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return Response::json(['error'=>'Invalid CSRF'], 400);
        $id = (int)($post['payment_id'] ?? 0);
        $reason = trim((string)($post['reason'] ?? ''));
        if ($id <= 0) return Response::json(['error'=>'Missing payment id'], 422);
        $p = \App\Infrastructure\DB\Database::run('SELECT * FROM payments WHERE id = ? LIMIT 1', [$id])->fetch();
        if (!$p) return Response::json(['error'=>'Not found'], 404);
        \App\Infrastructure\DB\Database::run('UPDATE payments SET status="deleted", updated_at=NOW() WHERE id = ?', [$id]);
        $adminId = $_SESSION['admin_user_id'] ?? null;
        \App\Infrastructure\DB\Database::run('INSERT INTO soft_deletions (table_name, record_id, record_type, reason, admin_user_id, deleted_at) VALUES (?,?,?,?,?,NOW())', [
            'payments', $id, 'payment', $reason ?: null, $adminId ?: null
        ]);
        if (($req->query['ajax'] ?? '') === '1' || strpos($req->header('Accept',''), 'application/json') !== false) {
            return Response::json(['ok'=>1]);
        }
        return new Response(302, ['Location' => $this->prefix() . '/payments?deleted=1'], '');
    }

    // GET /_admin/payments/{id}/history
    public function history(Request $req): Response
    {
        $id = (int)($req->attributes['routeParams']['id'] ?? 0);
        $p = \App\Infrastructure\DB\Database::run('SELECT p.*, s.name AS site_name FROM payments p JOIN sites s ON s.id=p.site_id WHERE p.id = ? LIMIT 1', [$id])->fetch();
        if (!$p) return new Response(404, ['Content-Type'=>'text/plain'], 'Not found');
        $rows = [];
        if ($this->hasTable('payment_status_history')) {
            try {
                $rows = \App\Infrastructure\DB\Database::run('SELECT * FROM payment_status_history WHERE payment_id = ? ORDER BY id DESC', [$id])->fetchAll();
            } catch (\Throwable $e) {
                $rows = [];
            }
        }

        // Webhooks relacionados ao pagamento
        $webhooks = [];
        if ($this->hasTable('inbound_webhooks')) {
            $whParams = [];
            $whWhereParts = [];
            if ($this->hasColumn('inbound_webhooks', 'payment_id')) {
                $whWhereParts[] = 'payment_id = ?';
                $whParams[] = $id;
            }
            if (!empty($p['stone_payment_id']) && $this->hasColumn('inbound_webhooks', 'stone_payment_id')) {
                $whWhereParts[] = 'stone_payment_id = ?';
                $whParams[] = $p['stone_payment_id'];
            }
            if (!empty($whWhereParts)) {
                try {
                    $webhooks = \App\Infrastructure\DB\Database::run(
                        'SELECT * FROM inbound_webhooks WHERE (' . implode(' OR ', $whWhereParts) . ') ORDER BY received_at DESC, id DESC',
                        $whParams
                    )->fetchAll();
                } catch (\Throwable $e) {
                    $webhooks = [];
                }
            }
        }

        $forwardLogs = [];
        if ($this->hasTable('webhook_forward_logs')) {
            $hasForwardNotification = $this->hasColumn('webhook_forward_logs', 'notification_id');
            $hasForwardPayment = $this->hasColumn('webhook_forward_logs', 'payment_id');
            $canJoinWebhooks = $this->hasTable('inbound_webhooks') && $hasForwardNotification;
            $selectProviderEvent = ($canJoinWebhooks && $this->hasColumn('inbound_webhooks', 'provider_event_id')) ? 'w.provider_event_id' : 'NULL';
            $selectInboundRequest = ($canJoinWebhooks && $this->hasColumn('inbound_webhooks', 'request_id')) ? 'w.request_id' : 'NULL';
            $select = 'SELECT f.*, ' . $selectProviderEvent . ' AS provider_event_id, ' . $selectInboundRequest . ' AS inbound_request_id FROM webhook_forward_logs f';
            $join = $canJoinWebhooks ? ' LEFT JOIN inbound_webhooks w ON w.id=f.notification_id' : '';
            try {
                if (!empty($webhooks) && $hasForwardNotification) {
                    $ids = array_map(fn($w) => (int)$w['id'], $webhooks);
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    $forwardLogs = \App\Infrastructure\DB\Database::run(
                        $select . $join . ' WHERE f.notification_id IN (' . $in . ') ORDER BY f.sent_at DESC, f.id DESC',
                        $ids
                    )->fetchAll();
                } elseif ($hasForwardPayment) {
                    $forwardLogs = \App\Infrastructure\DB\Database::run(
                        $select . $join . ' WHERE f.payment_id = ? ORDER BY f.sent_at DESC, f.id DESC',
                        [$id]
                    )->fetchAll();
                }
            } catch (\Throwable $e) {
                $forwardLogs = [];
            }
        }

        $statusMap = [ 'created'=>'Criado','queued'=>'Na fila','processing'=>'Processando','authorized'=>'Autorizado','paid'=>'Pago','failed'=>'Falhou','canceled'=>'Cancelado','refunded'=>'Estornado','chargeback'=>'Chargeback' ];
        $history = array_map(function($h) use ($statusMap){
            $context = $h['context'] ? json_decode((string)$h['context'], true) : null;
            $context = $this->translateProviderMessagesInContext($context);
            return [
                'id' => (int)$h['id'],
                'old_status' => $h['old_status'],
                'old_status_label' => $statusMap[$h['old_status']] ?? $h['old_status'],
                'new_status' => $h['new_status'],
                'new_status_label' => $statusMap[$h['new_status']] ?? $h['new_status'],
                'reason' => $h['reason'],
                'context' => $context,
                'created_at' => $h['created_at'],
            ];
        }, $rows);

        $webhooksOut = [];
        foreach ($webhooks as $w) {
            $payload = json_decode((string)$w['raw_payload'], true);
            $payloadStatus = is_array($payload) ? $this->extractPayloadStatus($payload) : null;
            $webhooksOut[] = [
                'id' => (int)$w['id'],
                'provider_event_id' => $w['provider_event_id'] ?? null,
                'event_type' => $w['event_type'] ?? null,
                'status' => $w['status'] ?? null,
                'payload_status' => $payloadStatus,
                'request_id' => $w['request_id'] ?? null,
                'received_at' => $w['received_at'] ?? null,
                'processed_at' => $w['processed_at'] ?? null,
            ];
        }

        $forwardsOut = [];
        foreach ($forwardLogs as $f) {
            $forwardsOut[] = [
                'id' => (int)$f['id'],
                'notification_id' => (int)($f['notification_id'] ?? 0),
                'provider_event_id' => $f['provider_event_id'] ?? null,
                'attempt' => (int)($f['attempt'] ?? 1),
                'target_url' => $f['target_url'] ?? null,
                'response_status' => isset($f['response_status']) ? (int)$f['response_status'] : null,
                'success' => (int)($f['success'] ?? 0),
                'error' => $f['error'] ?? null,
                'request_id' => $f['request_id'] ?? ($f['inbound_request_id'] ?? null),
                'sent_at' => $f['sent_at'] ?? null,
            ];
        }

        // Timeline unificada
        $timeline = [];
        foreach ($history as $h) {
            $timeline[] = [
                'type' => 'status',
                'at' => $h['created_at'] ?? null,
                'data' => $h,
            ];
        }
        foreach ($webhooksOut as $w) {
            $timeline[] = [
                'type' => 'webhook',
                'at' => $w['received_at'] ?? null,
                'data' => $w,
            ];
        }
        foreach ($forwardsOut as $f) {
            $timeline[] = [
                'type' => 'forward',
                'at' => $f['sent_at'] ?? null,
                'data' => $f,
            ];
        }
        usort($timeline, function($a, $b){
            $ta = (string)($a['at'] ?? '');
            $tb = (string)($b['at'] ?? '');
            if ($ta === $tb) return 0;
            return $ta < $tb ? 1 : -1;
        });

        // Discrepâncias: status no Stone vs interno
        $stoneStatus = null;
        if (!empty($webhooksOut)) {
            $latest = $webhooksOut[0];
            $stoneStatus = $this->mapEventToStatus((string)($latest['event_type'] ?? '')) ?: ($latest['payload_status'] ?? null);
        }
        $internalStatus = (string)($p['status'] ?? '');
        $recon = 'sem_evento';
        if ($stoneStatus) {
            $recon = ($stoneStatus === $internalStatus) ? 'ok' : 'divergente';
        }

        // JSON for AJAX modal
        $accept = strtolower((string)($req->header('accept') ?? ''));
        $isAjax = (($req->query['ajax'] ?? '') === '1') || str_contains($accept, 'application/json');
        if ($isAjax) {
            return Response::json([
                'payment' => [
                    'id' => (int)$p['id'],
                    'site' => $p['site_name'],
                    'reference' => $p['client_reference_id'],
                    'stone_payment_id' => $p['stone_payment_id'],
                    'status' => $p['status'],
                    'status_label' => $statusMap[$p['status']] ?? $p['status'],
                    'amount_cents' => (int)$p['amount_cents'],
                    'currency' => $p['currency'],
                ],
                'history' => $history,
                'webhooks' => $webhooksOut,
                'forwards' => $forwardsOut,
                'timeline' => $timeline,
                'reconciliation' => [
                    'internal_status' => $internalStatus,
                    'internal_status_label' => $statusMap[$internalStatus] ?? $internalStatus,
                    'stone_status' => $stoneStatus,
                    'stone_status_label' => $stoneStatus ? ($statusMap[$stoneStatus] ?? $stoneStatus) : null,
                    'status' => $recon,
                ],
            ]);
        }
        $html = View::render('admin/payment_history', [
            'prefix' => $this->prefix(),
            'payment' => $p,
            'history' => $history,
            'webhooks' => $webhooksOut,
            'forwards' => $forwardsOut,
            'timeline' => $timeline,
            'reconciliation' => [
                'internal_status' => $internalStatus,
                'internal_status_label' => $statusMap[$internalStatus] ?? $internalStatus,
                'stone_status' => $stoneStatus,
                'stone_status_label' => $stoneStatus ? ($statusMap[$stoneStatus] ?? $stoneStatus) : null,
                'status' => $recon,
            ],
        ]);
        return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
    }

    private function mapEventToStatus(string $event): ?string
    {
        $e = strtolower($event);
        if (str_contains($e, 'paid')) return 'paid';
        if (str_contains($e, 'authorized') || str_contains($e, 'authoriz')) return 'authorized';
        if (str_contains($e, 'refunded') || str_contains($e, 'refund') || str_contains($e, 'reversed') || str_contains($e, 'voided')) return 'refunded';
        if (str_contains($e, 'canceled') || str_contains($e, 'cancelled') || str_contains($e, 'void')) return 'canceled';
        if (str_contains($e, 'failed') || str_contains($e, 'denied') || str_contains($e, 'refused')) return 'failed';
        if (str_contains($e, 'chargeback') || str_contains($e, 'dispute')) return 'chargeback';
        if (str_contains($e, 'protest')) return 'failed';
        if (str_contains($e, 'processing') || str_contains($e, 'pending')) return 'processing';
        return null;
    }

    private function extractPayloadStatus(array $payload): ?string
    {
        $st = strtolower((string)($payload['status'] ?? ($payload['data']['status'] ?? '')));
        if (in_array($st, ['paid','authorized','processing','pending','canceled','refunded','failed','chargeback'], true)) {
            return $st;
        }
        return null;
    }

    private function translateProviderMessagesInContext(mixed $context): mixed
    {
        if (is_string($context)) {
            return I18n::translateProviderMessagePt($context);
        }
        if (!is_array($context)) return $context;
        $keys = ['message','error','error_message','provider_message','provider_error','detail','details','description'];
        foreach ($context as $k => $v) {
            if (is_string($v)) {
                if (in_array((string)$k, $keys, true)) {
                    $context[$k] = I18n::translateProviderMessagePt($v);
                }
            } elseif (is_array($v)) {
                if (array_is_list($v)) {
                    $context[$k] = array_map(fn($item) => $this->translateProviderMessagesInContext($item), $v);
                } else {
                    $context[$k] = $this->translateProviderMessagesInContext($v);
                }
            }
        }
        return $context;
    }

    private function hasTable(string $table): bool
    {
        try {
            $row = Database::run('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1', [$table])->fetch();
            return (bool)$row;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            $row = Database::run('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1', [$table, $column])->fetch();
            return (bool)$row;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
