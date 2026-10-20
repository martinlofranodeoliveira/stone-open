<?php
declare(strict_types=1);
namespace App\Infrastructure\DB {
    final class OfflineRow {
        public function __construct(private mixed $row) {}
        public function fetch(): mixed { return $this->row; }
    }
    final class Database {
        public static ?array $basic = null;
        public static ?array $account = null;
        public static bool $lookupUnavailable = false;
        public static int $writes = 0;
        public static function run(string $sql, array $params = []): OfflineRow {
            if (str_starts_with($sql, 'SELECT stone_webhook_basic_user')) {
                if (self::$lookupUnavailable) throw new \RuntimeException('Offline lookup unavailable');
                return new OfflineRow(self::$basic);
            }
            if (str_starts_with($sql, 'SELECT * FROM pagarme_accounts')) {
                if (self::$lookupUnavailable) throw new \RuntimeException('Offline lookup unavailable');
                return new OfflineRow(($params[0] ?? null) === 'account-example' ? self::$account : null);
            }
            if (str_starts_with($sql, 'INSERT INTO inbound_webhooks')) {
                self::$writes++;
                return new OfflineRow(null);
            }
            if (str_starts_with($sql, 'SELECT id FROM inbound_webhooks')) return new OfflineRow(null);
            throw new \LogicException('Unexpected offline query');
        }
    }
}
namespace {
    $root = dirname(__DIR__);
    foreach (['Support/Env.php','Support/Security/WebhookSignature.php','Infrastructure/Http/Request.php',
        'Infrastructure/Http/Response.php','Infrastructure/Stone/StoneAccount.php','Controllers/WebhooksController.php'] as $file) {
        require $root . '/src/App/' . $file;
    }
    use App\Infrastructure\DB\Database;
    use App\Infrastructure\Http\Request;
    use App\Controllers\WebhooksController;
    $password = 'example-test-password-only';
    $configuredBasic = ['stone_webhook_basic_user' => 'example-user',
        'stone_webhook_basic_pass_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost'=>4])];
    $configuredAccount = ['active_env'=>'sandbox','webhook_secret_sandbox'=>'offline-example-secret'];
    $payload = json_encode(['id'=>'example-event','type'=>'example.event','account_id'=>'account-example']);
    $signature = hash_hmac('sha256', $payload, 'offline-example-secret');
    $basic = 'Basic ' . base64_encode('example-user:' . $password);
    $cases = [
        ['unconfigured', null, null, [], 503, false],
        ['unconfigured supplied signature', null, null, ['x-hub-signature'=>$signature], 503, false],
        ['lookup unavailable', null, null, [], 503, false, true],
        ['valid basic', $configuredBasic, null, ['authorization'=>$basic], 200, true],
        ['wrong basic password', $configuredBasic, null, ['authorization'=>'Basic '.base64_encode('example-user:wrong-example')], 401, false],
        ['missing basic', $configuredBasic, null, [], 401, false],
        ['partial basic user', ['stone_webhook_basic_user'=>'example-user'], null, [], 503, false],
        ['partial basic hash', ['stone_webhook_basic_pass_hash'=>$configuredBasic['stone_webhook_basic_pass_hash']], null, [], 503, false],
        ['valid hmac', null, $configuredAccount, ['x-hub-signature'=>$signature], 200, true],
        ['valid prefixed hmac', null, $configuredAccount, ['x-hub-signature'=>'sha256='.$signature], 200, true],
        ['missing hmac', null, $configuredAccount, [], 400, false],
        ['wrong hmac', null, $configuredAccount, ['x-hub-signature'=>str_repeat('0',64)], 400, false],
        ['wrong hmac prefix', null, $configuredAccount, ['x-hub-signature'=>'sha1='.$signature], 400, false],
        ['basic does not bypass configured hmac', $configuredBasic, $configuredAccount, ['authorization'=>$basic], 400, false],
    ];
    $failed = 0; $checks = 0;
    foreach ($cases as $case) {
        [$label, Database::$basic, Database::$account, $headers, $status, $writes] = $case;
        Database::$lookupUnavailable = $case[6] ?? false;
        Database::$writes = 0;
        $request = new Request();
        $request->method='POST'; $request->path='/webhooks/stone'; $request->query=[];
        $request->headers=$headers; $request->rawBody=$payload;
        $response = (new WebhooksController())->handle($request);
        foreach (['status'=>$response->status === $status, 'persistence'=>((Database::$writes > 0) === $writes)] as $kind=>$ok) {
            $checks++;
            if (!$ok) { $failed++; echo 'FAIL '.$label.' '.$kind."\n"; }
        }
    }
    echo $checks.' webhook authentication checks; '.$failed." failures\n";
    exit($failed ? 1 : 0);
}
