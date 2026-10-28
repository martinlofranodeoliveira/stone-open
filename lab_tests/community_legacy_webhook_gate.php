<?php
require_once __DIR__ . '/../src/App/Support/Community/legacy_webhook_gate.php';

function checkField($want, $got, $label) {
    if ($want !== $got) {
        echo 'FAIL ';
        echo $label;
        exit(1);
    }
    echo 'ok ';
    echo $label;
}

function checkGate($out, $flag, $has, $count, $gate, $ready, $reason, $label) {
    if ($out->flag_on !== $flag) {
        echo 'FAIL ';
        echo $label;
        exit(1);
    }
    if ($out->has_endpoints !== $has) {
        echo 'FAIL ';
        echo $label;
        exit(1);
    }
    if ($out->endpoint_count !== $count) {
        echo 'FAIL ';
        echo $label;
        exit(1);
    }
    if ($out->gate !== $gate) {
        echo 'FAIL ';
        echo $label;
        exit(1);
    }
    if ($out->ready !== $ready) {
        echo 'FAIL ';
        echo $label;
        exit(1);
    }
    if ($out->reason !== $reason) {
        echo 'FAIL ';
        echo $label;
        exit(1);
    }
    echo 'ok ';
    echo $label;
}

function callGate($p) {
    $r = \App\Support\Community\LegacyWebhookGate\runFeature($p);
    return $r;
}

$e0 = new \stdClass();
$r = callGate($e0);
checkGate($r, false, false, 0, 'disabled', false, 'disabled_flag', 'c00');

$p1 = new \stdClass();
$p1->enable_legacy = true;
$p1->endpoints = ['https://example.com/hook'];
$r = callGate($p1);
checkGate($r, true, true, 1, 'enabled', true, 'ok', 'c01');

$p2 = new \stdClass();
$p2->enable_legacy = 'true';
$p2->endpoints = ['https://example.com/hook'];
$r = callGate($p2);
checkGate($r, true, true, 1, 'enabled', true, 'ok', 'c02');

$p3 = new \stdClass();
$p3->enable_legacy = true;
$p3->endpoints = [];
$r = callGate($p3);
checkGate($r, true, false, 0, 'disabled', false, 'missing_endpoints', 'c03');

$p4 = new \stdClass();
$p4->enable_legacy = true;
$p4->endpoints = ['', ''];
$r = callGate($p4);
checkGate($r, true, false, 0, 'disabled', false, 'missing_endpoints', 'c04');

$p5 = new \stdClass();
$p5->enable_legacy = false;
$p5->endpoints = ['https://example.com/hook'];
$r = callGate($p5);
checkGate($r, false, true, 1, 'disabled', false, 'disabled_flag', 'c05');

$p6 = new \stdClass();
$p6->enable_legacy = 'false';
$p6->endpoints = ['https://example.com/hook'];
$r = callGate($p6);
checkGate($r, false, true, 1, 'disabled', false, 'disabled_flag', 'c06');

$p7 = new \stdClass();
$p7->endpoints = ['https://example.com/hook'];
$r = callGate($p7);
checkGate($r, false, true, 1, 'disabled', false, 'disabled_flag', 'c07');

$p8 = new \stdClass();
$p8->enable_legacy = false;
$p8->endpoints = 'https://example.com/hook';
$r = callGate($p8);
checkGate($r, false, false, 0, 'disabled', false, 'disabled_flag', 'c08');

$p9 = new \stdClass();
$p9->enable_legacy = true;
$p9->endpoints = 'https://example.com/hook';
$r = callGate($p9);
checkGate($r, true, false, 0, 'disabled', false, 'missing_endpoints', 'c09');

$p10 = new \stdClass();
$p10->enable_legacy = true;
$p10->endpoints = ['a', '', 'b'];
$r = callGate($p10);
checkGate($r, true, true, 2, 'enabled', true, 'ok', 'c10');

$badList = ['True', 'TRUE', ' true', 'true ', '1'];
foreach ($badList as $bad) {
    $pb = new \stdClass();
    $pb->enable_legacy = $bad;
    $pb->endpoints = ['https://example.com/hook'];
    $rb = callGate($pb);
    checkField(false, $rb->flag_on, 'c11-flag');
    checkField('disabled', $rb->gate, 'c11-gate');
    checkField('disabled_flag', $rb->reason, 'c11-reason');
}

$base = new \stdClass();
$base->enable_legacy = true;
$base->endpoints = ['https://example.com/hook'];
$withExtra = new \stdClass();
$withExtra->enable_legacy = true;
$withExtra->endpoints = ['https://example.com/hook'];
$withExtra->extra = 123;
$ra = callGate($base);
$rb2 = callGate($withExtra);
checkField($ra->flag_on, $rb2->flag_on, 'c12-flag');
checkField($ra->gate, $rb2->gate, 'c12-gate');
checkField($ra->reason, $rb2->reason, 'c12-reason');
checkField($ra->endpoint_count, $rb2->endpoint_count, 'c12-count');

$pm = new \stdClass();
$pm->enable_legacy = true;
$pm->endpoints = ['https://example.com/hook'];
$first = callGate($pm);
checkField(true, $pm->enable_legacy, 'c13-flag');
checkField('https://example.com/hook', $pm->endpoints[0], 'c13-item');
$second = callGate($pm);
checkField($first->gate, $second->gate, 'c13-gate');
checkField($first->reason, $second->reason, 'c13-reason');
checkField($first->endpoint_count, $second->endpoint_count, 'c13-count');

$rNull = callGate(null);
checkGate($rNull, false, false, 0, 'disabled', false, 'disabled_flag', 'c14');

$rNum = callGate(123);
checkGate($rNum, false, false, 0, 'disabled', false, 'disabled_flag', 'c15');

$rStr = callGate('x');
checkGate($rStr, false, false, 0, 'disabled', false, 'disabled_flag', 'c16');

$rBool = callGate(true);
checkGate($rBool, false, false, 0, 'disabled', false, 'disabled_flag', 'c17');

$rArr = callGate(['enable_legacy' => true]);
checkGate($rArr, false, false, 0, 'disabled', false, 'disabled_flag', 'c18');

echo 'ALL PASS';
