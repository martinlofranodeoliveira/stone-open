#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Infrastructure\DB\Database;
use App\Application\WebhookService;

echo "Starting webhook worker...\n";

while (true) {
    $row = Database::run('SELECT id FROM inbound_webhooks WHERE status = "pending" ORDER BY id ASC LIMIT 1')->fetch();
    if ($row) {
        $id = (int)$row['id'];
        echo "Processing webhook #{$id}\n";
        try {
            (new WebhookService())->process($id);
        } catch (Throwable $e) {
            Database::run('UPDATE inbound_webhooks SET status = "failed", error = ? WHERE id = ?', [$e->getMessage(), $id]);
            try { \App\Support\Metrics::inc('webhooks_failed_total'); } catch (Throwable $ee) {}
        }
        continue; // pick next
    }
    // sleep a bit
    usleep(300000); // 300ms
}
