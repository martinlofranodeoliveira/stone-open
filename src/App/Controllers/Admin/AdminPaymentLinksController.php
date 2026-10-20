<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\View\View;
use App\Support\Env;

final class AdminPaymentLinksController
{
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
        $name = trim((string)($row['customer_name'] ?? ''));
        if ($name !== '') return $name;
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

    private function persistCustomerName(int $linkId, string $name): void
    {
        if ($linkId <= 0 || trim($name) === '') return;
        try {
            Database::run('UPDATE payment_links SET customer_name = ? WHERE id = ? AND (customer_name IS NULL OR customer_name = "")', [$name, $linkId]);
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
        return $v;
    }

    private function normalizeDateEnd(?string $v): ?string
    {
        $start = $this->normalizeDateStart($v);
        if ($start === null) return null;
        if (preg_match('/\d{2}:\d{2}:\d{2}$/', (string)$v)) return (string)$v;
        return $start . ' 23:59:59';
    }

    private function normalizeDocument(?string $v): string
    {
        $digits = preg_replace('/\D+/', '', trim((string)($v ?? '')));
        return is_string($digits) ? $digits : '';
    }

    private function paymentLinkFiltersFromRequest(Request $req): array
    {
        $env = strtolower(trim((string)($req->query['env'] ?? '')));
        if ($env !== '' && !in_array($env, ['sandbox', 'production'], true)) {
            $env = '';
        }

        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : 0;

        return [
            'site_id' => $siteId > 0 ? $siteId : '',
            'status' => trim((string)($req->query['status'] ?? '')),
            'env' => $env,
            'q' => trim((string)($req->query['q'] ?? '')),
            'transaction_id' => trim((string)($req->query['transaction_id'] ?? '')),
            'name' => trim((string)($req->query['name'] ?? '')),
            'email' => trim((string)($req->query['email'] ?? '')),
            'document' => $this->normalizeDocument((string)($req->query['document'] ?? ($req->query['cpf'] ?? ''))),
            'from' => (string)($req->query['from'] ?? ''),
            'to' => (string)($req->query['to'] ?? ''),
        ];
    }

    private function paymentLinkBaseFromSql(): string
    {
        return ' FROM payment_links pl JOIN sites s ON s.id=pl.site_id WHERE (pl.status <> "deleted" OR pl.status IS NULL)';
    }

    private function paymentLinkListSelectSql(): string
    {
        return 'SELECT pl.*, s.name AS site_name' . $this->paymentLinkBaseFromSql();
    }

    private function paymentLinkDataSelectSql(): string
    {
        return 'SELECT pl.id, s.name AS site_name, pl.env, pl.code, pl.stone_order_id, pl.customer_name, pl.customer_email, pl.request_payload, pl.response_payload, pl.amount_cents, pl.status, pl.checkout_url, pl.expires_at, pl.created_at';
    }

    private function applyPaymentLinkFilters(string $sql, array &$params, array $filter): string
    {
        if ($filter['site_id'] !== '') {
            $sql .= ' AND pl.site_id = ?';
            $params[] = (int)$filter['site_id'];
        }
        if ($filter['status'] !== '') {
            $sql .= ' AND pl.status = ?';
            $params[] = $filter['status'];
        }
        if ($filter['env'] !== '') {
            $sql .= ' AND pl.env = ?';
            $params[] = $filter['env'];
        }
        if ($filter['q'] !== '') {
            $sql = $this->appendPaymentLinkLikeFilter($sql, $params, [
                'pl.code',
                'pl.stone_order_id',
            ], $filter['q']);
        }
        if ($filter['transaction_id'] !== '') {
            $sql = $this->appendPaymentLinkLikeFilter($sql, $params, [
                'pl.stone_order_id',
                'pl.request_payload',
                'pl.response_payload',
            ], $filter['transaction_id']);
        }
        if ($filter['name'] !== '') {
            $sql = $this->appendPaymentLinkLikeFilter($sql, $params, [
                'pl.customer_name',
                'pl.request_payload',
                'pl.response_payload',
            ], $filter['name']);
        }
        if ($filter['email'] !== '') {
            $sql = $this->appendPaymentLinkLikeFilter($sql, $params, [
                'pl.customer_email',
                'pl.request_payload',
                'pl.response_payload',
            ], $filter['email']);
        }
        if ($filter['document'] !== '') {
            $sql = $this->appendPaymentLinkLikeFilter($sql, $params, [
                'pl.request_payload',
                'pl.response_payload',
            ], $filter['document']);
        }

        $from = $this->normalizeDateStart((string)($filter['from'] ?? ''));
        $to = $this->normalizeDateEnd((string)($filter['to'] ?? ''));
        if ($from !== null) {
            $sql .= ' AND pl.created_at >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $sql .= ' AND pl.created_at <= ?';
            $params[] = $to;
        }

        return $sql;
    }

    private function applyPaymentLinkDataTableSearch(string $sql, array &$params, string $search): string
    {
        $search = trim($search);
        if ($search === '') {
            return $sql;
        }

        $like = '%' . $search . '%';
        $clauses = [
            's.name LIKE ?',
            'pl.code LIKE ?',
            'pl.stone_order_id LIKE ?',
            'pl.customer_name LIKE ?',
            'pl.customer_email LIKE ?',
            'pl.request_payload LIKE ?',
            'pl.response_payload LIKE ?',
        ];
        foreach ($clauses as $_) {
            $params[] = $like;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $search, $m)) {
            $clauses[] = 'DATE(pl.created_at) = ?';
            $params[] = $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        $sql .= ' AND (' . implode(' OR ', $clauses) . ')';
        return $sql;
    }

    private function appendPaymentLinkLikeFilter(string $sql, array &$params, array $columns, string $term): string
    {
        $term = trim($term);
        if ($term === '' || $columns === []) {
            return $sql;
        }

        $like = '%' . $term . '%';
        $clauses = [];
        foreach ($columns as $column) {
            $clauses[] = $column . ' LIKE ?';
            $params[] = $like;
        }
        $sql .= ' AND (' . implode(' OR ', $clauses) . ')';
        return $sql;
    }

    public function list(Request $req): Response
    {
        $filter = $this->paymentLinkFiltersFromRequest($req);

        $sql = $this->paymentLinkListSelectSql();
        $params = [];
        $sql = $this->applyPaymentLinkFilters($sql, $params, $filter);
        $page = max(1, (int)($req->query['page'] ?? 1));
        $perPage = min(100, max(10, (int)($req->query['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $total = (int) Database::run('SELECT COUNT(*) c FROM (' . $sql . ') t', $params)->fetch()['c'];
        $sql .= ' ORDER BY pl.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        $rows = Database::run($sql, $params)->fetchAll();
        foreach ($rows as &$r) {
            $r['customer_name'] = $this->resolveCustomerName($r);
        }
        unset($r);
        $sites = Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll();

        $html = View::render('admin/payment_links_list', [
            'prefix' => $this->prefix(),
            'links' => $rows,
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

        $filter = $this->paymentLinkFiltersFromRequest($req);

        $base = $this->paymentLinkBaseFromSql();
        $params = [];
        $base = $this->applyPaymentLinkFilters($base, $params, $filter);

        $total = (int) Database::run('SELECT COUNT(*) c ' . $base, $params)->fetch()['c'];
        $filtered = $total;
        $rowBase = $base;
        $rowParams = $params;
        if ($search !== '') {
            $rowBase = $this->applyPaymentLinkDataTableSearch($base, $rowParams, $search);
            $filtered = (int) Database::run('SELECT COUNT(*) c ' . $rowBase, $rowParams)->fetch()['c'];
        }

        $orderColIdx = (int)($req->query['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string)($req->query['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $columns = [null,'pl.id','s.name','pl.env','pl.code','pl.customer_name','pl.amount_cents','pl.status','pl.checkout_url','pl.expires_at','pl.created_at',null];
        $orderBy = $columns[$orderColIdx] ?? 'pl.id';
        if (!$orderBy) $orderBy = 'pl.id';

        $sql = $this->paymentLinkDataSelectSql() . $rowBase . ' ORDER BY ' . $orderBy . ' ' . $orderDir . ' LIMIT ' . (int)$length . ' OFFSET ' . (int)$start;
        $rows = Database::run($sql, $rowParams)->fetchAll();
        foreach ($rows as &$r) {
            $r['customer_name'] = $this->resolveCustomerName($r);
            unset($r['request_payload'], $r['response_payload']);
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
        $filter = $this->paymentLinkFiltersFromRequest($req);

        $sql = $this->paymentLinkListSelectSql();
        $params = [];
        $sql = $this->applyPaymentLinkFilters($sql, $params, $filter);
        $sql .= ' ORDER BY pl.id DESC LIMIT 20000';
        $rows = Database::run($sql, $params)->fetchAll();
        foreach ($rows as &$r) {
            $r['customer_name'] = $this->resolveCustomerName($r);
        }
        unset($r);
        $cols = [
            'id' => 'ID','site_name' => 'Site','env' => 'Ambiente','code' => 'Código','customer_name' => 'Nome','stone_order_id' => 'Stone Order','amount_cents' => 'Valor (centavos)','status' => 'Status','checkout_url' => 'URL','expires_at' => 'Expira em','created_at' => 'Criado em'
        ];
        return Response::csv($rows, $cols, 'payment_links.csv');
    }

    public function exportXls(Request $req): Response
    {
        // Reaproveita exportCsv
        return $this->exportCsv($req);
    }

    private function prefix(): string
    {
        return rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');
    }

    public function json(Request $req): Response
    {
        $id = (int)($req->query['id'] ?? 0);
        if ($id <= 0) return Response::json(['error'=>'Invalid id'], 422);
        $r = Database::run('SELECT * FROM payment_links WHERE id = ? LIMIT 1', [$id])->fetch();
        if (!$r) return Response::json(['error'=>'Not found'], 404);
        $reqJ = $r['request_payload'] ? json_decode((string)$r['request_payload'], true) : null;
        $resJ = $r['response_payload'] ? json_decode((string)$r['response_payload'], true) : null;
        return Response::json(['link' => $r, 'request' => $reqJ, 'response' => $resJ]);
    }

    public function delete(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return Response::json(['error'=>'Invalid CSRF'], 400);
        $id = (int)($post['id'] ?? 0);
        $reason = trim((string)($post['reason'] ?? ''));
        if ($id <= 0) return Response::json(['error'=>'Invalid id'], 422);
        Database::run('UPDATE payment_links SET status="deleted", updated_at=NOW() WHERE id=?', [$id]);
        $adminId = $_SESSION['admin_user_id'] ?? null;
        Database::run('INSERT INTO soft_deletions (table_name, record_id, record_type, reason, admin_user_id, deleted_at) VALUES (?,?,?,?,?,NOW())', [
            'payment_links', $id, 'payment_link', $reason ?: null, $adminId ?: null
        ]);
        if (($req->query['ajax'] ?? '') === '1' || strpos($req->header('Accept',''), 'application/json') !== false) {
            return Response::json(['ok'=>1]);
        }
        return new Response(302, ['Location' => $this->prefix() . '/payment-links'], '');
    }

    // POST /_admin/payment-links/resend
    public function resend(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return Response::json(['error'=>'Invalid CSRF'], 400);
        $id = (int)($post['payment_link_id'] ?? 0);
        if ($id <= 0) return Response::json(['error'=>'Missing payment_link_id'], 422);
        $ok=0; $fail=0; $skip=0;
        $log = null;
        try {
            $pl = Database::run('SELECT * FROM payment_links WHERE id = ? LIMIT 1', [$id])->fetch();
            if (!$pl) { $skip++; }
            else {
                $payload = json_decode((string)($pl['response_payload'] ?? ''), true);
                if (!is_array($payload) || empty($payload)) {
                    $payload = json_decode((string)($pl['request_payload'] ?? ''), true);
                }
                if (!is_array($payload)) $payload = [];
                $status = strtolower((string)($pl['status'] ?? ''));
                $eventType = match ($status) {
                    'paid' => 'order.paid',
                    'canceled' => 'order.canceled',
                    'failed' => 'order.payment_failed',
                    'expired' => 'order.canceled',
                    'closed' => 'order.closed',
                    default => 'order.created',
                };
                // Garantir que o payload tenha um tipo para mapear status no forward
                if (empty($payload['type']) && empty($payload['event'])) {
                    $payload['type'] = $eventType;
                }
                $providerEventId = 'manual_link_' . (int)$pl['id'] . '_' . time() . '_' . random_int(1000,9999);
                Database::run('INSERT INTO inbound_webhooks (provider, provider_event_id, event_type, stone_payment_id, stone_subscription_id, site_id, payment_id, raw_payload, headers, signature, status, processed_at) VALUES ("manual", ?, ?, ?, ?, ?, NULL, ?, NULL, NULL, "processed", NOW())', [
                    $providerEventId,
                    $eventType,
                    $pl['stone_order_id'] ?? null,
                    null,
                    $pl['site_id'],
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]);
                $inb = Database::run('SELECT * FROM inbound_webhooks WHERE provider = "manual" AND provider_event_id = ? LIMIT 1', [$providerEventId])->fetch();
                if ($inb) {
                    $last = Database::run('SELECT MAX(attempt) a FROM webhook_forward_logs WHERE notification_id = ?', [$inb['id']])->fetch();
                    $attempt = ((int)($last['a'] ?? 0)) + 1;
                    (new \App\Application\WebhookService())->forwardToSite((int)$pl['site_id'], (int)$inb['id'], null, $payload, $attempt);
                    $log = Database::run('SELECT response_status, success, error, target_url FROM webhook_forward_logs WHERE notification_id = ? AND attempt = ? ORDER BY id DESC LIMIT 1', [$inb['id'], $attempt])->fetch() ?: null;
                    $ok++;
                } else {
                    $fail++;
                }
            }
        } catch (\Throwable $e) { $fail++; }
        if (($req->query['ajax'] ?? '') === '1' || strpos($req->header('Accept',''), 'application/json') !== false) {
            return Response::json(['resent'=>$ok,'failed'=>$fail,'skipped'=>$skip,'log'=>$log]);
        }
        return new Response(302, ['Location' => $this->prefix() . '/payment-links?resent='.$ok.'&failed='.$fail.'&skipped='.$skip], '');
    }
}
