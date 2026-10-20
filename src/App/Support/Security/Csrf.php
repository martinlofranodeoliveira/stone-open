<?php
declare(strict_types=1);

namespace App\Support\Security;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }
        $token = $_SESSION['csrf_token'];
        // Also expose a non-HttpOnly cookie for double-submit fallback (XSRF-TOKEN)
        // This helps in environments where session persistence falha entre GET e POST.
        if (!isset($_COOKIE['XSRF-TOKEN']) || $_COOKIE['XSRF-TOKEN'] !== $token) {
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            // Best-effort cookie; ignore warnings if headers already sent
            @setcookie('XSRF-TOKEN', $token, [
                'expires' => 0,
                'path' => '/',
                'secure' => $secure,
                'httponly' => false, // readable by JS if necessário
                'samesite' => 'Lax',
            ]);
        }
        return $token;
    }

    public static function check(?string $token): bool
    {
        // Fallbacks: header X-CSRF-Token / X-XSRF-Token, POST/GET param "csrf"
        if (!is_string($token) || $token === '') {
            $hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_SERVER['HTTP_X_XSRF_TOKEN'] ?? null);
            if (is_string($hdr) && $hdr !== '') $token = $hdr;
            if ((!is_string($token) || $token === '') && isset($_POST['csrf'])) $token = (string)$_POST['csrf'];
            if ((!is_string($token) || $token === '') && isset($_GET['csrf'])) $token = (string)$_GET['csrf'];
        }
        if (isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token)) {
            return true;
        }
        // Double-submit cookie fallback: aceitar se cookie XSRF-TOKEN for igual ao token enviado
        $cookie = $_COOKIE['XSRF-TOKEN'] ?? null;
        if (is_string($cookie) && is_string($token) && $cookie !== '' && hash_equals($cookie, $token)) {
            return true;
        }
        return false;
    }
}
