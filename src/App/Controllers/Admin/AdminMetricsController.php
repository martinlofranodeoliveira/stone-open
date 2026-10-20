<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Support\Env;

final class AdminMetricsController
{
    public function index(Request $req): Response
    {
        $lines = [];
        // Counters from file-based metrics
        $c = \App\Support\Metrics::readAll();
        $map = [
            'webhooks_received_total' => 'Total webhooks received',
            'webhooks_processed_total' => 'Total webhooks processed',
            'webhooks_failed_total' => 'Total webhooks failed',
            'forward_success_total' => 'Total forwards success',
            'forward_failure_total' => 'Total forwards failure',
            'forward_latency_ms_sum' => 'Sum of forward latencies (ms)',
            'forward_latency_ms_count' => 'Count of forward latency samples',
        ];
        foreach ($map as $k => $help) {
            $lines[] = '# HELP stone_' . $k . ' ' . $help;
            $lines[] = '# TYPE stone_' . $k . ' counter';
            $lines[] = 'stone_' . $k . ' ' . (int)($c[$k] ?? 0);
        }

        // Gauges from DB (indexed by status)
        $pending = (int) Database::run('SELECT COUNT(*) c FROM inbound_webhooks WHERE status = "pending"')->fetch()['c'];
        $processing = (int) Database::run('SELECT COUNT(*) c FROM inbound_webhooks WHERE status = "processing"')->fetch()['c'];
        $failed = (int) Database::run('SELECT COUNT(*) c FROM inbound_webhooks WHERE status = "failed"')->fetch()['c'];
        $lines[] = '# HELP stone_webhooks_pending Webhooks pending in queue';
        $lines[] = '# TYPE stone_webhooks_pending gauge';
        $lines[] = 'stone_webhooks_pending ' . $pending;
        $lines[] = '# HELP stone_webhooks_processing Webhooks processing';
        $lines[] = '# TYPE stone_webhooks_processing gauge';
        $lines[] = 'stone_webhooks_processing ' . $processing;
        $lines[] = '# HELP stone_webhooks_failed_gauge Webhooks failed (current)';
        $lines[] = '# TYPE stone_webhooks_failed_gauge gauge';
        $lines[] = 'stone_webhooks_failed_gauge ' . $failed;

        // Per-site backlog gauges + avg processing (24h)
        $healthHours = max(1, min(168, (int)Env::get('ADMIN_HEALTH_WINDOW_HOURS', '24')));
        $failDays = max(1, min(90, (int)Env::get('ADMIN_FAIL_BY_TYPE_DAYS', '7')));

        $siteRows = Database::run(
            "SELECT s.id, s.name,
                SUM(CASE WHEN w.status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN w.status = 'processing' THEN 1 ELSE 0 END) AS processing,
                SUM(CASE WHEN w.status = 'failed' THEN 1 ELSE 0 END) AS failed,
                AVG(CASE WHEN w.processed_at IS NOT NULL AND w.received_at >= DATE_SUB(NOW(), INTERVAL {$healthHours} HOUR)
                    THEN TIMESTAMPDIFF(SECOND, w.received_at, w.processed_at) END) AS avg_processing_sec
             FROM sites s
             LEFT JOIN inbound_webhooks w ON w.site_id = s.id
             GROUP BY s.id, s.name
             ORDER BY s.id"
        )->fetchAll();
        foreach ($siteRows as $r) {
            $siteId = (int)($r['id'] ?? 0);
            $siteName = $this->escapeLabel((string)($r['name'] ?? ''));
            $lines[] = 'stone_webhooks_pending_by_site{site_id="' . $siteId . '",site="' . $siteName . '"} ' . (int)($r['pending'] ?? 0);
            $lines[] = 'stone_webhooks_processing_by_site{site_id="' . $siteId . '",site="' . $siteName . '"} ' . (int)($r['processing'] ?? 0);
            $lines[] = 'stone_webhooks_failed_by_site{site_id="' . $siteId . '",site="' . $siteName . '"} ' . (int)($r['failed'] ?? 0);
            $avg = $r['avg_processing_sec'];
            $lines[] = 'stone_webhooks_avg_processing_seconds_by_site{site_id="' . $siteId . '",site="' . $siteName . '"} ' . ($avg !== null ? (float)$avg : 0);
        }

        // Failure rate by event type (7d)
        $failByType = Database::run(
            "SELECT event_type, COUNT(*) total, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) failed
             FROM inbound_webhooks
             WHERE received_at >= DATE_SUB(NOW(), INTERVAL {$failDays} DAY)
             GROUP BY event_type
             ORDER BY failed DESC, total DESC"
        )->fetchAll();
        foreach ($failByType as $row) {
            $etype = $this->escapeLabel((string)($row['event_type'] ?? 'unknown'));
            $total = (int)($row['total'] ?? 0);
            $failedT = (int)($row['failed'] ?? 0);
            $rate = $total > 0 ? ($failedT / $total) * 100 : 0;
            $lines[] = 'stone_webhooks_failed_by_type{event_type="' . $etype . '"} ' . $failedT;
            $lines[] = 'stone_webhooks_fail_rate_by_type{event_type="' . $etype . '"} ' . round($rate, 4);
        }

        $body = implode("\n", $lines) . "\n";
        return new Response(200, ['Content-Type' => 'text/plain; charset=utf-8'], $body);
    }

    private function escapeLabel(string $v): string
    {
        $v = str_replace("\\", "\\\\", $v);
        return str_replace("\"", "\\\"", $v);
    }
}
