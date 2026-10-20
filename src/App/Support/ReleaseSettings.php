<?php
declare(strict_types=1);
namespace App\Support;

/** Owned release configuration; no operational defaults or provider provisioning. */
final class ReleaseSettings
{
    public static function database(array $values): array
    {
        foreach (['DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
            $value = $values[$key] ?? null;
            if (!is_string($value) || trim($value) === '' ||
                preg_match('/^(?:replace[_ -]|your[_ -]|<)/i', $value)) {
                throw new \RuntimeException('Configure your own database settings before startup.');
            }
        }
        $rawPort = $values['DB_PORT'] ?? '3306';
        if (!is_string($rawPort) && !is_int($rawPort)) {
            throw new \RuntimeException('Invalid database port setting.');
        }
        $port = filter_var($rawPort, FILTER_VALIDATE_INT);
        if ($port === false || $port < 1 || $port > 65535) {
            throw new \RuntimeException('Invalid database port setting.');
        }
        return [
            'host' => (string)($values['DB_HOST'] ?? '127.0.0.1'),
            'port' => $port,
            'dbname' => $values['DB_NAME'],
            'user' => $values['DB_USER'],
            'pass' => $values['DB_PASS'],
            'charset' => 'utf8mb4',
        ];
    }
}
