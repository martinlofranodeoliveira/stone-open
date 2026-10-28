<?php
require __DIR__ . '/../src/App/Support/Community/runtime_extension_gate.php';

$failures = 0;
function check($name, $cond) {
    global $failures;
    if ($cond === true) {
        echo "PASS " . $name . "\n";
    } else {
        $failures = $failures + 1;
        echo "FAIL " . $name . "\n";
    }
}
function toArray($o) {
    return get_object_vars($o);
}
function runP($p) {
    return \App\Support\Community\RuntimeExtensionGate\runFeature($p);
}

// 1: all true => ready ok
$p = new \stdClass;
$p->has_pdo_mysql = true;
$p->has_curl = true;
$p->has_openssl = true;
$p->has_session = true;
$r = runP($p);
check('all-true-ready', $r->ready === true);
check('all-true-gate', $r->gate === 'ready');
check('all-true-reason', $r->reason === 'ok');
check('all-true-missing', $r->missing_count === 0);
check('all-true-storage', $r->storage_ok === true);
check('all-true-transport', $r->transport_ok === true);
check('all-true-session', $r->session_ok === true);

// 2: contract example missing storage
$p2 = new \stdClass;
$p2->has_pdo_mysql = false;
$p2->has_curl = true;
$p2->has_openssl = true;
$p2->has_session = true;
$r2 = runP($p2);
check('missing-storage-gate', $r2->gate === 'blocked');
check('missing-storage-reason', $r2->reason === 'missing_storage');
check('missing-storage-count', $r2->missing_count === 1);
check('missing-storage-ready', $r2->ready === false);
check('missing-storage-storage', $r2->storage_ok === false);
check('missing-storage-transport', $r2->transport_ok === true);

// 3: contract example missing transport
$p3 = new \stdClass;
$p3->has_pdo_mysql = true;
$p3->has_curl = true;
$p3->has_openssl = false;
$p3->has_session = true;
$r3 = runP($p3);
check('missing-transport-reason', $r3->reason === 'missing_transport');
check('missing-transport-transport', $r3->transport_ok === false);
check('missing-transport-count', $r3->missing_count === 1);
check('missing-transport-gate', $r3->gate === 'blocked');

// 4: missing session only
$p4 = new \stdClass;
$p4->has_pdo_mysql = true;
$p4->has_curl = true;
$p4->has_openssl = true;
$p4->has_session = false;
$r4 = runP($p4);
check('missing-session-reason', $r4->reason === 'missing_session');
check('missing-session-count', $r4->missing_count === 1);
check('missing-session-session', $r4->session_ok === false);
check('missing-session-ready', $r4->ready === false);

// 5: non-object payloads behave as empty
foreach ([null, 0, 1, 'x', true, false, []] as $i => $np) {
    $rn = runP($np);
    check('nonobject-' . $i . '-flags', $rn->has_pdo_mysql === false && $rn->has_curl === false && $rn->has_openssl === false && $rn->has_session === false);
    check('nonobject-' . $i . '-count', $rn->missing_count === 4);
    check('nonobject-' . $i . '-gate', $rn->gate === 'blocked' && $rn->ready === false && $rn->reason === 'missing_storage');
}
$empty = new \stdClass;
$re = runP($empty);
check('empty-count-4', $re->missing_count === 4);
check('empty-reason-storage', $re->reason === 'missing_storage');
check('empty-gate-blocked', $re->gate === 'blocked');

// 6: transport requires both
$a = new \stdClass;
$a->has_pdo_mysql = true;
$a->has_curl = true;
$a->has_openssl = false;
$a->has_session = true;
check('transport-needs-both-a', runP($a)->transport_ok === false);
$b = new \stdClass;
$b->has_pdo_mysql = true;
$b->has_curl = false;
$b->has_openssl = true;
$b->has_session = true;
check('transport-needs-both-b', runP($b)->transport_ok === false);
$c = new \stdClass;
$c->has_pdo_mysql = true;
$c->has_curl = true;
$c->has_openssl = true;
$c->has_session = true;
check('transport-both-true', runP($c)->transport_ok === true);

// 7: reason priority
$pr1 = new \stdClass;
$pr1->has_pdo_mysql = false;
$pr1->has_curl = false;
$pr1->has_openssl = true;
$pr1->has_session = false;
$rr1 = runP($pr1);
check('priority-storage-first', $rr1->reason === 'missing_storage' && $rr1->missing_count === 3);
$pr2 = new \stdClass;
$pr2->has_pdo_mysql = true;
$pr2->has_curl = true;
$pr2->has_openssl = false;
$pr2->has_session = false;
$rr2 = runP($pr2);
check('priority-transport-second', $rr2->reason === 'missing_transport' && $rr2->missing_count === 2);
$pr3 = new \stdClass;
$pr3->has_pdo_mysql = true;
$pr3->has_curl = true;
$pr3->has_openssl = true;
$pr3->has_session = false;
check('priority-session-last', runP($pr3)->reason === 'missing_session');

// 8: strict boolean: truthy non-bool treated as false
$t = new \stdClass;
$t->has_pdo_mysql = 1;
$t->has_curl = 'true';
$t->has_openssl = 1.0;
$t->has_session = [];
$rt = runP($t);
check('strict-bool-false', $rt->has_pdo_mysql === false && $rt->has_curl === false && $rt->has_openssl === false && $rt->has_session === false);
check('strict-bool-count', $rt->missing_count === 4);

// 9: extra fields ignored, input not mutated, deterministic
$ex = new \stdClass;
$ex->has_pdo_mysql = true;
$ex->has_curl = true;
$ex->has_openssl = true;
$ex->has_session = true;
$ex->extra = 'keep';
$ex->other = 123;
$before = json_encode($ex);
$rx1 = runP($ex);
$rx2 = runP($ex);
check('extra-ignored', !array_key_exists('extra', toArray($rx1)) && !array_key_exists('other', toArray($rx1)));
check('input-not-mutated', json_encode($ex) === $before);
check('deterministic', json_encode($rx1) === json_encode($rx2));
check('output-keys', count(toArray($rx1)) === 11);

if ($failures > 0) {
    echo "FAILURES: " . $failures . "\n";
    exit(1);
}
echo "ALL OK\n";
