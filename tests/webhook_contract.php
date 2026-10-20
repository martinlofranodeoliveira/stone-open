<?php
declare(strict_types=1);
require __DIR__.'/../src/App/Support/Security/WebhookSignature.php';
use App\Support\Security\WebhookSignature;
$key = str_repeat('a', 32);
$body = '{"event":"synthetic.unit","id":"unit-fixture"}';
$mac = hash_hmac('sha256', $body, $key);
$checks = [
 'raw sha256 accepted' => WebhookSignature::verify($body, $key, $mac),
 'prefixed sha256 accepted' => WebhookSignature::verify($body, $key, 'sha256='.$mac),
 'changed body rejected' => !WebhookSignature::verify($body.'changed', $key, $mac),
 'changed key rejected' => !WebhookSignature::verify($body, str_repeat('b',32), $mac),
 'missing signature rejected' => !WebhookSignature::verify($body, $key, null),
 'empty signature rejected' => !WebhookSignature::verify($body, $key, ''),
 'unsupported algorithm rejected' => !WebhookSignature::verify($body, $key, 'sha1='.$mac),
 'unconfigured secret rejected' => !WebhookSignature::verify($body, '', hash_hmac('sha256',$body,'')),
];
$failed = [];
foreach ($checks as $name=>$ok) { if (!$ok) $failed[]=$name; }
echo json_encode(['checks'=>count($checks),'failed'=>$failed],JSON_THROW_ON_ERROR).PHP_EOL;
exit($failed ? 1 : 0);
