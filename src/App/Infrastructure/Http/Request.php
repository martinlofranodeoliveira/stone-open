<?php
declare(strict_types=1);

namespace App\Infrastructure\Http;

final class Request
{
    public string $method;
    public string $path;
    public array $query;
    public array $headers;
    public string $rawBody;
    public mixed $parsedBody = null;
    public array $attributes = [];

    /**
     * Allow property-style access `$req->json` used across controllers.
     * - `__isset('json')` returns true so the null-coalesce operator (??)
     *   evaluates the left-hand side and calls __get.
     * - `__get('json')` decodes and caches the JSON body (same as json()).
     */
    public function __isset(string $name): bool
    {
        if ($name === 'json') return true;
        return false;
    }

    public function __get(string $name): mixed
    {
        if ($name === 'json') {
            return $this->json();
        }
        return null;
    }

    public static function fromGlobals(): self
    {
        $r = new self();
        $r->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $r->path = $path;
        $r->query = $_GET ?? [];
        // Try to collect headers robustly across SAPIs (Apache, FPM, CGI, proxies)
        $hdrs = [];
        if (function_exists('getallheaders')) {
            $got = getallheaders();
            if (is_array($got)) $hdrs = $got;
        }
        // Fallback/merge using $_SERVER (HTTP_* plus CONTENT_TYPE/LENGTH)
        foreach ($_SERVER as $k => $v) {
            if (!is_string($k)) continue;
            $val = is_string($v) ? $v : (string)$v;
            if (str_starts_with($k, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $hdrs[$name] = $val;
            } elseif ($k === 'CONTENT_TYPE') {
                $hdrs['content-type'] = $val;
            } elseif ($k === 'CONTENT_LENGTH') {
                $hdrs['content-length'] = $val;
            }
        }
        // Normalize to lowercase keys
        $norm = [];
        foreach ($hdrs as $k => $v) {
            $key = strtolower(is_string($k) ? $k : (string)$k);
            $norm[$key] = $v;
        }
        $r->headers = $norm;
        $r->rawBody = file_get_contents('php://input') ?: '';
        return $r;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = strtolower($name);
        return $this->headers[$key] ?? $default;
    }

    public function json(): mixed
    {
        if ($this->parsedBody !== null) return $this->parsedBody;
        $data = json_decode($this->rawBody, true);
        $this->parsedBody = $data;
        return $data;
    }
}
