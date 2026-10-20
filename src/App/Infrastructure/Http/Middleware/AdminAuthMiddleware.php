<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\{Request, Response};

final class AdminAuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!isset($_SESSION['admin_user_id'])) {
            return new Response(302, ['Location' => $this->adminPrefix() . '/login'], '');
        }
        return $next($request);
    }

    private function adminPrefix(): string
    {
        $p = \App\Support\Env::get('ADMIN_PREFIX', '/_admin');
        return rtrim($p, '/');
    }
}

