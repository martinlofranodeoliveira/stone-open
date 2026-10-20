<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\View\View;
use App\Support\Security\Csrf;
use App\Support\Env;

final class AdminUsersController
{
    public function list(Request $req): Response
    {
        $q = trim((string)($req->query['q'] ?? ''));
        $role = (string)($req->query['role'] ?? '');
        $status = (string)($req->query['status'] ?? '');
        $sql = 'SELECT * FROM admin_users WHERE 1';
        $params = [];
        if ($q !== '') { $sql .= ' AND (name LIKE ? OR email LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($role !== '') { $sql .= ' AND role = ?'; $params[] = $role; }
        if ($status !== '') { $sql .= ' AND status = ?'; $params[] = $status; }
        $rows = Database::run($sql . ' ORDER BY id DESC LIMIT 200', $params)->fetchAll();
        $html = View::render('admin/users_list', [
            'prefix' => $this->prefix(),
            'users' => $rows,
            'q' => $q,
            'role' => $role,
            'status' => $status,
            'csrf' => Csrf::token(),
            'currentRole' => $this->role(),
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function createForm(Request $req): Response
    {
        if (($this->role() ?? '') !== 'admin') return new Response(403, ['Content-Type'=>'text/plain'],'Forbidden');
        $html = View::render('admin/users_create', [
            'prefix' => $this->prefix(),
            'csrf' => Csrf::token(),
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function store(Request $req): Response
    {
        if (($this->role() ?? '') !== 'admin') return new Response(403, ['Content-Type'=>'text/plain'],'Forbidden');
        parse_str($req->rawBody, $post);
        if (!Csrf::check($post['csrf'] ?? null)) return new Response(400, ['Content-Type'=>'text/plain'],'Invalid CSRF');
        $name = trim((string)($post['name'] ?? ''));
        $email = trim((string)($post['email'] ?? ''));
        $pass = (string)($post['password'] ?? '');
        $role = (string)($post['role'] ?? 'admin');
        $status = (string)($post['status'] ?? 'active');
        $hash = password_hash($pass, PASSWORD_ARGON2ID);
        Database::run('INSERT INTO admin_users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)', [
            $name, $email, $hash, $role, $status
        ]);
        return new Response(302, ['Location' => $this->prefix() . '/users'], '');
    }

    public function editForm(Request $req): Response
    {
        if (($this->role() ?? '') !== 'admin') return new Response(403, ['Content-Type'=>'text/plain'],'Forbidden');
        $id = (int)($req->attributes['routeParams']['id'] ?? 0);
        $u = Database::run('SELECT * FROM admin_users WHERE id = ?', [$id])->fetch();
        if (!$u) return new Response(404, ['Content-Type'=>'text/plain'], 'Not found');
        $html = View::render('admin/users_edit', [
            'prefix' => $this->prefix(),
            'user' => $u,
            'csrf' => Csrf::token(),
        ]);
        return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
    }

    public function update(Request $req): Response
    {
        if (($this->role() ?? '') !== 'admin') return new Response(403, ['Content-Type'=>'text/plain'],'Forbidden');
        parse_str($req->rawBody, $post);
        if (!Csrf::check($post['csrf'] ?? null)) return new Response(400, ['Content-Type'=>'text/plain'],'Invalid CSRF');
        $id = (int)$post['id'];
        $name = trim((string)($post['name'] ?? ''));
        $email = trim((string)($post['email'] ?? ''));
        $role = (string)($post['role'] ?? 'admin');
        $status = (string)($post['status'] ?? 'active');
        Database::run('UPDATE admin_users SET name=?, email=?, role=?, status=? WHERE id=?', [
            $name, $email, $role, $status, $id
        ]);
        $pass = (string)($post['password'] ?? '');
        if ($pass !== '') {
            $hash = password_hash($pass, PASSWORD_ARGON2ID);
            Database::run('UPDATE admin_users SET password_hash=? WHERE id=?', [$hash, $id]);
        }
        return new Response(302, ['Location' => $this->prefix() . '/users'], '');
    }

    public function enable2fa(Request $req): Response
    {
        if (($this->role() ?? '') !== 'admin') return new Response(403, ['Content-Type'=>'text/plain'],'Forbidden');
        parse_str($req->rawBody, $post);
        if (!Csrf::check($post['csrf'] ?? null)) return new Response(400, ['Content-Type'=>'text/plain'],'Invalid CSRF');
        $id = (int)($post['id'] ?? 0);
        $secret = \App\Support\Security\Totp::generateBase32Secret();
        // Do NOT persist yet; keep pending in session until OTP is verified
        if (!isset($_SESSION['pending_twofa'])) $_SESSION['pending_twofa'] = [];
        $_SESSION['pending_twofa'][(int)$id] = $secret;
        $user = Database::run('SELECT * FROM admin_users WHERE id=?', [$id])->fetch();
        $issuer = parse_url(\App\Support\Env::get('APP_URL', 'stone.local'), PHP_URL_HOST) ?: 'StoneHub';
        $uri = \App\Support\Security\Totp::provisioningUri((string)$user['email'], $issuer, $secret);
        // Prefer JSON response for AJAX to render QR code modal inline
        $accept = strtolower((string)($req->header('accept') ?? ''));
        $isAjax = (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') || str_contains($accept, 'application/json');
        if ($isAjax) {
            return \App\Infrastructure\Http\Response::json(['ok'=>true, 'id'=>$id, 'email'=>(string)$user['email'], 'issuer'=>$issuer, 'secret'=>$secret, 'otpauth_uri'=>$uri]);
        }
        // Fallback HTML
        $html = '<html><body><div class="container"><div class="panel">'
              . '<h3>2FA pendente para ' . htmlspecialchars((string)$user['email'], ENT_QUOTES) . '</h3>'
              . '<p>Adicione no autenticador usando a URI:</p>'
              . '<pre>' . htmlspecialchars($uri, ENT_QUOTES) . '</pre>'
              . '<p class="muted">Depois, valide o código na tela do usuário.</p>'
              . '<a class="btn" href="' . $this->prefix() . '/users/' . (int)$id . '">Voltar</a>'
              . '</div></div></body></html>';
        return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
    }

    public function disable2fa(Request $req): Response
    {
        if (($this->role() ?? '') !== 'admin') return new Response(403, ['Content-Type'=>'text/plain'],'Forbidden');
        parse_str($req->rawBody, $post);
        if (!Csrf::check($post['csrf'] ?? null)) return new Response(400, ['Content-Type'=>'text/plain'],'Invalid CSRF');
        $id = (int)($post['id'] ?? 0);
        Database::run('UPDATE admin_users SET twofa_secret = NULL WHERE id = ?', [$id]);
        return new Response(302, ['Location' => $this->prefix() . '/users/' . $id], '');
    }

    public function verify2fa(Request $req): Response
    {
        if (($this->role() ?? '') !== 'admin') return new Response(403, ['Content-Type'=>'text/plain'],'Forbidden');
        parse_str($req->rawBody, $post);
        if (!Csrf::check($post['csrf'] ?? null)) return \App\Infrastructure\Http\Response::json(['ok'=>false, 'error'=>'Invalid CSRF'], 400);
        $id = (int)($post['id'] ?? 0);
        $otp = trim((string)($post['otp'] ?? ''));
        if ($id <= 0 || $otp === '') return \App\Infrastructure\Http\Response::json(['ok'=>false, 'error'=>'Missing id or otp'], 422);
        // Prefer pending secret in session (not yet persisted)
        $pending = $_SESSION['pending_twofa'][$id] ?? null;
        $secret = is_string($pending) && $pending !== '' ? $pending : '';
        if ($secret === '') {
            // Fallback to persisted secret (rare path if already enabled)
            $u = Database::run('SELECT twofa_secret FROM admin_users WHERE id=?', [$id])->fetch();
            $secret = (string)($u['twofa_secret'] ?? '');
            if ($secret === '') return \App\Infrastructure\Http\Response::json(['ok'=>false, 'error'=>'2FA not pending/enabled'], 400);
        }
        $valid = \App\Support\Security\Totp::verify($secret, $otp);
        if ($valid && isset($_SESSION['pending_twofa'][$id])) {
            // Persist and clear pending
            Database::run('UPDATE admin_users SET twofa_secret = ? WHERE id = ?', [$secret, $id]);
            unset($_SESSION['pending_twofa'][$id]);
        }
        return \App\Infrastructure\Http\Response::json(['ok'=>$valid]);
    }

    public function toggle(Request $req): Response
    {
        if (($this->role() ?? '') !== 'admin') return new Response(403, ['Content-Type'=>'text/plain'],'Forbidden');
        parse_str($req->rawBody, $post);
        if (!Csrf::check($post['csrf'] ?? null)) return new Response(400, ['Content-Type'=>'text/plain'],'Invalid CSRF');
        $id = (int)$post['id'];
        $u = Database::run('SELECT * FROM admin_users WHERE id=?', [$id])->fetch();
        if ($u) {
            $new = $u['status'] === 'active' ? 'disabled' : 'active';
            Database::run('UPDATE admin_users SET status=? WHERE id=?', [$new, $id]);
        }
        return new Response(302, ['Location' => $this->prefix() . '/users'], '');
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
