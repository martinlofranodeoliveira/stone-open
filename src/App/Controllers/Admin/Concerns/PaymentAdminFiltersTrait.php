<?php
declare(strict_types=1);

namespace App\Controllers\Admin\Concerns;

use App\Infrastructure\Http\Request;

trait PaymentAdminFiltersTrait
{
    private function paymentAdminFiltersFromRequest(Request $req): array
    {
        $env = strtolower(trim((string)($req->query['env'] ?? '')));
        if ($env !== '' && !in_array($env, ['sandbox', 'production'], true)) {
            $env = '';
        }

        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : 0;

        return [
            'site_id' => $siteId > 0 ? $siteId : '',
            'status' => trim((string)($req->query['status'] ?? '')),
            'type' => trim((string)($req->query['type'] ?? '')),
            'env' => $env,
            'q' => trim((string)($req->query['q'] ?? '')),
            'transaction_id' => trim((string)($req->query['transaction_id'] ?? '')),
            'name' => trim((string)($req->query['name'] ?? '')),
            'email' => trim((string)($req->query['email'] ?? '')),
            'document' => $this->normalizePaymentAdminDocument((string)($req->query['document'] ?? ($req->query['cpf'] ?? ''))),
            'from' => (string)($req->query['from'] ?? ''),
            'to' => (string)($req->query['to'] ?? ''),
        ];
    }

    private function applyPaymentAdminFilters(string $sql, array &$params, array $filter, bool $invoiceOnly): string
    {
        $sql .= $invoiceOnly ? ' AND p.is_invoice = 1' : ' AND p.is_invoice = 0';

        if ($filter['site_id'] !== '') {
            $sql .= ' AND p.site_id = ?';
            $params[] = (int)$filter['site_id'];
        }
        if ($filter['status'] !== '') {
            $sql .= ' AND p.status = ?';
            $params[] = $filter['status'];
        }
        if ($filter['type'] !== '') {
            $sql .= ' AND p.payment_method_type = ?';
            $params[] = $filter['type'];
        }
        if ($filter['env'] !== '') {
            $sql .= ' AND p.env = ?';
            $params[] = $filter['env'];
        }
        if ($filter['q'] !== '') {
            $sql = $this->appendPaymentLikeFilter($sql, $params, [
                'p.client_reference_id',
                'p.stone_payment_id',
            ], $filter['q']);
        }
        if ($filter['transaction_id'] !== '') {
            $sql = $this->appendPaymentLikeFilter($sql, $params, [
                'p.stone_payment_id',
                'p.provider_event_id',
                'p.request_payload',
                'p.response_payload',
                'p.metadata',
            ], $filter['transaction_id']);
        }
        if ($filter['name'] !== '') {
            $sql = $this->appendPaymentLikeFilter($sql, $params, [
                'p.customer_name',
                'c.name',
                'p.request_payload',
                'p.response_payload',
            ], $filter['name']);
        }
        if ($filter['email'] !== '') {
            $sql = $this->appendPaymentLikeFilter($sql, $params, [
                'c.email',
                'p.request_payload',
                'p.response_payload',
            ], $filter['email']);
        }
        if ($filter['document'] !== '') {
            $sql = $this->appendPaymentLikeFilter($sql, $params, [
                'c.document',
                'p.request_payload',
                'p.response_payload',
            ], $filter['document']);
        }

        $from = $this->normalizeDateStart((string)($filter['from'] ?? ''));
        $to = $this->normalizeDateEnd((string)($filter['to'] ?? ''));
        if ($from !== null) {
            $sql .= ' AND p.created_at >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $sql .= ' AND p.created_at <= ?';
            $params[] = $to;
        }

        return $sql;
    }

    private function applyPaymentAdminDataTableSearch(string $sql, array &$params, string $search): string
    {
        $search = trim($search);
        if ($search === '') {
            return $sql;
        }

        $like = '%' . $search . '%';
        $clauses = [
            's.name LIKE ?',
            'p.client_reference_id LIKE ?',
            'p.stone_payment_id LIKE ?',
            'p.provider_event_id LIKE ?',
            'p.customer_name LIKE ?',
            'c.name LIKE ?',
            'c.email LIKE ?',
            'c.document LIKE ?',
            'p.request_payload LIKE ?',
            'p.response_payload LIKE ?',
            'p.metadata LIKE ?',
        ];
        foreach ($clauses as $_) {
            $params[] = $like;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $search, $m)) {
            $clauses[] = 'DATE(p.created_at) = ?';
            $params[] = $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        $sql .= ' AND (' . implode(' OR ', $clauses) . ')';
        return $sql;
    }

    private function appendPaymentLikeFilter(string $sql, array &$params, array $columns, string $term): string
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

    private function normalizePaymentAdminDocument(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim((string)($value ?? '')));
        return is_string($digits) ? $digits : '';
    }
}
