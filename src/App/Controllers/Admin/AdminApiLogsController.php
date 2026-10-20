<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\View\View;
use App\Support\Env;

final class AdminApiLogsController
{
    public function list(Request $req): Response
    {
        $sites = Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll();
        $filter = [
            'site_id' => isset($req->query['site_id']) ? (int)$req->query['site_id'] : null,
            'method' => (string)($req->query['method'] ?? ''),
            'path' => trim((string)($req->query['path'] ?? '')),
            'status' => isset($req->query['status']) && $req->query['status'] !== '' ? (int)$req->query['status'] : null,
            'from' => (string)($req->query['from'] ?? ''),
            'to' => (string)($req->query['to'] ?? ''),
        ];
        $html = View::render('admin/api_logs_list', [
            'prefix' => $this->prefix(),
            'sites' => $sites,
            'filter' => $filter,
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function data(Request $req): Response
    {
        $draw = (int)($req->query['draw'] ?? 1);
        $start = max(0, (int)($req->query['start'] ?? 0));
        $length = min(200, max(10, (int)($req->query['length'] ?? 25)));
        $search = trim((string)($req->query['search']['value'] ?? ''));

        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $method = (string)($req->query['method'] ?? '');
        $path = trim((string)($req->query['path'] ?? ''));
        $status = isset($req->query['status']) && $req->query['status'] !== '' ? (int)$req->query['status'] : null;
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');

        $base = ' FROM api_request_logs a LEFT JOIN sites s ON s.id=a.site_id WHERE 1';
        $params = [];
        if ($siteId) { $base .= ' AND a.site_id = ?'; $params[] = $siteId; }
        if ($method !== '') { $base .= ' AND a.method = ?'; $params[] = $method; }
        if ($path !== '') { $base .= ' AND a.path LIKE ?'; $params[] = "%$path%"; }
        if ($status !== null) { $base .= ' AND a.status = ?'; $params[] = $status; }
        if ($from !== '') { $base .= ' AND a.created_at >= ?'; $params[] = $from; }
        if ($to !== '') { $base .= ' AND a.created_at <= ?'; $params[] = $to; }
        if ($search !== '') { $base .= ' AND (s.name LIKE ? OR a.path LIKE ? OR a.ip LIKE ? OR a.client_id LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

        $total = (int) Database::run('SELECT COUNT(*) c FROM api_request_logs')->fetch()['c'];
        $filtered = (int) Database::run('SELECT COUNT(*) c ' . $base, $params)->fetch()['c'];

        $orderColIdx = (int)($req->query['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string)($req->query['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $columns = ['a.id','s.name','a.method','a.path','a.status','a.duration_ms','a.ip','a.created_at'];
        $orderBy = $columns[$orderColIdx] ?? 'a.id';

        $sql = 'SELECT a.id, s.name AS site_name, a.method, a.path, a.status, a.duration_ms, a.ip, a.created_at ' . $base . ' ORDER BY ' . $orderBy . ' ' . $orderDir . ' LIMIT ' . (int)$length . ' OFFSET ' . (int)$start;
        $rows = Database::run($sql, $params)->fetchAll();
        return Response::json(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$rows]);
    }

    public function exportCsv(Request $req): Response
    {
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $method = (string)($req->query['method'] ?? '');
        $path = trim((string)($req->query['path'] ?? ''));
        $status = isset($req->query['status']) && $req->query['status'] !== '' ? (int)$req->query['status'] : null;
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $sql = 'SELECT a.*, s.name AS site_name ' . 'FROM api_request_logs a LEFT JOIN sites s ON s.id=a.site_id WHERE 1';
        $params = [];
        if ($siteId) { $sql .= ' AND a.site_id = ?'; $params[] = $siteId; }
        if ($method !== '') { $sql .= ' AND a.method = ?'; $params[] = $method; }
        if ($path !== '') { $sql .= ' AND a.path LIKE ?'; $params[] = "%$path%"; }
        if ($status !== null) { $sql .= ' AND a.status = ?'; $params[] = $status; }
        if ($from !== '') { $sql .= ' AND a.created_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND a.created_at <= ?'; $params[] = $to; }
        $sql .= ' ORDER BY a.id DESC LIMIT 20000';
        $rows = Database::run($sql, $params)->fetchAll();
        $cols = [
            'id'=>'ID','site_name'=>'Site','method'=>'Método','path'=>'Rota','status'=>'HTTP','duration_ms'=>'ms','ip'=>'IP','auth_type'=>'Auth','client_id'=>'ClientId','created_at'=>'Quando','request_body'=>'Req','response_body'=>'Resp'
        ];
        return Response::csv($rows, $cols, 'api_request_logs.csv');
    }

    public function exportXls(Request $req): Response
    {
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $method = (string)($req->query['method'] ?? '');
        $path = trim((string)($req->query['path'] ?? ''));
        $status = isset($req->query['status']) && $req->query['status'] !== '' ? (int)$req->query['status'] : null;
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $sql = 'SELECT a.*, s.name AS site_name ' . 'FROM api_request_logs a LEFT JOIN sites s ON s.id=a.site_id WHERE 1';
        $params = [];
        if ($siteId) { $sql .= ' AND a.site_id = ?'; $params[] = $siteId; }
        if ($method !== '') { $sql .= ' AND a.method = ?'; $params[] = $method; }
        if ($path !== '') { $sql .= ' AND a.path LIKE ?'; $params[] = "%$path%"; }
        if ($status !== null) { $sql .= ' AND a.status = ?'; $params[] = $status; }
        if ($from !== '') { $sql .= ' AND a.created_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND a.created_at <= ?'; $params[] = $to; }
        $sql .= ' ORDER BY a.id DESC LIMIT 20000';
        $rows = Database::run($sql, $params)->fetchAll();
        $cols = [ 'id'=>'ID','site_name'=>'Site','method'=>'Método','path'=>'Rota','status'=>'HTTP','duration_ms'=>'ms','ip'=>'IP','auth_type'=>'Auth','client_id'=>'ClientId','created_at'=>'Quando','request_body'=>'Req','response_body'=>'Resp' ];
        return Response::xls($rows, $cols, 'api_request_logs.xls');
    }

    private function prefix(): string
    {
        return rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');
    }
}

