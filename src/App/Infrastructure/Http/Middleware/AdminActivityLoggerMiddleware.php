<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;

final class AdminActivityLoggerMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $res = $next($request);
        try {
            $uid = $_SESSION['admin_user_id'] ?? null;
            $method = $_SERVER['REQUEST_METHOD'] ?? '';
            $path = $request->path ?? '';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $requestId = (string)($request->attributes['request_id'] ?? '');
            try {
                Database::run('INSERT INTO admin_access_logs (admin_user_id, method, path, ip, user_agent, request_id) VALUES (?, ?, ?, ?, ?, ?)', [
                    $uid, $method, $path, $ip, $ua, $requestId
                ]);
            } catch (\Throwable $e) {
                Database::run('INSERT INTO admin_access_logs (admin_user_id, method, path, ip, user_agent) VALUES (?, ?, ?, ?, ?)', [
                    $uid, $method, $path, $ip, $ua
                ]);
            }
        } catch (\Throwable $e) {
            // ignore logging errors
        }
        return $res;
    }
}
