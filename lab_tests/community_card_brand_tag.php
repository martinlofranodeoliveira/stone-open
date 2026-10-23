<?php
require __DIR__ . '/../src/App/Support/Community/card_brand_tag.php';

$failures = 0;

$p1 = new \stdClass;
$p1->brand = 'visa';
$beforeBrand1 = 'visa';
$r1 = \App\Support\Community\CardBrandTag\runFeature($p1);
if ($r1->raw === 'visa') {
  echo "PASS visa raw\n";
} else {
  echo "FAIL visa raw\n";
  $failures = $failures + 1;
}
if ($r1->normalized === 'visa') {
  echo "PASS visa normalized\n";
} else {
  echo "FAIL visa normalized\n";
  $failures = $failures + 1;
}
if ($r1->brand_source === 'brand') {
  echo "PASS visa brand_source\n";
} else {
  echo "FAIL visa brand_source\n";
  $failures = $failures + 1;
}
if ($r1->has_brand === true) {
  echo "PASS visa has_brand\n";
} else {
  echo "FAIL visa has_brand\n";
  $failures = $failures + 1;
}
if ($r1->category === 'international') {
  echo "PASS visa category\n";
} else {
  echo "FAIL visa category\n";
  $failures = $failures + 1;
}
if ($r1->is_known === true) {
  echo "PASS visa is_known\n";
} else {
  echo "FAIL visa is_known\n";
  $failures = $failures + 1;
}
if ($p1->brand === $beforeBrand1) {
  echo "PASS visa preserves input\n";
} else {
  echo "FAIL visa preserves input\n";
  $failures = $failures + 1;
}
$r1b = \App\Support\Community\CardBrandTag\runFeature($p1);
if ($r1b->raw === $r1->raw) {
  echo "PASS visa deterministic raw\n";
} else {
  echo "FAIL visa deterministic raw\n";
  $failures = $failures + 1;
}
if ($r1b->normalized === $r1->normalized) {
  echo "PASS visa deterministic normalized\n";
} else {
  echo "FAIL visa deterministic normalized\n";
  $failures = $failures + 1;
}
if ($r1b->category === $r1->category) {
  echo "PASS visa deterministic category\n";
} else {
  echo "FAIL visa deterministic category\n";
  $failures = $failures + 1;
}

$p2 = new \stdClass;
$p2->bandeira = 'elo';
$r2 = \App\Support\Community\CardBrandTag\runFeature($p2);
if ($r2->raw === 'elo') {
  echo "PASS elo raw\n";
} else {
  echo "FAIL elo raw\n";
  $failures = $failures + 1;
}
if ($r2->normalized === 'elo') {
  echo "PASS elo normalized\n";
} else {
  echo "FAIL elo normalized\n";
  $failures = $failures + 1;
}
if ($r2->brand_source === 'bandeira') {
  echo "PASS elo brand_source\n";
} else {
  echo "FAIL elo brand_source\n";
  $failures = $failures + 1;
}
if ($r2->has_brand === true) {
  echo "PASS elo has_brand\n";
} else {
  echo "FAIL elo has_brand\n";
  $failures = $failures + 1;
}
if ($r2->category === 'domestic') {
  echo "PASS elo category\n";
} else {
  echo "FAIL elo category\n";
  $failures = $failures + 1;
}
if ($r2->is_known === true) {
  echo "PASS elo is_known\n";
} else {
  echo "FAIL elo is_known\n";
  $failures = $failures + 1;
}

$p3 = new \stdClass;
$p3->card_brand = 'generica';
$r3 = \App\Support\Community\CardBrandTag\runFeature($p3);
if ($r3->raw === 'generica') {
  echo "PASS generica raw\n";
} else {
  echo "FAIL generica raw\n";
  $failures = $failures + 1;
}
if ($r3->normalized === 'generic') {
  echo "PASS generica normalized\n";
} else {
  echo "FAIL generica normalized\n";
  $failures = $failures + 1;
}
if ($r3->brand_source === 'card_brand') {
  echo "PASS generica brand_source\n";
} else {
  echo "FAIL generica brand_source\n";
  $failures = $failures + 1;
}
if ($r3->has_brand === true) {
  echo "PASS generica has_brand\n";
} else {
  echo "FAIL generica has_brand\n";
  $failures = $failures + 1;
}
if ($r3->category === 'fallback') {
  echo "PASS generica category\n";
} else {
  echo "FAIL generica category\n";
  $failures = $failures + 1;
}
if ($r3->is_known === true) {
  echo "PASS generica is_known\n";
} else {
  echo "FAIL generica is_known\n";
  $failures = $failures + 1;
}

$p4 = new \stdClass;
$r4 = \App\Support\Community\CardBrandTag\runFeature($p4);
if ($r4->raw === '') {
  echo "PASS empty raw\n";
} else {
  echo "FAIL empty raw\n";
  $failures = $failures + 1;
}
if ($r4->normalized === 'unknown') {
  echo "PASS empty normalized\n";
} else {
  echo "FAIL empty normalized\n";
  $failures = $failures + 1;
}
if ($r4->brand_source === 'none') {
  echo "PASS empty brand_source\n";
} else {
  echo "FAIL empty brand_source\n";
  $failures = $failures + 1;
}
if ($r4->has_brand === false) {
  echo "PASS empty has_brand\n";
} else {
  echo "FAIL empty has_brand\n";
  $failures = $failures + 1;
}
if ($r4->category === 'unknown') {
  echo "PASS empty category\n";
} else {
  echo "FAIL empty category\n";
  $failures = $failures + 1;
}
if ($r4->is_known === false) {
  echo "PASS empty is_known\n";
} else {
  echo "FAIL empty is_known\n";
  $failures = $failures + 1;
}

$p5 = new \stdClass;
$p5->brand = 'Mastercard';
$p5->extra = 'ignore-me';
$r5 = \App\Support\Community\CardBrandTag\runFeature($p5);
if ($r5->raw === 'Mastercard') {
  echo "PASS case raw\n";
} else {
  echo "FAIL case raw\n";
  $failures = $failures + 1;
}
if ($r5->normalized === 'unknown') {
  echo "PASS case normalized\n";
} else {
  echo "FAIL case normalized\n";
  $failures = $failures + 1;
}
if ($r5->category === 'unknown') {
  echo "PASS case category\n";
} else {
  echo "FAIL case category\n";
  $failures = $failures + 1;
}
if ($r5->is_known === false) {
  echo "PASS case is_known\n";
} else {
  echo "FAIL case is_known\n";
  $failures = $failures + 1;
}
if ($r5->brand_source === 'brand') {
  echo "PASS case brand_source\n";
} else {
  echo "FAIL case brand_source\n";
  $failures = $failures + 1;
}
if ($r5->has_brand === true) {
  echo "PASS case has_brand\n";
} else {
  echo "FAIL case has_brand\n";
  $failures = $failures + 1;
}
if ($p5->extra === 'ignore-me') {
  echo "PASS case extra preserved\n";
} else {
  echo "FAIL case extra preserved\n";
  $failures = $failures + 1;
}
if ($p5->brand === 'Mastercard') {
  echo "PASS case input unmutated\n";
} else {
  echo "FAIL case input unmutated\n";
  $failures = $failures + 1;
}
$r5b = \App\Support\Community\CardBrandTag\runFeature($p5);
if ($r5b->normalized === $r5->normalized) {
  echo "PASS case deterministic\n";
} else {
  echo "FAIL case deterministic\n";
  $failures = $failures + 1;
}

if ($failures > 0) {
  echo "FAILURES\n";
  exit(1);
}
echo "ALL OK\n";
