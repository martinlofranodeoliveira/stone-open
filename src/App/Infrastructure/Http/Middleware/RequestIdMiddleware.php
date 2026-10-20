<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\{Request, Response};

final class RequestIdMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $rid = (string)($request->header('x-request-id') ?? $request->header('x-correlation-id') ?? '');
        $rid = trim($rid);
        if ($rid === '') {
            $rid = bin2hex(random_bytes(12));
        } elseif (strlen($rid) > 128) {
            $rid = substr($rid, 0, 128);
        }

        $request->attributes['request_id'] = $rid;
        $request->attributes['correlation_id'] = $rid;

        $res = $next($request);
        $res->headers['X-Request-Id'] = $rid;
        $res->headers['X-Correlation-Id'] = $rid;
        return $res;
    }
}
