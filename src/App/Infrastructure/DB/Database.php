<?php
declare(strict_types=1);

namespace App\Infrastructure\DB;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;
    private static array $config = [];

    public static function init(array $config): void
    {
        self::$config = $config;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;
        $host = self::$config['host'] ?? '127.0.0.1';
        $port = (int)(self::$config['port'] ?? 3306);
        $db   = self::$config['dbname'] ?? 'stone';
        $charset = self::$config['charset'] ?? 'utf8mb4';
        $user = self::$config['user'] ?? 'root';
        $pass = self::$config['pass'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            self::$pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'DB connection failed', 'message' => $e->getMessage()]);
            exit;
        }
        return self::$pdo;
    }

    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}

