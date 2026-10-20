<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Support\Env;

final class ApiRequestLoggerMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $t0 = microtime(true);
        $res = $next($request);
        $dur = (int)round((microtime(true) - $t0) * 1000);

        try {
            $requestId = (string)($request->attributes['request_id'] ?? '');
            $site = $request->attributes['site'] ?? null;
            $siteId = $site['id'] ?? null;
            $method = $_SERVER['REQUEST_METHOD'] ?? '';
            $path = $request->path ?? '';
            $qs = '';
            if (is_array($request->query ?? null) && !empty($request->query)) {
                $qs = http_build_query($request->query);
            }
            $pathFull = $path . ($qs !== '' ? ('?' . $qs) : '');
            $status = (int)($res->status ?? 0);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $authz = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
            $authType = '';
            if (stripos($authz, 'bearer ') === 0) $authType = 'bearer';
            elseif (isset($_SERVER['HTTP_X_CLIENT_ID']) || isset($_SERVER['HTTP_X_CLIENT_SECRET'])) $authType = 'client_headers';
            $clientId = (string)($_SERVER['HTTP_X_CLIENT_ID'] ?? ($site['client_id'] ?? ''));

            $body = $request->rawBody ?? '';
            if ($body === '' && is_array($request->parsedBody ?? null) && !empty($request->parsedBody)) {
                $body = json_encode($request->parsedBody, JSON_UNESCAPED_UNICODE);
            } elseif ($body === '' && !empty($_POST)) {
                $body = json_encode($_POST, JSON_UNESCAPED_UNICODE);
            }
            $respBody = $res->body ?? '';
            // Always write to file (obrigatório)
            $this->writeFileLog([
                'request_id' => $requestId,
                'site_id' => $siteId,
                'method' => $method,
                'path' => $pathFull,
                'status' => $status,
                'duration_ms' => $dur,
                'ip' => $ip,
                'user_agent' => $ua,
                'auth_type' => $authType,
                'client_id' => $clientId,
                'request_body' => $body,
                'response_body' => $respBody,
                'time' => date('Y-m-d H:i:s'),
            ]);

            // Optional DB logging (via env LOG_API_DB=1)
            if ((string)Env::get('LOG_API_DB', '1') === '1') {
                $this->ensureTable();
                $bodyDb = $body; if (strlen($bodyDb) > 8192) $bodyDb = substr($bodyDb, 0, 8192) . '...';
                $respDb = $respBody; if (strlen($respDb) > 8192) $respDb = substr($respDb, 0, 8192) . '...';
                try {
                    Database::run('INSERT INTO api_request_logs (request_id, site_id, method, path, status, duration_ms, ip, user_agent, auth_type, client_id, request_body, response_body, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())', [
                        $requestId, $siteId, $method, $pathFull, $status, $dur, $ip, $ua, $authType, $clientId, $bodyDb, $respDb
                    ]);
                } catch (\Throwable $e) {
                    Database::run('INSERT INTO api_request_logs (site_id, method, path, status, duration_ms, ip, user_agent, auth_type, client_id, request_body, response_body, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())', [
                        $siteId, $method, $pathFull, $status, $dur, $ip, $ua, $authType, $clientId, $bodyDb, $respDb
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // ignore logging errors
        }
        return $res;
    }

    private function ensureTable(): void
    {
        try {
            $row = Database::run('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "api_request_logs"')->fetch();
            if (!$row) {
                Database::run('CREATE TABLE api_request_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    request_id VARCHAR(64) NULL,
                    site_id INT NULL,
                    method VARCHAR(10) NOT NULL,
                    path VARCHAR(255) NOT NULL,
                    status INT NOT NULL,
                    duration_ms INT NOT NULL,
                    ip VARCHAR(64) NULL,
                    user_agent VARCHAR(255) NULL,
                    auth_type VARCHAR(32) NULL,
                    client_id VARCHAR(80) NULL,
                    request_body MEDIUMTEXT NULL,
                    response_body MEDIUMTEXT NULL,
                    created_at DATETIME NOT NULL,
                    INDEX idx_request_id (request_id),
                    INDEX idx_site_created (site_id, created_at),
                    INDEX idx_path_created (path, created_at),
                    INDEX idx_status_created (status, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            }
            $row = Database::run('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "api_request_logs" AND COLUMN_NAME = "request_id"')->fetch();
            if (!$row) {
                Database::run('ALTER TABLE api_request_logs ADD COLUMN request_id VARCHAR(64) NULL AFTER id');
                Database::run('CREATE INDEX idx_request_id ON api_request_logs (request_id)');
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function writeFileLog(array $data): void
    {
        // Caminho no root do projeto: storage/logs/api
        $root = dirname(__DIR__, 5); // .../src/App/Infrastructure/Http/Middleware -> repo root
        $dir = $root . '/storage/logs/api';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
            if (!is_dir($dir)) @mkdir($dir, 0777, true); // tentativa permissiva
        }
        $file = $dir . '/api-requests-' . date('Y-m-d') . '.log';

        $prettyReq = $this->prettyJsonOrRaw($data['request_body'] ?? '');
        $prettyRes = $this->prettyJsonOrRaw($data['response_body'] ?? '');
        // Truncar para arquivo (mais curto para legibilidade)
        $prettyReq = $this->truncate($prettyReq, 4096);
        $prettyRes = $this->truncate($prettyRes, 4096);

        $ua = str_replace(["\r","\n"],' ', (string)($data['user_agent'] ?? ''));
        $ct = (string)($_SERVER['CONTENT_TYPE'] ?? '');
        $entry = [
            'time' => $data['time'] ?? date('Y-m-d H:i:s'),
            'request_id' => (string)($data['request_id'] ?? ''),
            'method' => $data['method'] ?? '',
            'path' => $data['path'] ?? '',
            'status' => (int)($data['status'] ?? 0),
            'duration_ms' => (int)($data['duration_ms'] ?? 0),
            'site_id' => $data['site_id'] ?? null,
            'auth_type' => $data['auth_type'] ?? '',
            'client_id' => $data['client_id'] ?? '',
            'ip' => $data['ip'] ?? '',
            'content_type' => $ct,
            'user_agent' => $ua,
            'request_body' => $prettyReq,
            'response_body' => $prettyRes,
        ];
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        $ok = @file_put_contents($file, $line, FILE_APPEND);
        if ($ok === false) {
            // Fallback para /tmp quando storage não é gravável
            $tmp = rtrim(sys_get_temp_dir(), '/').'/stone-api-requests-' . date('Y-m-d') . '.log';
            @file_put_contents($tmp, $line, FILE_APPEND);
        }
    }

    private function prettyJsonOrRaw(string $text): string
    {
        $text = (string)$text;
        $j = json_decode($text, true);
        if (is_array($j)) {
            return json_encode($j, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        return $text;
    }

    private function truncate(string $text, int $limit): string
    {
        if (strlen($text) > $limit) return substr($text, 0, $limit) . "\n... (truncated)";
        return $text;
    }
}
