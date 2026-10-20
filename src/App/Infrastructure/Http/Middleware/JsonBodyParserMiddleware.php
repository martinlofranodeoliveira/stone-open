<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\{Request, Response};

final class JsonBodyParserMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $contentType = $request->header('Content-Type', '');
        if (str_contains(strtolower($contentType), 'application/json')) {
            $request->json();
        }
        return $next($request);
    }
}

