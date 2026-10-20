<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\{Request, Response};

final class RateLimitMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $site = $request->attributes['site'] ?? null;
        if (!$site) return Response::json(['error' => 'Unauthorized'], 401);

        $limit = (int)($site['rate_limit_per_minute'] ?? 60);
        $key = 'site_' . $site['id'] . '_' . date('YmdHi'); // per minute bucket
        // Usar diretório raiz do projeto (não dentro de src)
        $root = dirname(__DIR__, 5);
        $dir = $root . '/storage/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
            if (!is_dir($dir)) @mkdir($dir, 0777, true);
        }
        $path = $dir . '/' . $key . '.cnt';
        if (!is_dir($dir)) {
            // Fallback para /tmp
            $dir = rtrim(sys_get_temp_dir(), '/');
            $path = $dir . '/' . $key . '.cnt';
        }

        $fh = fopen($path, 'c+');
        if ($fh) {
            if (flock($fh, LOCK_EX)) {
                $size = filesize($path) ?: 0;
                $count = 0;
                if ($size > 0) { fseek($fh, 0); $count = (int)trim((string)fread($fh, $size)); }
                if ($count >= $limit) {
                    flock($fh, LOCK_UN); fclose($fh);
                    return Response::json(['error' => 'Too Many Requests'], 429);
                }
                $count++;
                ftruncate($fh, 0); fseek($fh, 0); fwrite($fh, (string)$count); fflush($fh);
                flock($fh, LOCK_UN);
            }
            fclose($fh);
        }
        return $next($request);
    }
}
