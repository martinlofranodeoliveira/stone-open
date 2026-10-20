<?php
declare(strict_types=1);

namespace App\Infrastructure\Http;

final class Router
{
    private array $routes = [];

    public function map(string $method, string $pattern, array $handler, array $middlewares = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) continue;
            $params = $this->match($route['pattern'], $request->path);
            if ($params === null) continue;
            $request->attributes['routeParams'] = $params;
            return $this->runMiddlewares($route['middlewares'], $request, $route['handler']);
        }
        return Response::json(['error' => 'Not Found'], 404);
    }

    private function runMiddlewares(array $middlewares, Request $request, array $handler): Response
    {
        $next = function (Request $r) use ($handler): Response {
            [$class, $method] = $handler;
            $controller = new $class();
            return $controller->$method($r);
        };

        // Build pipeline backwards
        foreach (array_reverse($middlewares) as $mwClass) {
            $prevNext = $next;
            $next = function (Request $r) use ($prevNext, $mwClass): Response {
                $mw = new $mwClass();
                return $mw->handle($r, $prevNext);
            };
        }
        return $next($request);
    }

    private function match(string $pattern, string $path): ?array
    {
        // Simple pattern: /a/b/{id}
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        if (preg_match($regex, $path, $matches)) {
            $params = [];
            foreach ($matches as $k => $v) {
                if (!is_int($k)) $params[$k] = $v;
            }
            return $params;
        }
        return null;
    }
}

