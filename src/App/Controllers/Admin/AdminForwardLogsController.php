<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\View\View;
use App\Support\Env;

final class AdminForwardLogsController
{
    public function list(Request $req): Response
    {
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $notificationId = isset($req->query['notification_id']) ? (int)$req->query['notification_id'] : null;
        $success = (string)($req->query['success'] ?? '');
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $sql = 'SELECT f.*, s.name as site_name FROM webhook_forward_logs f JOIN sites s ON s.id=f.site_id WHERE 1';
        $params = [];
        if ($siteId) { $sql .= ' AND f.site_id = ?'; $params[] = $siteId; }
        if ($notificationId) { $sql .= ' AND f.notification_id = ?'; $params[] = $notificationId; }
        if ($success !== '') { $sql .= ' AND f.success = ?'; $params[] = ($success==='1'||$success==='true')?1:0; }
        if ($from !== '') { $sql .= ' AND f.sent_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND f.sent_at <= ?'; $params[] = $to; }
        $page = max(1, (int)($req->query['page'] ?? 1));
        $perPage = min(100, max(10, (int)($req->query['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $sqlCount = 'SELECT COUNT(*) c FROM (' . $sql . ') t';
        $total = (int) Database::run($sqlCount, $params)->fetch()['c'];
        $sql .= ' ORDER BY f.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        $rows = Database::run($sql, $params)->fetchAll();
        $sites = Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll();
        $html = View::render('admin/forward_logs_list', [
            'prefix' => $this->prefix(),
            'logs' => $rows,
            'sites' => $sites,
            'filter' => compact('siteId','notificationId','success','from','to'),
            'page' => $page,'perPage' => $perPage,'total' => $total,
            'csrf' => \App\Support\Security\Csrf::token(),
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function exportCsv(Request $req): Response
    {
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $notificationId = isset($req->query['notification_id']) ? (int)$req->query['notification_id'] : null;
        $success = (string)($req->query['success'] ?? '');
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $sql = 'SELECT f.*, s.name as site_name FROM webhook_forward_logs f JOIN sites s ON s.id=f.site_id WHERE 1';
        $params = [];
        if ($siteId) { $sql .= ' AND f.site_id = ?'; $params[] = $siteId; }
        if ($notificationId) { $sql .= ' AND f.notification_id = ?'; $params[] = $notificationId; }
        if ($success !== '') { $sql .= ' AND f.success = ?'; $params[] = ($success==='1'||$success==='true')?1:0; }
        if ($from !== '') { $sql .= ' AND f.sent_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND f.sent_at <= ?'; $params[] = $to; }
        $sql .= ' ORDER BY f.id DESC LIMIT 20000';
        $rows = Database::run($sql, $params)->fetchAll();
        $cols = [
            'id'=>'ID','site_name'=>'Site','notification_id'=>'Notificação','payment_id'=>'Pagamento','target_url'=>'URL','attempt'=>'Tentativa','response_status'=>'HTTP','success'=>'Sucesso','sent_at'=>'Enviado em'
        ];
        return Response::csv($rows, $cols, 'forward_logs.csv');
    }

    public function exportXls(Request $req): Response
    {
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $notificationId = isset($req->query['notification_id']) ? (int)$req->query['notification_id'] : null;
        $success = (string)($req->query['success'] ?? '');
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $sql = 'SELECT f.*, s.name as site_name FROM webhook_forward_logs f JOIN sites s ON s.id=f.site_id WHERE 1';
        $params = [];
        if ($siteId) { $sql .= ' AND f.site_id = ?'; $params[] = $siteId; }
        if ($notificationId) { $sql .= ' AND f.notification_id = ?'; $params[] = $notificationId; }
        if ($success !== '') { $sql .= ' AND f.success = ?'; $params[] = ($success==='1'||$success==='true')?1:0; }
        if ($from !== '') { $sql .= ' AND f.sent_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND f.sent_at <= ?'; $params[] = $to; }
        $sql .= ' ORDER BY f.id DESC LIMIT 20000';
        $rows = Database::run($sql, $params)->fetchAll();
        $cols = [ 'id'=>'ID','site_name'=>'Site','notification_id'=>'Notificação','payment_id'=>'Pagamento','target_url'=>'URL','attempt'=>'Tentativa','response_status'=>'HTTP','success'=>'Sucesso','sent_at'=>'Enviado em' ];
        return Response::xls($rows, $cols, 'forward_logs.xls');
    }

    public function data(Request $req): Response
    {
        $draw = (int)($req->query['draw'] ?? 1);
        $start = max(0, (int)($req->query['start'] ?? 0));
        $length = min(200, max(10, (int)($req->query['length'] ?? 25)));
        $search = trim((string)($req->query['search']['value'] ?? ''));

        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $notificationId = isset($req->query['notification_id']) ? (int)$req->query['notification_id'] : null;
        $success = (string)($req->query['success'] ?? '');
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');

        $base = ' FROM webhook_forward_logs f JOIN sites s ON s.id=f.site_id WHERE 1';
        $params = [];
        if ($siteId) { $base .= ' AND f.site_id = ?'; $params[] = $siteId; }
        if ($notificationId) { $base .= ' AND f.notification_id = ?'; $params[] = $notificationId; }
        if ($success !== '') { $base .= ' AND f.success = ?'; $params[] = ($success==='1'||$success==='true')?1:0; }
        if ($from !== '') { $base .= ' AND f.sent_at >= ?'; $params[] = $from; }
        if ($to !== '') { $base .= ' AND f.sent_at <= ?'; $params[] = $to; }
        if ($search !== '') { $base .= ' AND (s.name LIKE ? OR f.target_url LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

        $total = (int) Database::run('SELECT COUNT(*) c FROM webhook_forward_logs')->fetch()['c'];
        $filtered = (int) Database::run('SELECT COUNT(*) c ' . $base, $params)->fetch()['c'];

        $orderColIdx = (int)($req->query['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string)($req->query['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $columns = ['f.id','s.name','f.notification_id','f.payment_id','f.target_url','f.attempt','f.response_status','f.success','f.sent_at'];
        $orderBy = $columns[$orderColIdx] ?? 'f.id';

        $sql = 'SELECT f.id, s.name AS site_name, f.notification_id, f.payment_id, f.target_url, f.attempt, f.response_status, f.success, f.sent_at ' . $base . ' ORDER BY ' . $orderBy . ' ' . $orderDir . ' LIMIT ' . (int)$length . ' OFFSET ' . (int)$start;
        $rows = Database::run($sql, $params)->fetchAll();
        return Response::json([
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
}
