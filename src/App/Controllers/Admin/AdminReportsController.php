<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\DB\Database;
use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\View\View;
use App\Support\Env;

final class AdminReportsController
{
    public function index(Request $req): Response
    {
        $scope = $this->scope((string)($req->query['scope'] ?? 'overview')); // payments | subscriptions | overview
        $method = $this->method((string)($req->query['method'] ?? '')); // credit_card | pix | ''
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $projDays = (int)($req->query['proj_days'] ?? 30);
        if ($projDays <= 0) $projDays = 30;

        $sites = Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll();

        $fromN = $this->normalizeDateStart($from) ?? date('Y-m-d', strtotime('-30 days'));
        $toN = $this->normalizeDateEnd($to) ?? date('Y-m-d 23:59:59');

        $data = [];
        if ($scope === 'payments') {
            $data = $this->paymentsData($siteId, $method, $fromN, $toN, $projDays);
        } elseif ($scope === 'boletos') {
            $data = $this->boletosData($siteId, $fromN, $toN);
        } elseif ($scope === 'subscriptions') {
            $data = $this->subscriptionsData($siteId, $fromN, $toN, $projDays);
        } else {
            $data = $this->overviewData($siteId, $fromN, $toN, $projDays);
        }

        $html = View::render('admin/reports', [
            'prefix' => $this->prefix(),
            'scope' => $scope,
            'method' => $method,
            'siteId' => $siteId,
            'from' => $from,
            'to' => $to,
            'sites' => $sites,
            'data' => $data,
            'projDays' => $projDays,
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function exportCsv(Request $req): Response
    {
        $scope = $this->scope((string)($req->query['scope'] ?? 'overview'));
        $method = $this->method((string)($req->query['method'] ?? ''));
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $fromN = $this->normalizeDateStart((string)($req->query['from'] ?? '')) ?? date('Y-m-d', strtotime('-30 days'));
        $toN = $this->normalizeDateEnd((string)($req->query['to'] ?? '')) ?? date('Y-m-d 23:59:59');
        $exportType = (string)($req->query['export'] ?? 'series'); // series | forecast

        if ($scope === 'overview') {
            return Response::json(['error' => 'Export not available for overview'], 422);
        }
        if ($scope === 'boletos') {
            $series = $this->paymentsDaily($siteId, 'boleto', $fromN, $toN);
            $rows = $series['rows'];
            $cols = [ 'd' => 'Dia', 'c' => 'Transações', 'v' => 'Receita (centavos)', 'appr' => 'Aprovação (%)' ];
            return Response::csv($rows, $cols, 'relatorio_boletos.csv');
        }
        if ($scope === 'payments') {
            if ($exportType === 'forecast') {
                // Forecast by site for selected method (default both rows)
                $rows = [];
                if ($method === 'pix' || $method === '') {
                    $f = $this->forecastPix($siteId, $fromN, $toN, (int)($req->query['proj_days'] ?? 30));
                    foreach ($f['by_site'] as $r) { $rows[] = ['site_name'=>$r['site_name'],'method'=>'pix','daily_avg_cents'=>$r['daily_avg_cents'],'forecast_cents'=>$r['forecast_cents']]; }
                }
                if ($method === 'credit_card' || $method === '') {
                    $f = $this->forecastCredit($siteId, $fromN, $toN, (int)($req->query['proj_days'] ?? 30));
                    foreach ($f['by_site'] as $r) { $rows[] = ['site_name'=>$r['site_name'],'method'=>'credit_card','daily_avg_cents'=>$r['daily_avg_cents'],'forecast_cents'=>$r['forecast_cents']]; }
                }
                $cols = ['site_name'=>'Site','method'=>'Método','daily_avg_cents'=>'Média Diária (centavos)','forecast_cents'=>'Previsto (centavos)'];
                return Response::csv($rows, $cols, 'forecast_pagamentos.csv');
            }
            $series = $this->paymentsDaily($siteId, $method, $fromN, $toN);
            $rows = $series['rows'];
            $cols = [ 'd' => 'Dia', 'c' => 'Transações', 'v' => 'Receita (centavos)', 'appr' => 'Aprovação (%)' ];
            return Response::csv($rows, $cols, 'relatorio_pagamentos.csv');
        }
        if ($exportType === 'forecast') {
            $f = $this->forecastSubscriptions($siteId, (int)($req->query['proj_days'] ?? 30));
            $rows = [];
            foreach ($f['by_site'] as $r) { $rows[] = ['site_name'=>$r['site_name'],'mrr_cents'=>$r['mrr_cents'],'daily_avg_cents'=>$r['daily_avg_cents'],'forecast_cents'=>$r['forecast_cents']]; }
            $cols = ['site_name'=>'Site','mrr_cents'=>'MRR (centavos)','daily_avg_cents'=>'Média Diária (centavos)','forecast_cents'=>'Previsto (centavos)'];
            return Response::csv($rows, $cols, 'forecast_assinaturas.csv');
        }
        $series = $this->subscriptionsDaily($siteId, $fromN, $toN);
        $rows = $series['rows'];
        $cols = [ 'd' => 'Dia', 'new_c' => 'Novas', 'active_c' => 'Ativas', 'past_due_c' => 'Atrasadas' ];
        return Response::csv($rows, $cols, 'relatorio_assinaturas.csv');
    }

    public function exportXls(Request $req): Response
    {
        $scope = $this->scope((string)($req->query['scope'] ?? 'overview'));
        $method = $this->method((string)($req->query['method'] ?? ''));
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $fromN = $this->normalizeDateStart((string)($req->query['from'] ?? '')) ?? date('Y-m-d', strtotime('-30 days'));
        $toN = $this->normalizeDateEnd((string)($req->query['to'] ?? '')) ?? date('Y-m-d 23:59:59');

        $exportType = (string)($req->query['export'] ?? 'series');
        if ($scope === 'payments') {
            if ($exportType === 'forecast') {
                $rows = [];
                if ($method === 'pix' || $method === '') {
                    $f = $this->forecastPix($siteId, $fromN, $toN, (int)($req->query['proj_days'] ?? 30));
                    foreach ($f['by_site'] as $r) { $rows[] = ['site_name'=>$r['site_name'],'method'=>'pix','daily_avg_cents'=>$r['daily_avg_cents'],'forecast_cents'=>$r['forecast_cents']]; }
                }
                if ($method === 'credit_card' || $method === '') {
                    $f = $this->forecastCredit($siteId, $fromN, $toN, (int)($req->query['proj_days'] ?? 30));
                    foreach ($f['by_site'] as $r) { $rows[] = ['site_name'=>$r['site_name'],'method'=>'credit_card','daily_avg_cents'=>$r['daily_avg_cents'],'forecast_cents'=>$r['forecast_cents']]; }
                }
                $cols = ['site_name'=>'Site','method'=>'Método','daily_avg_cents'=>'Média Diária (centavos)','forecast_cents'=>'Previsto (centavos)'];
                return Response::xls($rows, $cols, 'forecast_pagamentos.xls');
            }
            $series = $this->paymentsDaily($siteId, $method, $fromN, $toN);
            $rows = $series['rows'];
            $cols = [ 'd' => 'Dia', 'c' => 'Transações', 'v' => 'Receita (centavos)', 'appr' => 'Aprovação (%)' ];
            return Response::xls($rows, $cols, 'relatorio_pagamentos.xls');
        }
        if ($scope === 'boletos') {
            $series = $this->paymentsDaily($siteId, 'boleto', $fromN, $toN);
            $rows = $series['rows'];
            $cols = [ 'd' => 'Dia', 'c' => 'Transações', 'v' => 'Receita (centavos)', 'appr' => 'Aprovação (%)' ];
            return Response::xls($rows, $cols, 'relatorio_boletos.xls');
        }
        if ($exportType === 'forecast') {
            $f = $this->forecastSubscriptions($siteId, (int)($req->query['proj_days'] ?? 30));
            $rows = [];
            foreach ($f['by_site'] as $r) { $rows[] = ['site_name'=>$r['site_name'],'mrr_cents'=>$r['mrr_cents'],'daily_avg_cents'=>$r['daily_avg_cents'],'forecast_cents'=>$r['forecast_cents']]; }
            $cols = ['site_name'=>'Site','mrr_cents'=>'MRR (centavos)','daily_avg_cents'=>'Média Diária (centavos)','forecast_cents'=>'Previsto (centavos)'];
            return Response::xls($rows, $cols, 'forecast_assinaturas.xls');
        }
        $series = $this->subscriptionsDaily($siteId, $fromN, $toN);
        $rows = $series['rows'];
        $cols = [ 'd' => 'Dia', 'new_c' => 'Novas', 'active_c' => 'Ativas', 'past_due_c' => 'Atrasadas' ];
        return Response::xls($rows, $cols, 'relatorio_assinaturas.xls');
    }

    // Relatório por Conta Pagar.me
    public function accounts(Request $req): Response
    {
        \App\Infrastructure\Stone\StoneAccount::ensureSchema();
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $fromN = $this->normalizeDateStart($from) ?? date('Y-m-d', strtotime('-30 days'));
        $toN = $this->normalizeDateEnd($to) ?? date('Y-m-d 23:59:59');

        $rows = $this->accountsRows($fromN, $toN);

        $html = View::render('admin/reports_accounts', [
            'prefix' => $this->prefix(),
            'from' => $from, 'to' => $to,
            'rows' => $rows,
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function accountsExportCsv(Request $req): Response
    {
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $fromN = $this->normalizeDateStart($from) ?? date('Y-m-d', strtotime('-30 days'));
        $toN = $this->normalizeDateEnd($to) ?? date('Y-m-d 23:59:59');
        $rows = $this->accountsRows($fromN, $toN);
        // Map for CSV
        $out = [];
        foreach ($rows as $r) {
            $paid = (int)($r['paid_cents'] ?? 0);
            $out[] = [
                'account_id' => (int)$r['account_id'],
                'account_name' => (string)$r['account_name'],
                'environment' => (string)$r['active_env'],
                'account_uid' => (string)($r['stone_account_uid'] ?? ''),
                'paid_cents' => $paid,
                'paid_brl' => number_format($paid/100, 2, ',', '.'),
                'total_tx' => (int)($r['total_tx'] ?? 0),
            ];
        }
        $cols = [
            'account_id' => 'ID',
            'account_name' => 'Conta',
            'environment' => 'Ambiente',
            'account_uid' => 'Account UID',
            'paid_cents' => 'Receita (centavos)',
            'paid_brl' => 'Receita (R$)',
            'total_tx' => 'Transações',
        ];
        return Response::csv($out, $cols, 'relatorio_contas.csv');
    }

    public function accountsExportXls(Request $req): Response
    {
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $fromN = $this->normalizeDateStart($from) ?? date('Y-m-d', strtotime('-30 days'));
        $toN = $this->normalizeDateEnd($to) ?? date('Y-m-d 23:59:59');
        $rows = $this->accountsRows($fromN, $toN);
        $out = [];
        foreach ($rows as $r) {
            $paid = (int)($r['paid_cents'] ?? 0);
            $out[] = [
                'account_id' => (int)$r['account_id'],
                'account_name' => (string)$r['account_name'],
                'environment' => (string)$r['active_env'],
                'account_uid' => (string)($r['stone_account_uid'] ?? ''),
                'paid_brl' => 'R$ ' . number_format($paid/100, 2, ',', '.'),
                'total_tx' => (int)($r['total_tx'] ?? 0),
            ];
        }
        $cols = [
            'account_id' => 'ID',
            'account_name' => 'Conta',
            'environment' => 'Ambiente',
            'account_uid' => 'Account UID',
            'paid_brl' => 'Receita (R$)',
            'total_tx' => 'Transações',
        ];
        return Response::xls($out, $cols, 'relatorio_contas.xls');
    }

    private function accountsRows(string $fromN, string $toN): array
    {
        $env = $this->env();
        $sql = 'SELECT a.id as account_id, a.name as account_name, a.active_env, a.stone_account_uid,
                       COALESCE(SUM(CASE WHEN p.status = "paid" THEN p.amount_cents ELSE 0 END),0) as paid_cents,
                       COUNT(*) as total_tx
                FROM pagarme_accounts a
                LEFT JOIN sites s ON s.pagarme_account_id = a.id
                LEFT JOIN payments p ON p.site_id = s.id AND p.created_at >= ? AND p.created_at <= ? AND p.status <> "deleted" AND p.env = ?
                GROUP BY a.id, a.name, a.active_env, a.stone_account_uid
                ORDER BY paid_cents DESC';
        return Database::run($sql, [$fromN, $toN, $env])->fetchAll();
    }

    private function paymentsData(?int $siteId, string $method, string $fromN, string $toN, int $projDays): array
    {
        $env = $this->env();
        $series = $this->paymentsDaily($siteId, $method, $fromN, $toN);

        $params = [];
        $base = ' FROM payments p WHERE p.status <> "deleted" AND p.created_at >= ? AND p.created_at <= ? AND p.env = ?';
        $params[] = $fromN; $params[] = $toN; $params[] = $env;
        if ($siteId !== null && $siteId > 0) { $base .= ' AND p.site_id = ?'; $params[] = $siteId; }
        if ($method !== '') { $base .= ' AND p.payment_method_type = ?'; $params[] = $method; }

        $byMethod = Database::run('SELECT p.payment_method_type m, COUNT(*) c' . $base . ' GROUP BY p.payment_method_type', $params)->fetchAll();
        $byStatus = Database::run('SELECT p.status s, COUNT(*) c' . $base . ' GROUP BY p.status', $params)->fetchAll();

        $topSql = 'SELECT COALESCE(s.name, CONCAT("Site #", p.site_id)) n, COALESCE(SUM(p.amount_cents),0) v FROM payments p LEFT JOIN sites s ON s.id=p.site_id WHERE p.status="paid" AND p.updated_at >= ? AND p.updated_at <= ? AND p.env = ?';
        $topParams = [$fromN, $toN, $env];
        if ($siteId !== null && $siteId > 0) { $topSql .= ' AND p.site_id = ?'; $topParams[] = $siteId; }
        if ($method !== '') { $topSql .= ' AND p.payment_method_type = ?'; $topParams[] = $method; }
        $topSql .= ' GROUP BY p.site_id, s.name ORDER BY v DESC LIMIT 5';
        $topSites = Database::run($topSql, $topParams)->fetchAll();

        // Recent list (cap 100 to keep page light)
        $listSql = 'SELECT p.*, s.name as site_name FROM payments p JOIN sites s ON s.id=p.site_id WHERE p.status <> "deleted" AND p.created_at >= ? AND p.created_at <= ? AND p.env = ?';
        $listParams = [$fromN, $toN, $env];
        if ($siteId !== null && $siteId > 0) { $listSql .= ' AND p.site_id = ?'; $listParams[] = $siteId; }
        if ($method !== '') { $listSql .= ' AND p.payment_method_type = ?'; $listParams[] = $method; }
        $listSql .= ' ORDER BY p.id DESC LIMIT 100';
        $list = Database::run($listSql, $listParams)->fetchAll();

        // Baseline for forecasts uses selected period
        $baselineFrom = $fromN;
        $baselineTo = $toN;
        $forecastPix = $this->forecastPix($siteId, $baselineFrom, $baselineTo, $projDays);
        $forecastCredit = $this->forecastCredit($siteId, $baselineFrom, $baselineTo, $projDays);

        // Daily revenue by method (pix vs credit_card)
        $revByMethod = $this->paymentsRevenueByMethodDaily($siteId, $fromN, $toN);

        return [
            'series' => $series,
            'byMethod' => $byMethod,
            'byStatus' => $byStatus,
            'topSites' => $topSites,
            'list' => $list,
            'forecast' => [ 'pix' => $forecastPix, 'credit_card' => $forecastCredit ],
            'revByMethod' => $revByMethod,
        ];
    }

    private function boletosData(?int $siteId, string $fromN, string $toN): array
    {
        $env = $this->env();
        $series = $this->paymentsDaily($siteId, 'boleto', $fromN, $toN);
        $params = [$fromN, $toN, $env];
        $base = ' FROM payments p WHERE p.status <> "deleted" AND p.payment_method_type = "boleto" AND p.created_at >= ? AND p.created_at <= ? AND p.env = ?';
        if ($siteId !== null && $siteId > 0) { $base .= ' AND p.site_id = ?'; $params[] = $siteId; }
        $byStatus = Database::run('SELECT p.status s, COUNT(*) c' . $base . ' GROUP BY p.status', $params)->fetchAll();

        $listSql = 'SELECT p.*, s.name as site_name FROM payments p JOIN sites s ON s.id=p.site_id WHERE p.status <> "deleted" AND p.payment_method_type = "boleto" AND p.created_at >= ? AND p.created_at <= ? AND p.env = ?';
        $listParams = [$fromN, $toN, $env];
        if ($siteId !== null && $siteId > 0) { $listSql .= ' AND p.site_id = ?'; $listParams[] = $siteId; }
        $listSql .= ' ORDER BY p.id DESC LIMIT 100';
        $list = Database::run($listSql, $listParams)->fetchAll();

        return [
            'series' => $series,
            'byStatus' => $byStatus,
            'list' => $list,
        ];
    }

    private function subscriptionsData(?int $siteId, string $fromN, string $toN, int $projDays): array
    {
        $env = $this->env();
        $series = $this->subscriptionsDaily($siteId, $fromN, $toN);

        $params = [];
        $base = ' FROM subscriptions s WHERE s.status <> "deleted" AND s.env = ?';
        $params[] = $env;
        if ($siteId) { $base .= ' AND s.site_id = ?'; $params[] = $siteId; }

        $byStatus = Database::run('SELECT s.status st, COUNT(*) c' . $base . ' GROUP BY s.status', $params)->fetchAll();
        $active = (int) (Database::run('SELECT COUNT(*) c' . $base . ' AND s.status="active"', $params)->fetch()['c'] ?? 0);
        $pastDue = (int) (Database::run('SELECT COUNT(*) c' . $base . ' AND s.status="past_due"', $params)->fetch()['c'] ?? 0);

        // Cancellations per day (churn proxy) within selected period
        $cancelParams = [$fromN, $toN, $env];
        $cancelSql = 'SELECT DATE(s.updated_at) d, COUNT(*) c FROM subscriptions s WHERE s.status = "canceled" AND s.updated_at >= ? AND s.updated_at <= ? AND s.env = ?';
        if ($siteId !== null && $siteId > 0) { $cancelSql .= ' AND s.site_id = ?'; $cancelParams[] = $siteId; }
        $cancelSql .= ' GROUP BY d ORDER BY d';
        $subsCanceledByDay = Database::run($cancelSql, $cancelParams)->fetchAll();

        $listSql = 'SELECT s.*, si.name as site_name FROM subscriptions s JOIN sites si ON si.id=s.site_id WHERE s.status <> "deleted" AND s.env = ?';
        $listParams = [];
        $listParams[] = $env;
        if ($siteId !== null && $siteId > 0) { $listSql .= ' AND s.site_id = ?'; $listParams[] = $siteId; }
        // Limit by period using created_at
        $listSql .= ' AND s.created_at >= ? AND s.created_at <= ?';
        $listParams[] = $fromN; $listParams[] = $toN;
        $listSql .= ' ORDER BY s.id DESC LIMIT 100';
        $list = Database::run($listSql, $listParams)->fetchAll();

        $forecastSubs = $this->forecastSubscriptions($siteId, $projDays);

        return [
            'series' => $series,
            'byStatus' => $byStatus,
            'active' => $active,
            'pastDue' => $pastDue,
            'list' => $list,
            'subsCanceledByDay' => $subsCanceledByDay,
            'forecast' => [ 'subs' => $forecastSubs ],
        ];
    }

    private function forecastCredit(?int $siteId, string $baselineFrom, string $baselineTo, int $projDays): array
    {
        $env = $this->env();
        $baseDays = max(1, (int) floor((strtotime($baselineTo) - strtotime($baselineFrom)) / 86400) + 1);
        $sql = 'SELECT p.site_id, COALESCE(SUM(p.amount_cents),0) v, COUNT(DISTINCT DATE(p.updated_at)) d, s.name as site_name
                FROM payments p JOIN sites s ON s.id=p.site_id
                WHERE p.status = "paid" AND p.payment_method_type = "credit_card" AND p.updated_at >= ? AND p.updated_at <= ? AND p.env = ?';
        $params = [$baselineFrom, $baselineTo, $env];
        if ($siteId !== null && $siteId > 0) { $sql .= ' AND p.site_id = ?'; $params[] = $siteId; }
        $sql .= ' GROUP BY p.site_id, s.name';
        $rows = Database::run($sql, $params)->fetchAll();
        $bySite = [];
        $total = 0;
        foreach ($rows as $r) {
            $v = (int)($r['v'] ?? 0);
            $daily = (int) round($v / $baseDays);
            $f = (int) ($daily * $projDays);
            $bySite[] = [
                'site_id' => (int)$r['site_id'],
                'site_name' => (string)($r['site_name'] ?? ('Site #'.(int)$r['site_id'])),
                'daily_avg_cents' => $daily,
                'forecast_cents' => $f,
            ];
            $total += $f;
        }
        usort($bySite, function($a,$b){ return $b['forecast_cents'] <=> $a['forecast_cents']; });
        return [ 'total_cents' => $total, 'by_site' => $bySite, 'baseline_days' => $baseDays ];
    }

    private function paymentsRevenueByMethodDaily(?int $siteId, string $fromN, string $toN): array
    {
        $env = $this->env();
        $base = ' FROM payments p WHERE p.updated_at >= ? AND p.updated_at <= ? AND p.status = "paid" AND p.env = ?';
        $baseParams = [$fromN, $toN, $env];
        if ($siteId !== null && $siteId > 0) { $base .= ' AND p.site_id = ?'; $baseParams[] = $siteId; }

        $rowsPix = Database::run('SELECT DATE(p.updated_at) d, COALESCE(SUM(p.amount_cents),0) v ' . $base . ' AND p.payment_method_type = "pix" GROUP BY d ORDER BY d', $baseParams)->fetchAll();
        $rowsCred = Database::run('SELECT DATE(p.updated_at) d, COALESCE(SUM(p.amount_cents),0) v ' . $base . ' AND p.payment_method_type = "credit_card" GROUP BY d ORDER BY d', $baseParams)->fetchAll();

        $mapPix = []; foreach ($rowsPix as $r) { $mapPix[$r['d']] = (int)$r['v']; }
        $mapCred = []; foreach ($rowsCred as $r) { $mapCred[$r['d']] = (int)$r['v']; }
        $days = array_values(array_unique(array_merge(array_keys($mapPix), array_keys($mapCred)))); sort($days);
        $pix = []; $cred = [];
        foreach ($days as $d) { $pix[] = $mapPix[$d] ?? 0; $cred[] = $mapCred[$d] ?? 0; }
        return [ 'days' => $days, 'pix' => $pix, 'credit' => $cred ];
    }

    private function forecastPix(?int $siteId, string $baselineFrom, string $baselineTo, int $projDays): array
    {
        $env = $this->env();
        // Use baseline window length for daily average
        $baseDays = max(1, (int) floor((strtotime($baselineTo) - strtotime($baselineFrom)) / 86400) + 1);
        $sql = 'SELECT p.site_id, COALESCE(SUM(p.amount_cents),0) v, COUNT(DISTINCT DATE(p.updated_at)) d, s.name as site_name
                FROM payments p JOIN sites s ON s.id=p.site_id
                WHERE p.status = "paid" AND p.payment_method_type = "pix" AND p.updated_at >= ? AND p.updated_at <= ? AND p.env = ?';
        $params = [$baselineFrom, $baselineTo, $env];
        if ($siteId !== null && $siteId > 0) { $sql .= ' AND p.site_id = ?'; $params[] = $siteId; }
        $sql .= ' GROUP BY p.site_id, s.name';
        $rows = Database::run($sql, $params)->fetchAll();
        $bySite = [];
        $total = 0;
        foreach ($rows as $r) {
            $v = (int)($r['v'] ?? 0);
            $daily = (int) round($v / $baseDays);
            $f = (int) ($daily * $projDays);
            $bySite[] = [
                'site_id' => (int)$r['site_id'],
                'site_name' => (string)($r['site_name'] ?? ('Site #'.(int)$r['site_id'])),
                'daily_avg_cents' => $daily,
                'forecast_cents' => $f,
            ];
            $total += $f;
        }
        // Sort desc by forecast
        usort($bySite, function($a,$b){ return $b['forecast_cents'] <=> $a['forecast_cents']; });
        return [ 'total_cents' => $total, 'by_site' => $bySite, 'baseline_days' => $baseDays ];
    }

    private function forecastSubscriptions(?int $siteId, int $projDays): array
    {
        $env = $this->env();
        // Assume monthly billing; daily average = MRR/30
        $sql = 'SELECT su.site_id, COALESCE(SUM(su.amount_cents),0) v, si.name as site_name
                FROM subscriptions su JOIN sites si ON si.id=su.site_id
                WHERE su.status = "active" AND su.env = ?';
        $params = [$env];
        if ($siteId !== null && $siteId > 0) { $sql .= ' AND su.site_id = ?'; $params[] = $siteId; }
        $sql .= ' GROUP BY su.site_id, si.name';
        $rows = Database::run($sql, $params)->fetchAll();
        $bySite = [];
        $total = 0;
        foreach ($rows as $r) {
            $mrr = (int)($r['v'] ?? 0);
            $daily = (int) round($mrr / 30);
            $f = (int) ($daily * $projDays);
            $bySite[] = [
                'site_id' => (int)$r['site_id'],
                'site_name' => (string)($r['site_name'] ?? ('Site #'.(int)$r['site_id'])),
                'mrr_cents' => $mrr,
                'daily_avg_cents' => $daily,
                'forecast_cents' => $f,
            ];
            $total += $f;
        }
        usort($bySite, function($a,$b){ return $b['forecast_cents'] <=> $a['forecast_cents']; });
        return [ 'total_cents' => $total, 'by_site' => $bySite ];
    }

    private function paymentsDaily(?int $siteId, string $method, string $fromN, string $toN): array
    {
        $env = $this->env();
        // counts by day
        $baseWhere = ' WHERE p.created_at >= ? AND p.created_at <= ? AND p.status <> "deleted" AND p.env = ?';
        $params = [$fromN, $toN, $env];
        if ($siteId !== null && $siteId > 0) { $baseWhere .= ' AND p.site_id = ?'; $params[] = $siteId; }
        if ($method !== '') { $baseWhere .= ' AND p.payment_method_type = ?'; $params[] = $method; }

        $counts = Database::run('SELECT DATE(p.created_at) d, COUNT(*) c FROM payments p' . $baseWhere . ' GROUP BY d ORDER BY d', $params)->fetchAll();
        $revenue = Database::run('SELECT DATE(p.updated_at) d, COALESCE(SUM(p.amount_cents),0) v FROM payments p' . $baseWhere . ' AND p.status="paid" GROUP BY d ORDER BY d', $params)->fetchAll();
        $paid = Database::run('SELECT DATE(p.updated_at) d, COUNT(*) c FROM payments p' . $baseWhere . ' AND p.status="paid" GROUP BY d ORDER BY d', $params)->fetchAll();

        // Build aligned labels and approval rate
        $cntMap = []; foreach ($counts as $r) { $cntMap[$r['d']] = (int)$r['c']; }
        $revMap = []; foreach ($revenue as $r) { $revMap[$r['d']] = (int)$r['v']; }
        $paidMap = []; foreach ($paid as $r) { $paidMap[$r['d']] = (int)$r['c']; }
        $labels = array_values(array_unique(array_merge(array_keys($cntMap), array_keys($revMap)))); sort($labels);
        $rows = [];
        foreach ($labels as $d) {
            $c = $cntMap[$d] ?? 0; $v = $revMap[$d] ?? 0; $p = $paidMap[$d] ?? 0; $appr = $c ? round(($p/$c)*100, 1) : 0.0;
            $rows[] = ['d' => $d, 'c' => $c, 'v' => $v, 'appr' => $appr];
        }
        return [
            'days' => array_map(fn($r)=>$r['d'], $rows),
            'counts' => array_map(fn($r)=>$r['c'], $rows),
            'revenue' => array_map(fn($r)=>$r['v'], $rows),
            'approval' => array_map(fn($r)=>$r['appr'], $rows),
            'rows' => $rows,
        ];
    }

    private function subscriptionsDaily(?int $siteId, string $fromN, string $toN): array
    {
        $env = $this->env();
        $params = [$fromN, $toN, $env];
        $baseNew = ' FROM subscriptions s WHERE s.created_at >= ? AND s.created_at <= ? AND s.env = ?';
        if ($siteId !== null && $siteId > 0) { $baseNew .= ' AND s.site_id = ?'; $params[] = $siteId; }
        $new = Database::run('SELECT DATE(s.created_at) d, COUNT(*) c' . $baseNew . ' GROUP BY d ORDER BY d', $params)->fetchAll();

        // current totals by status (global or per site)
        $base = ' FROM subscriptions s WHERE s.env = ?'; $p2 = [$env];
        if ($siteId !== null && $siteId > 0) { $base .= ' AND s.site_id = ?'; $p2[] = $siteId; }
        $activeC = (int)(Database::run('SELECT COUNT(*) c' . $base . ' AND s.status="active"', $p2)->fetch()['c'] ?? 0);
        $pastDueC = (int)(Database::run('SELECT COUNT(*) c' . $base . ' AND s.status="past_due"', $p2)->fetch()['c'] ?? 0);

        return [
            'days' => array_map(fn($r)=>$r['d'], $new),
            'newCounts' => array_map(fn($r)=>(int)$r['c'], $new),
            'active' => $activeC,
            'pastDue' => $pastDueC,
            'rows' => array_map(fn($r)=>['d'=>$r['d'],'new_c'=>(int)$r['c'],'active_c'=>$activeC,'past_due_c'=>$pastDueC], $new),
        ];
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

    private function scope(string $v): string
    {
        $v = strtolower($v);
        return in_array($v, ['payments','subscriptions','overview','boletos'], true) ? $v : 'payments';
    }

    private function method(string $v): string
    {
        $v = strtolower(trim($v));
        return in_array($v, ['credit_card','pix','boleto'], true) ? $v : '';
    }

    private function env(): string
    {
        return 'production';
    }

    private function overviewData(?int $siteId, string $fromN, string $toN, int $projDays): array
    {
        $env = $this->env();
        $params = [$fromN, $toN, $env];
        $base = ' FROM payments p WHERE p.created_at >= ? AND p.created_at <= ? AND p.status <> "deleted" AND p.env = ?';
        $baseUpd = ' FROM payments p WHERE p.updated_at >= ? AND p.updated_at <= ? AND p.status <> "deleted" AND p.env = ?';
        if ($siteId !== null && $siteId > 0) { $base .= ' AND p.site_id = ?'; $baseUpd .= ' AND p.site_id = ?'; $params[] = $siteId; }
        $notRec = ' AND p.is_invoice = 0';
        $isRec = ' AND p.is_invoice = 1';

        $cntCard = Database::run('SELECT DATE(p.created_at) d, COUNT(*) c' . $base . ' AND p.payment_method_type = "credit_card"' . $notRec . ' GROUP BY d ORDER BY d', $params)->fetchAll();
        $revCard = Database::run('SELECT DATE(p.updated_at) d, COALESCE(SUM(p.amount_cents),0) v' . $baseUpd . ' AND p.payment_method_type = "credit_card"' . $notRec . ' AND p.status="paid" GROUP BY d ORDER BY d', $params)->fetchAll();
        $totCard = (int) (Database::run('SELECT COALESCE(SUM(p.amount_cents),0) v' . $baseUpd . ' AND p.payment_method_type = "credit_card"' . $notRec . ' AND p.status="paid"', $params)->fetch()['v'] ?? 0);

        $cntPix = Database::run('SELECT DATE(p.created_at) d, COUNT(*) c' . $base . ' AND p.payment_method_type = "pix"' . $notRec . ' GROUP BY d ORDER BY d', $params)->fetchAll();
        $revPix = Database::run('SELECT DATE(p.updated_at) d, COALESCE(SUM(p.amount_cents),0) v' . $baseUpd . ' AND p.payment_method_type = "pix"' . $notRec . ' AND p.status="paid" GROUP BY d ORDER BY d', $params)->fetchAll();
        $totPix = (int) (Database::run('SELECT COALESCE(SUM(p.amount_cents),0) v' . $baseUpd . ' AND p.payment_method_type = "pix"' . $notRec . ' AND p.status="paid"', $params)->fetch()['v'] ?? 0);

        $cntRec = Database::run('SELECT DATE(p.created_at) d, COUNT(*) c' . $base . $isRec . ' GROUP BY d ORDER BY d', $params)->fetchAll();
        $revRec = Database::run('SELECT DATE(p.updated_at) d, COALESCE(SUM(p.amount_cents),0) v' . $baseUpd . $isRec . ' AND p.status="paid" GROUP BY d ORDER BY d', $params)->fetchAll();
        $totRec = (int) (Database::run('SELECT COALESCE(SUM(p.amount_cents),0) v' . $baseUpd . $isRec . ' AND p.status="paid"', $params)->fetch()['v'] ?? 0);

        // Unified counts/revenue for "Receita, Transações e Aprovação"
        $cntAll = Database::run('SELECT DATE(p.created_at) d, COUNT(*) c' . $base . ' GROUP BY d ORDER BY d', $params)->fetchAll();
        $paidAll = Database::run('SELECT DATE(p.updated_at) d, COUNT(*) c' . $baseUpd . ' AND p.status="paid" GROUP BY d ORDER BY d', $params)->fetchAll();
        $revAll = Database::run('SELECT DATE(p.updated_at) d, COALESCE(SUM(p.amount_cents),0) v' . $baseUpd . ' AND p.status="paid" GROUP BY d ORDER BY d', $params)->fetchAll();

        $m = function($arr){ $o=[]; foreach ($arr as $r){ $o[$r['d']] = (int)($r['c'] ?? $r['v'] ?? 0); } return $o; };
        $cardC = $m($cntCard); $cardV = $m($revCard);
        $pixC = $m($cntPix); $pixV = $m($revPix);
        $recC = $m($cntRec); $recV = $m($revRec);
        $allCnt = $m($cntAll); $allPaid = $m($paidAll); $allRev = $m($revAll);
        $labels = array_values(array_unique(array_merge(array_keys($cardC), array_keys($cardV), array_keys($pixC), array_keys($pixV), array_keys($recC), array_keys($recV), array_keys($allCnt), array_keys($allPaid), array_keys($allRev)))); sort($labels);
        $series = [
            'days' => $labels,
            'card_counts' => array_map(fn($d)=>$cardC[$d]??0, $labels),
            'card_revenue' => array_map(fn($d)=>$cardV[$d]??0, $labels),
            'pix_counts' => array_map(fn($d)=>$pixC[$d]??0, $labels),
            'pix_revenue' => array_map(fn($d)=>$pixV[$d]??0, $labels),
            'rec_counts' => array_map(fn($d)=>$recC[$d]??0, $labels),
            'rec_revenue' => array_map(fn($d)=>$recV[$d]??0, $labels),
            'all_counts' => array_map(fn($d)=>$allCnt[$d]??0, $labels),
            'all_paid_counts' => array_map(fn($d)=>$allPaid[$d]??0, $labels),
            'all_revenue' => array_map(fn($d)=>$allRev[$d]??0, $labels),
        ];
        // Status unificado
        $byStatus = Database::run('SELECT p.status s, COUNT(*) c' . $base . ' GROUP BY p.status', $params)->fetchAll();
        // Forecasts
        $fPix = $this->forecastPix($siteId, $fromN, $toN, $projDays);
        $fCred = $this->forecastCredit($siteId, $fromN, $toN, $projDays);
        $fSubs = $this->forecastSubscriptions($siteId, $projDays);
        // Métodos de pagamento (unificado): pix, cartão e recorrente (assinaturas)
        $countCard = (int) (Database::run('SELECT COUNT(*) c' . $base . ' AND p.payment_method_type = "credit_card"' . $notRec, $params)->fetch()['c'] ?? 0);
        $countPix = (int) (Database::run('SELECT COUNT(*) c' . $base . ' AND p.payment_method_type = "pix"' . $notRec, $params)->fetch()['c'] ?? 0);
        $countRec = (int) (Database::run('SELECT COUNT(*) c' . $base . $isRec, $params)->fetch()['c'] ?? 0);

        return [
            'series' => $series,
            'totals' => [ 'card_cents' => $totCard, 'pix_cents' => $totPix, 'rec_cents' => $totRec ],
            'byStatus' => $byStatus,
            'forecast' => [ 'pix' => $fPix, 'credit_card' => $fCred, 'rec' => $fSubs ],
            'byMethod' => [
                [ 'm' => 'credit_card', 'c' => $countCard ],
                [ 'm' => 'pix', 'c' => $countPix ],
                [ 'm' => 'rec', 'c' => $countRec ],
            ],
        ];
    }

    private function prefix(): string
    {
        return rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');
    }
}
