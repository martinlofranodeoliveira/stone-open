<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\{Request, Response};
use App\Support\Env;

final class AdminSessionMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $this->startSession();
        // Optional IP whitelist
        $whitelist = trim(Env::get('ADMIN_IP_WHITELIST', ''));
        if ($whitelist !== '') {
            $ips = array_filter(array_map('trim', explode(',', $whitelist)));
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
            if (!in_array($clientIp, $ips, true)) {
                return Response::json(['error' => 'Forbidden'], 403);
            }
        }
        // Optional Basic Auth
        $basicUser = Env::get('ADMIN_BASIC_USER', '');
        $basicPass = Env::get('ADMIN_BASIC_PASS', '');
        if ($basicUser !== '' && $basicPass !== '') {
            if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])
                || $_SERVER['PHP_AUTH_USER'] !== $basicUser || $_SERVER['PHP_AUTH_PW'] !== $basicPass) {
                header('WWW-Authenticate: Basic realm="Restricted"');
                return new Response(401, ['Content-Type' => 'text/plain'], 'Unauthorized');
            }
        }
        return $next($request);
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $name = Env::get('ADMIN_SESSION_NAME', 'STONEADMINSESSID');
        $lifetime = (int)Env::get('ADMIN_SESSION_LIFETIME', '43200');
        session_name($name);
        if ($lifetime > 0) {
            ini_set('session.gc_maxlifetime', (string)$lifetime);
            ini_set('session.cookie_lifetime', (string)$lifetime);
        }
        session_set_cookie_params([
            'lifetime' => $lifetime > 0 ? $lifetime : 0,
            'path' => '/',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        @session_start();
    }

    private function isHttps(): bool
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off') return true;
        $proto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($proto === 'https') return true;
        $ssl = strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
        if ($ssl === 'on') return true;
        $port = (string)($_SERVER['HTTP_X_FORWARDED_PORT'] ?? '');
        if ($port === '443') return true;
        return false;
    }
}
