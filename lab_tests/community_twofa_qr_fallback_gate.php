<?php
require_once __DIR__ . '/../src/App/Support/Community/twofa_qr_fallback_gate.php';

$failures = 0;

function t_check($cond, $name)
{
    global $failures;
    if ($cond) {
        echo "PASS " . $name . "\n";
    } else {
        echo "FAIL " . $name . "\n";
        $failures++;
    }
}

function t_payload($map)
{
    $o = new \stdClass;
    foreach ($map as $k => $v) {
        $o->$k = $v;
    }
    return $o;
}

function t_call($payload)
{
    return \App\Support\Community\TwofaQrFallbackGate\runFeature($payload);
}

// 1. Empty object yields missing_setup / missing_material.
$r = t_call(new \stdClass);
t_check($r->library_available === false, 'empty library_available false');
t_check($r->qr_generated === false, 'empty qr_generated false');
t_check($r->has_manual_key === false, 'empty has_manual_key false');
t_check($r->has_manual_uri === false, 'empty has_manual_uri false');
t_check($r->external_image_used === false, 'empty external_image_used false');
t_check($r->local_qr_ok === false, 'empty local_qr_ok false');
t_check($r->has_manual_fallback === false, 'empty has_manual_fallback false');
t_check($r->privacy_ok === true, 'empty privacy_ok true');
t_check($r->display === 'missing_setup', 'empty display missing_setup');
t_check($r->ready === false, 'empty ready false');
t_check($r->reason === 'missing_material', 'empty reason missing_material');

// 2. Non-object payloads behave as empty object.
foreach (array(null, 0, 1, 'x', true, false, array()) as $i => $bad) {
    $rb = t_call($bad);
    t_check($rb->privacy_ok === true && $rb->display === 'missing_setup' && $rb->ready === false && $rb->reason === 'missing_material', 'non-object ' . $i . ' behaves empty');
    t_check($rb->library_available === false && $rb->local_qr_ok === false && $rb->has_manual_fallback === false, 'non-object ' . $i . ' flags false');
}
$rbArr = t_call(array('library_available' => true));
t_check($rbArr->library_available === false && $rbArr->reason === 'missing_material', 'array payload behaves empty');

// 3. Local QR ok.
$r = t_call(t_payload(array('library_available' => true, 'qr_generated' => true, 'has_manual_key' => true, 'has_manual_uri' => true, 'external_image_used' => false)));
t_check($r->local_qr_ok === true, 'local local_qr_ok true');
t_check($r->display === 'local_qr', 'local display local_qr');
t_check($r->ready === true, 'local ready true');
t_check($r->reason === 'ok_local', 'local reason ok_local');
t_check($r->privacy_ok === true, 'local privacy_ok true');

// Local without manual material still local.
$r = t_call(t_payload(array('library_available' => true, 'qr_generated' => true, 'external_image_used' => false)));
t_check($r->display === 'local_qr' && $r->reason === 'ok_local' && $r->ready === true, 'local-only without manual still ok_local');

// 4. Manual fallback when library missing.
$r = t_call(t_payload(array('library_available' => false, 'qr_generated' => false, 'has_manual_key' => true, 'has_manual_uri' => true, 'external_image_used' => false)));
t_check($r->local_qr_ok === false, 'manual local_qr_ok false');
t_check($r->has_manual_fallback === true, 'manual has_manual_fallback true');
t_check($r->display === 'manual_only', 'manual display manual_only');
t_check($r->ready === true, 'manual ready true');
t_check($r->reason === 'ok_manual', 'manual reason ok_manual');

// QR failed but manual present.
$r = t_call(t_payload(array('library_available' => true, 'qr_generated' => false, 'has_manual_key' => true, 'has_manual_uri' => true, 'external_image_used' => false)));
t_check($r->display === 'manual_only' && $r->reason === 'ok_manual' && $r->ready === true, 'qr-failed falls back to manual_only');

// Partial manual material is missing_setup.
$r = t_call(t_payload(array('has_manual_key' => true, 'has_manual_uri' => false, 'external_image_used' => false)));
t_check($r->has_manual_fallback === false && $r->display === 'missing_setup' && $r->reason === 'missing_material' && $r->ready === false, 'partial manual is missing_material');

// 5. External image blocks regardless of material.
$r = t_call(t_payload(array('library_available' => true, 'qr_generated' => true, 'has_manual_key' => true, 'has_manual_uri' => true, 'external_image_used' => true)));
t_check($r->privacy_ok === false, 'blocked privacy_ok false');
t_check($r->ready === false, 'blocked ready false');
t_check($r->reason === 'external_image_blocked', 'blocked reason external_image_blocked');
t_check($r->display === 'local_qr', 'blocked preserves local_qr display');

$r = t_call(t_payload(array('library_available' => false, 'qr_generated' => false, 'has_manual_key' => true, 'has_manual_uri' => true, 'external_image_used' => true)));
t_check($r->privacy_ok === false && $r->ready === false && $r->reason === 'external_image_blocked', 'blocked manual still external_image_blocked');

$r = t_call(t_payload(array('external_image_used' => true)));
t_check($r->privacy_ok === false && $r->ready === false && $r->reason === 'external_image_blocked', 'blocked empty still external_image_blocked');

// Non-boolean truthy values are false.
$r = t_call(t_payload(array('library_available' => 1, 'qr_generated' => 'yes', 'has_manual_key' => 1, 'has_manual_uri' => 1, 'external_image_used' => 0)));
t_check($r->library_available === false && $r->qr_generated === false && $r->local_qr_ok === false, 'truthy ints are not true');
t_check($r->has_manual_fallback === false && $r->external_image_used === false && $r->privacy_ok === true, 'truthy manual/external strict boolean');

// 6. Extra fields ignored, input never mutated, deterministic.
$in = t_payload(array('library_available' => true, 'qr_generated' => true, 'external_image_used' => false, 'extra_flag' => true, 'display' => 'hacked'));
$before = json_encode($in);
$a = t_call($in);
$after = json_encode($in);
t_check($before === $after, 'input never mutated');
t_check(!isset($a->extra_flag), 'extra fields ignored');
t_check($a->display === 'local_qr' && $a->reason === 'ok_local', 'extra fields do not change decision');
$b = t_call(t_payload(array('library_available' => true, 'qr_generated' => true, 'external_image_used' => false, 'extra_flag' => true, 'display' => 'hacked')));
t_check(json_encode($a) === json_encode($b), 'identical input yields identical output');
$c1 = t_call(t_payload(array('has_manual_key' => true, 'has_manual_uri' => true)));
$c2 = t_call(t_payload(array('has_manual_key' => true, 'has_manual_uri' => true)));
t_check(json_encode($c1) === json_encode($c2), 'deterministic manual case');

if ($failures > 0) {
    echo "FAILURES " . $failures . "\n";
    exit(1);
}
echo "ALL PASS\n";
