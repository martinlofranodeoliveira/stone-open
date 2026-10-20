<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\View\View;
use App\Support\Env;

final class AdminDashboardController
{
    public function index(Request $req): Response
    {
        $env = 'production';
        $counts = [
            'sites' => (int) Database::run('SELECT COUNT(*) c FROM sites')->fetch()['c'],
            'customers' => (int) Database::run('SELECT COUNT(*) c FROM customers WHERE env = ?', [$env])->fetch()['c'],
            'payments' => (int) Database::run('SELECT COUNT(*) c FROM payments WHERE status <> "deleted" AND env = ?', [$env])->fetch()['c'],
            'webhooks_pending' => (int) Database::run('SELECT COUNT(*) c FROM inbound_webhooks WHERE status = "pending"')->fetch()['c'],
        ];

        // Payments by day (last 14 days) and revenue (paid only)
        $seriesDays = Database::run(
            "SELECT DATE(created_at) d, COUNT(*) c FROM payments WHERE status <> 'deleted' AND env = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY d ORDER BY d",
            [$env]
        )->fetchAll();
        $seriesRevenue = Database::run(
            "SELECT DATE(updated_at) d, COALESCE(SUM(amount_cents),0) v FROM payments WHERE status='paid' AND env = ? AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY d ORDER BY d",
            [$env]
        )->fetchAll();
        $byMethod = Database::run(
            "SELECT payment_method_type m, COUNT(*) c FROM payments WHERE status <> 'deleted' AND env = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY m",
            [$env]
        )->fetchAll();
        $byStatus = Database::run(
            "SELECT status s, COUNT(*) c FROM payments WHERE status <> 'deleted' AND env = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY s",
            [$env]
        )->fetchAll();

        // Subscriptions overview
        $subsByStatus = Database::run(
            "SELECT status s, COUNT(*) c FROM subscriptions WHERE status <> 'deleted' AND env = ? GROUP BY s",
            [$env]
        )->fetchAll();
        $subsActive = (int) (Database::run("SELECT COUNT(*) c FROM subscriptions WHERE status='active' AND env = ?", [$env])->fetch()['c'] ?? 0);
        $subsPastDue = (int) (Database::run("SELECT COUNT(*) c FROM subscriptions WHERE status='past_due' AND env = ?", [$env])->fetch()['c'] ?? 0);
        $subsNewByDay = Database::run(
            "SELECT DATE(created_at) d, COUNT(*) c FROM subscriptions WHERE status <> 'deleted' AND env = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY d ORDER BY d",
            [$env]
        )->fetchAll();
        $mrrCents = (int) (Database::run("SELECT COALESCE(SUM(amount_cents),0) v FROM subscriptions WHERE status='active' AND env = ?", [$env])->fetch()['v'] ?? 0);

        // Approval (30d)
        $paid30 = (int) (Database::run("SELECT COUNT(*) c FROM payments WHERE status='paid' AND status <> 'deleted' AND env = ? AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$env])->fetch()['c'] ?? 0);
        $total30 = (int) (Database::run("SELECT COUNT(*) c FROM payments WHERE status <> 'deleted' AND env = ? AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$env])->fetch()['c'] ?? 0);

        // Refunds / Chargebacks (30d) summary and (14d) series
        $ref30 = Database::run("SELECT COUNT(*) c, COALESCE(SUM(amount_cents),0) v FROM payments WHERE status='refunded' AND env = ? AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$env])->fetch();
        $cbk30 = Database::run("SELECT COUNT(*) c, COALESCE(SUM(amount_cents),0) v FROM payments WHERE status='chargeback' AND env = ? AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$env])->fetch();
        $refSeries = Database::run("SELECT DATE(updated_at) d, COALESCE(SUM(amount_cents),0) v FROM payments WHERE status='refunded' AND env = ? AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY d ORDER BY d", [$env])->fetchAll();
        $cbkSeries = Database::run("SELECT DATE(updated_at) d, COALESCE(SUM(amount_cents),0) v FROM payments WHERE status='chargeback' AND env = ? AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY d ORDER BY d", [$env])->fetchAll();

        // Incidents: top statuses (30d, excluding paid)
        $incidentsTop = Database::run("SELECT status s, COUNT(*) c FROM payments WHERE updated_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND env = ? AND status <> 'paid' AND status <> 'deleted' GROUP BY s ORDER BY c DESC LIMIT 5", [$env])->fetchAll();

        $healthHours = max(1, min(168, (int)Env::get('ADMIN_HEALTH_WINDOW_HOURS', '24')));
        $failDays = max(1, min(90, (int)Env::get('ADMIN_FAIL_BY_TYPE_DAYS', '7')));

        // Health by site (webhooks + rate limit)
        $healthBySite = Database::run(
            "SELECT s.id, s.name, s.rate_limit_per_minute,
                (SELECT `count` FROM site_rate_counters src WHERE src.site_id = s.id AND src.window_start_minute = DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:00') LIMIT 1) AS rate_used_minute,
                (SELECT COUNT(*) FROM inbound_webhooks w WHERE w.site_id = s.id AND w.status = 'pending') AS webhooks_pending,
                (SELECT COUNT(*) FROM inbound_webhooks w WHERE w.site_id = s.id AND w.status = 'processing') AS webhooks_processing,
                (SELECT COUNT(*) FROM inbound_webhooks w WHERE w.site_id = s.id AND w.status = 'failed' AND w.received_at >= DATE_SUB(NOW(), INTERVAL {$healthHours} HOUR)) AS webhooks_failed_24h,
                (SELECT AVG(TIMESTAMPDIFF(SECOND, w.received_at, w.processed_at)) FROM inbound_webhooks w WHERE w.site_id = s.id AND w.processed_at IS NOT NULL AND w.received_at >= DATE_SUB(NOW(), INTERVAL {$healthHours} HOUR)) AS avg_processing_sec
             FROM sites s
             ORDER BY s.name"
        )->fetchAll();

        // Failure rate by event type (last 7 days)
        $failByType = Database::run(
            "SELECT event_type, COUNT(*) total, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) failed
             FROM inbound_webhooks
             WHERE received_at >= DATE_SUB(NOW(), INTERVAL {$failDays} DAY)
             GROUP BY event_type
             ORDER BY failed DESC, total DESC
             LIMIT 10"
        )->fetchAll();

        $html = View::render('admin/dashboard', [
            'prefix' => $this->prefix(),
            'user' => $_SESSION['admin_user_name'] ?? 'admin',
            'counts' => $counts,
            'seriesDays' => $seriesDays,
            'seriesRevenue' => $seriesRevenue,
            'byMethod' => $byMethod,
            'byStatus' => $byStatus,
            // Extra: pagamentos aprovados x total por dia (últimos 14)
            'paidByDay' => Database::run(
                "SELECT DATE(updated_at) d, COUNT(*) c FROM payments WHERE status='paid' AND env = ? AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY d ORDER BY d",
                [$env]
            )->fetchAll(),
            'totalByDay' => Database::run(
                "SELECT DATE(updated_at) d, COUNT(*) c FROM payments WHERE env = ? AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY d ORDER BY d",
                [$env]
            )->fetchAll(),
            // Top sites por receita (30 dias)
            'topSites' => Database::run(
                "SELECT COALESCE(s.name, CONCAT('Site #', p.site_id)) n, COALESCE(SUM(p.amount_cents),0) v FROM payments p LEFT JOIN sites s ON s.id=p.site_id WHERE p.status='paid' AND p.env = ? AND p.updated_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY p.site_id, s.name ORDER BY v DESC LIMIT 5",
                [$env]
            )->fetchAll(),
            // Approval global (30d)
            'paid30' => $paid30,
            'total30' => $total30,
            // Refunds/Chargebacks summaries and sparklines
            'refunds30' => $ref30,
            'chargebacks30' => $cbk30,
            'refundsSeries' => $refSeries,
            'chargebacksSeries' => $cbkSeries,
            // Incident statuses top (30d)
            'incidentsTop' => $incidentsTop,
            'subsByStatus' => $subsByStatus,
            'subsActive' => $subsActive,
            'subsPastDue' => $subsPastDue,
            'subsNewByDay' => $subsNewByDay,
            'mrrCents' => $mrrCents,
            'healthBySite' => $healthBySite,
            'failByType' => $failByType,
            'healthWindowHours' => $healthHours,
            'failByTypeDays' => $failDays,
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    private function prefix(): string
    {
        return rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');
    }
}
