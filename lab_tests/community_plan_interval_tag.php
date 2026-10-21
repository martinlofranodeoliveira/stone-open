<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/App/Support/Community/plan_interval_tag.php';
$failures = 0;
$total = 0;
function checkOut($out) {
  if (!($out instanceof \stdClass)) {
    return false;
  }
  $vars = get_object_vars($out);
  if (count($vars) !== 7) {
    return false;
  }
  if (!isset($out->raw)) {
    return false;
  }
  if (!isset($out->normalized)) {
    return false;
  }
  if (!isset($out->interval_source)) {
    return false;
  }
  if (!isset($out->has_interval)) {
    return false;
  }
  if (!isset($out->cycle_days)) {
    return false;
  }
  if (!isset($out->category)) {
    return false;
  }
  if (!isset($out->is_known)) {
    return false;
  }
  if (!is_string($out->raw)) {
    return false;
  }
  if ($out->normalized !== 'daily' && $out->normalized !== 'weekly' && $out->normalized !== 'monthly' && $out->normalized !== 'yearly' && $out->normalized !== 'unknown') {
    return false;
  }
  if ($out->interval_source !== 'interval' && $out->interval_source !== 'periodo' && $out->interval_source !== 'billing_interval' && $out->interval_source !== 'none') {
    return false;
  }
  if (!is_bool($out->has_interval)) {
    return false;
  }
  if ($out->cycle_days !== 0 && $out->cycle_days !== 1 && $out->cycle_days !== 7 && $out->cycle_days !== 30 && $out->cycle_days !== 365) {
    return false;
  }
  if ($out->category !== 'short' && $out->category !== 'standard' && $out->category !== 'long' && $out->category !== 'unknown') {
    return false;
  }
  if (!is_bool($out->is_known)) {
    return false;
  }
  return true;
}
$total++;
$p = new \stdClass();
$before = json_encode($p);
$r = \App\Support\Community\PlanIntervalTag\runFeature($p);
if ($r->raw === '' && $r->normalized === 'unknown' && $r->interval_source === 'none' && $r->has_interval === false && $r->cycle_days === 0 && $r->category === 'unknown' && $r->is_known === false && checkOut($r) && json_encode($p) === $before) {
  echo "ok empty\n";
} else {
  $failures++;
  echo "FAIL empty " . json_encode($r) . "\n";
}
$total++;
$p2 = new \stdClass();
$p2->interval = 'monthly';
$r = \App\Support\Community\PlanIntervalTag\runFeature($p2);
if ($r->raw === 'monthly' && $r->normalized === 'monthly' && $r->interval_source === 'interval' && $r->has_interval === true && $r->cycle_days === 30 && $r->category === 'standard' && $r->is_known === true && checkOut($r)) {
  echo "ok monthly\n";
} else {
  $failures++;
  echo "FAIL monthly " . json_encode($r) . "\n";
}
$total++;
$p3 = new \stdClass();
$p3->periodo = 'semanal';
$r = \App\Support\Community\PlanIntervalTag\runFeature($p3);
if ($r->raw === 'semanal' && $r->normalized === 'weekly' && $r->interval_source === 'periodo' && $r->cycle_days === 7 && $r->category === 'short' && $r->is_known === true && checkOut($r)) {
  echo "ok semanal\n";
} else {
  $failures++;
  echo "FAIL semanal " . json_encode($r) . "\n";
}
$total++;
$cases = [['daily', 'daily', 1, 'short'], ['diaria', 'daily', 1, 'short'], ['weekly', 'weekly', 7, 'short'], ['semanal', 'weekly', 7, 'short'], ['monthly', 'monthly', 30, 'standard'], ['mensal', 'monthly', 30, 'standard'], ['yearly', 'yearly', 365, 'long'], ['anual', 'yearly', 365, 'long'], ['annual', 'yearly', 365, 'long']];
$ok = true;
foreach ($cases as $c) {
  $pp = new \stdClass();
  $pp->interval = $c[0];
  $rr = \App\Support\Community\PlanIntervalTag\runFeature($pp);
  if (!($rr->raw === $c[0] && $rr->normalized === $c[1] && $rr->cycle_days === $c[2] && $rr->category === $c[3] && $rr->is_known === true && checkOut($rr))) {
    $ok = false;
    echo "FAIL map " . $c[0] . " " . json_encode($rr) . "\n";
  }
}
if ($ok) {
  echo "ok mappings\n";
} else {
  $failures++;
}
$total++;
$pp = new \stdClass();
$pp->interval = 'daily';
$pp->periodo = 'monthly';
$pp->billing_interval = 'yearly';
$r = \App\Support\Community\PlanIntervalTag\runFeature($pp);
if ($r->raw === 'daily' && $r->interval_source === 'interval' && $r->normalized === 'daily') {
  echo "ok priority-interval\n";
} else {
  $failures++;
  echo "FAIL priority-interval " . json_encode($r) . "\n";
}
$total++;
$pp = new \stdClass();
$pp->periodo = 'monthly';
$pp->billing_interval = 'yearly';
$r = \App\Support\Community\PlanIntervalTag\runFeature($pp);
if ($r->raw === 'monthly' && $r->interval_source === 'periodo' && $r->normalized === 'monthly') {
  echo "ok priority-periodo\n";
} else {
  $failures++;
  echo "FAIL priority-periodo " . json_encode($r) . "\n";
}
$total++;
$pp = new \stdClass();
$pp->billing_interval = 'anual';
$r = \App\Support\Community\PlanIntervalTag\runFeature($pp);
if ($r->raw === 'anual' && $r->interval_source === 'billing_interval' && $r->normalized === 'yearly' && $r->cycle_days === 365 && $r->category === 'long') {
  echo "ok priority-billing\n";
} else {
  $failures++;
  echo "FAIL priority-billing " . json_encode($r) . "\n";
}
$total++;
$pp = new \stdClass();
$pp->interval = 123;
$pp->periodo = 'mensal';
$r = \App\Support\Community\PlanIntervalTag\runFeature($pp);
if ($r->raw === 'mensal' && $r->interval_source === 'periodo' && $r->normalized === 'monthly') {
  echo "ok nonstring-skipped\n";
} else {
  $failures++;
  echo "FAIL nonstring-skipped " . json_encode($r) . "\n";
}
$total++;
$pp = new \stdClass();
$pp->interval = null;
$pp->periodo = 7;
$pp->billing_interval = [1];
$r = \App\Support\Community\PlanIntervalTag\runFeature($pp);
if ($r->raw === '' && $r->normalized === 'unknown' && $r->interval_source === 'none' && $r->has_interval === false && $r->cycle_days === 0 && $r->category === 'unknown' && $r->is_known === false) {
  echo "ok all-nonstrings\n";
} else {
  $failures++;
  echo "FAIL all-nonstrings " . json_encode($r) . "\n";
}
$total++;
$bad = ['fortnightly', 'Monthly', 'MONTHLY', ' monthly', 'monthly ', 'Daily', ''];
$ok2 = true;
foreach ($bad as $b) {
  $pp = new \stdClass();
  $pp->interval = $b;
  $rr = \App\Support\Community\PlanIntervalTag\runFeature($pp);
  if (!($rr->raw === $b && $rr->normalized === 'unknown' && $rr->cycle_days === 0 && $rr->category === 'unknown' && $rr->is_known === false && $rr->has_interval === true)) {
    $ok2 = false;
    echo "FAIL unknown " . json_encode($b) . " " . json_encode($rr) . "\n";
  }
}
if ($ok2) {
  echo "ok unknown-variants\n";
} else {
  $failures++;
}
$total++;
$pp = new \stdClass();
$pp->interval = 'monthly';
$pp->extra = 'keep';
$before = json_encode($pp);
$r = \App\Support\Community\PlanIntervalTag\runFeature($pp);
if ($r->normalized === 'monthly' && checkOut($r) && json_encode($pp) === $before) {
  echo "ok extra-ignored\n";
} else {
  $failures++;
  echo "FAIL extra-ignored " . json_encode($r) . "\n";
}
$total++;
$inputs = [null, 'monthly', 123, [], 4.5, true];
$ok3 = true;
foreach ($inputs as $ni) {
  $rr = \App\Support\Community\PlanIntervalTag\runFeature($ni);
  if (!($rr->raw === '' && $rr->normalized === 'unknown' && $rr->interval_source === 'none' && $rr->has_interval === false && $rr->cycle_days === 0 && $rr->category === 'unknown' && $rr->is_known === false && checkOut($rr))) {
    $ok3 = false;
    echo "FAIL nonobject " . json_encode($rr) . "\n";
  }
}
if ($ok3) {
  echo "ok nonobject\n";
} else {
  $failures++;
}
$total++;
$pa = new \stdClass();
$pa->periodo = 'diaria';
$pb = new \stdClass();
$pb->periodo = 'diaria';
$a = \App\Support\Community\PlanIntervalTag\runFeature($pa);
$b = \App\Support\Community\PlanIntervalTag\runFeature($pb);
if (json_encode($a) === json_encode($b) && $a->normalized === 'daily' && $a->cycle_days === 1 && $a->category === 'short' && checkOut($a) && checkOut($b)) {
  echo "ok deterministic\n";
} else {
  $failures++;
  echo "FAIL deterministic\n";
}
if ($failures === 0) {
  echo "PASS all " . $total . " checks\n";
  exit(0);
} else {
  echo "FAIL " . $failures . "/" . $total . "\n";
  exit(1);
}
