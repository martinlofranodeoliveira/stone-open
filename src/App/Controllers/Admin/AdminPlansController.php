<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\View\View;
use App\Support\Security\Csrf;
use App\Support\Env;
use App\Infrastructure\Stone\StoneAccount;

final class AdminPlansController
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
    private function logPlanDebug(array $row): void
    {
        try {
            $root = dirname(__DIR__, 4); // go from Controllers/Admin to project root
            $dir = $root . '/storage/logs/stone';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $file = $dir . '/plan-debug-' . date('Y-m-d') . '.log';
            $row['time'] = date('Y-m-d H:i:s');
            @file_put_contents($file, json_encode($row, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        } catch (\Throwable $e) {
            // ignore logging errors
        }
    }
    private function mapStoneFieldToForm(string $param): string
    {
        $p = strtolower($param);
        if ($p === '') return '';
        if (str_contains($p, 'interval') && str_contains($p, 'count')) return 'interval_count';
        if (preg_match('/\binterval\b/', $p)) return 'interval_unit';
        if (preg_match('/billing.*day/', $p)) return 'billing_day';
        if (preg_match('/billing.*type/', $p)) return 'billing_type';
        if (preg_match('/pricing_scheme|price/', $p)) return 'amount_display';
        if (preg_match('/statement/', $p)) return 'statement_descriptor';
        return '';
    }
    public function list(Request $req): Response
    {
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $status = (string)($req->query['status'] ?? '');
        $q = trim((string)($req->query['q'] ?? ''));
        $env = (string)($req->query['env'] ?? '');
        if ($env !== '' && !in_array($env, ['sandbox','production'], true)) $env = '';
        if ($env === '' && $siteId) {
            $site = Database::run('SELECT * FROM sites WHERE id=? LIMIT 1', [$siteId])->fetch();
            if ($site) $env = $this->resolveEnv($site);
        }
        $sql = 'SELECT p.*, s.name as site_name FROM plans p JOIN sites s ON s.id=p.site_id WHERE 1';
        $params = [];
        if ($siteId) { $sql .= ' AND p.site_id = ?'; $params[] = $siteId; }
        if ($status !== '') { $sql .= ' AND p.status = ?'; $params[] = $status; }
        if ($env !== '') { $sql .= ' AND p.env = ?'; $params[] = $env; }
        if ($q !== '') { $sql .= ' AND (p.name LIKE ? OR p.code LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        $page = max(1, (int)($req->query['page'] ?? 1));
        $perPage = min(100, max(10, (int)($req->query['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $sqlCount = 'SELECT COUNT(*) c FROM (' . $sql . ') t';
        $total = (int) Database::run($sqlCount, $params)->fetch()['c'];
        $sql .= ' ORDER BY p.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        $rows = Database::run($sql, $params)->fetchAll();
        // Human-friendly interval text for static table
        foreach ($rows as &$r) {
            $r['interval_human'] = $this->intervalHuman((int)$r['interval_count'], (string)$r['interval_unit'], (int)($r['billing_cycles'] ?? 1));
        }
        unset($r);
        $sites = Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll();
        $html = View::render('admin/plans_list', [
            'prefix' => $this->prefix(),
            'plans' => $rows,
            'sites' => $sites,
            'filter' => compact('siteId','status','env','q'),
            'page' => $page, 'perPage' => $perPage, 'total' => $total,
            'csrf' => Csrf::token(),
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
        $status = (string)($req->query['status'] ?? '');
        $q = trim((string)($req->query['q'] ?? ''));
        $env = (string)($req->query['env'] ?? '');
        if ($env !== '' && !in_array($env, ['sandbox','production'], true)) $env = '';
        if ($env === '' && $siteId) {
            $site = Database::run('SELECT * FROM sites WHERE id=? LIMIT 1', [$siteId])->fetch();
            if ($site) $env = $this->resolveEnv($site);
        }

        $base = ' FROM plans p JOIN sites s ON s.id=p.site_id WHERE 1';
        $params = [];
        if ($siteId) { $base .= ' AND p.site_id = ?'; $params[] = $siteId; }
        if ($status !== '') { $base .= ' AND p.status = ?'; $params[] = $status; }
        if ($env !== '') { $base .= ' AND p.env = ?'; $params[] = $env; }
        if ($q !== '') { $base .= ' AND (p.name LIKE ? OR p.code LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($search !== '') { $base .= ' AND (s.name LIKE ? OR p.name LIKE ? OR p.code LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

        $total = (int) Database::run('SELECT COUNT(*) c FROM plans')->fetch()['c'];
        $filtered = (int) Database::run('SELECT COUNT(*) c ' . $base, $params)->fetch()['c'];
        $orderColIdx = (int)($req->query['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string)($req->query['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $columns = ['p.id','s.name','p.env','p.name','p.code','p.amount_cents','p.interval_count','p.status','p.stone_plan_id', null];
        $orderBy = $columns[$orderColIdx] ?? 'p.id';
        if (!$orderBy) $orderBy = 'p.id';
        $sql = 'SELECT p.id, s.name AS site_name, p.env, p.name, p.code, p.amount_cents, p.interval_count, p.interval_unit, p.billing_cycles, p.status, p.stone_plan_id ' . $base . ' ORDER BY ' . $orderBy . ' ' . $orderDir . ' LIMIT ' . (int)$length . ' OFFSET ' . (int)$start;
        $rows = Database::run($sql, $params)->fetchAll();
        foreach ($rows as &$r) {
            $r['interval_desc'] = $this->intervalHuman((int)$r['interval_count'], (string)$r['interval_unit'], (int)($r['billing_cycles'] ?? 1));
        }
        unset($r);
        return Response::json(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$rows]);
    }

    public function createForm(Request $req): Response
    {
        $sites = Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll();
        $html = View::render('admin/plans_create', [
            'prefix' => $this->prefix(),
            'sites' => $sites,
            'csrf' => Csrf::token(),
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function store(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!Csrf::check($post['csrf'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid CSRF');
        }
        $siteId = (int)($post['site_id'] ?? 0);
        $name = trim((string)($post['name'] ?? ''));
        $code = trim((string)($post['code'] ?? ''));
        if ($code === '') {
            $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8','ASCII//TRANSLIT', $name) ?: $name));
            $base = trim(preg_replace('/-+/', '-', $base), '-');
            if ($base === '') $base = 'plan';
            $code = substr($base . '-' . date('YmdHis'), 0, 64);
        }
        $amount = (int)($post['amount_cents'] ?? 0);
        if ($amount <= 0) {
            return new Response(422, ['Content-Type' => 'text/plain; charset=utf-8'], 'Valor do plano deve ser maior que zero.');
        }
        $currency = strtoupper((string)($post['currency'] ?? 'BRL'));
        $intervalUnit = (string)($post['interval_unit'] ?? 'month');
        $intervalCount = (int)($post['interval_count'] ?? 1);
        $desc = (string)($post['description'] ?? '');
        $billingCycles = (int)($post['billing_cycles'] ?? 1);
        if ($billingCycles <= 0) $billingCycles = 1;
        $billingType = (string)($post['billing_type'] ?? '');
        $billingDay = isset($post['billing_day']) ? (int)$post['billing_day'] : null;
        $methods = isset($post['payment_methods']) ? (array)$post['payment_methods'] : [];
        $installmentsCsv = trim((string)($post['installments'] ?? ''));
        $installments = [];
        if ($installmentsCsv !== '') { foreach (explode(',', $installmentsCsv) as $i) { $n=(int)trim($i); if ($n>0) $installments[]=$n; } }
        $trialDays = isset($post['trial_period_days']) ? (int)$post['trial_period_days'] : null;
        $statement = trim((string)($post['statement_descriptor'] ?? ''));

        // Monta payload para Stone (sem itens obrigatórios)
        $payload = [
            'name' => $name,
            'currency' => strtoupper($currency ?: 'BRL'),
            'interval' => $intervalUnit,
            'interval_count' => max(1, $intervalCount),
        ];
        if ($desc !== '') $payload['description'] = $desc;
        if (!empty($methods)) {
            $mapped = [];
            foreach ($methods as $m) { $m=strtolower((string)$m); if (in_array($m,['cartao','cartão'])) $m='credit_card'; $mapped[]=$m; }
            $payload['payment_methods'] = array_values(array_unique($mapped));
        }
        if (!empty($installments)) $payload['installments'] = array_values(array_unique($installments));
        if ($billingType !== '') $payload['billing_type'] = $billingType;
        if ($billingType === 'exact_day' && $billingDay) $payload['billing_day'] = $billingDay;
        if ($trialDays !== null && $trialDays > 0) $payload['trial_period_days'] = $trialDays;
        if ($statement !== '') $payload['statement_descriptor'] = $statement;

        $corrId = bin2hex(random_bytes(6));
        $this->logPlanDebug([
            'corr_id' => $corrId,
            'action' => 'create_plan_pre',
            'site_id' => $siteId,
            'admin_user_id' => $_SESSION['admin_user_id'] ?? null,
            'payload' => $payload,
        ]);

        try {
            // Resolve conta pela relação do site
            $site = Database::run('SELECT * FROM sites WHERE id=? LIMIT 1', [$siteId])->fetch() ?: [];
            $env = $site ? $this->resolveEnv($site) : 'sandbox';
            $client = $site ? \App\Infrastructure\Stone\StoneClient::forSite($site) : null;
            if (!$client) throw new \RuntimeException('Site não encontrado/sem conta vinculada');
            $res = $client->createPlan($payload);
            $this->logPlanDebug([
                'corr_id' => $corrId,
                'action' => 'create_plan_post',
                'response' => $res,
            ]);
            $stoneId = (string)($res['id'] ?? '');
            $status = (string)($res['status'] ?? 'active');
            if ($stoneId === '') throw new \RuntimeException('Resposta sem id do plano');
            Database::run('INSERT INTO plans (site_id, name, code, env, description, amount_cents, currency, interval_unit, interval_count, billing_cycles, status, stone_plan_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?, ?, NOW(), NOW())', [
                $siteId, $name, $code, $env, $desc?:null, $amount, $currency, $intervalUnit, max(1,$intervalCount), $billingCycles, $status, $stoneId
            ]);
            return new Response(302, ['Location' => $this->prefix() . '/plans'], '');
        } catch (\App\Infrastructure\Stone\StoneApiException $e) {
            $detail = '';
            $errs = $e->getErrors();
            if (is_array($errs) && isset($errs[0])) {
                $first = $errs[0];
                if (is_array($first)) {
                    $field = isset($first['parameter_name']) ? (string)$first['parameter_name'] : (isset($first['field']) ? (string)$first['field'] : '');
                    $msg = isset($first['message']) ? (string)$first['message'] : '';
                    $code = isset($first['code']) ? (string)$first['code'] : '';
                    $detail = trim(($field ? ($field.': ') : '') . ($msg ?: $code));
                    // Redirect back with hint to highlight field
                    $hint = $this->mapStoneFieldToForm($field);
                    $qs = http_build_query(['error' => $e->getErrorMessage() . ($detail?(' - '.$detail):''), 'hint_field' => $hint]);
                    return new Response(302, ['Location' => $this->prefix() . '/plans/create?' . $qs], '');
                } elseif (is_string($first)) {
                    $detail = $first;
                }
            }
            $this->logPlanDebug([
                'corr_id' => $corrId,
                'action' => 'create_plan_error',
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getErrorMessage(),
                'errors' => $e->getErrors(),
            ]);
            $hint = $detail ? (' Detalhe: ' . $detail) : '';
            return new Response(400, ['Content-Type'=>'text/plain; charset=utf-8'], 'Falha ao criar plano na Stone: ' . $e->getErrorCode() . ': ' . $e->getErrorMessage() . $hint);
        } catch (\Throwable $e) {
            $this->logPlanDebug([
                'corr_id' => $corrId,
                'action' => 'create_plan_throwable',
                'error' => $e->getMessage(),
            ]);
            return new Response(500, ['Content-Type'=>'text/plain; charset=utf-8'], 'Falha ao criar plano na Stone: ' . $e->getMessage());
        }
    }

    public function editForm(Request $req): Response
    {
        $id = (int)($req->attributes['routeParams']['id'] ?? 0);
        $plan = Database::run('SELECT * FROM plans WHERE id = ?', [$id])->fetch();
        if (!$plan) return new Response(404, ['Content-Type' => 'text/plain'], 'Not found');
        $sites = Database::run('SELECT id, name FROM sites ORDER BY name')->fetchAll();
        $html = View::render('admin/plans_edit', [
            'prefix' => $this->prefix(),
            'plan' => $plan,
            'sites' => $sites,
            'csrf' => Csrf::token(),
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function update(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!Csrf::check($post['csrf'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid CSRF');
        }
        $id = (int)$post['id'];
        $siteId = (int)($post['site_id'] ?? 0);
        $name = trim((string)($post['name'] ?? ''));
        $code = trim((string)($post['code'] ?? ''));
        $amount = (int)($post['amount_cents'] ?? 0);
        if ($amount <= 0) {
            $qs = http_build_query(['error' => 'Valor do plano deve ser maior que zero.', 'hint_field' => 'amount_display', 'id' => $id]);
            return new Response(302, ['Location' => $this->prefix() . '/plans/' . $id . '?' . $qs], '');
        }
        $currency = (string)($post['currency'] ?? 'BRL');
        $intervalUnit = (string)($post['interval_unit'] ?? 'month');
        $intervalCount = (int)($post['interval_count'] ?? 1);
        $billingCycles = (int)($post['billing_cycles'] ?? 1);
        if ($billingCycles <= 0) $billingCycles = 1;
        $status = (string)($post['status'] ?? 'active');
        $desc = (string)($post['description'] ?? '');
        $stonePlanId = trim((string)($post['stone_plan_id'] ?? ''));
        Database::run('UPDATE plans SET site_id=?, name=?, code=?, description=?, amount_cents=?, currency=?, interval_unit=?, interval_count=?, billing_cycles=?, status=?, stone_plan_id=? WHERE id=?', [
            $siteId, $name, $code, $desc, $amount, $currency, $intervalUnit, max(1,$intervalCount), $billingCycles, $status, ($stonePlanId ?: null), $id
        ]);
        // Sincroniza com Stone (PUT exige corpo válido; evitar enviar 'items' sem id)
        $payload = [
            'name' => $name,
            'status' => $status,
            'currency' => strtoupper($currency ?: 'BRL'),
            'interval' => $intervalUnit,
            'interval_count' => max(1, $intervalCount),
        ];
        if ($desc !== '') $payload['description'] = $desc;
        if (isset($post['trial_period_days'])) { $tpd = (int)$post['trial_period_days']; if ($tpd > 0) $payload['trial_period_days'] = $tpd; }
        $corrId = bin2hex(random_bytes(6));
        $this->logPlanDebug([
            'corr_id' => $corrId,
            'action' => 'update_plan_pre',
            'plan_id' => $id,
            'admin_user_id' => $_SESSION['admin_user_id'] ?? null,
            'payload' => $payload,
        ]);

        try {
            $site = Database::run('SELECT * FROM sites WHERE id=? LIMIT 1', [(int)$pl['site_id']])->fetch() ?: [];
            $client = $site ? \App\Infrastructure\Stone\StoneClient::forSite($site) : null;
            if (!$client) throw new \RuntimeException('Site não encontrado/sem conta vinculada');
            if ($stonePlanId !== '') {
                // Completar payload com campos condicionais do plano remoto (ex.: exact_day)
                try {
                    $remote = $client->findPlan($stonePlanId);
                    if (is_array($remote)) {
                        if (!empty($remote['billing_type'])) {
                            $payload['billing_type'] = (string)$remote['billing_type'];
                            if ($payload['billing_type'] === 'exact_day') {
                                if (!empty($remote['billing_days']) && is_array($remote['billing_days'])) {
                                    $payload['billing_days'] = array_values(array_filter(array_map('intval', (array)$remote['billing_days'])));
                                } elseif (isset($remote['billing_day'])) {
                                    $d = (int)$remote['billing_day']; if ($d >= 1 && $d <= 28) $payload['billing_days'] = [$d];
                                }
                            }
                        }
                        if (!empty($remote['payment_methods']) && is_array($remote['payment_methods'])) {
                            $payload['payment_methods'] = array_values(array_unique(array_map('strval', $remote['payment_methods'])));
                        }
                        if (!empty($remote['installments']) && is_array($remote['installments'])) {
                            $payload['installments'] = array_values(array_filter(array_map('intval', $remote['installments'])));
                        }
                        // Atualizar preço do item principal se houver
                        if (!empty($remote['items']) && is_array($remote['items'])) {
                            $candidate = null;
                            foreach ($remote['items'] as $ri) {
                                if (is_array($ri) && isset($ri['id'])) { $candidate = $ri; break; }
                            }
                            if (is_array($candidate) && !empty($candidate['id'])) {
                                $schemeType = null;
                                if (isset($candidate['pricing_scheme']['scheme_type'])) $schemeType = (string)$candidate['pricing_scheme']['scheme_type'];
                                $it = [
                                    'id' => (string)$candidate['id'],
                                    'pricing_scheme' => [ 'price' => $amount ],
                                ];
                                if ($schemeType) $it['pricing_scheme']['scheme_type'] = $schemeType;
                                $payload['items'] = [ $it ];
                            }
                        }
                    }
                } catch (\Throwable $e) { /* best effort */ }
                $res = $client->updatePlan($stonePlanId, $payload);
                $this->logPlanDebug(['corr_id'=>$corrId,'action'=>'update_plan_post','response'=>$res]);
                $st = (string)($res['status'] ?? $status);
                Database::run('UPDATE plans SET status=? WHERE id=?', [$st, $id]);
            } else {
                // Criação remota sem itens (permitir planos sem items)
                $res = $client->createPlan($payload);
                $this->logPlanDebug(['corr_id'=>$corrId,'action'=>'create_plan_post_update_path','response'=>$res]);
                $newId = (string)($res['id'] ?? '');
                $st = (string)($res['status'] ?? $status);
                Database::run('UPDATE plans SET stone_plan_id=?, status=? WHERE id=?', [$newId ?: null, $st, $id]);
            }
        } catch (\App\Infrastructure\Stone\StoneApiException $e) {
            $errs = $e->getErrors();
            $this->logPlanDebug(['corr_id'=>$corrId,'action'=>'update_plan_error','error_code'=>$e->getErrorCode(),'error_message'=>$e->getErrorMessage(),'errors'=>$errs]);
            $field = null; $detail='';
            if (is_array($errs) && isset($errs[0]) && is_array($errs[0])) {
                $field = (string)($errs[0]['parameter_name'] ?? ($errs[0]['field'] ?? ''));
                $msg = (string)($errs[0]['message'] ?? '');
                $code = (string)($errs[0]['code'] ?? '');
                $detail = trim(($field?($field.': '):'') . ($msg ?: $code));
            }
            $hint = $this->mapStoneFieldToForm($field ?? '');
            $qs = http_build_query(['error' => $e->getErrorMessage() . ($detail?(' - '.$detail):''), 'hint_field' => $hint]);
            return new Response(302, ['Location' => $this->prefix() . '/plans/' . $id . '?' . $qs], '');
        } catch (\Throwable $e) {
            // ignore genérico
            $this->logPlanDebug(['corr_id'=>$corrId,'action'=>'update_plan_throwable','error'=>$e->getMessage()]);
        }
        return new Response(302, ['Location' => $this->prefix() . '/plans'], '');
    }

    public function toggle(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!Csrf::check($post['csrf'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid CSRF');
        }
        $id = (int)$post['id'];
        $plan = Database::run('SELECT * FROM plans WHERE id = ?', [$id])->fetch();
        if ($plan) {
            $new = $plan['status'] === 'active' ? 'inactive' : 'active';
            Database::run('UPDATE plans SET status = ? WHERE id = ?', [$new, $id]);
            // Propaga para a Stone se houver stone_plan_id
            $stoneId = (string)($plan['stone_plan_id'] ?? '');
            if ($stoneId !== '') {
                try {
                    $payload = [
                        'name' => (string)$plan['name'],
                        'status' => $new,
                        'currency' => strtoupper((string)$plan['currency'] ?: 'BRL'),
                        'interval' => (string)$plan['interval_unit'],
                        'interval_count' => max(1, (int)$plan['interval_count']),
                    ];
                    if (!empty($plan['description'])) $payload['description'] = (string)$plan['description'];
                    // Completar com campos condicionais remotos
                    $remote = $client->findPlan($stoneId);
                    if (is_array($remote)) {
                        if (!empty($remote['billing_type'])) {
                            $payload['billing_type'] = (string)$remote['billing_type'];
                            if ($payload['billing_type'] === 'exact_day') {
                                if (!empty($remote['billing_days']) && is_array($remote['billing_days'])) {
                                    $payload['billing_days'] = array_values(array_filter(array_map('intval', (array)$remote['billing_days'])));
                                } elseif (isset($remote['billing_day'])) {
                                    $d = (int)$remote['billing_day']; if ($d >= 1 && $d <= 28) $payload['billing_days'] = [$d];
                                }
                            }
                        }
                        if (!empty($remote['payment_methods']) && is_array($remote['payment_methods'])) {
                            $payload['payment_methods'] = array_values(array_unique(array_map('strval', $remote['payment_methods'])));
                        }
                        if (!empty($remote['installments']) && is_array($remote['installments'])) {
                            $payload['installments'] = array_values(array_filter(array_map('intval', $remote['installments'])));
                        }
                    }
                    $client->updatePlan($stoneId, $payload);
                } catch (\Throwable $e) {
                    // ignorar erro de propagação; status local já foi atualizado
                }
            }
        }
        return new Response(302, ['Location' => $this->prefix() . '/plans'], '');
    }

    public function pushToStone(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) return new Response(400, ['Content-Type'=>'text/plain'], 'Invalid CSRF');
        $id = (int)($post['id'] ?? 0);
        $plan = Database::run('SELECT * FROM plans WHERE id=?', [$id])->fetch();
        if (!$plan) return new Response(404, ['Content-Type'=>'text/plain'], 'Not found');
        $payload = [
            'name' => $plan['name'],
            'description' => $plan['description'] ?: null,
            'status' => (string)$plan['status'],
            'currency' => $plan['currency'],
            'interval' => $plan['interval_unit'],
            'interval_count' => (int)$plan['interval_count'],
            'metadata' => [ 'site_id' => (int)$plan['site_id'], 'local_plan_id' => (int)$plan['id'] ],
        ];
        try {
            $site = Database::run('SELECT * FROM sites WHERE id=? LIMIT 1', [(int)$row['site_id']])->fetch() ?: [];
            $client = $site ? \App\Infrastructure\Stone\StoneClient::forSite($site) : null;
            if (!$client) throw new \RuntimeException('Site não encontrado/sem conta vinculada');
            if ($plan['stone_plan_id']) {
                // Completar com campos condicionais remotos para garantir validade do PUT
                try {
                    $remote = $client->findPlan($plan['stone_plan_id']);
                    if (is_array($remote)) {
                        if (!empty($remote['billing_type'])) {
                            $payload['billing_type'] = (string)$remote['billing_type'];
                            if ($payload['billing_type'] === 'exact_day') {
                                if (!empty($remote['billing_days']) && is_array($remote['billing_days'])) {
                                    $payload['billing_days'] = array_values(array_filter(array_map('intval', (array)$remote['billing_days'])));
                                } elseif (isset($remote['billing_day'])) {
                                    $d = (int)$remote['billing_day']; if ($d >= 1 && $d <= 28) $payload['billing_days'] = [$d];
                                }
                            }
                        }
                        if (!empty($remote['payment_methods']) && is_array($remote['payment_methods'])) {
                            $payload['payment_methods'] = array_values(array_unique(array_map('strval', $remote['payment_methods'])));
                        }
                        if (!empty($remote['installments']) && is_array($remote['installments'])) {
                            $payload['installments'] = array_values(array_filter(array_map('intval', $remote['installments'])));
                        }
                        // Atualizar preço do item principal se houver
                        if (!empty($remote['items']) && is_array($remote['items'])) {
                            $candidate = null;
                            foreach ($remote['items'] as $ri) {
                                if (is_array($ri) && isset($ri['id'])) { $candidate = $ri; break; }
                            }
                            if (is_array($candidate) && !empty($candidate['id'])) {
                                $schemeType = null;
                                if (isset($candidate['pricing_scheme']['scheme_type'])) $schemeType = (string)$candidate['pricing_scheme']['scheme_type'];
                                $it = [
                                    'id' => (string)$candidate['id'],
                                    'pricing_scheme' => [ 'price' => (int)$plan['amount_cents'] ],
                                ];
                                if ($schemeType) $it['pricing_scheme']['scheme_type'] = $schemeType;
                                $payload['items'] = [ $it ];
                            }
                        }
                    }
                } catch (\Throwable $e) { /* ignore */ }
                $res = $client->updatePlan($plan['stone_plan_id'], $payload);
            } else {
                // Criação remota sem itens (permitir planos sem items)
                $res = $client->createPlan($payload);
                Database::run('UPDATE plans SET stone_plan_id=? WHERE id=?', [$res['id'] ?? null, $id]);
            }
            return new Response(302, ['Location' => $this->prefix() . '/plans'], '');
        } catch (\Throwable $e) {
            return new Response(500, ['Content-Type'=>'text/plain'], 'Plan push failed: '.$e->getMessage());
        }
    }

    private function prefix(): string
    {
        return rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');
    }

    private function intervalHuman(int $count, string $unit, int $cycles): string
    {
        $unit = strtolower($unit);
        $map = [
            'day' => ['sing' => 'dia', 'pl' => 'dias'],
            'week' => ['sing' => 'semana', 'pl' => 'semanas'],
            'month' => ['sing' => 'mês', 'pl' => 'meses'],
            'year' => ['sing' => 'ano', 'pl' => 'anos'],
        ];
        $u = $map[$unit] ?? ['sing' => 'período', 'pl' => 'períodos'];
        $label = 'A cada ' . $count . ' ' . ($count === 1 ? $u['sing'] : $u['pl']);
        if ($cycles > 0) {
            $label .= ' • ' . $cycles . ' ' . ($cycles === 1 ? 'ciclo' : 'ciclos');
        }
        return $label;
    }
}
