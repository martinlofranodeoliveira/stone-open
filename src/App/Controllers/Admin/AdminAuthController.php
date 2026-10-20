<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\View\View;
use App\Support\Security\{Captcha, Csrf};
use App\Support\Env;

final class AdminAuthController
{
    public function loginForm(Request $req): Response
    {
        $html = View::render('admin/login', [
            'prefix' => $this->prefix(),
            'csrf' => Csrf::token(),
            'error' => $_GET['error'] ?? null,
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public function login(Request $req): Response
    {
        // Seja resiliente: use $_POST quando for form urlencoded/multipart
        $post = $_POST ?: [];
        if (!$post && $req->rawBody !== '') {
            parse_str($req->rawBody, $post);
            if (!is_array($post)) $post = [];
        }
        $email = trim((string)($post['email'] ?? ''));
        $pass = (string)($post['password'] ?? '');
        $csrf = $post['csrf'] ?? '';
        if (!\App\Support\Security\Csrf::check($csrf)) {
            // Em vez de 400, redireciona para o login com erro amigável
            return new Response(302, ['Location' => $this->prefix() . '/login?error=csrf'], '');
        }
        $captcha = (string)($post['captcha'] ?? '');
        if (!Captcha::verify($captcha)) {
            return new Response(302, ['Location' => $this->prefix() . '/login?error=captcha'], '');
        }
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $user = Database::run('SELECT * FROM admin_users WHERE email = ? AND status = "active" LIMIT 1', [$email])->fetch();
        $ok = $user && password_verify($pass, $user['password_hash']);
        if ($ok && !empty($user['twofa_secret'])) {
            $otp = trim((string)($post['otp'] ?? ''));
            if ($otp === '' || !\App\Support\Security\Totp::verify((string)$user['twofa_secret'], $otp)) {
                $ok = false; // fail login if 2FA required and invalid/missing
            }
        }
        $requestId = (string)($req->attributes['request_id'] ?? '');
        try {
            Database::run('INSERT INTO admin_login_logs (admin_user_id, email, ip, user_agent, request_id, success) VALUES (?, ?, ?, ?, ?, ?)', [
                $user['id'] ?? null, $email, $ip, $ua, $requestId, (int)$ok
            ]);
        } catch (\Throwable $e) {
            Database::run('INSERT INTO admin_login_logs (admin_user_id, email, ip, user_agent, success) VALUES (?, ?, ?, ?, ?)', [
                $user['id'] ?? null, $email, $ip, $ua, (int)$ok
            ]);
        }
        if (!$ok) {
            return new Response(302, ['Location' => $this->prefix() . '/login?error=1'], '');
        }
        $_SESSION['admin_user_id'] = (int)$user['id'];
        $_SESSION['admin_user_name'] = $user['name'];
        $_SESSION['admin_user_role'] = $user['role'];
        return new Response(302, ['Location' => $this->prefix()], '');
    }

    public function logout(Request $req): Response
    {
        session_destroy();
        return new Response(302, ['Location' => $this->prefix() . '/login'], '');
    }

    public function captcha(Request $req): Response
    {
        $theme = strtolower((string)($_GET['theme'] ?? 'light'));
        if ($theme !== 'dark') $theme = 'light';
        $code = Captcha::generate(6);
        $svg = Captcha::svg($code, $theme);
        return new Response(200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ], $svg);
    }

    private function prefix(): string
    {
        return rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');
    }
}
