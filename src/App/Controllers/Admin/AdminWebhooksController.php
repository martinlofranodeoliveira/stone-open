<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\View\View;
use App\Support\Security\Csrf;
use App\Support\Env;
use App\Application\WebhookService;

final class AdminWebhooksController
{
    public function lookupByProviderEvent(Request $req): Response
    {
        $pid = trim((string)($req->query['provider_event_id'] ?? ''));
        if ($pid === '') return Response::json(['error'=>'provider_event_id required'], 422);
        $row = Database::run('SELECT id FROM inbound_webhooks WHERE provider_event_id = ? ORDER BY id DESC LIMIT 1', [$pid])->fetch();
        if (!$row) return Response::json(['id'=>null]);
        return Response::json(['id'=>(int)$row['id']]);
    }
    public function list(Request $req): Response
    {
        $status = (string)($req->query['status'] ?? '');
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $event = (string)($req->query['event'] ?? '');
        $kind = (string)($req->query['kind'] ?? '');
        $kind = (string)($req->query['kind'] ?? '');
        $kind = (string)($req->query['kind'] ?? '');
        $kind = (string)($req->query['kind'] ?? '');
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');

        $sql = 'SELECT w.*, 
                       COALESCE(s.name, s2.name, s3.name, s4.name) AS site_name,
                       COALESCE(p.client_reference_id, p2.client_reference_id) AS ref_code,
                       CASE WHEN w.stone_subscription_id IS NOT NULL OR w.event_type LIKE "subscription.%" THEN "subscription" ELSE "payment" END AS kind
                FROM inbound_webhooks w 
                LEFT JOIN sites s ON s.id = w.site_id 
                LEFT JOIN payments p ON p.id = w.payment_id 
                LEFT JOIN payments p2 ON p2.stone_payment_id = w.stone_payment_id
                LEFT JOIN subscriptions sub ON sub.stone_subscription_id = w.stone_subscription_id
                LEFT JOIN sites s2 ON s2.id = p.site_id
                LEFT JOIN sites s3 ON s3.id = p2.site_id
                LEFT JOIN sites s4 ON s4.id = sub.site_id
                LEFT JOIN soft_deletions sd ON sd.table_name="inbound_webhooks" AND sd.record_id=w.id 
                WHERE sd.id IS NULL';
        $params = [];
        if ($status !== '') { $sql .= ' AND w.status = ?'; $params[] = $status; }
        if ($siteId) { $sql .= ' AND w.site_id = ?'; $params[] = $siteId; }
        if ($event !== '') { $sql .= ' AND w.event_type LIKE ?'; $params[] = "%$event%"; }
        if ($from !== '') { $sql .= ' AND w.received_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND w.received_at <= ?'; $params[] = $to; }
        if ($kind === 'payment') { $sql .= ' AND (w.stone_subscription_id IS NULL AND w.event_type NOT LIKE "subscription.%")'; }
        if ($kind === 'subscription') { $sql .= ' AND (w.stone_subscription_id IS NOT NULL OR w.event_type LIKE "subscription.%")'; }
        if ($kind === 'payment') { $sql .= ' AND (w.stone_subscription_id IS NULL AND w.event_type NOT LIKE "subscription.%")'; }
        if ($kind === 'subscription') { $sql .= ' AND (w.stone_subscription_id IS NOT NULL OR w.event_type LIKE "subscription.%")'; }
        if ($kind === 'payment') { $sql .= ' AND (w.stone_subscription_id IS NULL AND w.event_type NOT LIKE "subscription.%")'; }
        if ($kind === 'subscription') { $sql .= ' AND (w.stone_subscription_id IS NOT NULL OR w.event_type LIKE "subscription.%")'; }
        if ($kind === 'payment') { $sql .= ' AND (w.stone_subscription_id IS NULL AND w.event_type NOT LIKE "subscription.%")'; }
        if ($kind === 'subscription') { $sql .= ' AND (w.stone_subscription_id IS NOT NULL OR w.event_type LIKE "subscription.%")'; }
        if ($kind === 'payment') { $sql .= ' AND (w.stone_subscription_id IS NULL AND w.event_type NOT LIKE "subscription.%")'; }
        if ($kind === 'subscription') { $sql .= ' AND (w.stone_subscription_id IS NOT NULL OR w.event_type LIKE "subscription.%")'; }
        $page = max(1, (int)($req->query['page'] ?? 1));
        $perPage = min(100, max(10, (int)($req->query['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $sqlCount = 'SELECT COUNT(*) c FROM (' . $sql . ') t';
        $total = (int) Database::run($sqlCount, $params)->fetch()['c'];
        $sql .= ' ORDER BY w.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        $rows = Database::run($sql, $params)->fetchAll();
        $sites = Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll();

        $html = View::render('admin/webhooks_list', [
            'prefix' => $this->prefix(),
            'webhooks' => $rows,
            'sites' => $sites,
            'filter' => compact('status','siteId','event','from','to','kind'),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'csrf' => Csrf::token(),
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function resend(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!Csrf::check($post['csrf'] ?? null)) {
            if (($req->query['ajax'] ?? '') === '1' || str_contains(strtolower((string)$req->header('accept')), 'application/json')) {
                return Response::json(['error' => 'Invalid CSRF'], 400);
            }
            return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid CSRF');
        }
        $ids = $post['ids'] ?? [];
        if (!is_array($ids)) { $ids = [$ids]; }
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (count($ids) !== 1) {
            if (($req->query['ajax'] ?? '') === '1' || str_contains(strtolower((string)$req->header('accept')), 'application/json')) {
                return Response::json(['error' => 'Batch resend disabled'], 403);
            }
            return new Response(403, ['Content-Type' => 'text/plain'], 'Batch resend disabled');
        }
        $count = 0; $failed = 0; $resolved = 0; $errors = [];
        $svc = new WebhookService();
        foreach ($ids as $id) {
            $row = Database::run('SELECT * FROM inbound_webhooks WHERE id = ?', [$id])->fetch();
            if (!$row) continue;
            $payload = json_decode($row['raw_payload'] ?? '[]', true) ?: [];
            $last = Database::run('SELECT MAX(attempt) a FROM webhook_forward_logs WHERE notification_id = ?', [$id])->fetch();
            $attempt = ((int)($last['a'] ?? 0)) + 1;
            // Fallback: resolver site_id se ausente (pelo pagamento/cobrança/assinatura)
            $siteId = (int)($row['site_id'] ?? 0);
            if ($siteId <= 0) {
                if (!empty($row['payment_id'])) {
                    $pp = Database::run('SELECT site_id FROM payments WHERE id = ? LIMIT 1', [$row['payment_id']])->fetch();
                    if ($pp && isset($pp['site_id'])) $siteId = (int)$pp['site_id'];
                }
                if ($siteId <= 0 && !empty($row['stone_payment_id'])) {
                    $pp = Database::run('SELECT site_id FROM payments WHERE stone_payment_id = ? LIMIT 1', [$row['stone_payment_id']])->fetch();
                    if ($pp && isset($pp['site_id'])) $siteId = (int)$pp['site_id'];
                }
                if ($siteId <= 0 && !empty($row['stone_subscription_id'])) {
                    $ss = Database::run('SELECT site_id FROM subscriptions WHERE stone_subscription_id = ? LIMIT 1', [$row['stone_subscription_id']])->fetch();
                    if ($ss && isset($ss['site_id'])) $siteId = (int)$ss['site_id'];
                }
                if ($siteId > 0) {
                    try { Database::run('UPDATE inbound_webhooks SET site_id = ? WHERE id = ?', [$siteId, $id]); $resolved++; } catch (\Throwable $e) {}
                }
            }
            try {
                $svc->forwardToSite($siteId, (int)$row['id'], (int)($row['payment_id'] ?? 0) ?: null, $payload, $attempt);
                $count++;
            } catch (\Throwable $e) {
                $failed++; $errors[] = ['id'=>$id, 'error'=>$e->getMessage()];
            }
        }
        if (($req->query['ajax'] ?? '') === '1' || str_contains(strtolower((string)$req->header('accept')), 'application/json')) {
            return Response::json(['resent'=>$count, 'failed'=>$failed, 'resolved'=>$resolved, 'errors'=>$errors]);
        }
        return new Response(302, ['Location' => $this->prefix() . '/webhooks?resent=' . $count . '&failed=' . $failed . '&resolved=' . $resolved], '');
    }

    public function exportCsv(Request $req): Response
    {
        $status = (string)($req->query['status'] ?? '');
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $event = (string)($req->query['event'] ?? '');
        $kind = (string)($req->query['kind'] ?? '');
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $sql = 'SELECT w.*, 
                       COALESCE(s.name, s2.name, s3.name, s4.name) AS site_name,
                       COALESCE(p.client_reference_id, p2.client_reference_id) AS ref_code,
                       CASE WHEN w.stone_subscription_id IS NOT NULL OR w.event_type LIKE "subscription.%" THEN "subscription" ELSE "payment" END AS kind
                FROM inbound_webhooks w 
                LEFT JOIN sites s ON s.id = w.site_id 
                LEFT JOIN payments p ON p.id = w.payment_id 
                LEFT JOIN payments p2 ON p2.stone_payment_id = w.stone_payment_id
                LEFT JOIN subscriptions sub ON sub.stone_subscription_id = w.stone_subscription_id
                LEFT JOIN sites s2 ON s2.id = p.site_id
                LEFT JOIN sites s3 ON s3.id = p2.site_id
                LEFT JOIN sites s4 ON s4.id = sub.site_id
                LEFT JOIN soft_deletions sd ON sd.table_name="inbound_webhooks" AND sd.record_id=w.id 
                WHERE sd.id IS NULL';
        $params = [];
        if ($status !== '') { $sql .= ' AND w.status = ?'; $params[] = $status; }
        if ($siteId) { $sql .= ' AND w.site_id = ?'; $params[] = $siteId; }
        if ($event !== '') { $sql .= ' AND w.event_type LIKE ?'; $params[] = "%$event%"; }
        if ($from !== '') { $sql .= ' AND w.received_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND w.received_at <= ?'; $params[] = $to; }
        $sql .= ' ORDER BY w.id DESC LIMIT 20000';
        $rows = Database::run($sql, $params)->fetchAll();
        $cols = [
            'id'=>'ID','site_name'=>'Site','event_type'=>'Evento','status'=>'Status','received_at'=>'Recebido','processed_at'=>'Processado','provider_event_id'=>'Evento Provedor'
        ];
        return \App\Infrastructure\Http\Response::csv($rows, $cols, 'webhooks.csv');
    }

    public function exportXls(Request $req): Response
    {
        $status = (string)($req->query['status'] ?? '');
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $event = (string)($req->query['event'] ?? '');
        $kind = (string)($req->query['kind'] ?? '');
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $sql = 'SELECT w.*, 
                       COALESCE(s.name, s2.name, s3.name, s4.name) AS site_name
                FROM inbound_webhooks w 
                LEFT JOIN sites s ON s.id = w.site_id 
                LEFT JOIN payments p ON p.id = w.payment_id 
                LEFT JOIN payments p2 ON p2.stone_payment_id = w.stone_payment_id
                LEFT JOIN subscriptions sub ON sub.stone_subscription_id = w.stone_subscription_id
                LEFT JOIN sites s2 ON s2.id = p.site_id
                LEFT JOIN sites s3 ON s3.id = p2.site_id
                LEFT JOIN sites s4 ON s4.id = sub.site_id
                WHERE 1';
        $params = [];
        if ($status !== '') { $sql .= ' AND w.status = ?'; $params[] = $status; }
        if ($siteId) { $sql .= ' AND w.site_id = ?'; $params[] = $siteId; }
        if ($event !== '') { $sql .= ' AND w.event_type LIKE ?'; $params[] = "%$event%"; }
        if ($from !== '') { $sql .= ' AND w.received_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND w.received_at <= ?'; $params[] = $to; }
        if ($kind === 'payment') { $sql .= ' AND (w.stone_subscription_id IS NULL AND w.event_type NOT LIKE "subscription.%")'; }
        if ($kind === 'subscription') { $sql .= ' AND (w.stone_subscription_id IS NOT NULL OR w.event_type LIKE "subscription.%")'; }
        $sql .= ' ORDER BY w.id DESC LIMIT 20000';
        $rows = Database::run($sql, $params)->fetchAll();
        $cols = [ 'id'=>'ID','site_name'=>'Site','event_type'=>'Evento','status'=>'Status','received_at'=>'Recebido','processed_at'=>'Processado','provider_event_id'=>'Evento Provedor' ];
        return \App\Infrastructure\Http\Response::xls($rows, $cols, 'webhooks.xls');
    }

    public function data(Request $req): Response
    {
        $draw = (int)($req->query['draw'] ?? 1);
        $start = max(0, (int)($req->query['start'] ?? 0));
        $length = min(200, max(10, (int)($req->query['length'] ?? 25)));
        $search = trim((string)($req->query['search']['value'] ?? ''));

        $status = (string)($req->query['status'] ?? '');
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $event = (string)($req->query['event'] ?? '');
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');

        $base = ' FROM inbound_webhooks w 
                  LEFT JOIN sites s ON s.id=w.site_id 
                  LEFT JOIN payments p ON p.id = w.payment_id 
                  LEFT JOIN payments p2 ON p2.stone_payment_id = w.stone_payment_id
                  LEFT JOIN subscriptions sub ON sub.stone_subscription_id = w.stone_subscription_id
                  LEFT JOIN sites s2 ON s2.id = p.site_id
                  LEFT JOIN sites s3 ON s3.id = p2.site_id
                  LEFT JOIN sites s4 ON s4.id = sub.site_id
                  LEFT JOIN soft_deletions sd ON sd.table_name="inbound_webhooks" AND sd.record_id=w.id 
                  WHERE sd.id IS NULL';
        $params = [];
        if ($status !== '') { $base .= ' AND w.status = ?'; $params[] = $status; }
        if ($siteId) { $base .= ' AND w.site_id = ?'; $params[] = $siteId; }
        if ($event !== '') { $base .= ' AND w.event_type LIKE ?'; $params[] = "%$event%"; }
        if ($from !== '') { $base .= ' AND w.received_at >= ?'; $params[] = $from; }
        if ($to !== '') { $base .= ' AND w.received_at <= ?'; $params[] = $to; }
        if ($kind === 'payment') { $base .= ' AND (w.stone_subscription_id IS NULL AND w.event_type NOT LIKE "subscription.%")'; }
        if ($kind === 'subscription') { $base .= ' AND (w.stone_subscription_id IS NOT NULL OR w.event_type LIKE "subscription.%")'; }
        if ($search !== '') { 
            $base .= ' AND (COALESCE(s.name, s2.name, s3.name, s4.name) LIKE ? 
                         OR COALESCE(p.client_reference_id, p2.client_reference_id) LIKE ?
                         OR w.event_type LIKE ? 
                         OR w.provider_event_id LIKE ?)'; 
            $params[] = "%$search%"; 
            $params[] = "%$search%"; 
            $params[] = "%$search%"; 
            $params[] = "%$search%"; 
        }

        $total = (int) Database::run('SELECT COUNT(*) c FROM inbound_webhooks w LEFT JOIN soft_deletions sd ON sd.table_name="inbound_webhooks" AND sd.record_id=w.id WHERE sd.id IS NULL')->fetch()['c'];
        $filtered = (int) Database::run('SELECT COUNT(*) c ' . $base, $params)->fetch()['c'];

        $orderColIdx = (int)($req->query['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string)($req->query['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        // Map DataTables column index -> SQL column
        // UI columns: [0]=id, [1]=ref_code, [2]=site_name, [3]=is_subscription, [4]=event_type, [5]=status, [6]=received_at, [7]=actions
        $columns = [
            'w.id',
            'COALESCE(p.client_reference_id, p2.client_reference_id)',
            'COALESCE(s.name, s2.name, s3.name, s4.name)',
            'CASE WHEN w.stone_subscription_id IS NOT NULL OR w.event_type LIKE "subscription.%" THEN 1 ELSE 0 END',
            'w.event_type',
            'w.status',
            'w.received_at',
            'w.id'
        ];
        $orderBy = $columns[$orderColIdx] ?? 'w.id';
        $sql = 'SELECT 
                    w.id, 
                    COALESCE(s.name, s2.name, s3.name, s4.name) AS site_name, 
                    w.event_type, 
                    w.status, 
                    w.received_at,
                    COALESCE(p.client_reference_id, p2.client_reference_id) AS ref_code,
                    CASE WHEN w.stone_subscription_id IS NOT NULL OR w.event_type LIKE "subscription.%" THEN 1 ELSE 0 END AS is_subscription
                ' . $base . ' ORDER BY ' . $orderBy . ' ' . $orderDir . ' LIMIT ' . (int)$length . ' OFFSET ' . (int)$start;
        $rows = Database::run($sql, $params)->fetchAll();
        return \App\Infrastructure\Http\Response::json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows,
        ]);
    }

    private function prefix(): string
    {
        return rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');
    }

    public function json(Request $req): Response
    {
        $id = (int)($req->query['id'] ?? 0);
        if ($id <= 0) return Response::json(['error'=>'Invalid id'], 422);
        $w = Database::run('SELECT * FROM inbound_webhooks WHERE id = ? LIMIT 1', [$id])->fetch();
        if (!$w) return Response::json(['error'=>'Not found'], 404);
        $payload = $w['raw_payload'] ? json_decode((string)$w['raw_payload'], true) : null;
        $headers = $w['headers'] ? json_decode((string)$w['headers'], true) : null;
        return Response::json(['webhook' => $w, 'payload' => $payload, 'headers' => $headers]);
    }

    public function delete(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return Response::json(['error'=>'Invalid CSRF'], 400);
        $id = (int)($post['id'] ?? 0);
        $reason = trim((string)($post['reason'] ?? ''));
        if ($id <= 0) return Response::json(['error'=>'Invalid id'], 422);
        $adminId = $_SESSION['admin_user_id'] ?? null;
        Database::run('INSERT INTO soft_deletions (table_name, record_id, record_type, reason, admin_user_id, deleted_at) VALUES (?,?,?,?,?,NOW())', [
            'inbound_webhooks', $id, 'webhook', $reason ?: null, $adminId ?: null
        ]);
        if (($req->query['ajax'] ?? '') === '1' || strpos($req->header('Accept',''), 'application/json') !== false) {
            return Response::json(['ok'=>1]);
        }
        return new Response(302, ['Location' => $this->prefix() . '/webhooks'], '');
    }
}
