<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\DB\Database;
use App\Infrastructure\Http\{Request, Response};

final class AuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        // Option A: Bearer token (API_TOKEN)
        $authz = (string)($request->header('Authorization') ?? '');
        if (stripos($authz, 'bearer ') === 0) {
            $token = trim(substr($authz, 7));
            if ($token !== '') {
                $site = Database::run('SELECT * FROM sites WHERE api_token = ? AND status = "active" LIMIT 1', [$token])->fetch();
                if ($site) {
                    $request->attributes['site'] = $site;
                    return $next($request);
                }
            }
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        // Option B: Client ID + Secret headers
        $clientId = $request->header('X-Client-Id') ?? '';
        $clientSecret = $request->header('X-Client-Secret') ?? '';
        if ($clientId === '' || $clientSecret === '') {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        $site = Database::run('SELECT * FROM sites WHERE client_id = ? AND status = "active" LIMIT 1', [$clientId])->fetch();
        if (!$site || !password_verify($clientSecret, $site['client_secret_hash'])) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        $request->attributes['site'] = $site;
        return $next($request);
    }
}
