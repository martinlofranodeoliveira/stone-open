<?php
require __DIR__ . '/../src/App/Support/Community/list_sort_tag.php';

$passes = 0;
$failures = 0;

function ok($cond, $msg) {
  global $passes, $failures;
  if ($cond) { $passes++; echo "PASS: " . $msg . "\n"; }
  else { $failures++; echo "FAIL: " . $msg . "\n"; }
}

function eq($a, $b, $msg) {
  global $passes, $failures;
  if ($a === $b) { $passes++; echo "PASS: " . $msg . "\n"; }
  else { $failures++; echo "FAIL: " . $msg . " expected=" . var_export($b, true) . " got=" . var_export($a, true) . "\n"; }
}

function runTag($payload) {
  return \App\Support\Community\ListSortTag\runFeature($payload);
}

function expectTag($payload, $exp, $msg) {
  $out = runTag($payload);
  ok(is_object($out), $msg . " returns object");
  $vars = get_object_vars($out);
  $keys = array_keys($vars);
  sort($keys);
  $want = array("direction", "field", "has_sort", "is_known", "normalized", "raw", "sort_source");
  ok($keys === $want, $msg . " exact keys");
  foreach ($exp as $k => $v) {
    eq($out->$k, $v, $msg . " field " . $k);
  }
}

// Contract examples
$p = (object)array("sort" => "recent");
expectTag($p, array("raw" => "recent", "normalized" => "recent", "sort_source" => "sort", "has_sort" => true, "field" => "date", "direction" => "descending", "is_known" => true), "example sort=recent");

$p = (object)array("ordenacao" => "valor_desc");
expectTag($p, array("raw" => "valor_desc", "normalized" => "amount_high", "sort_source" => "ordenacao", "has_sort" => true, "field" => "amount", "direction" => "descending", "is_known" => true), "example ordenacao=valor_desc");

$p = new \stdClass;
expectTag($p, array("raw" => "", "normalized" => "unknown", "sort_source" => "none", "has_sort" => false, "field" => "unknown", "direction" => "unknown", "is_known" => false), "example empty");

// Full literal mapping via sort
$cases = array(
  array("recent", "recent", "date", "descending"),
  array("recentes", "recent", "date", "descending"),
  array("newest", "recent", "date", "descending"),
  array("oldest", "oldest", "date", "ascending"),
  array("mais_antigos", "oldest", "date", "ascending"),
  array("amount_desc", "amount_high", "amount", "descending"),
  array("valor_desc", "amount_high", "amount", "descending"),
  array("maior_valor", "amount_high", "amount", "descending"),
  array("amount_asc", "amount_low", "amount", "ascending"),
  array("valor_asc", "amount_low", "amount", "ascending"),
  array("menor_valor", "amount_low", "amount", "ascending"),
  array("name_asc", "name_az", "name", "ascending"),
  array("nome_asc", "name_az", "name", "ascending"),
  array("nome_az", "name_az", "name", "ascending"),
  array("name_desc", "name_za", "name", "descending"),
  array("nome_desc", "name_za", "name", "descending"),
  array("nome_za", "name_za", "name", "descending")
);
foreach ($cases as $c) {
  $p = (object)array("sort" => $c[0]);
  expectTag($p, array("raw" => $c[0], "normalized" => $c[1], "sort_source" => "sort", "has_sort" => true, "field" => $c[2], "direction" => $c[3], "is_known" => true), "map " . $c[0]);
}

// Alternate sources
$p = (object)array("ordenacao" => "mais_antigos");
expectTag($p, array("raw" => "mais_antigos", "normalized" => "oldest", "sort_source" => "ordenacao", "has_sort" => true, "field" => "date", "direction" => "ascending", "is_known" => true), "ordenacao source");

$p = (object)array("order_by" => "nome_az");
expectTag($p, array("raw" => "nome_az", "normalized" => "name_az", "sort_source" => "order_by", "has_sort" => true, "field" => "name", "direction" => "ascending", "is_known" => true), "order_by source");

// Precedence: sort wins
$p = (object)array("sort" => "oldest", "ordenacao" => "valor_desc", "order_by" => "nome_az");
$out = runTag($p);
eq($out->raw, "oldest", "precedence sort wins raw");
eq($out->sort_source, "sort", "precedence sort wins source");
eq($out->normalized, "oldest", "precedence sort wins normalized");

// Precedence: ordenacao wins when sort missing
$p = (object)array("ordenacao" => "valor_desc", "order_by" => "nome_az");
$out = runTag($p);
eq($out->raw, "valor_desc", "precedence ordenacao wins");
eq($out->sort_source, "ordenacao", "precedence ordenacao source");

