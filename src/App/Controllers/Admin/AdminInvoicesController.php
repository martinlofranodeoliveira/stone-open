<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Admin\Concerns\PaymentAdminFiltersTrait;
use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\View\View;
use App\Support\Env;

final class AdminInvoicesController
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
        return $v;
    }

    private function normalizeDateEnd(?string $v): ?string
    {
        $start = $this->normalizeDateStart($v);
        if ($start === null) return null;
        if (preg_match('/\d{2}:\d{2}:\d{2}$/', (string)$v)) return (string)$v;
        return $start . ' 23:59:59';
    }

    private function invoiceWhere(): string
    {
        return ' AND p.is_invoice = 1';
    }

    private function invoiceBaseFromSql(): string
    {
        return ' FROM payments p JOIN sites s ON s.id=p.site_id LEFT JOIN customers c ON c.id=p.customer_id WHERE p.status <> "deleted"';
    }

    private function invoiceListSelectSql(): string
    {
        return 'SELECT p.*, s.name AS site_name, p.customer_name AS customer_name_local, c.name AS customer_name_join, c.email AS customer_email_join, c.document AS customer_document_join' . $this->invoiceBaseFromSql();
    }

    private function invoiceDataSelectSql(): string
    {
        return 'SELECT p.id, s.name AS site_name, p.env, p.client_reference_id, p.stone_payment_id, p.customer_name AS customer_name_local, c.name AS customer_name_join, c.email AS customer_email_join, c.document AS customer_document_join, p.request_payload, p.response_payload, p.amount_cents, p.payment_method_type, p.status, p.created_at';
    }

    public function list(Request $req): Response
    {
        $filter = $this->paymentAdminFiltersFromRequest($req);

        $sql = $this->invoiceListSelectSql();
        $params = [];
        $sql = $this->applyPaymentAdminFilters($sql, $params, $filter, true);
        $page = max(1, (int)($req->query['page'] ?? 1));
        $perPage = min(100, max(10, (int)($req->query['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $sqlCount = 'SELECT COUNT(*) c FROM (' . $sql . ') t';
        $total = (int) Database::run($sqlCount, $params)->fetch()['c'];
        $sql .= ' ORDER BY p.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        $rows = Database::run($sql, $params)->fetchAll();
        foreach ($rows as &$r) {
            $r['customer_name'] = $this->resolveCustomerName($r);
        }
        unset($r);

        $sites = Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll();
        $html = View::render('admin/invoices_list', [
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

        $base = $this->invoiceBaseFromSql();
        $params = [];
        $base = $this->applyPaymentAdminFilters($base, $params, $filter, true);

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
        $columns = [null,'p.id','s.name','p.env','p.client_reference_id','p.customer_name','p.amount_cents','p.payment_method_type','p.status','p.created_at'];
        $orderBy = $columns[$orderColIdx] ?? 'p.id';
        if (!$orderBy) $orderBy = 'p.id';

        $sql = $this->invoiceDataSelectSql() . $rowBase . ' ORDER BY ' . $orderBy . ' ' . $orderDir . ' LIMIT ' . (int)$length . ' OFFSET ' . (int)$start;
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
        $filter = $this->paymentAdminFiltersFromRequest($req);

        $sql = $this->invoiceListSelectSql();
        $params = [];
        $sql = $this->applyPaymentAdminFilters($sql, $params, $filter, true);
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
            'customer_name' => 'Aluno',
            'stone_payment_id' => 'Stone ID',
            'amount_cents' => 'Valor (centavos)',
            'currency' => 'Moeda',
            'payment_method_type' => 'Tipo',
            'status' => 'Status',
            'created_at' => 'Criado em',
        ];
        return Response::csv($rows, $cols, 'invoices.csv');
    }

    public function exportXls(Request $req): Response
    {
        return $this->exportCsv($req);
    }

    private function prefix(): string
    {
        return rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');
    }
}
