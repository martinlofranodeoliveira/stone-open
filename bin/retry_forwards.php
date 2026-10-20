#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Application\WebhookService;

echo "Starting forward retry worker...\n";
$svc = new WebhookService();

while (true) {
    try {
        $processed = $svc->retryDueForwards(100, 5);
        if ($processed > 0) {
            echo date('c') . " retried forwards: {$processed}\n";
        }
    } catch (Throwable $e) {
        fwrite(STDERR, 'Retry error: ' . $e->getMessage() . "\n");
    }
    // sleep a bit
    sleep(5);
}

