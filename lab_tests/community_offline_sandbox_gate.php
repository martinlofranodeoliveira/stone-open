<?php
require __DIR__ . '/../src/App/Support/Community/offline_sandbox_gate.php';

function check_ok($cond, $msg) {
    if ($cond !== true) {
        echo "FAIL: " . $msg . "\n";
        exit(1);
    }
}

function mk($arr) {
    $o = new \stdClass;
    foreach ($arr as $k => $v) {
        $o->$k = $v;
    }
    return $o;
}

function expect_fields($out, $msg) {
    $vars = get_object_vars($out);
    $want = array('source_readonly', 'runtime_readonly', 'network_isolated', 'no_effective_caps', 'no_new_privs', 'filesystem_ok', 'network_ok', 'privilege_ok', 'missing_count', 'ready', 'gate', 'reason');
    foreach ($want as $k) {
        if (array_key_exists($k, $vars) !== true) {
            echo "FAIL missing field " . $k . " in " . $msg . "\n";
            exit(1);
        }
    }
    if (count($vars) !== 12) {
        echo "FAIL field count in " . $msg . "\n";
        exit(1);
    }
}

$a = mk(array('source_readonly' => true, 'runtime_readonly' => true, 'network_isolated' => true, 'no_effective_caps' => true, 'no_new_privs' => true));
$r = \App\Support\Community\OfflineSandboxGate\runFeature($a);
expect_fields($r, 'all-true');
check_ok($r->source_readonly === true, 'all-true source');
check_ok($r->runtime_readonly === true, 'all-true runtime');
check_ok($r->network_isolated === true, 'all-true network_isolated');
check_ok($r->no_effective_caps === true, 'all-true caps');
check_ok($r->no_new_privs === true, 'all-true privs');
check_ok($r->filesystem_ok === true, 'all-true filesystem_ok');
check_ok($r->network_ok === true, 'all-true network_ok');
check_ok($r->privilege_ok === true, 'all-true privilege_ok');
check_ok($r->missing_count === 0, 'all-true missing_count');
check_ok($r->ready === true, 'all-true ready');
check_ok($r->gate === 'ready', 'all-true gate');
check_ok($r->reason === 'ok', 'all-true reason');

$b = new \stdClass;
$rb = \App\Support\Community\OfflineSandboxGate\runFeature($b);
expect_fields($rb, 'empty');
check_ok($rb->filesystem_ok === false, 'empty filesystem_ok');
check_ok($rb->network_ok === false, 'empty network_ok');
check_ok($rb->privilege_ok === false, 'empty privilege_ok');
check_ok($rb->missing_count === 5, 'empty missing_count');
check_ok($rb->ready === false, 'empty ready');
check_ok($rb->gate === 'blocked', 'empty gate');
check_ok($rb->reason === 'missing_filesystem', 'empty reason');

$c = mk(array('source_readonly' => true, 'runtime_readonly' => true, 'network_isolated' => true, 'no_effective_caps' => true, 'no_new_privs' => false));
$rc = \App\Support\Community\OfflineSandboxGate\runFeature($c);
expect_fields($rc, 'priv-missing');
check_ok($rc->filesystem_ok === true, 'priv-missing filesystem_ok');
check_ok($rc->network_ok === true, 'priv-missing network_ok');
check_ok($rc->privilege_ok === false, 'priv-missing privilege_ok');
check_ok($rc->missing_count === 1, 'priv-missing count');
check_ok($rc->ready === false, 'priv-missing ready');
check_ok($rc->gate === 'blocked', 'priv-missing gate');
check_ok($rc->reason === 'missing_privileges', 'priv-missing reason');

$rn = \App\Support\Community\OfflineSandboxGate\runFeature(null);
check_ok($rn->missing_count === 5, 'null count');
check_ok($rn->gate === 'blocked', 'null gate');
check_ok($rn->reason === 'missing_filesystem', 'null reason');

$rs = \App\Support\Community\OfflineSandboxGate\runFeature('x');
check_ok($rs->missing_count === 5, 'string count');
check_ok($rs->ready === false, 'string ready');

