<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\View\View;
use App\Support\Env;

final class AdminCustomersController
{
    public function list(Request $req): Response
    {
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $q = trim((string)($req->query['q'] ?? ''));
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');

        $sql = 'SELECT c.*, s.name as site_name FROM customers c JOIN sites s ON s.id=c.site_id WHERE 1';
        $params = [];
        if ($siteId) { $sql .= ' AND c.site_id = ?'; $params[] = $siteId; }
        if ($q !== '') { $sql .= ' AND (c.name LIKE ? OR c.email LIKE ? OR c.document LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($from !== '') { $sql .= ' AND c.created_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND c.created_at <= ?'; $params[] = $to; }
        $page = max(1, (int)($req->query['page'] ?? 1));
        $perPage = min(100, max(10, (int)($req->query['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $sqlCount = 'SELECT COUNT(*) c FROM (' . $sql . ') t';
        $total = (int) Database::run($sqlCount, $params)->fetch()['c'];
        $sql .= ' ORDER BY c.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        $rows = Database::run($sql, $params)->fetchAll();
        $sites = Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll();

        $html = View::render('admin/customers_list', [
            'prefix' => $this->prefix(),
            'customers' => $rows,
            'sites' => $sites,
            'filter' => compact('siteId','q','from','to'),
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

        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $q = trim((string)($req->query['q'] ?? ''));
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');

        $base = ' FROM customers c JOIN sites s ON s.id=c.site_id WHERE 1';
        $params = [];
        if ($siteId) { $base .= ' AND c.site_id = ?'; $params[] = $siteId; }
        if ($q !== '') { $base .= ' AND (c.name LIKE ? OR c.email LIKE ? OR c.document LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($from !== '') { $base .= ' AND c.created_at >= ?'; $params[] = $from; }
        if ($to !== '') { $base .= ' AND c.created_at <= ?'; $params[] = $to; }
        if ($search !== '') { $base .= ' AND (s.name LIKE ? OR c.name LIKE ? OR c.email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

        $total = (int) Database::run('SELECT COUNT(*) c FROM customers')->fetch()['c'];
        $filtered = (int) Database::run('SELECT COUNT(*) c ' . $base, $params)->fetch()['c'];

        $orderColIdx = (int)($req->query['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string)($req->query['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $columns = ['c.id','s.name','c.name','c.email','c.document','c.created_at'];
        $orderBy = $columns[$orderColIdx] ?? 'c.id';

        $sql = 'SELECT c.id, s.name AS site_name, c.name, c.email, c.document, c.created_at ' . $base . ' ORDER BY ' . $orderBy . ' ' . $orderDir . ' LIMIT ' . (int)$length . ' OFFSET ' . (int)$start;
        $rows = Database::run($sql, $params)->fetchAll();
        return \App\Infrastructure\Http\Response::json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows,
        ]);
    }

    public function exportCsv(Request $req): Response
    {
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $q = trim((string)($req->query['q'] ?? ''));
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $sql = 'SELECT c.*, s.name as site_name FROM customers c JOIN sites s ON s.id=c.site_id WHERE 1';
        $params = [];
        if ($siteId) { $sql .= ' AND c.site_id = ?'; $params[] = $siteId; }
        if ($q !== '') { $sql .= ' AND (c.name LIKE ? OR c.email LIKE ? OR c.document LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($from !== '') { $sql .= ' AND c.created_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND c.created_at <= ?'; $params[] = $to; }
        $sql .= ' ORDER BY c.id DESC LIMIT 20000';
        $rows = Database::run($sql, $params)->fetchAll();
        $cols = [
            'id' => 'ID','site_name' => 'Site','name' => 'Nome','email' => 'Email','document' => 'Documento','created_at' => 'Criado em'
        ];
        return Response::csv($rows, $cols, 'customers.csv');
    }

    public function exportXls(Request $req): Response
    {
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $q = trim((string)($req->query['q'] ?? ''));
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $sql = 'SELECT c.*, s.name as site_name FROM customers c JOIN sites s ON s.id=c.site_id WHERE 1';
        $params = [];
        if ($siteId) { $sql .= ' AND c.site_id = ?'; $params[] = $siteId; }
        if ($q !== '') { $sql .= ' AND (c.name LIKE ? OR c.email LIKE ? OR c.document LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($from !== '') { $sql .= ' AND c.created_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND c.created_at <= ?'; $params[] = $to; }
        $sql .= ' ORDER BY c.id DESC LIMIT 20000';
        $rows = Database::run($sql, $params)->fetchAll();
        $cols = [ 'id'=>'ID','site_name'=>'Site','name'=>'Nome','email'=>'Email','document'=>'Documento','created_at'=>'Criado em' ];
        return Response::xls($rows, $cols, 'customers.xls');
    }

    private function prefix(): string
    {
        return rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');
    }
}
