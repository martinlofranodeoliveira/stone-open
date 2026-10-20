<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\View\View;
use App\Support\Security\Csrf;
use App\Support\Env;
use App\Infrastructure\Stone\StoneDiagnostics;

final class AdminAccountController
{
    public function edit(Request $req): Response
    {
        $this->ensureTable();
        $row = $this->loadSettings();
        // Load pagarme accounts for auth test dropdown
        try {
            $accounts = Database::run('SELECT id, name, active_env, status FROM pagarme_accounts ORDER BY name')->fetchAll();
        } catch (\Throwable $e) { $accounts = []; }
        $html = View::render('admin/account', [
            'prefix' => $this->prefix(),
            'csrf' => Csrf::token(),
            'basic_user' => (string)($row['stone_webhook_basic_user'] ?? ''),
            'basic_enabled' => !empty($row['stone_webhook_basic_user']) && !empty($row['stone_webhook_basic_pass_hash']),
            'accounts' => $accounts,
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function save(Request $req): Response
    {
        $this->ensureTable();
        if (($this->role() ?? '') !== 'admin') return new Response(403, ['Content-Type'=>'text/plain'],'Forbidden');
        parse_str($req->rawBody, $post);
        if (empty($post) && !empty($_POST)) { $post = $_POST; }
        if (!Csrf::check($post['csrf'] ?? null)) {
            return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid CSRF');
        }

        $enable = isset($post['enable_basic']) && (string)$post['enable_basic'] === '1';
        $user = trim((string)($post['basic_user'] ?? ''));
        $pass = (string)($post['basic_pass'] ?? '');

        $row = $this->loadSettings();
        $hasRow = !empty($row);

        if (!$enable) {
            // Disable Basic auth entirely
            if ($hasRow) {
                Database::run('UPDATE account_settings SET stone_webhook_basic_user=NULL, stone_webhook_basic_pass_hash=NULL WHERE id=1');
            } else {
                Database::run('INSERT INTO account_settings (id, stone_webhook_basic_user, stone_webhook_basic_pass_hash) VALUES (1, NULL, NULL)');
            }
            return new Response(302, ['Location' => $this->prefix() . '/account?saved=1&disabled=1'], '');
        }

        if ($user === '') {
            return new Response(400, ['Content-Type' => 'text/plain'], 'Usuário é obrigatório quando a autenticação Basic está habilitada');
        }

        // If password provided, rotate hash; if omitted and there is already a hash, keep it
        $hash = null;
        if ($pass !== '') {
            $hash = password_hash($pass, PASSWORD_ARGON2ID);
        }

        if ($hasRow) {
            if ($hash) {
                Database::run('UPDATE account_settings SET stone_webhook_basic_user=?, stone_webhook_basic_pass_hash=? WHERE id=1', [$user, $hash]);
            } else {
                // keep existing hash
                Database::run('UPDATE account_settings SET stone_webhook_basic_user=? WHERE id=1', [$user]);
            }
        } else {
            Database::run('INSERT INTO account_settings (id, stone_webhook_basic_user, stone_webhook_basic_pass_hash) VALUES (1, ?, ?)', [$user, $hash]);
        }

        return new Response(302, ['Location' => $this->prefix() . '/account?saved=1'], '');
    }

    // GET /_admin/account/test-auth?env=production|sandbox&account_id=123
    public function testAuth(Request $req): Response
    {
        $env = strtolower((string)($req->query['env'] ?? ''));
        if ($env === 'homolog') $env = 'sandbox';
        if (!in_array($env, ['production','sandbox'], true)) $env = '';

        // Prefer explicit account_id; otherwise, choose an active account (matching env if provided)
        $accId = isset($req->query['account_id']) ? (int)$req->query['account_id'] : 0;
        if ($accId <= 0) {
            try {
                if ($env !== '') {
                    $row = Database::run('SELECT id FROM pagarme_accounts WHERE status="active" AND active_env = ? ORDER BY id ASC LIMIT 1', [$env])->fetch();
                } else {
                    $row = Database::run('SELECT id FROM pagarme_accounts WHERE status="active" ORDER BY id ASC LIMIT 1')->fetch();
                }
                $accId = (int)($row['id'] ?? 0);
            } catch (\Throwable $e) { $accId = 0; }
        }
        if ($accId <= 0) {
            $payload = json_encode(['status'=>'error','http'=>0,'message'=>'Nenhuma conta ativa encontrada para testar','testedEnv'=>$env], JSON_UNESCAPED_UNICODE);
            return new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], (string)$payload);
        }

        $result = \App\Infrastructure\Stone\StoneDiagnostics::authCheckAccount($accId, $env ?: null);
        $payload = json_encode($result, JSON_UNESCAPED_UNICODE);
        return new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], (string)$payload);
    }

    private function loadSettings(): array
    {
        try {
            $row = Database::run('SELECT * FROM account_settings WHERE id=1 LIMIT 1')->fetch();
            return $row ?: [];
        } catch (\Throwable $e) {
            // Table might not exist yet; treat as empty
            return [];
        }
    }

    private function ensureTable(): void
    {
        try {
            Database::run('CREATE TABLE IF NOT EXISTS account_settings (
              id TINYINT UNSIGNED NOT NULL DEFAULT 1,
              stone_webhook_basic_user VARCHAR(150) NULL,
              stone_webhook_basic_pass_hash VARCHAR(255) NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        } catch (\Throwable $e) {
            // ignore; will fail later with explicit error if DB is not writable
        }
    }

    private function prefix(): string
    {
        return rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');
    }

    private function role(): ?string
    {
        return $_SESSION['admin_user_role'] ?? null;
    }
}
