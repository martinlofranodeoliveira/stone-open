<?php
require __DIR__ . '/../src/App/Support/Community/release_db_startup_gate.php';

$failures = 0;
$checks = 0;

function t_check($cond, $label) {
    global $failures, $checks;
    $checks = $checks + 1;
    if (!$cond) {
        $failures = $failures + 1;
        echo "FAIL: " . $label . "\n";
    } else {
        echo "PASS: " . $label . "\n";
    }
}

function t_run($payload) {
    return \App\Support\Community\ReleaseDbStartupGate\runFeature($payload);
}

function t_obj($arr) {
    $o = new \stdClass();
    foreach ($arr as $k => $v) {
        $o->$k = $v;
    }
    return $o;
}

// 1. All true -> start/ok/0
$r = t_run(t_obj(['has_database' => true, 'has_user' => true, 'has_password' => true]));
t_check($r->has_database === true, 'all-true has_database');
t_check($r->has_user === true, 'all-true has_user');
t_check($r->has_password === true, 'all-true has_password');
t_check($r->missing_count === 0, 'all-true missing_count 0');
t_check($r->ready === true, 'all-true ready');
t_check($r->gate === 'start', 'all-true gate start');
t_check($r->reason === 'ok', 'all-true reason ok');

// 2. Missing user -> blocked/missing_user/1
$r = t_run(t_obj(['has_database' => true, 'has_user' => false, 'has_password' => true]));
t_check($r->missing_count === 1 && $r->ready === false, 'missing-user count/ready');
t_check($r->gate === 'blocked' && $r->reason === 'missing_user', 'missing-user gate/reason');

// 3. Empty object -> blocked/missing_database/3
$r = t_run(new \stdClass());
t_check($r->has_database === false && $r->has_user === false && $r->has_password === false, 'empty flags false');
t_check($r->missing_count === 3 && $r->ready === false, 'empty count/ready');
t_check($r->gate === 'blocked' && $r->reason === 'missing_database', 'empty gate/reason');

// 4. Non-object payloads behave as empty object
foreach ([null, 0, 1, 'x', true, false, [], [true], 3.5] as $i => $bad) {
    $r = t_run($bad);
    t_check($r->has_database === false && $r->has_user === false && $r->has_password === false, 'non-object flags false #' . $i);
    t_check($r->missing_count === 3 && $r->ready === false && $r->gate === 'blocked' && $r->reason === 'missing_database', 'non-object decision #' . $i);
}

// 5. Strict boolean true only
$r = t_run(t_obj(['has_database' => 1, 'has_user' => 'true', 'has_password' => 1.0]));
t_check($r->has_database === false && $r->has_user === false && $r->has_password === false, 'truthy non-bool false');
t_check($r->missing_count === 3 && $r->reason === 'missing_database', 'truthy count/reason');
$r = t_run(t_obj(['has_database' => true, 'has_user' => 1, 'has_password' => true]));
t_check($r->has_user === false && $r->missing_count === 1 && $r->reason === 'missing_user', 'int 1 not true');

// 6. missing_count equals number of false flags
$r = t_run(t_obj(['has_database' => true, 'has_user' => true, 'has_password' => false]));
t_check($r->missing_count === 1 && $r->reason === 'missing_password' && $r->gate === 'blocked', 'one missing password');
$r = t_run(t_obj(['has_database' => false, 'has_user' => true, 'has_password' => false]));
t_check($r->missing_count === 2 && $r->reason === 'missing_database', 'two missing reason database first');
$r = t_run(t_obj(['has_database' => true, 'has_user' => false, 'has_password' => false]));
t_check($r->missing_count === 2 && $r->reason === 'missing_user', 'two missing user first');

// 7. First-missing order database, user, password
$r = t_run(t_obj(['has_database' => false, 'has_user' => false, 'has_password' => false]));
t_check($r->reason === 'missing_database', 'order all false -> database');
$r = t_run(t_obj(['has_database' => true, 'has_user' => false, 'has_password' => false]));
t_check($r->reason === 'missing_user', 'order user before password');

// 8. Extra fields ignored
$r = t_run(t_obj(['has_database' => true, 'has_user' => true, 'has_password' => true, 'extra' => 'x', 'ready' => false]));
t_check($r->ready === true && $r->gate === 'start' && $r->reason === 'ok' && $r->missing_count === 0, 'extra ignored');
t_check(!isset($r->extra), 'no extra in output');

// 9. Input never mutated
$in = t_obj(['has_database' => true, 'has_user' => true, 'has_password' => true, 'extra' => 5]);
$before = json_encode($in);
$r = t_run($in);
$t_after = json_encode($in);
t_check($before === $t_after, 'input not mutated');

// 10. Deterministic same input identical output
$a = t_run(t_obj(['has_database' => true, 'has_user' => false, 'has_password' => true]));
$b = t_run(t_obj(['has_database' => true, 'has_user' => false, 'has_password' => true]));
t_check(json_encode($a) === json_encode($b), 'deterministic output');

// 11. Missing values count as false
$r = t_run(t_obj(['has_database' => true]));
t_check($r->has_user === false && $r->has_password === false && $r->missing_count === 2, 'missing keys false');
t_check($r->reason === 'missing_user', 'missing keys reason user');

echo "\nCHECKS: " . $checks . " FAILURES: " . $failures . "\n";
if ($failures > 0) {
    exit(1);
}
echo "ALL COMMUNITY_RELEASE_DB_STARTUP_GATE TESTS PASSED\n";
