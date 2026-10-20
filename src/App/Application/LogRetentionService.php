<?php
declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\DB\Database;
use App\Support\Env;

final class LogRetentionService
{
    public function cleanup(): array
    {
        $days = max(1, (int)Env::get('API_LOG_RETENTION_DAYS', '30'));
        $dbDeleted = 0;
        $filesDeleted = 0;

        try {
            $stmt = Database::run('DELETE FROM api_request_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days]);
            if (method_exists($stmt, 'rowCount')) {
                $dbDeleted = (int)$stmt->rowCount();
            }
        } catch (\Throwable $e) {
            // ignore DB cleanup errors
        }

        $filesDeleted = $this->cleanupApiFiles($days);

        return [
            'days' => $days,
            'db_deleted' => $dbDeleted,
            'files_deleted' => $filesDeleted,
        ];
    }

    private function cleanupApiFiles(int $days): int
    {
        $root = dirname(__DIR__, 3); // /src/App/Application -> repo root
        $dir = $root . '/storage/logs/api';
        if (!is_dir($dir)) return 0;

        $cutoff = time() - ($days * 86400);
        $files = glob($dir . '/api-requests-*') ?: [];
        $deleted = 0;
        foreach ($files as $file) {
            if (!is_file($file)) continue;
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $cutoff) {
                if (@unlink($file)) $deleted++;
            }
        }
        return $deleted;
    }
}
