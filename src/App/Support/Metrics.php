<?php
declare(strict_types=1);

namespace App\Support;

final class Metrics
{
    private static function enabled(): bool
    {
        return strtolower((string)Env::get('METRICS_ENABLED', '0')) === '1';
    }

    public static function inc(string $key, int $delta = 1): void
    {
        if (!self::enabled()) return;
        $dir = __DIR__ . '/../../storage/metrics';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $path = $dir . '/' . preg_replace('/[^a-zA-Z0-9_:]/', '_', $key) . '.cnt';
        $fh = fopen($path, 'c+');
        if (!$fh) return;
        try {
            if (!flock($fh, LOCK_EX)) { fclose($fh); return; }
            $size = filesize($path) ?: 0;
            if ($size > 0) { fseek($fh, 0); $cur = (int)trim((string)fread($fh, $size)); } else { $cur = 0; }
            $cur += $delta;
            ftruncate($fh, 0);
            fseek($fh, 0);
            fwrite($fh, (string)$cur);
            fflush($fh);
            flock($fh, LOCK_UN);
        } finally {
            fclose($fh);
        }
    }

    public static function readAll(): array
    {
        $dir = __DIR__ . '/../../storage/metrics';
        $out = [];
        if (!is_dir($dir)) return $out;
        $files = glob($dir . '/*.cnt') ?: [];
        foreach ($files as $f) {
            $key = basename($f, '.cnt');
            $val = (int)trim((string)@file_get_contents($f));
            $out[$key] = $val;
        }
        return $out;
    }
}

