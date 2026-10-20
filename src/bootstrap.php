<?php
declare(strict_types=1);

#ini_set('display_errors',1);
#ini_set('display_startup_erros',1);
#error_reporting(E_ALL);

// Simple PSR-4 autoloader for the App\ namespace
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/App/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Timezone & basic settings
date_default_timezone_set('UTC');

use App\Support\Env;
use App\Support\ReleaseSettings;
use App\Infrastructure\DB\Database;

// Load environment variables from project root .env if present
Env::load(__DIR__ . '/../.env');

// Ensure runtime directories exist
@mkdir(__DIR__ . '/../storage/logs', 0775, true);
@mkdir(__DIR__ . '/../storage/ratelimit', 0775, true);

define("VERSAO_SISTEMA", Env::get('VERSAO_SISTEMA', '1.0'));

if (Env::get('STONE_ENV', 'homolog') === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING);
} else {
    ini_set('display_errors', '1');
    #error_reporting(E_ALL);
}

define("APP_URL_STONE_HUB", Env::get('APP_URL', 'http://127.0.0.1:8080'));

// Initialize DB (lazy in class)
Database::init(ReleaseSettings::database([
    'DB_HOST' => Env::get('DB_HOST', '127.0.0.1'),
    'DB_PORT' => Env::get('DB_PORT', '3306'),
    'DB_NAME' => Env::get('DB_NAME', ''),
    'DB_USER' => Env::get('DB_USER', ''),
    'DB_PASS' => Env::get('DB_PASS', ''),
]));

