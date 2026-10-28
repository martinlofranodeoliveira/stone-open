<?php
require __DIR__ . '/../src/App/Support/Community/webhook_auth_gate.php';

$failures = 0;
$passes = 0;

function expect($cond, $label)
{
    global $failures, $passes;
    if ($cond === true) {
        $passes = $passes + 1;
        echo 'PASS ' . $label . PHP_EOL;
    } else {
        $failures = $failures + 1;
        echo 'FAIL ' . $label . PHP_EOL;
    }
}

function mk($arr)
{
    $o = new \stdClass;
    foreach ($arr as $k => $v) {
        $o->$k = $v;
    }
    return $o;
}

function run($p)
{
    return \App\Support\Community\WebhookAuthGate\runFeature($p);
}

function snapshot($v)
{
    return json_encode($v);
}

// 1: empty object
$r = run(mk([]));
expect($r->has_basic === false, 'empty has_basic false');
expect($r->has_hmac === false, 'empty has_hmac false');
expect($r->basic_valid === false, 'empty basic_valid false');
expect($r->signature_valid === false, 'empty signature_valid false');
expect($r->has_auth_config === false, 'empty has_auth_config false');
expect($r->requires_signature === false, 'empty requires_signature false');
expect($r->allowed === false, 'empty allowed false');
expect($r->http_status === 503, 'empty 503');
expect($r->decision === 'blocked_503', 'empty blocked_503');
expect($r->reason === 'missing_config', 'empty missing_config');
expect($r->block_persist === true, 'empty block_persist true');

// 2: non-object behaves as empty
foreach ([null, false, 0, 1, 'x', 42, [], ['has_basic' => true]] as $i => $bad) {
    $rr = run($bad);
    expect($rr->allowed === false && $rr->http_status === 503 && $rr->decision === 'blocked_503' && $rr->reason === 'missing_config' && $rr->block_persist === true && $rr->has_auth_config === false, 'non-object empty ' . $i);
}

// 3: basic-only valid -> proceed ok
$r = run(mk(['has_basic' => true, 'has_hmac' => false, 'basic_valid' => true, 'signature_valid' => false]));
expect($r->allowed === true, 'basic-only allowed');
expect($r->http_status === 200, 'basic-only 200');
expect($r->decision === 'proceed', 'basic-only proceed');
expect($r->reason === 'ok', 'basic-only ok');
expect($r->block_persist === false, 'basic-only no block');
expect($r->has_auth_config === true && $r->requires_signature === false, 'basic-only config flags');

// 4: basic-only invalid -> blocked invalid_basic
$r = run(mk(['has_basic' => true, 'has_hmac' => false, 'basic_valid' => false, 'signature_valid' => false]));
expect($r->allowed === false && $r->http_status === 503 && $r->decision === 'blocked_503', 'basic invalid blocked 503');
expect($r->reason === 'invalid_basic', 'basic invalid reason');
expect($r->block_persist === true, 'basic invalid block');

// 5: hmac requires signature even when basic valid
$r = run(mk(['has_basic' => true, 'has_hmac' => true, 'basic_valid' => true, 'signature_valid' => false]));
expect($r->allowed === false, 'hmac missing sig blocked');
expect($r->http_status === 503 && $r->decision === 'blocked_503', 'hmac missing sig 503');
expect($r->reason === 'missing_signature', 'hmac missing sig reason');
expect($r->requires_signature === true, 'hmac requires sig true');
expect($r->block_persist === true, 'hmac missing sig block');

// 6: hmac-only missing sig
$r = run(mk(['has_basic' => false, 'has_hmac' => true, 'basic_valid' => false, 'signature_valid' => false]));
expect($r->allowed === false && $r->reason === 'missing_signature' && $r->requires_signature === true, 'hmac-only missing sig');

// 7: hmac-only valid sig allows
$r = run(mk(['has_basic' => false, 'has_hmac' => true, 'basic_valid' => false, 'signature_valid' => true]));
expect($r->allowed === true && $r->http_status === 200 && $r->decision === 'proceed' && $r->reason === 'ok', 'hmac-only valid allows');

// 8: both valid allows
$r = run(mk(['has_basic' => true, 'has_hmac' => true, 'basic_valid' => true, 'signature_valid' => true]));
expect($r->allowed === true && $r->http_status === 200 && $r->decision === 'proceed' && $r->reason === 'ok', 'both valid allows');

// 9: both, single basic failure blocks with invalid_basic
$in = mk(['has_basic' => true, 'has_hmac' => true, 'basic_valid' => false, 'signature_valid' => true]);
$before = snapshot($in);
$r = run($in);
expect($r->allowed === false && $r->http_status === 503 && $r->decision === 'blocked_503', 'both basic fail blocked');
expect($r->reason === 'invalid_basic', 'both basic fail reason');
expect(snapshot($in) === $before, 'input not mutated');

// 10: extra fields ignored + deterministic + allowed values
$in = mk(['has_basic' => true, 'has_hmac' => false, 'basic_valid' => true, 'signature_valid' => false, 'extra' => 123, 'forward_to' => 'x']);
$a = run($in);
$b = run(mk(['has_basic' => true, 'has_hmac' => false, 'basic_valid' => true, 'signature_valid' => false, 'extra' => 999]));
expect(snapshot($a) === snapshot($b), 'extra ignored deterministic');
expect($a->http_status === 200 || $a->http_status === 503, 'status allowed values');
expect($a->decision === 'proceed' || $a->decision === 'blocked_503', 'decision allowed values');
expect($a->reason === 'ok' || $a->reason === 'missing_config' || $a->reason === 'missing_signature' || $a->reason === 'invalid_basic', 'reason allowed values');
$c1 = run(mk(['has_basic' => true, 'has_hmac' => true, 'basic_valid' => true, 'signature_valid' => true]));
$c2 = run(mk(['has_basic' => true, 'has_hmac' => true, 'basic_valid' => true, 'signature_valid' => true]));
expect(snapshot($c1) === snapshot($c2), 'same input identical output');

// 11: contract example 1 exact
$r = run(mk(['basic_valid' => true, 'has_basic' => true, 'has_hmac' => false, 'signature_valid' => false]));
expect($r->allowed === true && $r->basic_valid === true && $r->block_persist === false && $r->decision === 'proceed' && $r->has_auth_config === true && $r->has_basic === true && $r->has_hmac === false && $r->http_status === 200 && $r->reason === 'ok' && $r->requires_signature === false && $r->signature_valid === false, 'contract example basic-only');

echo 'passes=' . $passes . ' failures=' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
