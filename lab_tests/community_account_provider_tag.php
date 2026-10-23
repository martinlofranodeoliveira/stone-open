<?php
require __DIR__ . '/../src/App/Support/Community/account_provider_tag.php';

function fail_case($msg) {
    fwrite(STDERR, "FAIL: " . $msg . "\n");
    exit(1);
}

function eq_case($a, $b, $msg) {
    if ($a !== $b) {
        fail_case($msg . " expected " . var_export($b, true) . " got " . var_export($a, true));
    }
}

function mk_payload($pairs) {
    $o = new stdClass();
    foreach ($pairs as $k => $v) {
        $o->$k = $v;
    }
    return $o;
}

function call_feature($p) {
    return \App\Support\Community\AccountProviderTag\runFeature($p);
}

$p1 = mk_payload(array('provider' => 'stone'));
$r1 = call_feature($p1);
eq_case($r1->raw, 'stone', 'case1 raw');
eq_case($r1->normalized, 'stone', 'case1 normalized');
eq_case($r1->provider_source, 'provider', 'case1 source');
eq_case($r1->has_provider, true, 'case1 has_provider');
eq_case($r1->category, 'external', 'case1 category');
eq_case($r1->is_known, true, 'case1 is_known');

$p2 = mk_payload(array('provedor' => 'interno'));
$r2 = call_feature($p2);
eq_case($r2->raw, 'interno', 'case2 raw');
eq_case($r2->normalized, 'manual', 'case2 normalized');
eq_case($r2->provider_source, 'provedor', 'case2 source');
eq_case($r2->has_provider, true, 'case2 has_provider');
eq_case($r2->category, 'internal', 'case2 category');
eq_case($r2->is_known, true, 'case2 is_known');

$p3 = mk_payload(array('account_provider' => 'pagarme'));
$r3 = call_feature($p3);
eq_case($r3->raw, 'pagarme', 'case3 raw');
eq_case($r3->normalized, 'pagarme', 'case3 normalized');
eq_case($r3->provider_source, 'account_provider', 'case3 source');
eq_case($r3->has_provider, true, 'case3 has_provider');
eq_case($r3->category, 'external', 'case3 category');
eq_case($r3->is_known, true, 'case3 is_known');

$p4 = mk_payload(array());
$r4 = call_feature($p4);
eq_case($r4->raw, '', 'case4 raw');
eq_case($r4->normalized, 'unknown', 'case4 normalized');
eq_case($r4->provider_source, 'none', 'case4 source');
eq_case($r4->has_provider, false, 'case4 has_provider');
eq_case($r4->category, 'unknown', 'case4 category');
eq_case($r4->is_known, false, 'case4 is_known');

$p5 = mk_payload(array('provider' => 'stone', 'extra' => 'keep', 'other' => 123));
$before5 = json_encode($p5);
$r5 = call_feature($p5);
eq_case($r5->raw, 'stone', 'case5 raw');
eq_case($r5->normalized, 'stone', 'case5 normalized');
eq_case(json_encode($p5), $before5, 'case5 input mutated');
eq_case(isset($r5->extra), false, 'case5 extra leaked');

$p6a = mk_payload(array('provider' => 'pagarme'));
$p6b = mk_payload(array('provider' => 'pagarme'));
$r6a = call_feature($p6a);
$r6b = call_feature($p6b);
eq_case(json_encode($r6a), json_encode($r6b), 'case6 deterministic');

$p7 = mk_payload(array('provider' => 123, 'provedor' => null, 'account_provider' => array('x')));
$r7 = call_feature($p7);
eq_case($r7->raw, '', 'case7 raw');
eq_case($r7->normalized, 'unknown', 'case7 normalized');
eq_case($r7->provider_source, 'none', 'case7 source');
eq_case($r7->has_provider, false, 'case7 has_provider');
eq_case($r7->category, 'unknown', 'case7 category');
eq_case($r7->is_known, false, 'case7 is_known');

$r8 = call_feature(null);
eq_case($r8->normalized, 'unknown', 'case8 null normalized');
eq_case($r8->has_provider, false, 'case8 null has_provider');
$r8b = call_feature(array('provider' => 'stone'));
eq_case($r8b->normalized, 'unknown', 'case8 array normalized');

$p9 = mk_payload(array('provider' => 'manual', 'provedor' => 'stone', 'account_provider' => 'pagarme'));
$r9 = call_feature($p9);
eq_case($r9->raw, 'manual', 'case9 priority raw');
eq_case($r9->provider_source, 'provider', 'case9 priority source');
eq_case($r9->normalized, 'manual', 'case9 priority normalized');

$p10 = mk_payload(array('provedor' => 'stone', 'account_provider' => 'pagarme'));
$r10 = call_feature($p10);
eq_case($r10->provider_source, 'provedor', 'case10 provedor priority');

$p11 = mk_payload(array('provider' => 'stone_pagamentos'));
$r11 = call_feature($p11);
eq_case($r11->normalized, 'stone', 'case11 stone_pagamentos');
eq_case($r11->category, 'external', 'case11 stone_pagamentos category');

$p12 = mk_payload(array('provider' => 'Stone'));
$r12 = call_feature($p12);
eq_case($r12->normalized, 'unknown', 'case12 case sensitive');
eq_case($r12->is_known, false, 'case12 unknown is_known');

$p13 = mk_payload(array('provider' => ' manual'));
$r13 = call_feature($p13);
eq_case($r13->normalized, 'unknown', 'case13 no trimming');

fwrite(STDOUT, "PASS community_account_provider_tag\n");
