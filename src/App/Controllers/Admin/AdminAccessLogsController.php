<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\View\View;
use App\Support\Env;

final class AdminAccessLogsController
{
    public function list(Request $req): Response
    {
        $userId = isset($req->query['user_id']) ? (int)$req->query['user_id'] : null;
        $ip = trim((string)($req->query['ip'] ?? ''));
        $path = trim((string)($req->query['path'] ?? ''));
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $users = Database::run('SELECT id, name FROM admin_users ORDER BY name')->fetchAll();
        $html = View::render('admin/access_logs_list', [
            'prefix' => $this->prefix(),
            'users' => $users,
            'filter' => compact('userId','ip','path','from','to'),
        ]);
        return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
    }

    public function data(Request $req): Response
    {
        $draw = (int)($req->query['draw'] ?? 1);
        $start = max(0, (int)($req->query['start'] ?? 0));
        $length = min(200, max(10, (int)($req->query['length'] ?? 25)));
        $search = trim((string)($req->query['search']['value'] ?? ''));

        $userId = isset($req->query['user_id']) ? (int)$req->query['user_id'] : null;
        $ip = trim((string)($req->query['ip'] ?? ''));
        $path = trim((string)($req->query['path'] ?? ''));
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $hasRequestId = $this->hasColumn('admin_access_logs', 'request_id');

        $base = ' FROM admin_access_logs a LEFT JOIN admin_users u ON u.id=a.admin_user_id WHERE 1';
        $params = [];
        if ($userId) { $base .= ' AND a.admin_user_id = ?'; $params[] = $userId; }
        if ($ip !== '') { $base .= ' AND a.ip = ?'; $params[] = $ip; }
        if ($path !== '') { $base .= ' AND a.path LIKE ?'; $params[] = "%$path%"; }
        if ($from !== '') { $base .= ' AND a.created_at >= ?'; $params[] = $from; }
        if ($to !== '') { $base .= ' AND a.created_at <= ?'; $params[] = $to; }
        if ($search !== '') {
            $base .= ' AND (u.name LIKE ? OR a.path LIKE ? OR a.ip LIKE ?';
            $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
            if ($hasRequestId) { $base .= ' OR a.request_id LIKE ?'; $params[] = "%$search%"; }
            $base .= ')';
        }

        $total = (int) Database::run('SELECT COUNT(*) c FROM admin_access_logs')->fetch()['c'];
        $filtered = (int) Database::run('SELECT COUNT(*) c ' . $base, $params)->fetch()['c'];
        $orderColIdx = (int)($req->query['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string)($req->query['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $columns = ['a.id','u.name','a.method','a.path','a.ip', ($hasRequestId ? 'a.request_id' : 'a.id'), 'a.created_at'];
        $orderBy = $columns[$orderColIdx] ?? 'a.id';
        $selectRequestId = $hasRequestId ? 'a.request_id' : "''";
        $sql = 'SELECT a.id, u.name as user_name, a.method, a.path, a.ip, ' . $selectRequestId . ' AS request_id, a.created_at ' . $base . ' ORDER BY ' . $orderBy . ' ' . $orderDir . ' LIMIT ' . (int)$length . ' OFFSET ' . (int)$start;
        $rows = Database::run($sql, $params)->fetchAll();
        return Response::json(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$rows]);
    }

    public function exportCsv(Request $req): Response
    {
        $userId = isset($req->query['user_id']) ? (int)$req->query['user_id'] : null;
        $ip = trim((string)($req->query['ip'] ?? ''));
        $path = trim((string)($req->query['path'] ?? ''));
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $hasRequestId = $this->hasColumn('admin_access_logs', 'request_id');
        $selectRequestId = $hasRequestId ? 'a.request_id' : "''";
        $sql = 'SELECT a.id, a.admin_user_id, a.method, a.path, a.ip, a.user_agent, ' . $selectRequestId . ' AS request_id, a.created_at, u.name as user_name FROM admin_access_logs a LEFT JOIN admin_users u ON u.id=a.admin_user_id WHERE 1';
        $params = [];
        if ($userId) { $sql .= ' AND a.admin_user_id = ?'; $params[] = $userId; }
        if ($ip !== '') { $sql .= ' AND a.ip = ?'; $params[] = $ip; }
        if ($path !== '') { $sql .= ' AND a.path LIKE ?'; $params[] = "%$path%"; }
        if ($from !== '') { $sql .= ' AND a.created_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND a.created_at <= ?'; $params[] = $to; }
        $sql .= ' ORDER BY a.id DESC LIMIT 20000';
        $rows = Database::run($sql, $params)->fetchAll();
        $cols = ['id'=>'ID','user_name'=>'Usuário','method'=>'Método','path'=>'Rota','ip'=>'IP','request_id'=>'Request ID','created_at'=>'Quando'];
        return Response::csv($rows, $cols, 'admin_access_logs.csv');
    }

    private function prefix(): string
    {
        return rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');
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
