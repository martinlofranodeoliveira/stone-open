#!/usr/bin/env php
<?php
declare(strict_types=1);


use App\Infrastructure\DB\Database;

$name = trim((string)($argv[1] ?? ''));
$email = trim((string)($argv[2] ?? ''));
$pass = (string)($argv[3] ?? '');
$role = $argv[4] ?? 'admin';

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 12) {
    fwrite(STDERR, 'Usage: create_admin.php NAME EMAIL PASSWORD [ROLE]; choose your own password of 12 or more characters.'.PHP_EOL);
    exit(2);
}
require __DIR__ . '/../src/bootstrap.php';

$hash = password_hash($pass, PASSWORD_ARGON2ID);

Database::run('INSERT INTO admin_users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, "active")', [
    $name, $email, $hash, $role
]);
$id = (int) Database::pdo()->lastInsertId();
echo 'Administrator created with id '.$id.PHP_EOL;

