<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\{Request, Response};

final class SecurityHeadersMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $res = $next($request);
        $res->headers['X-Frame-Options'] = 'DENY';
        $res->headers['X-Content-Type-Options'] = 'nosniff';
        $res->headers['Referrer-Policy'] = 'no-referrer';
        $res->headers['Content-Security-Policy'] = "default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; script-src 'self' 'unsafe-inline'; img-src 'self' data: https://api.pagar.me; font-src 'self' https://fonts.gstatic.com; connect-src 'self'";
        return $res;
    }
}