// Precedence: non-string sort falls through
$p = (object)array("sort" => 123, "ordenacao" => "valor_desc");
$out = runTag($p);
eq($out->raw, "valor_desc", "non-string sort falls through");
eq($out->sort_source, "ordenacao", "non-string sort source falls through");

$p = (object)array("sort" => 123, "ordenacao" => 456, "order_by" => "newest");
$out = runTag($p);
eq($out->raw, "newest", "double non-string falls to order_by");
eq($out->sort_source, "order_by", "double non-string source");

// Unknown / missing / non-string
$p = (object)array("sort" => "Recent");
expectTag($p, array("raw" => "Recent", "normalized" => "unknown", "sort_source" => "sort", "has_sort" => true, "field" => "unknown", "direction" => "unknown", "is_known" => false), "case-sensitive Recent");

$p = (object)array("sort" => " recent");
$out = runTag($p);
eq($out->normalized, "unknown", "no trimming leading space");

$p = (object)array("sort" => "recent ");
$out = runTag($p);
eq($out->normalized, "unknown", "no trimming trailing space");

$p = (object)array("sort" => "bogus");
expectTag($p, array("raw" => "bogus", "normalized" => "unknown", "sort_source" => "sort", "has_sort" => true, "field" => "unknown", "direction" => "unknown", "is_known" => false), "unknown literal");

$p = (object)array("sort" => "");
$out = runTag($p);
eq($out->has_sort, true, "empty string is still has_sort");
eq($out->normalized, "unknown", "empty string unknown");
eq($out->is_known, false, "empty string not known");

foreach (array(123, null, true, array("recent")) as $i => $bad) {
  $p = (object)array("sort" => $bad);
  $out = runTag($p);
  eq($out->has_sort, false, "non-string sort #" . $i . " has_sort false");
  eq($out->raw, "", "non-string sort #" . $i . " raw empty");
  eq($out->normalized, "unknown", "non-string sort #" . $i . " unknown");
  eq($out->sort_source, "none", "non-string sort #" . $i . " source none");
  eq($out->is_known, false, "non-string sort #" . $i . " not known");
}

// Non-object payloads behave as empty
foreach (array(null, "recent", 123, array("sort" => "recent"), true) as $i => $badPayload) {
  $out = runTag($badPayload);
  eq($out->has_sort, false, "non-object #" . $i . " has_sort false");
  eq($out->raw, "", "non-object #" . $i . " raw empty");
  eq($out->sort_source, "none", "non-object #" . $i . " source none");
  eq($out->normalized, "unknown", "non-object #" . $i . " unknown");
}

// Extra fields ignored, output has no extras
$p = (object)array("sort" => "recent", "foo" => "bar", "amount" => 5, "extra" => array(1, 2));
$out = runTag($p);
eq($out->normalized, "recent", "extra fields ignored normalized");
$vars = get_object_vars($out);
eq(count($vars), 7, "output has exactly 7 fields");

// Determinism + no mutation
$p = (object)array("sort" => "valor_desc", "other" => "x");
$before = json_encode($p);
$a = runTag($p);
$b = runTag($p);
eq(json_encode($a), json_encode($b), "repeated calls identical");
eq(json_encode($p), $before, "input not mutated");

// Enum validation
$allowedNorm = array("recent" => true, "oldest" => true, "amount_high" => true, "amount_low" => true, "name_az" => true, "name_za" => true, "unknown" => true);
$allowedSrc = array("sort" => true, "ordenacao" => true, "order_by" => true, "none" => true);
$allowedField = array("date" => true, "amount" => true, "name" => true, "unknown" => true);
$allowedDir = array("descending" => true, "ascending" => true, "unknown" => true);
$probe = array((object)array("sort" => "recent"), (object)array("order_by" => "menor_valor"), new \stdClass, (object)array("sort" => "nope"));
foreach ($probe as $i => $pp) {
  $o = runTag($pp);
  ok(isset($allowedNorm[$o->normalized]), "enum normalized #" . $i);
  ok(isset($allowedSrc[$o->sort_source]), "enum sort_source #" . $i);
  ok(isset($allowedField[$o->field]), "enum field #" . $i);
  ok(isset($allowedDir[$o->direction]), "enum direction #" . $i);
  ok(is_string($o->raw), "raw is string #" . $i);
  ok(is_bool($o->has_sort), "has_sort is bool #" . $i);
  ok(is_bool($o->is_known), "is_known is bool #" . $i);
}

echo "\nDone passes=" . $passes . " failures=" . $failures . "\n";
if ($failures > 0) { exit(1); }