$ri = \App\Support\Community\OfflineSandboxGate\runFeature(7);
check_ok($ri->missing_count === 5, 'int count');

$rarr = \App\Support\Community\OfflineSandboxGate\runFeature(array('source_readonly' => true));
check_ok($rarr->missing_count === 5, 'array behaves as empty');
check_ok($rarr->source_readonly === false, 'array source false');

$fs = mk(array('source_readonly' => false, 'runtime_readonly' => true, 'network_isolated' => true, 'no_effective_caps' => true, 'no_new_privs' => true));
$rfs = \App\Support\Community\OfflineSandboxGate\runFeature($fs);
check_ok($rfs->filesystem_ok === false, 'fs false');
check_ok($rfs->network_ok === true, 'fs network true');
check_ok($rfs->privilege_ok === true, 'fs priv true');
check_ok($rfs->missing_count === 1, 'fs count 1');
check_ok($rfs->reason === 'missing_filesystem', 'fs reason');

$net = mk(array('source_readonly' => true, 'runtime_readonly' => true, 'network_isolated' => false, 'no_effective_caps' => true, 'no_new_privs' => true));
$rnet = \App\Support\Community\OfflineSandboxGate\runFeature($net);
check_ok($rnet->filesystem_ok === true, 'net fs true');
check_ok($rnet->network_ok === false, 'net false');
check_ok($rnet->privilege_ok === true, 'net priv true');
check_ok($rnet->missing_count === 1, 'net count');
check_ok($rnet->reason === 'missing_network', 'net reason');

$prio = mk(array('source_readonly' => false, 'runtime_readonly' => false, 'network_isolated' => false, 'no_effective_caps' => false, 'no_new_privs' => false));
$rprio = \App\Support\Community\OfflineSandboxGate\runFeature($prio);
check_ok($rprio->reason === 'missing_filesystem', 'priority filesystem first');

$prio2 = mk(array('source_readonly' => true, 'runtime_readonly' => true, 'network_isolated' => false, 'no_effective_caps' => false, 'no_new_privs' => false));
$rprio2 = \App\Support\Community\OfflineSandboxGate\runFeature($prio2);
check_ok($rprio2->reason === 'missing_network', 'priority network second');
check_ok($rprio2->missing_count === 3, 'priority count 3');

$truthy = mk(array('source_readonly' => 1, 'runtime_readonly' => 'true', 'network_isolated' => 1, 'no_effective_caps' => 1, 'no_new_privs' => 1));
$rt = \App\Support\Community\OfflineSandboxGate\runFeature($truthy);
check_ok($rt->source_readonly === false, 'truthy strict false');
check_ok($rt->missing_count === 5, 'truthy count 5');

$ex = mk(array('source_readonly' => true, 'runtime_readonly' => true, 'network_isolated' => true, 'no_effective_caps' => true, 'no_new_privs' => true, 'extra' => 123));
$rex = \App\Support\Community\OfflineSandboxGate\runFeature($ex);
expect_fields($rex, 'extra ignored fields');
check_ok($rex->ready === true, 'extra ready');
check_ok($rex->reason === 'ok', 'extra reason');

$mut = mk(array('source_readonly' => true, 'runtime_readonly' => true, 'network_isolated' => true, 'no_effective_caps' => true, 'no_new_privs' => true, 'extra' => 'keep'));
$before = json_encode($mut);
\App\Support\Community\OfflineSandboxGate\runFeature($mut);
check_ok(json_encode($mut) === $before, 'input not mutated');

$det1 = \App\Support\Community\OfflineSandboxGate\runFeature($a);
$det2 = \App\Support\Community\OfflineSandboxGate\runFeature(mk(array('source_readonly' => true, 'runtime_readonly' => true, 'network_isolated' => true, 'no_effective_caps' => true, 'no_new_privs' => true)));
check_ok(json_encode($det1) === json_encode($det2), 'deterministic');
check_ok(json_encode($det1) === json_encode($r), 'deterministic stable');

echo "PASS\n";
