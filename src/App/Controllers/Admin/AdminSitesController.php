<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\View\View;
use App\Support\Security\Csrf;
use App\Support\Env;

final class AdminSitesController
{
    public function createForm(Request $req): Response
    {
        // Ensure accounts table exists
        \App\Infrastructure\Stone\StoneAccount::ensureSchema();
        $accounts = \App\Infrastructure\DB\Database::run('SELECT id, name, active_env, stone_account_uid FROM pagarme_accounts WHERE status="active" ORDER BY name')->fetchAll();
        $html = \App\Infrastructure\View\View::render('admin/sites_create', [
            'prefix' => $this->prefix(),
            'csrf' => \App\Support\Security\Csrf::token(),
            'accounts' => $accounts,
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function store(Request $req): Response
    {
        $this->ensureSitesTable();
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check($post['csrf'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid CSRF');
        }
        $name = trim((string)($post['name'] ?? ''));
        $domain = trim((string)($post['domain'] ?? ''));
        $callback = trim((string)($post['callback_url'] ?? ''));
        $status = (string)($post['status'] ?? 'active');
        $rate = (int)($post['rate_limit_per_minute'] ?? 60);
        $interestPayer = in_array(($post['interest_payer'] ?? 'user'), ['user','merchant']) ? $post['interest_payer'] : 'user';
        $installmentsFree = max(1, (int)($post['installments_free'] ?? 1));
        $requireBilling = null;
        if (isset($post['require_billing_address']) && $post['require_billing_address'] !== '') {
            $requireBilling = ((int)$post['require_billing_address']) === 1 ? 1 : 0;
        }
        $pagarmeAccountId = isset($post['pagarme_account_id']) ? (int)$post['pagarme_account_id'] : null;

        $clientId = bin2hex(random_bytes(16));
        $secretPlain = bin2hex(random_bytes(24));
        $secretHash = password_hash($secretPlain, PASSWORD_ARGON2ID);
        $forwardSecret = bin2hex(random_bytes(24));
        $apiToken = bin2hex(random_bytes(32));

        try {
            // Newer schema: stores hint and plaintext for admin visibility
            $stoneAcc = trim((string)($post['stone_account_id'] ?? '')) ?: null;
            try {
                Database::run('INSERT INTO sites (name, domain, callback_url, stone_account_id, pagarme_account_id, client_secret_hint, client_secret_plain, webhook_forward_secret, api_token, client_id, client_secret_hash, interest_payer, installments_free, require_billing_address, status, rate_limit_per_minute) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                    $name, $domain, $callback, $stoneAcc, $pagarmeAccountId ?: null, substr($secretPlain, -6), $secretPlain, $forwardSecret, $apiToken, $clientId, $secretHash, $interestPayer, $installmentsFree, $requireBilling, $status, $rate
                ]);
            } catch (\Throwable $e0) {
                try {
                    Database::run('INSERT INTO sites (name, domain, callback_url, client_secret_hint, client_secret_plain, webhook_forward_secret, api_token, client_id, client_secret_hash, interest_payer, installments_free, require_billing_address, status, rate_limit_per_minute, pagarme_account_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                        $name, $domain, $callback, substr($secretPlain, -6), $secretPlain, $forwardSecret, $apiToken, $clientId, $secretHash, $interestPayer, $installmentsFree, $requireBilling, $status, $rate, $pagarmeAccountId ?: null
                    ]);
                } catch (\Throwable $e00) {
                    Database::run('INSERT INTO sites (name, domain, callback_url, client_secret_hint, webhook_forward_secret, api_token, client_id, client_secret_hash, interest_payer, installments_free, status, rate_limit_per_minute) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                        $name, $domain, $callback, substr($secretPlain, -6), $forwardSecret, $apiToken, $clientId, $secretHash, $interestPayer, $installmentsFree, $status, $rate
                    ]);
                }
            }
        } catch (\Throwable $e) {
            try {
                // Fallback for schema without client_secret_plain
                Database::run('INSERT INTO sites (name, domain, callback_url, client_secret_hint, webhook_forward_secret, api_token, client_id, client_secret_hash, interest_payer, installments_free, status, rate_limit_per_minute) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                    $name, $domain, $callback, substr($secretPlain, -6), $forwardSecret, $apiToken, $clientId, $secretHash, $interestPayer, $installmentsFree, $status, $rate
                ]);
            } catch (\Throwable $e2) {
                // Fallback for older schema without client_secret_hint
                Database::run('INSERT INTO sites (name, domain, callback_url, webhook_forward_secret, api_token, client_id, client_secret_hash, interest_payer, installments_free, status, rate_limit_per_minute) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                    $name, $domain, $callback, $forwardSecret, $apiToken, $clientId, $secretHash, $interestPayer, $installmentsFree, $status, $rate
                ]);
            }
        }
        $id = (int) \App\Infrastructure\DB\Database::pdo()->lastInsertId();
        // If stone_account_id provided and column exists but previous insert path didn't set, try update
        try {
            if (!empty($post['stone_account_id'])) {
                Database::run('UPDATE sites SET stone_account_id = ? WHERE id = ?', [trim((string)$post['stone_account_id']), $id]);
            }
        } catch (\Throwable $e) {}
        // flash secrets to session (will be shown once in modal on listing page)
        $_SESSION['new_site_secrets'] = [
            'id' => $id,
            'client_id' => $clientId,
            'client_secret' => $secretPlain,
            'forward_secret' => $forwardSecret,
            'api_token' => $apiToken,
        ];
        return new Response(302, ['Location' => $this->prefix() . '/sites?created=1'], '');
    }
    public function list(Request $req): Response
    {
        $this->ensureSitesTable();
        $q = trim((string)($req->query['q'] ?? ''));
        $sql = 'SELECT * FROM sites WHERE 1';
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (name LIKE ? OR domain LIKE ?)';
            $params[] = "%$q%"; $params[] = "%$q%";
        }
        $rows = \App\Infrastructure\DB\Database::run($sql . ' ORDER BY id DESC LIMIT 100', $params)->fetchAll();
        $newSecrets = $_SESSION['new_site_secrets'] ?? null; unset($_SESSION['new_site_secrets']);
        $html = View::render('admin/sites_list', [
            'prefix' => $this->prefix(),
            'sites' => $rows,
            'q' => $q,
            'csrf' => Csrf::token(),
            'newSecrets' => $newSecrets,
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function data(Request $req): Response
    {
        $this->ensureSitesTable();
        $draw = (int)($req->query['draw'] ?? 1);
        $start = max(0, (int)($req->query['start'] ?? 0));
        $length = min(200, max(10, (int)($req->query['length'] ?? 25)));
        $search = trim((string)($req->query['search']['value'] ?? ''));
        $q = trim((string)($req->query['q'] ?? ''));

        $base = ' FROM sites WHERE 1';
        $params = [];
        if ($q !== '') { $base .= ' AND (name LIKE ? OR domain LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($search !== '') { $base .= ' AND (name LIKE ? OR domain LIKE ? OR callback_url LIKE ?)'; $params += []; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

        $total = (int) \App\Infrastructure\DB\Database::run('SELECT COUNT(*) c FROM sites')->fetch()['c'];
        $filtered = (int) \App\Infrastructure\DB\Database::run('SELECT COUNT(*) c ' . $base, $params)->fetch()['c'];

        $orderColIdx = (int)($req->query['order'][0]['column'] ?? 0);
        $orderDir = strtolower((string)($req->query['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $columns = ['id','name','domain','status','client_secret_hint','callback_url','created_at'];
        $orderBy = $columns[$orderColIdx] ?? 'id';

        $sql = 'SELECT id, name, domain, status, client_secret_hint, client_secret_plain, callback_url, created_at, client_id, webhook_forward_secret, api_token, interest_payer, installments_free,
                rate_limit_per_minute,
                breaker_open_until, breaker_failure_count,
                (SELECT `count` FROM site_rate_counters src WHERE src.site_id = sites.id AND src.window_start_minute = DATE_FORMAT(NOW(), "%Y-%m-%d %H:%i:00") LIMIT 1) AS rate_used_minute ' . $base . ' ORDER BY ' . $orderBy . ' ' . $orderDir . ' LIMIT ' . (int)$length . ' OFFSET ' . (int)$start;
        try {
            $rows = Database::run($sql, $params)->fetchAll();
        } catch (\Throwable $e) {
            // Fallback without client_secret_hint column
            // Try without client_secret_plain
            try {
                $sql2 = 'SELECT id, name, domain, status, client_secret_hint, callback_url, created_at, client_id, webhook_forward_secret, api_token, interest_payer, installments_free,
                        rate_limit_per_minute,
                        breaker_open_until, breaker_failure_count,
                        (SELECT `count` FROM site_rate_counters src WHERE src.site_id = sites.id AND src.window_start_minute = DATE_FORMAT(NOW(), "%Y-%m-%d %H:%i:00") LIMIT 1) AS rate_used_minute ' . $base . ' ORDER BY ' . $orderBy . ' ' . $orderDir . ' LIMIT ' . (int)$length . ' OFFSET ' . (int)$start;
                $rows = Database::run($sql2, $params)->fetchAll();
                foreach ($rows as &$r) { if (!isset($r['client_secret_plain'])) $r['client_secret_plain'] = null; }
            } catch (\Throwable $e2) {
                $sql3 = 'SELECT id, name, domain, status, callback_url, created_at, client_id, webhook_forward_secret, api_token, interest_payer, installments_free,
                        rate_limit_per_minute,
                        breaker_open_until, breaker_failure_count,
                        (SELECT `count` FROM site_rate_counters src WHERE src.site_id = sites.id AND src.window_start_minute = DATE_FORMAT(NOW(), "%Y-%m-%d %H:%i:00") LIMIT 1) AS rate_used_minute ' . $base . ' ORDER BY ' . $orderBy . ' ' . $orderDir . ' LIMIT ' . (int)$length . ' OFFSET ' . (int)$start;
                $rows = Database::run($sql3, $params)->fetchAll();
                foreach ($rows as &$r) { if (!isset($r['client_secret_hint'])) $r['client_secret_hint'] = null; if (!isset($r['client_secret_plain'])) $r['client_secret_plain'] = null; }
            }
        }
        return \App\Infrastructure\Http\Response::json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows,
        ]);
    }

    public function editForm(Request $req): Response
    {
        $id = (int)($req->attributes['routeParams']['id'] ?? 0);
        $site = Database::run('SELECT * FROM sites WHERE id = ?', [$id])->fetch();
        if (!$site) return new Response(404, ['Content-Type' => 'text/plain'], 'Not found');
        \App\Infrastructure\Stone\StoneAccount::ensureSchema();
        $accounts = Database::run('SELECT id, name, active_env, stone_account_uid FROM pagarme_accounts WHERE status="active" ORDER BY name')->fetchAll();
        $html = View::render('admin/sites_edit', [
            'prefix' => $this->prefix(),
            'site' => $site,
            'csrf' => Csrf::token(),
            'accounts' => $accounts,
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function update(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!Csrf::check($post['csrf'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid CSRF');
        }
        $id = (int)($post['id'] ?? 0);
        $name = trim((string)($post['name'] ?? ''));
        $domain = trim((string)($post['domain'] ?? ''));
        $callback = trim((string)($post['callback_url'] ?? ''));
        $status = (string)($post['status'] ?? 'active');
        $rate = (int)($post['rate_limit_per_minute'] ?? 60);
        $interestPayer = in_array(($post['interest_payer'] ?? 'user'), ['user','merchant']) ? $post['interest_payer'] : 'user';
        $installmentsFree = max(1, (int)($post['installments_free'] ?? 1));
        $requireBilling = null;
        if (isset($post['require_billing_address']) && $post['require_billing_address'] !== '') {
            $requireBilling = ((int)$post['require_billing_address']) === 1 ? 1 : 0;
        }
        // Update with optional stone_account_id when available
        $stoneAcc = trim((string)($post['stone_account_id'] ?? '')) ?: null;
        $pagarmeAccountId = isset($post['pagarme_account_id']) ? (int)$post['pagarme_account_id'] : null;
        try {
            Database::run('UPDATE sites SET name=?, domain=?, callback_url=?, stone_account_id=?, pagarme_account_id=?, interest_payer=?, installments_free=?, require_billing_address=?, status=?, rate_limit_per_minute=? WHERE id=?', [
                $name, $domain, $callback, $stoneAcc, $pagarmeAccountId ?: null, $interestPayer, $installmentsFree, $requireBilling, $status, $rate, $id
            ]);
        } catch (\Throwable $e) {
            Database::run('UPDATE sites SET name=?, domain=?, callback_url=?, interest_payer=?, installments_free=?, status=?, rate_limit_per_minute=? WHERE id=?', [
                $name, $domain, $callback, $interestPayer, $installmentsFree, $status, $rate, $id
            ]);
        }
        return new Response(302, ['Location' => $this->prefix() . '/sites'], '');
    }

    public function resetSecrets(Request $req): Response
    {
        $this->ensureSitesTable();
        if (($this->role() ?? '') !== 'admin') return new Response(403, ['Content-Type'=>'text/plain'],'Forbidden');
        parse_str($req->rawBody, $post);
        if (!Csrf::check($post['csrf'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid CSRF');
        }
        $id = (int)$post['id'];
        $newClientSecret = bin2hex(random_bytes(24));
        $hash = password_hash($newClientSecret, PASSWORD_ARGON2ID);
        $newForwardSecret = bin2hex(random_bytes(24));
        $newApiToken = bin2hex(random_bytes(32));
        $siteRow = Database::run('SELECT client_id FROM sites WHERE id=? LIMIT 1', [$id])->fetch();
        $clientId = (string)($siteRow['client_id'] ?? '');
        try {
            Database::run('UPDATE sites SET client_secret_hash=?, client_secret_hint=?, client_secret_plain=?, webhook_forward_secret=?, api_token=? WHERE id=?', [
                $hash, substr($newClientSecret, -6), $newClientSecret, $newForwardSecret, $newApiToken, $id
            ]);
        } catch (\Throwable $e) {
            try {
                Database::run('UPDATE sites SET client_secret_hash=?, client_secret_hint=?, webhook_forward_secret=?, api_token=? WHERE id=?', [
                    $hash, substr($newClientSecret, -6), $newForwardSecret, $newApiToken, $id
                ]);
            } catch (\Throwable $e2) {
                Database::run('UPDATE sites SET client_secret_hash=?, webhook_forward_secret=?, api_token=? WHERE id=?', [
                    $hash, $newForwardSecret, $newApiToken, $id
                ]);
            }
        }
        $accept = strtolower((string)($req->header('accept') ?? ''));
        $isAjax = (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') || str_contains($accept, 'application/json');
        if ($isAjax) {
            return Response::json([
                'ok' => true,
                'id' => $id,
                'client_id' => $clientId,
                'client_secret' => $newClientSecret,
                'forward_secret' => $newForwardSecret,
                'api_token' => $newApiToken,
            ]);
        }
        $html = '<html><body><pre>'
              . htmlspecialchars(
                    "Novos segredos gerados para o site #{$id}\n"
                  . "CLIENT_ID={$clientId}\n"
                  . "CLIENT_SECRET={$newClientSecret}\n"
                  . "FORWARD_SECRET={$newForwardSecret}\n"
                  . "API_TOKEN={$newApiToken}",
                  ENT_QUOTES
                )
              . '</pre><a href="' . $this->prefix() . '/sites">Voltar</a></body></html>';
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function rotateApiToken(Request $req): Response
    {
        $this->ensureSitesTable();
        if (($this->role() ?? '') !== 'admin') return new Response(403, ['Content-Type'=>'text/plain'],'Forbidden');
        parse_str($req->rawBody, $post);
        if (!Csrf::check($post['csrf'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid CSRF');
        }
        $id = (int)($post['id'] ?? 0);
        $newApiToken = bin2hex(random_bytes(32));
        Database::run('UPDATE sites SET api_token = ? WHERE id = ?', [$newApiToken, $id]);
        $accept = strtolower((string)($req->header('accept') ?? ''));
        $isAjax = (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') || str_contains($accept, 'application/json');
        if ($isAjax) {
            return Response::json(['ok'=>true, 'id'=>$id, 'api_token'=>$newApiToken]);
        }
        $html = '<html><body><div class="container"><div class="panel">'
              . '<h3>Novo API_TOKEN para o site #' . (int)$id . '</h3>'
              . '<p class="muted">Guarde com segurança. Será exibido apenas uma vez.</p>'
              . '<pre>' . htmlspecialchars($newApiToken, ENT_QUOTES) . '</pre>'
              . '<a class="btn" href="' . $this->prefix() . '/sites">Voltar</a>'
              . '</div></div></body></html>';
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function toggleStatus(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check(($post['csrf'] ?? null))) {
            return new Response(400, ['Content-Type'=>'text/plain'], 'Invalid CSRF');
        }
        $id = (int)($post['id'] ?? 0);
        $site = \App\Infrastructure\DB\Database::run('SELECT * FROM sites WHERE id=?', [$id])->fetch();
        if ($site) {
            $new = $site['status'] === 'active' ? 'disabled' : 'active';
            \App\Infrastructure\DB\Database::run('UPDATE sites SET status=? WHERE id=?', [$new, $id]);
        }
        return new Response(302, ['Location' => $this->prefix() . '/sites'], '');
    }

    public function delete(Request $req): Response
    {
        parse_str($req->rawBody, $post);
        if (!\App\Support\Security\Csrf::check(($post['csrf'] ?? null))) {
            return new Response(400, ['Content-Type'=>'text/plain'], 'Invalid CSRF');
        }
        $id = (int)($post['id'] ?? 0);
        if ($id > 0) {
            \App\Infrastructure\DB\Database::run('DELETE FROM sites WHERE id = ?', [$id]);
        }
        return new Response(302, ['Location' => $this->prefix() . '/sites?deleted=1'], '');
    }

    // Admin preview of installments based on site configuration
    public function quotePreview(Request $req): Response
    {
        $id = (int)($req->attributes['routeParams']['id'] ?? 0);
        $site = Database::run('SELECT * FROM sites WHERE id = ? LIMIT 1', [$id])->fetch();
        if (!$site) return Response::json(['error' => 'Not found'], 404);

        $amount = (int) ($req->query['amount_cents'] ?? 0);
        $bin = (string) ($req->query['bin'] ?? '');
        if ($amount <= 0 || $bin === '') return Response::json(['error' => 'amount_cents and bin are required'], 422);
        $max = isset($req->query['max_parcelas']) ? (int)$req->query['max_parcelas'] : null;
        $free = isset($req->query['parcelas_sem_juros']) ? (int)$req->query['parcelas_sem_juros'] : null;
        $rate = isset($req->query['taxa_juros']) ? (float)$req->query['taxa_juros'] : null;
        $min  = isset($req->query['valor_minimo_parcela_centavos']) ? (int)$req->query['valor_minimo_parcela_centavos'] : null;

        $payer = strtolower((string)($site['interest_payer'] ?? 'user'));
        if ($payer === 'merchant') {
            // If not explicitly provided in query, default to site's installments_free
            if (!$free || $free < 1) $free = (int)($site['installments_free'] ?? 1);
            if ($max !== null && $max > 0 && $free > 0 && $max > $free) {
                $max = $free; // evita parcelas com juros quando vendedor subsidia
            }
        }

        $svc = new \App\Application\CardService();
        $forceNoInterest = ($payer === 'merchant');
        $data = $svc->quote($bin, $amount, $max, $free, $rate, $min, null, $forceNoInterest);
        $data['interest_payer'] = in_array($payer, ['merchant','user']) ? $payer : 'user';
        return Response::json($data);
    }

    private function prefix(): string
    {
        return rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');
    }

    private function role(): ?string
    {
        return $_SESSION['admin_user_role'] ?? null;
    }

    private function ensureSitesTable(): void
    {
        try {
            $row = Database::run('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "sites" AND COLUMN_NAME = "client_secret_hint"')->fetch();
            if (!$row) {
                Database::run('ALTER TABLE sites ADD COLUMN client_secret_hint VARCHAR(16) NULL AFTER callback_url');
            }
            $row2 = Database::run('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "sites" AND COLUMN_NAME = "client_secret_plain"')->fetch();
            if (!$row2) {
                Database::run('ALTER TABLE sites ADD COLUMN client_secret_plain VARCHAR(128) NULL AFTER client_secret_hint');
            }
            $row3 = Database::run('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "sites" AND COLUMN_NAME = "stone_account_id"')->fetch();
            if (!$row3) {
                Database::run('ALTER TABLE sites ADD COLUMN stone_account_id VARCHAR(64) NULL AFTER callback_url');
            }
            $row4 = Database::run('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "sites" AND COLUMN_NAME = "pagarme_account_id"')->fetch();
            if (!$row4) {
                Database::run('ALTER TABLE sites ADD COLUMN pagarme_account_id BIGINT UNSIGNED NULL AFTER stone_account_id');
            }
            $row5 = Database::run('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "sites" AND COLUMN_NAME = "require_billing_address"')->fetch();
            if (!$row5) {
                Database::run('ALTER TABLE sites ADD COLUMN require_billing_address TINYINT(1) NULL DEFAULT NULL AFTER installments_free');
            }
        } catch (\Throwable $e) {
            // ignore; if it still fails later the SELECT that references the column will error and surface properly
        }
    }
}
