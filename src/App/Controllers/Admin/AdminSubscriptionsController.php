<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\View\View;
use App\Infrastructure\Stone\StoneAccount;
use App\Support\Env;

final class AdminSubscriptionsController
{
    private function resolveEnv(array $site): string
    {
        $env = 'sandbox';
        try {
            $acc = StoneAccount::resolveForSite($site);
            $env = strtolower((string)($acc['active_env'] ?? 'sandbox'));
        } catch (\Throwable $e) {
            $env = strtolower((string)($site['active_env'] ?? 'sandbox'));
        }
        if ($env === 'homolog') $env = 'sandbox';
        return in_array($env, ['sandbox','production'], true) ? $env : 'sandbox';
    }
    public function list(Request $req): Response
    {
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $status = (string)($req->query['status'] ?? '');
        $planId = (string)($req->query['plan_id'] ?? '');
        $env = (string)($req->query['env'] ?? '');
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        if ($env !== '' && !in_array($env, ['sandbox','production'], true)) $env = '';
        $sql = "SELECT su.*, s.name as site_name, c.name as customer_name, c.email as customer_email,
                       p.name AS plan_name, p.amount_cents AS plan_amount_cents, p.currency AS plan_currency,
                       p.interval_unit AS plan_interval_unit, p.interval_count AS plan_interval_count,
                       p.billing_cycles AS plan_billing_cycles,
                       (SELECT COUNT(*) FROM payments pay
                          WHERE pay.status = \"paid\"
                            AND pay.site_id = su.site_id
                            AND pay.env = su.env
                            AND pay.metadata LIKE CONCAT('%\\\"subscription_stone_id\\\":\\\"', su.stone_subscription_id, '\\\"%')
                       ) AS occ_paid
                FROM subscriptions su
                JOIN sites s ON s.id=su.site_id
                LEFT JOIN customers c ON c.id=su.customer_id
                LEFT JOIN plans p ON p.site_id = su.site_id AND p.stone_plan_id = su.plan_id AND p.env = su.env
                WHERE su.status <> \"deleted\"";
        $params = [];
        if ($siteId) { $sql .= ' AND su.site_id = ?'; $params[] = $siteId; }
        if ($status !== '') { $sql .= ' AND su.status = ?'; $params[] = $status; }
        if ($planId !== '') { $sql .= ' AND su.plan_id LIKE ?'; $params[] = "%$planId%"; }
        if ($env !== '') { $sql .= ' AND su.env = ?'; $params[] = $env; }
        if ($from !== '') { $sql .= ' AND su.created_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND su.created_at <= ?'; $params[] = $to; }
        $page = max(1, (int)($req->query['page'] ?? 1));
        $perPage = min(100, max(10, (int)($req->query['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $sqlCount = 'SELECT COUNT(*) c FROM (' . $sql . ') t';
        $total = (int) Database::run($sqlCount, $params)->fetch()['c'];
        $sql .= ' ORDER BY su.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        $rows = Database::run($sql, $params)->fetchAll();
        foreach ($rows as &$r) {
            $paid = isset($r['occ_paid']) ? (int)$r['occ_paid'] : 0;
            $total = isset($r['plan_billing_cycles']) ? (int)$r['plan_billing_cycles'] : 0;
            $r['plan_progress'] = ($total > 0 ? ($paid . ' de ' . $total) : '');
        }
        unset($r);

        $sites = Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll();
        $html = View::render('admin/subscriptions_list', [
            'prefix' => $this->prefix(),
            'subscriptions' => $rows,
            'sites' => $sites,
            'filter' => compact('siteId','status','planId','env','from','to'),
            'page' => $page,'perPage' => $perPage,'total' => $total,
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function createForm(Request $req): Response
    {
        $sites = Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll();
        $html = \App\Infrastructure\View\View::render('admin/subscriptions_create', [
            'prefix' => $this->prefix(),
            'sites' => $sites,
            'csrf' => \App\Support\Security\Csrf::token(),
        ]);
        return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
    }

    public function store(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return new Response(400, ['Content-Type'=>'text/plain'],'Invalid CSRF');
        $siteId = (int)($post['site_id'] ?? 0);
        $customerId = trim((string)($post['stone_customer_id'] ?? ''));
        $planId = trim((string)($post['stone_plan_id'] ?? ''));
        // Valor da assinatura deve vir do plano (tabela plans)
        $amount = null;
        $currency = (string)($post['currency'] ?? 'BRL');
        $payload = [];
        if ($planId !== '') $payload['plan_id'] = $planId;
        if ($customerId !== '') $payload['customer_id'] = $customerId;
        // Não enviar amount custom; usar preço do plano na Stone
        if ($currency) $payload['currency'] = $currency;
        try {
            $site = Database::run('SELECT * FROM sites WHERE id=? LIMIT 1', [$siteId])->fetch() ?: [];
            $env = $site ? $this->resolveEnv($site) : 'sandbox';
            $client = $site ? \App\Infrastructure\Stone\StoneClient::forSite($site) : null;
            if (!$client) throw new \RuntimeException('Site não encontrado/sem conta vinculada');
            $res = $client->createSubscription($payload);
            // persist local
            $startDate = null; if (!empty($res['start_at'])) { $ts = strtotime((string)$res['start_at']); if ($ts) $startDate = date('Y-m-d', $ts); }
            // Obter valor do plano
            $planRow = $planId !== '' ? Database::run('SELECT amount_cents FROM plans WHERE site_id=? AND stone_plan_id=? AND env = ? LIMIT 1', [$siteId, $planId, $env])->fetch() : null;
            $amountFromPlan = isset($planRow['amount_cents']) ? (int)$planRow['amount_cents'] : null;
            Database::run('INSERT INTO subscriptions (site_id, stone_subscription_id, env, amount_cents, currency, status, start_date, metadata, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', [
                $siteId,
                $res['id'] ?? null,
                $env,
                $amountFromPlan,
                $currency,
                strtolower((string)($res['status'] ?? 'active')),
                $startDate,
                json_encode($res, JSON_UNESCAPED_UNICODE),
            ]);
            $newId = (int)Database::pdo()->lastInsertId();
            if ($newId > 0) {
                $evt = strtolower((string)($res['status'] ?? 'active')) === 'active' ? 'subscription.activated' : 'subscription.created';
                try { (new \App\Application\WebhookService())->emitSubscriptionEvent($newId, $evt); } catch (\Throwable $e) {}
            }
            return new Response(302, ['Location' => $this->prefix() . '/subscriptions'], '');
        } catch (\Throwable $e) {
            return new Response(500, ['Content-Type'=>'text/plain'], 'Create failed: ' . $e->getMessage());
        }
    }

    public function cancel(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return new Response(400, ['Content-Type'=>'text/plain'],'Invalid CSRF');
        $id = (int)($post['id'] ?? 0);
        $sub = Database::run('SELECT * FROM subscriptions WHERE id = ?', [$id])->fetch();
        if (!$sub) return new Response(404, ['Content-Type'=>'text/plain'], 'Not found');
        $sid = (string)$sub['stone_subscription_id'];
        try {
            $site = Database::run('SELECT * FROM sites WHERE id=? LIMIT 1', [$sub['site_id']])->fetch() ?: [];
            $client = $site ? \App\Infrastructure\Stone\StoneClient::forSite($site) : null;
            if (!$client) throw new \RuntimeException('Site não encontrado/sem conta vinculada');
            $client->cancelSubscription($sid);
            Database::run('UPDATE subscriptions SET status = ?, updated_at = NOW() WHERE id = ?', ['canceled', $id]);
            try { (new \App\Application\WebhookService())->emitSubscriptionEvent($id, 'subscription.canceled'); } catch (\Throwable $e) {}
            if (($req->query['ajax'] ?? '') === '1' || strpos($req->header('Accept',''), 'application/json') !== false) {
                return Response::json(['ok'=>1,'status'=>'canceled']);
            }
            return new Response(302, ['Location' => $this->prefix() . '/subscriptions'], '');
        } catch (\Throwable $e) {
            if (($req->query['ajax'] ?? '') === '1' || strpos($req->header('Accept',''), 'application/json') !== false) {
                return Response::json(['error'=>'Cancel failed','message'=>$e->getMessage()], 500);
            }
            return new Response(500, ['Content-Type'=>'text/plain'], 'Cancel failed: ' . $e->getMessage());
        }
    }

    public function pause(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return new Response(400, ['Content-Type'=>'text/plain'],'Invalid CSRF');
        $id = (int)($post['id'] ?? 0);
        $sub = Database::run('SELECT * FROM subscriptions WHERE id = ?', [$id])->fetch();
        if (!$sub) return new Response(404, ['Content-Type'=>'text/plain'], 'Not found');
        try {
            $site = Database::run('SELECT * FROM sites WHERE id=? LIMIT 1', [$sub['site_id']])->fetch() ?: [];
            $client = $site ? \App\Infrastructure\Stone\StoneClient::forSite($site) : null;
            if (!$client) throw new \RuntimeException('Site não encontrado/sem conta vinculada');
            $client->enableManualBilling((string)$sub['stone_subscription_id']);
            // Persist manual_billing=true in metadata
            $meta = $sub['metadata'] ? json_decode((string)$sub['metadata'], true) : [];
            if (!is_array($meta)) $meta = [];
            $meta['manual_billing'] = true;
            Database::run('UPDATE subscriptions SET metadata = ?, updated_at = NOW() WHERE id = ?', [ json_encode($meta, JSON_UNESCAPED_UNICODE), $id ]);
            try { (new \App\Application\WebhookService())->emitSubscriptionEvent($id, 'subscription.paused'); } catch (\Throwable $e) {}
            if (($req->query['ajax'] ?? '') === '1' || strpos($req->header('Accept',''), 'application/json') !== false) {
                return Response::json(['ok'=>1,'manual_billing'=>true]);
            }
            return new Response(302, ['Location' => $this->prefix() . '/subscriptions'], '');
        } catch (\Throwable $e) {
            if (($req->query['ajax'] ?? '') === '1' || strpos($req->header('Accept',''), 'application/json') !== false) {
                return Response::json(['error'=>'Pause failed','message'=>$e->getMessage()], 500);
            }
            return new Response(500, ['Content-Type'=>'text/plain'], 'Pause failed: ' . $e->getMessage());
        }
    }

    public function resume(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return new Response(400, ['Content-Type'=>'text/plain'],'Invalid CSRF');
        $id = (int)($post['id'] ?? 0);
        $sub = Database::run('SELECT * FROM subscriptions WHERE id = ?', [$id])->fetch();
        if (!$sub) return new Response(404, ['Content-Type'=>'text/plain'], 'Not found');
        try {
            $site = Database::run('SELECT * FROM sites WHERE id=? LIMIT 1', [$sub['site_id']])->fetch() ?: [];
            $client = $site ? \App\Infrastructure\Stone\StoneClient::forSite($site) : null;
            if (!$client) throw new \RuntimeException('Site não encontrado/sem conta vinculada');
            $client->disableManualBilling((string)$sub['stone_subscription_id']);
            // Persist manual_billing=false in metadata
            $meta = $sub['metadata'] ? json_decode((string)$sub['metadata'], true) : [];
            if (!is_array($meta)) $meta = [];
            $meta['manual_billing'] = false;
            Database::run('UPDATE subscriptions SET metadata = ?, updated_at = NOW() WHERE id = ?', [ json_encode($meta, JSON_UNESCAPED_UNICODE), $id ]);
            try { (new \App\Application\WebhookService())->emitSubscriptionEvent($id, 'subscription.resumed'); } catch (\Throwable $e) {}
            if (($req->query['ajax'] ?? '') === '1' || strpos($req->header('Accept',''), 'application/json') !== false) {
                return Response::json(['ok'=>1,'manual_billing'=>false]);
            }
            return new Response(302, ['Location' => $this->prefix() . '/subscriptions'], '');
        } catch (\Throwable $e) {
            if (($req->query['ajax'] ?? '') === '1' || strpos($req->header('Accept',''), 'application/json') !== false) {
                return Response::json(['error'=>'Resume failed','message'=>$e->getMessage()], 500);
            }
            return new Response(500, ['Content-Type'=>'text/plain'], 'Resume failed: ' . $e->getMessage());
        }
    }

    public function emitEvent(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return Response::json(['error'=>'Invalid CSRF'], 400);
        $id = (int)($req->attributes['routeParams']['id'] ?? ($post['id'] ?? 0));
        if ($id <= 0) return Response::json(['error'=>'Invalid id'], 422);
        $sub = Database::run('SELECT * FROM subscriptions WHERE id = ? LIMIT 1', [$id])->fetch();
        if (!$sub) return Response::json(['error'=>'Not found'], 404);
        $event = trim((string)($post['event_type'] ?? ($post['event'] ?? '')));
        if ($event === '') {
            $map = [
                'active' => 'subscription.activated',
                'trialing' => 'subscription.created',
                'past_due' => 'subscription.past_due',
                'paused' => 'subscription.paused',
                'canceled' => 'subscription.canceled',
                'expired' => 'subscription.expired',
            ];
            $event = $map[(string)($sub['status'] ?? '')] ?? 'subscription.created';
        }
        $norm = strtolower($event);
        $allowed = [
            'subscription.created',
            'subscription.activated',
            'subscription.canceled',
            'subscription.paused',
            'subscription.resumed',
            'subscription.past_due',
            'subscription.expired',
        ];
        if ($norm === 'subscription.overdue') $norm = 'subscription.past_due';
        if (!in_array($norm, $allowed, true)) {
            return Response::json(['error'=>'Invalid event_type'], 422);
        }
        try {
            $res = (new \App\Application\WebhookService())->emitSubscriptionEvent($id, $norm);
            return Response::json(['ok'=>1,'event_type'=>$norm,'result'=>$res]);
        } catch (\Throwable $e) {
            return Response::json(['error'=>'Emit failed','message'=>$e->getMessage()], 500);
        }
    }

    public function events(Request $req): Response
    {
        $id = (int)($req->attributes['routeParams']['id'] ?? 0);
        $sub = Database::run('SELECT * FROM subscriptions WHERE id = ?', [$id])->fetch();
        if (!$sub) return new Response(404, ['Content-Type' => 'text/plain'], 'Not found');
        $rows = Database::run('SELECT * FROM subscription_events WHERE subscription_id = ? ORDER BY COALESCE(occurred_at, created_at) ASC, id ASC LIMIT 500', [$id])->fetchAll();
        $html = View::render('admin/subscription_events_list', [
            'prefix' => $this->prefix(),
            'subscription' => $sub,
            'events' => $rows,
            'csrf' => \App\Support\Security\Csrf::token(),
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function exportCsv(Request $req): Response
    {
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $status = (string)($req->query['status'] ?? '');
        $planId = (string)($req->query['plan_id'] ?? '');
        $env = (string)($req->query['env'] ?? '');
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        if ($env !== '' && !in_array($env, ['sandbox','production'], true)) $env = '';
        $sql = 'SELECT su.*, s.name as site_name, c.name as customer_name, c.email as customer_email FROM subscriptions su JOIN sites s ON s.id=su.site_id LEFT JOIN customers c ON c.id=su.customer_id WHERE su.status <> "deleted"';
        $params = [];
        if ($siteId) { $sql .= ' AND su.site_id = ?'; $params[] = $siteId; }
        if ($status !== '') { $sql .= ' AND su.status = ?'; $params[] = $status; }
        if ($planId !== '') { $sql .= ' AND su.plan_id LIKE ?'; $params[] = "%$planId%"; }
        if ($env !== '') { $sql .= ' AND su.env = ?'; $params[] = $env; }
        if ($from !== '') { $sql .= ' AND su.created_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND su.created_at <= ?'; $params[] = $to; }
        $sql .= ' ORDER BY su.id DESC LIMIT 20000';
        $rows = Database::run($sql, $params)->fetchAll();
        // Formatar coluna Valor com ocorrências: "N x R$ V" ou apenas "R$ V"
        foreach ($rows as &$r) {
            $occ = null;
            if (!empty($r['metadata'])) {
                $m = json_decode((string)$r['metadata'], true);
                if (is_array($m) && isset($m['items'][0]['cycles'])) $occ = (int)$m['items'][0]['cycles'];
            }
            $val = (int)($r['amount_cents'] ?? 0);
            $brl = 'R$ ' . number_format($val/100, 2, ',', '.');
            $r['valor'] = $occ ? ($occ . ' x ' . $brl) : $brl;
        }
        unset($r);
        $cols = [
            'id'=>'ID','site_name'=>'Site','env'=>'Ambiente','stone_subscription_id'=>'Stone Sub ID','plan_id'=>'Plano','valor'=>'Valor','status'=>'Status','start_date'=>'Início','end_date'=>'Fim','created_at'=>'Criado em'
        ];
        return \App\Infrastructure\Http\Response::csv($rows, $cols, 'subscriptions.csv');
    }

    public function exportXls(Request $req): Response
    {
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $status = (string)($req->query['status'] ?? '');
        $planId = (string)($req->query['plan_id'] ?? '');
        $env = (string)($req->query['env'] ?? '');
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        if ($env !== '' && !in_array($env, ['sandbox','production'], true)) $env = '';
        $sql = 'SELECT su.*, s.name as site_name, c.name as customer_name, c.email as customer_email FROM subscriptions su JOIN sites s ON s.id=su.site_id LEFT JOIN customers c ON c.id=su.customer_id WHERE su.status <> "deleted"';
        $params = [];
        if ($siteId) { $sql .= ' AND su.site_id = ?'; $params[] = $siteId; }
        if ($status !== '') { $sql .= ' AND su.status = ?'; $params[] = $status; }
        if ($planId !== '') { $sql .= ' AND su.plan_id LIKE ?'; $params[] = "%$planId%"; }
        if ($env !== '') { $sql .= ' AND su.env = ?'; $params[] = $env; }
        if ($from !== '') { $sql .= ' AND su.created_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND su.created_at <= ?'; $params[] = $to; }
        $sql .= ' ORDER BY su.id DESC LIMIT 20000';
        $rows = Database::run($sql, $params)->fetchAll();
        foreach ($rows as &$r) {
            $occ = null;
            if (!empty($r['metadata'])) {
                $m = json_decode((string)$r['metadata'], true);
                if (is_array($m) && isset($m['items'][0]['cycles'])) $occ = (int)$m['items'][0]['cycles'];
            }
            $val = (int)($r['amount_cents'] ?? 0);
            $brl = 'R$ ' . number_format($val/100, 2, ',', '.');
            $r['valor'] = $occ ? ($occ . ' x ' . $brl) : $brl;
        }
        unset($r);
        $cols = [ 'id'=>'ID','site_name'=>'Site','env'=>'Ambiente','customer_name'=>'Cliente','customer_email'=>'Email','plan_id'=>'Plano','valor'=>'Valor','status'=>'Status','start_date'=>'Início','end_date'=>'Fim','created_at'=>'Criado em' ];
        return \App\Infrastructure\Http\Response::xls($rows, $cols, 'subscriptions.xls');
    }

    public function data(Request $req): Response
    {
        $draw = (int)($req->query['draw'] ?? 1);
        $start = max(0, (int)($req->query['start'] ?? 0));
        $length = min(200, max(10, (int)($req->query['length'] ?? 25)));
        $search = trim((string)($req->query['search']['value'] ?? ''));

        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $status = (string)($req->query['status'] ?? '');
        $planId = (string)($req->query['plan_id'] ?? '');
        $env = (string)($req->query['env'] ?? '');
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        if ($env !== '' && !in_array($env, ['sandbox','production'], true)) $env = '';

        $base = ' FROM subscriptions su JOIN sites s ON s.id=su.site_id LEFT JOIN customers c ON c.id=su.customer_id LEFT JOIN plans p ON p.site_id = su.site_id AND p.stone_plan_id = su.plan_id AND p.env = su.env WHERE su.status <> "deleted"';
        $params = [];
        if ($siteId) { $base .= ' AND su.site_id = ?'; $params[] = $siteId; }
        if ($status !== '') { $base .= ' AND su.status = ?'; $params[] = $status; }
        if ($planId !== '') { $base .= ' AND su.plan_id LIKE ?'; $params[] = "%$planId%"; }
        if ($env !== '') { $base .= ' AND su.env = ?'; $params[] = $env; }
        if ($from !== '') { $base .= ' AND su.created_at >= ?'; $params[] = $from; }
        if ($to !== '') { $base .= ' AND su.created_at <= ?'; $params[] = $to; }
        if ($search !== '') { $base .= ' AND (s.name LIKE ? OR c.name LIKE ? OR su.plan_id LIKE ? OR p.name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

        $totalSql = 'SELECT COUNT(*) c FROM subscriptions su WHERE su.status <> "deleted"';
        $totalParams = [];
        if ($env !== '') { $totalSql .= ' AND su.env = ?'; $totalParams[] = $env; }
        $total = (int) Database::run($totalSql, $totalParams)->fetch()['c'];
        $filtered = (int) Database::run('SELECT COUNT(*) c ' . $base, $params)->fetch()['c'];

        $orderColIdx = (int)($req->query['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string)($req->query['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $columns = [
            'su.id', // 0 ID
            's.name', // 1 Site
            'su.env', // 2 Ambiente
            'c.name', // 3 Cliente
            'p.name', // 4 Plano
            null, // 5 Parcelas (derivado)
            'su.amount_cents', // 6 Valor
            'su.status', // 7 Status
            'su.start_date', // 8 Início
            'su.end_date', // 9 Fim
            null, // 10 Ações
        ];
        $orderBy = $columns[$orderColIdx] ?? 'su.id';
        if (!$orderBy) $orderBy = 'su.id';

        $sql = "SELECT su.id, s.name AS site_name, su.env, c.name AS customer_name, c.email AS customer_email,
                       COALESCE(p.name, su.plan_id) AS plan_label,
                       su.plan_id, su.amount_cents, su.currency, su.status, su.start_date, su.end_date, su.metadata,
                       p.billing_cycles AS plan_billing_cycles,
                       (SELECT COUNT(*) FROM payments pay WHERE pay.status = \"paid\" AND pay.site_id = su.site_id AND pay.env = su.env AND pay.metadata LIKE CONCAT('%\\\"subscription_stone_id\\\":\\\"', su.stone_subscription_id, '\\\"%')) AS occ_paid
                " . $base . ' ORDER BY ' . $orderBy . ' ' . $orderDir . ' LIMIT ' . (int)$length . ' OFFSET ' . (int)$start;
        $rows = Database::run($sql, $params)->fetchAll();
        // Enrich with occurrences (parcelas totais) and manual_billing if present in metadata
        foreach ($rows as &$r) {
            $occ = null;
            $manual = null;
            if (!empty($r['metadata'])) {
                $m = json_decode((string)$r['metadata'], true);
                if (is_array($m)) {
                    if (isset($m['manual_billing'])) $manual = (bool)$m['manual_billing'];
                    // Try common paths
                    if (isset($m['items'][0]['cycles'])) {
                        $occ = (int)$m['items'][0]['cycles'];
                    } elseif (isset($m['plan']['items'][0]['cycles'])) {
                        $occ = (int)$m['plan']['items'][0]['cycles'];
                    } else {
                        // Deep search for a numeric 'cycles'
                        $stack = [$m];
                        while ($stack) {
                            $cur = array_pop($stack);
                            if (!is_array($cur)) continue;
                            foreach ($cur as $k => $v) {
                                if ($k === 'cycles' && is_numeric($v)) { $occ = (int)$v; break 2; }
                                if (is_array($v)) $stack[] = $v;
                            }
                        }
                    }
                    if ($occ && $occ < 0) $occ = null;
                }
            }
            $r['occurrences'] = $occ;
            $r['manual_billing'] = $manual;
        }
        unset($r);
        // Compute progress label
        foreach ($rows as &$r) {
            $paid = isset($r['occ_paid']) ? (int)$r['occ_paid'] : 0;
            $total = isset($r['plan_billing_cycles']) ? (int)$r['plan_billing_cycles'] : 0;
            $r['plan_progress'] = ($total > 0 ? ($paid . ' de ' . $total) : '');
        }
        unset($r);
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

    // GET /_admin/subscriptions/json?id=123
    public function json(Request $req): Response
    {
        $id = (int)($req->query['id'] ?? 0);
        if ($id <= 0) return Response::json(['error'=>'Invalid id'], 422);
        $row = Database::run('SELECT su.*, s.name AS site_name, c.name AS customer_name, c.email AS customer_email FROM subscriptions su JOIN sites s ON s.id=su.site_id LEFT JOIN customers c ON c.id=su.customer_id WHERE su.id = ? AND su.status <> "deleted" LIMIT 1', [$id])->fetch();
        if (!$row) return Response::json(['error'=>'Not found'], 404);
        // Try to include card_installments if column exists
        try {
            $events = Database::run('SELECT id, event_type, occurrence, card_installments, occurred_at, created_at, provider_event_id FROM subscription_events WHERE subscription_id = ? ORDER BY COALESCE(occurred_at, created_at) ASC, id ASC LIMIT 500', [$id])->fetchAll();
        } catch (\Throwable $e) {
            try {
                $events = Database::run('SELECT id, event_type, occurrence, occurred_at, created_at, provider_event_id FROM subscription_events WHERE subscription_id = ? ORDER BY COALESCE(occurred_at, created_at) ASC, id ASC LIMIT 500', [$id])->fetchAll();
                foreach ($events as &$ev) { if (!array_key_exists('card_installments', $ev)) $ev['card_installments'] = null; }
                unset($ev);
            } catch (\Throwable $e2) {
                $events = Database::run('SELECT id, event_type, occurred_at, created_at, provider_event_id FROM subscription_events WHERE subscription_id = ? ORDER BY COALESCE(occurred_at, created_at) ASC, id ASC LIMIT 500', [$id])->fetchAll();
                foreach ($events as &$ev) { $ev['occurrence'] = null; $ev['card_installments'] = null; }
                unset($ev);
            }
        }
        $statusMap = [ 'active'=>'Ativa','trialing'=>'Em teste','past_due'=>'Em atraso','paused'=>'Pausada','canceled'=>'Cancelada','expired'=>'Expirada','deleted'=>'Deletada' ];
        $br = function(?string $d){ if(!$d) return null; if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { [$y,$m,$da] = explode('-', $d); return $da.'/'.$m.'/'.$y; } $ts=strtotime($d); return $ts? date('d/m/Y H:i', $ts): $d; };
        $meta = $row['metadata'] ? json_decode((string)$row['metadata'], true) : null;
        // Try to detect occurrences (cycles) from metadata.items[0].cycles
        $occ = null; $each = (int)($row['amount_cents'] ?? 0);
        if (is_array($meta)) {
            $items = $meta['items'] ?? null;
            if (is_array($items) && isset($items[0]) && is_array($items[0]) && isset($items[0]['cycles'])) {
                $occ = (int)$items[0]['cycles'];
            }
        }
        return Response::json([
            'subscription' => [
                'id' => (int)$row['id'],
                'stone_subscription_id' => $row['stone_subscription_id'],
                'site' => $row['site_name'],
                'customer' => $row['customer_name'],
                'customer_email' => $row['customer_email'],
                'plan_id' => $row['plan_id'],
                'status' => $row['status'],
                'status_label' => $statusMap[$row['status']] ?? $row['status'],
                'amount_cents' => (int)$row['amount_cents'],
                'currency' => $row['currency'] ?? 'BRL',
                'occurrences' => $occ,
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'start_date_br' => $br($row['start_date'] ?? null),
                'end_date_br' => $br($row['end_date'] ?? null),
            ],
            'events' => array_map(function($e) use ($br){
                $when = $e['occurred_at'] ?? null;
                if (!$when && !empty($e['created_at'])) $when = $e['created_at'];
                return [
                    'id' => (int)$e['id'],
                    'event_type' => $e['event_type'],
                    'provider_event_id' => $e['provider_event_id'] ?? null,
                    'occurrence' => isset($e['occurrence']) ? (int)$e['occurrence'] : null,
                    'card_installments' => isset($e['card_installments']) ? (int)$e['card_installments'] : null,
                    'occurred_at' => $when,
                    'occurred_at_br' => $br($when),
                ];
            }, $events),
        ]);
    }

    // GET /_admin/subscriptions/{id}/plan — retorna dados do plano e invoices pagos
    public function planInfo(Request $req): Response
    {
        $id = (int)($req->attributes['routeParams']['id'] ?? 0);
        $sub = Database::run('SELECT * FROM subscriptions WHERE id = ? LIMIT 1', [$id])->fetch();
        if (!$sub) return Response::json(['error'=>'Not found'], 404);
        $plan = Database::run('SELECT * FROM plans WHERE site_id = ? AND stone_plan_id = ? AND env = ? LIMIT 1', [$sub['site_id'], $sub['plan_id'], $sub['env'] ?? 'sandbox'])->fetch();
        // Contabiliza parcelas pagas a partir dos pagamentos marcados como 'paid'
        $like = '%"subscription_stone_id":"' . addcslashes((string)$sub['stone_subscription_id'], '"') . '"%';
        $paid = (int)(Database::run('SELECT COUNT(*) c FROM payments WHERE site_id = ? AND env = ? AND status = "paid" AND metadata LIKE ?', [$sub['site_id'], $sub['env'] ?? 'sandbox', $like])->fetch()['c'] ?? 0);
        $total = (int)($plan['billing_cycles'] ?? 0);
        // invoices (payments) por assinatura via metadata.subscription_stone_id
        $sid = (string)$sub['stone_subscription_id'];
        $like = '%"subscription_stone_id":"' . addcslashes($sid, '"') . '"%';
        $pays = Database::run('SELECT id, amount_cents, currency, status, created_at, updated_at, metadata FROM payments WHERE site_id = ? AND env = ? AND metadata LIKE ? ORDER BY id DESC LIMIT 200', [$sub['site_id'], $sub['env'] ?? 'sandbox', $like])->fetchAll();
        $invoices = [];
        foreach ($pays as $p) {
            $meta = $p['metadata'] ? json_decode((string)$p['metadata'], true) : [];
            $occ = is_array($meta) && isset($meta['occurrence']) ? (int)$meta['occurrence'] : null;
            $paidHist = Database::run('SELECT created_at FROM payment_status_history WHERE payment_id = ? AND new_status = "paid" ORDER BY id DESC LIMIT 1', [$p['id']])->fetch();
            // Tentar extrair paid_at do JSON do webhook ligado a este pagamento
            $inb = Database::run('SELECT raw_payload, received_at FROM inbound_webhooks WHERE payment_id = ? ORDER BY id DESC LIMIT 1', [$p['id']])->fetch();
            $pj = [];
            try { $pj = $inb && !empty($inb['raw_payload']) ? json_decode((string)$inb['raw_payload'], true) : []; } catch (\Throwable $e) { $pj = []; }
            $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
            $paidJson = null;
            if (is_array($pj)) {
                foreach ([['data','paid_at'], ['data','charge','paid_at'], ['data','charges',0,'paid_at'], ['data','last_transaction','paid_at'], ['paid_at']] as $pth) {
                    $v = $read($pj, $pth);
                    if (!empty($v) && is_string($v)) { $paidJson = $v; break; }
                }
            }
            $paidAt = $paidJson ?: ($paidHist['created_at'] ?? ($inb['received_at'] ?? null));
            $invoices[] = [
                'payment_id' => (int)$p['id'],
                'occurrence' => $occ,
                'amount_cents' => (int)$p['amount_cents'],
                'currency' => (string)$p['currency'],
                'status' => (string)$p['status'],
                'paid_at' => $paidAt,
            ];
        }
        return Response::json([
            'plan' => $plan ? [
                'name' => $plan['name'],
                'amount_cents' => (int)$plan['amount_cents'],
                'currency' => $plan['currency'],
                'interval' => $plan['interval_unit'],
                'interval_count' => (int)$plan['interval_count'],
                'billing_cycles' => (int)$plan['billing_cycles'],
            ] : null,
            'progress' => [ 'paid' => $paid, 'total' => $total ],
            'invoices' => $invoices,
        ]);
    }

    // POST /_admin/subscriptions/forward-payment { payment_id }
    public function forwardPayment(Request $req): Response
    {
        // Accept x-www-form-urlencoded (preferred), fallback to query/$_POST if necessary
        $post = [];
        if (is_string($req->rawBody ?? null)) parse_str($req->rawBody, $post);
        if (!isset($post['payment_id']) && isset($_POST['payment_id'])) $post['payment_id'] = $_POST['payment_id'];
        if (!isset($post['csrf']) && isset($_POST['csrf'])) $post['csrf'] = $_POST['csrf'];
        if (!isset($post['payment_id']) && isset($req->query['payment_id'])) $post['payment_id'] = $req->query['payment_id'];
        if (!isset($post['csrf']) && isset($req->query['csrf'])) $post['csrf'] = $req->query['csrf'];
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return Response::json(['error'=>'Invalid CSRF'], 400);
        $paymentId = (int)($post['payment_id'] ?? 0);
        if ($paymentId <= 0) return Response::json(['error'=>'Invalid payment_id'], 422);
        $pay = Database::run('SELECT * FROM payments WHERE id = ? LIMIT 1', [$paymentId])->fetch();
        if (!$pay) return Response::json(['error'=>'Payment not found'], 404);
        $inb = Database::run('SELECT * FROM inbound_webhooks WHERE payment_id = ? ORDER BY id DESC LIMIT 1', [$paymentId])->fetch();
        if (!$inb) return Response::json(['error'=>'No inbound webhook associated with this payment'], 404);
        $payload = json_decode((string)$inb['raw_payload'], true) ?: [];
        try {
            (new \App\Application\WebhookService())->forwardToSite((int)$inb['site_id'], (int)$inb['id'], $paymentId, $payload, 1);
            return Response::json(['ok'=>1]);
        } catch (\Throwable $e) {
            return Response::json(['error'=>'Forward failed','message'=>$e->getMessage()], 500);
        }
    }
}
