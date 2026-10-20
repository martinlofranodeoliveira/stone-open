#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Infrastructure\DB\Database;

[$name, $domain, $callback] = [
    $argv[1] ?? 'Example Site',
    $argv[2] ?? 'example.invalid',
    $argv[3] ?? 'http://example.invalid/webhooks/stone',
];

$clientId = bin2hex(random_bytes(16));
$secretPlain = bin2hex(random_bytes(24));
$secretHash = password_hash($secretPlain, PASSWORD_ARGON2ID);
$forwardSecret = bin2hex(random_bytes(24));
$apiToken = bin2hex(random_bytes(32));

try {
    // Newer schema including hint + plaintext
    Database::run('INSERT INTO sites (name, domain, callback_url, client_secret_hint, client_secret_plain, webhook_forward_secret, api_token, client_id, client_secret_hash, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "active")', [
        $name, $domain, $callback, substr($secretPlain, -6), $secretPlain, $forwardSecret, $apiToken, $clientId, $secretHash,
    ]);
} catch (\Throwable $e) {
    try {
        // Fallback without client_secret_plain
        Database::run('INSERT INTO sites (name, domain, callback_url, client_secret_hint, webhook_forward_secret, api_token, client_id, client_secret_hash, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "active")', [
            $name, $domain, $callback, substr($secretPlain, -6), $forwardSecret, $apiToken, $clientId, $secretHash,
        ]);
    } catch (\Throwable $e2) {
        // Older schema without hint
        Database::run('INSERT INTO sites (name, domain, callback_url, webhook_forward_secret, api_token, client_id, client_secret_hash, status) VALUES (?, ?, ?, ?, ?, ?, ?, "active")', [
            $name, $domain, $callback, $forwardSecret, $apiToken, $clientId, $secretHash,
        ]);
    }
}

$id = (int) Database::pdo()->lastInsertId();
echo "Created site #{$id}\n";
echo "CLIENT_ID={$clientId}\n";
echo "CLIENT_SECRET={$secretPlain}\n";
echo "FORWARD_SECRET={$forwardSecret}\n";
echo "API_TOKEN={$apiToken}\n";
