<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\DB\Database;
use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\View\View;
use App\Support\Env;
use App\Support\Security\Csrf;

final class AdminPagarmeAccountsController
{
    public function list(Request $req): Response
    {
        \App\Infrastructure\Stone\StoneAccount::ensureSchema();
        $rows = Database::run('SELECT * FROM pagarme_accounts ORDER BY id DESC LIMIT 200')->fetchAll();
        $html = View::render('admin/pagarme_accounts_list', [
            'prefix' => $this->prefix(),
            'accounts' => $rows,
            'csrf' => Csrf::token(),
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function createForm(Request $req): Response
    {
        \App\Infrastructure\Stone\StoneAccount::ensureSchema();
        $html = View::render('admin/pagarme_accounts_create', [
            'prefix' => $this->prefix(),
            'csrf' => Csrf::token(),
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function store(Request $req): Response
    {
        if (($this->role() ?? '') !== 'admin') return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        // Robust parse: support JSON, x-www-form-urlencoded, or multipart (FormData)
        $post = [];
        $ct = strtolower($req->header('content-type', ''));
        if (str_contains($ct, 'application/json')) {
            $post = is_array($req->json) ? $req->json : [];
        } elseif (str_contains($ct, 'application/x-www-form-urlencoded')) {
            parse_str($req->rawBody, $post);
        } else {
            $post = $_POST ?? [];
        }
        if (!Csrf::check($post['csrf'] ?? null)) return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid CSRF');
        $name = trim((string)($post['name'] ?? ''));
        $uid = trim((string)($post['stone_account_uid'] ?? '')) ?: null;
        $env = in_array(($post['active_env'] ?? 'sandbox'), ['sandbox', 'production'], true) ? $post['active_env'] : 'sandbox';
        $apiSandbox = $this->cleanKey($post['api_key_sandbox'] ?? null);
        $apiProd    = $this->cleanKey($post['api_key_production'] ?? null);
        $pubSandbox = $this->cleanKey($post['public_key_sandbox'] ?? null);
        $pubProd    = $this->cleanKey($post['public_key_production'] ?? null);
        $whSandbox  = $this->cleanKey($post['webhook_secret_sandbox'] ?? null);
        $whProd     = $this->cleanKey($post['webhook_secret_production'] ?? null);
        $status = in_array(($post['status'] ?? 'active'), ['active', 'disabled'], true) ? $post['status'] : 'active';
        Database::run('INSERT INTO pagarme_accounts (name, stone_account_uid, active_env, api_key_sandbox, api_key_production, public_key_sandbox, public_key_production, webhook_secret_sandbox, webhook_secret_production, status) VALUES (?,?,?,?,?,?,?,?,?,?)', [
            $name, $uid, $env, $apiSandbox, $apiProd, $pubSandbox, $pubProd, $whSandbox, $whProd, $status
        ]);
        return new Response(302, ['Location' => $this->prefix() . '/pagarme-accounts'], '');
    }

    public function editForm(Request $req): Response
    {
        $id = (int)($req->attributes['routeParams']['id'] ?? 0);
        $row = Database::run('SELECT * FROM pagarme_accounts WHERE id=? LIMIT 1', [$id])->fetch();
        if (!$row) return new Response(404, ['Content-Type' => 'text/plain'], 'Not found');
        $html = View::render('admin/pagarme_accounts_edit', [
            'prefix' => $this->prefix(),
            'acc' => $row,
            'csrf' => Csrf::token(),
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function update(Request $req): Response
    {
        if (($this->role() ?? '') !== 'admin') return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        parse_str($req->rawBody, $post);
        if (!Csrf::check($post['csrf'] ?? null)) return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid CSRF');
        $id = (int)($post['id'] ?? 0);
        if ($id <= 0) {
            $id = (int)($req->query['id'] ?? 0);
        }
        $name = trim((string)($post['name'] ?? ''));
        $uid = trim((string)($post['stone_account_uid'] ?? '')) ?: null;
        $env = in_array(($post['active_env'] ?? 'sandbox'), ['sandbox', 'production'], true) ? $post['active_env'] : 'sandbox';
        $apiSandbox = $this->cleanKey($post['api_key_sandbox'] ?? null);
        $apiProd    = $this->cleanKey($post['api_key_production'] ?? null);
        $pubSandbox = $this->cleanKey($post['public_key_sandbox'] ?? null);
        $pubProd    = $this->cleanKey($post['public_key_production'] ?? null);
        $whSandbox  = $this->cleanKey($post['webhook_secret_sandbox'] ?? null);
        $whProd     = $this->cleanKey($post['webhook_secret_production'] ?? null);
        $status = in_array(($post['status'] ?? 'active'), ['active', 'disabled'], true) ? $post['status'] : 'active';
        Database::run('UPDATE pagarme_accounts SET name=?, stone_account_uid=?, active_env=?, api_key_sandbox=?, api_key_production=?, public_key_sandbox=?, public_key_production=?, webhook_secret_sandbox=?, webhook_secret_production=?, status=? WHERE id=?', [
            $name, $uid, $env, $apiSandbox, $apiProd, $pubSandbox, $pubProd, $whSandbox, $whProd, $status, $id
        ]);
        return new Response(302, ['Location' => $this->prefix() . '/pagarme-accounts'], '');
    }

    public function delete(Request $req): Response
    {
        if (($this->role() ?? '') !== 'admin') return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        parse_str($req->rawBody, $post);
        if (!Csrf::check($post['csrf'] ?? null)) return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid CSRF');
        $id = (int)($post['id'] ?? 0);
        if ($id > 0) {
            Database::run('DELETE FROM pagarme_accounts WHERE id=?', [$id]);
        }
        return new Response(302, ['Location' => $this->prefix() . '/pagarme-accounts'], '');
    }

    // POST /_admin/pagarme-accounts/toggle-env (AJAX)
    public function toggleEnv(Request $req): Response
    {
        if (($this->role() ?? '') !== 'admin') return Response::json(['error' => 'Forbidden'], 403);
        // Parse corpo de forma robusta (JSON, x-www-form-urlencoded, multipart)
        $post = [];
        $ct = strtolower($req->header('content-type', ''));
        if (str_contains($ct, 'application/json')) {
            $post = is_array($req->json) ? $req->json : [];
        } elseif (str_contains($ct, 'application/x-www-form-urlencoded')) {
            parse_str($req->rawBody, $post);
        } else {
            $post = $_POST ?? [];
        }
        $csrfToken = $post['csrf'] ?? ($req->query['csrf'] ?? null);
        if (!Csrf::check($csrfToken)) return Response::json(['error' => 'Invalid CSRF'], 400);
        $id = (int)($post['id'] ?? 0);
        if ($id <= 0) { $id = (int)($req->query['id'] ?? 0); }
        if ($id <= 0) return Response::json(['error' => 'Invalid id'], 422);
        $row = Database::run('SELECT id, active_env FROM pagarme_accounts WHERE id=? LIMIT 1', [$id])->fetch();
        if (!$row) return Response::json(['error' => 'Not found'], 404);
        $cur = strtolower((string)($row['active_env'] ?? 'sandbox'));
        $next = $cur === 'production' ? 'sandbox' : 'production';
        Database::run('UPDATE pagarme_accounts SET active_env = ?, updated_at = NOW() WHERE id = ?', [$next, $id]);
        return Response::json(['ok' => 1, 'active_env' => $next]);
    }

    private function prefix(): string
    {
        return rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');
    }

    private function role(): ?string
    {
        return $_SESSION['admin_user_role'] ?? null;
    }

    private function cleanKey($value): ?string
    {
        $s = trim((string)($value ?? ''));
        if ($s === '') return null;
        // remove todos os espaços e quebras de linha internas
        $s = preg_replace('/\s+/', '', $s) ?? $s;
        return $s !== '' ? $s : null;
    }
}
