<?php
require __DIR__ . '/../src/App/Support/Community/synthetic_amount_precision_gate.php';
$failures = 0;
function checkEq($actual, $expected, $msg) {
  global $failures;
  if ($actual !== $expected) {
    echo "FAIL " . $msg . " expected=" . var_export($expected, true) . " actual=" . var_export($actual, true) . "\n";
    $failures++;
  } else {
    echo "PASS " . $msg . "\n";
  }
}
$p = new \stdClass;
$r = \App\Support\Community\SyntheticAmountPrecisionGate\runFeature($p);
checkEq($r->has_amount, false, "empty has_amount");
checkEq($r->has_decimals, false, "empty has_decimals");
checkEq($r->raw_amount, 0, "empty raw_amount");
checkEq($r->raw_decimals, 0, "empty raw_decimals");
checkEq($r->amount_source, "none", "empty amount_source");
checkEq($r->decimals_source, "none", "empty decimals_source");
checkEq($r->range_ok, false, "empty range_ok");
checkEq($r->decimals_ok, false, "empty decimals_ok");
checkEq($r->is_precise, false, "empty is_precise");
checkEq($r->status, "imprecise", "empty status");
$p2 = new \stdClass;
$p2->amount_cents = 4200;
$p2->decimals = 2;
$r2 = \App\Support\Community\SyntheticAmountPrecisionGate\runFeature($p2);
checkEq($r2->amount_source, "amount_cents", "4200 amount_source");
checkEq($r2->decimals_source, "decimals", "4200 decimals_source");
checkEq($r2->raw_amount, 4200, "4200 raw_amount");
checkEq($r2->raw_decimals, 2, "4200 raw_decimals");
checkEq($r2->has_amount, true, "4200 has_amount");
checkEq($r2->has_decimals, true, "4200 has_decimals");
checkEq($r2->range_ok, true, "4200 range_ok");
checkEq($r2->decimals_ok, true, "4200 decimals_ok");
checkEq($r2->is_precise, true, "4200 is_precise");
checkEq($r2->status, "precise", "4200 status");
$p3 = new \stdClass;
$p3->valor_centavos = -42;
$p3->casas = 0;
$r3 = \App\Support\Community\SyntheticAmountPrecisionGate\runFeature($p3);
checkEq($r3->amount_source, "valor_centavos", "valor amount_source");
checkEq($r3->decimals_source, "casas", "valor decimals_source");
checkEq($r3->raw_amount, -42, "valor raw_amount");
checkEq($r3->raw_decimals, 0, "valor raw_decimals");
checkEq($r3->range_ok, true, "valor range_ok");
checkEq($r3->decimals_ok, true, "valor decimals_ok");
checkEq($r3->is_precise, true, "valor is_precise");
checkEq($r3->status, "precise", "valor status");
$p4 = new \stdClass;
$p4->amount_cents = 100000000;
$p4->decimals = 2;
$r4 = \App\Support\Community\SyntheticAmountPrecisionGate\runFeature($p4);
checkEq($r4->raw_amount, 100000000, "oor raw_amount");
checkEq($r4->raw_decimals, 2, "oor raw_decimals");
checkEq($r4->range_ok, false, "oor range_ok");
checkEq($r4->is_precise, false, "oor is_precise");
checkEq($r4->status, "imprecise", "oor status");
$p5 = new \stdClass;
$p5->amount_cents = 100;
$p5->decimals = 3;
$p5->extra = "ignore-me";
$before = json_encode($p5);
$r5 = \App\Support\Community\SyntheticAmountPrecisionGate\runFeature($p5);
$after = json_encode($p5);
checkEq($r5->decimals_ok, false, "d3 decimals_ok");
checkEq($r5->is_precise, false, "d3 is_precise");
checkEq($r5->status, "imprecise", "d3 status");
checkEq($r5->raw_amount, 100, "d3 raw_amount");
checkEq($r5->raw_decimals, 3, "d3 raw_decimals");
checkEq($after, $before, "input not mutated");
checkEq(isset($r5->extra), false, "extra ignored");
$r6 = \App\Support\Community\SyntheticAmountPrecisionGate\runFeature(null);
checkEq($r6->status, "imprecise", "null status");
checkEq($r6->has_amount, false, "null has_amount");
if ($failures > 0) {
  echo "FAILURES: " . $failures . "\n";
  exit(1);
}
echo "ALL PASS\n";
