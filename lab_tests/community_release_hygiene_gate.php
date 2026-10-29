<?php
require __DIR__ . '/../src/App/Support/Community/release_hygiene_gate.php';

$failures = 0;

function check($cond, $label) {
    global $failures;
    if ($cond !== true) {
        $failures = $failures + 1;
        echo "FAIL: " . $label . "\n";
    } else {
        echo "PASS: " . $label . "\n";
    }
}

function makeObj($map) {
    $o = new \stdClass();
    foreach ($map as $k => $v) {
        $o->$k = $v;
    }
    return $o;
}

function expectGate($payload, $exp) {
    $out = \App\Support\Community\ReleaseHygieneGate\runFeature($payload);
    foreach ($exp as $k => $v) {
        $actual = $out->$k;
        check($actual === $v, 'field ' . $k . ' expected ' . var_export($v, true) . ' got ' . var_export($actual, true));
    }
    $vars = get_object_vars($out);
    check(count($vars) === 10, 'output has exactly 10 fields');
    $keys = array_keys($vars);
    sort($keys);
    $want = array('clean', 'gate', 'has_env', 'has_logs', 'has_operational_docs', 'has_postman_capture', 'has_storage_cache', 'has_webhook_capture', 'present_count', 'reason');
    check($keys === $want, 'output keys exact set');
    return $out;
}

// 1. Empty object
$empty = new \stdClass();
expectGate($empty, array('has_env' => false, 'has_logs' => false, 'has_storage_cache' => false, 'has_postman_capture' => false, 'has_webhook_capture' => false, 'has_operational_docs' => false, 'present_count' => 0, 'clean' => true, 'gate' => 'clean', 'reason' => 'ok'));

// 2. Non-object payloads behave as empty
foreach (array(null, 0, 1, 'x', true, false, array(), array('has_env' => true)) as $i => $bad) {
    $out = \App\Support\Community\ReleaseHygieneGate\runFeature($bad);
    check($out->present_count === 0 && $out->clean === true && $out->gate === 'clean' && $out->reason === 'ok', 'non-object case ' . $i . ' treated as empty');
    check($out->has_env === false && $out->has_logs === false && $out->has_storage_cache === false, 'non-object flags false ' . $i);
}

// 3. Only boolean true enables; coercion cases
$coerce = makeObj(array('has_env' => 1, 'has_logs' => 'true', 'has_storage_cache' => null, 'has_postman_capture' => array(true), 'has_webhook_capture' => makeObj(array('x' => 1)), 'has_operational_docs' => 0));
expectGate($coerce, array('has_env' => false, 'has_logs' => false, 'has_storage_cache' => false, 'has_postman_capture' => false, 'has_webhook_capture' => false, 'has_operational_docs' => false, 'present_count' => 0, 'clean' => true, 'gate' => 'clean', 'reason' => 'ok'));
$oneTrue = makeObj(array('has_env' => 1, 'has_logs' => true));
expectGate($oneTrue, array('has_env' => false, 'has_logs' => true, 'has_storage_cache' => false, 'has_postman_capture' => false, 'has_webhook_capture' => false, 'has_operational_docs' => false, 'present_count' => 1, 'clean' => false, 'gate' => 'blocked', 'reason' => 'has_logs'));

// 4. Documented examples
$ex1 = new \stdClass();
expectGate($ex1, array('has_env' => false, 'has_logs' => false, 'has_storage_cache' => false, 'has_postman_capture' => false, 'has_webhook_capture' => false, 'has_operational_docs' => false, 'present_count' => 0, 'clean' => true, 'gate' => 'clean', 'reason' => 'ok'));
expectGate(makeObj(array('has_env' => true)), array('has_env' => true, 'has_logs' => false, 'has_storage_cache' => false, 'has_postman_capture' => false, 'has_webhook_capture' => false, 'has_operational_docs' => false, 'present_count' => 1, 'clean' => false, 'gate' => 'blocked', 'reason' => 'has_env'));
expectGate(makeObj(array('has_logs' => true, 'has_webhook_capture' => true)), array('has_env' => false, 'has_logs' => true, 'has_storage_cache' => false, 'has_postman_capture' => false, 'has_webhook_capture' => true, 'has_operational_docs' => false, 'present_count' => 2, 'clean' => false, 'gate' => 'blocked', 'reason' => 'has_logs'));

// 5. present_count range and priority
$all = makeObj(array('has_env' => true, 'has_logs' => true, 'has_storage_cache' => true, 'has_postman_capture' => true, 'has_webhook_capture' => true, 'has_operational_docs' => true));
expectGate($all, array('has_env' => true, 'has_logs' => true, 'has_storage_cache' => true, 'has_postman_capture' => true, 'has_webhook_capture' => true, 'has_operational_docs' => true, 'present_count' => 6, 'clean' => false, 'gate' => 'blocked', 'reason' => 'has_env'));
expectGate(makeObj(array('has_storage_cache' => true, 'has_postman_capture' => true)), array('has_env' => false, 'has_logs' => false, 'has_storage_cache' => true, 'has_postman_capture' => true, 'has_webhook_capture' => false, 'has_operational_docs' => false, 'present_count' => 2, 'clean' => false, 'gate' => 'blocked', 'reason' => 'has_storage_cache'));
expectGate(makeObj(array('has_postman_capture' => true, 'has_webhook_capture' => true, 'has_operational_docs' => true)), array('has_env' => false, 'has_logs' => false, 'has_storage_cache' => false, 'has_postman_capture' => true, 'has_webhook_capture' => true, 'has_operational_docs' => true, 'present_count' => 3, 'clean' => false, 'gate' => 'blocked', 'reason' => 'has_postman_capture'));
expectGate(makeObj(array('has_webhook_capture' => true, 'has_operational_docs' => true)), array('has_env' => false, 'has_logs' => false, 'has_storage_cache' => false, 'has_postman_capture' => false, 'has_webhook_capture' => true, 'has_operational_docs' => true, 'present_count' => 2, 'clean' => false, 'gate' => 'blocked', 'reason' => 'has_webhook_capture'));
expectGate(makeObj(array('has_operational_docs' => true)), array('has_env' => false, 'has_logs' => false, 'has_storage_cache' => false, 'has_postman_capture' => false, 'has_webhook_capture' => false, 'has_operational_docs' => true, 'present_count' => 1, 'clean' => false, 'gate' => 'blocked', 'reason' => 'has_operational_docs'));

// 6. Extra fields ignored, input never mutated, deterministic
$in = makeObj(array('has_env' => true, 'extra' => 'keep', 'nested' => array(1, 2)));
$before = json_encode($in);
$outA = \App\Support\Community\ReleaseHygieneGate\runFeature($in);
$after = json_encode($in);
check($before === $after, 'input never mutated');
check(!isset($outA->extra), 'extra field not echoed');
$outB = \App\Support\Community\ReleaseHygieneGate\runFeature($in);
check(json_encode($outA) === json_encode($outB), 'deterministic identical output');
check($outA->present_count === 1 && $outA->reason === 'has_env', 'extra-input count/reason correct');

if ($failures > 0) {
    echo "FAILED: " . $failures . " assertion(s)\n";
    exit(1);
}
echo "ALL OK\n";
