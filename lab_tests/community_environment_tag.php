<?php
require __DIR__ . '/../src/App/Support/Community/environment_tag.php';

$failures = 0;
$checks = 0;
function expect($cond, $label) {
  global $failures, $checks;
  $checks++;
  if ($cond) {
    echo "ok - " . $label . "\n";
  } else {
    $failures++;
    echo "FAIL - " . $label . "\n";
  }
}
function mk($pairs) {
  $o = new stdClass();
  foreach ($pairs as $k => $v) {
    $o->$k = $v;
  }
  return $o;
}
function runP($p) {
  return \App\Support\Community\EnvironmentTag\runFeature($p);
}
function isDefaultUnknown($out) {
  return $out->raw === "" && $out->normalized === "unknown" && $out->environment_source === "none" && $out->has_environment === false && $out->category === "unknown" && $out->is_live === false && $out->is_known === false;
}

// Empty object defaults
$r = runP(new stdClass());
expect(isDefaultUnknown($r), "empty object yields unknown defaults");

// Non-object payloads yield defaults
foreach ([null, "sandbox", 123, 0, true, false, [], ["environment" => "live"]] as $i => $bad) {
  $o = runP($bad);
  expect(isDefaultUnknown($o), "non-object payload #" . $i . " yields unknown defaults");
}

// Contract examples
$r = runP(mk(["environment" => "sandbox"]));
expect($r->raw === "sandbox", "example sandbox raw");
expect($r->normalized === "test", "example sandbox normalized test");
expect($r->environment_source === "environment", "example sandbox source");
expect($r->has_environment === true, "example sandbox has_environment");
expect($r->category === "non_live", "example sandbox category");
expect($r->is_live === false, "example sandbox is_live false");
expect($r->is_known === true, "example sandbox is_known true");

$r = runP(mk(["ambiente" => "producao"]));
expect($r->raw === "producao" && $r->normalized === "live", "example producao normalized live");
expect($r->environment_source === "ambiente", "example producao source ambiente");
expect($r->category === "live" && $r->is_live === true && $r->is_known === true, "example producao live flags");

$r = runP(new stdClass());
expect($r->raw === "" && $r->normalized === "unknown" && $r->environment_source === "none", "example empty object");

// Priority: environment over ambiente over account_mode
$r = runP(mk(["environment" => "live", "ambiente" => "sandbox", "account_mode" => "sandbox"]));
expect($r->raw === "live" && $r->environment_source === "environment", "priority environment first");
$r = runP(mk(["ambiente" => "producao", "account_mode" => "sandbox"]));
expect($r->raw === "producao" && $r->environment_source === "ambiente", "priority ambiente second");
$r = runP(mk(["account_mode" => "sandbox"]));
expect($r->raw === "sandbox" && $r->environment_source === "account_mode", "priority account_mode third");
$r = runP(mk(["environment" => "sandbox", "extra" => "live"]));
expect($r->raw === "sandbox" && $r->environment_source === "environment", "extra fields ignored");

// Non-string values ignored
$r = runP(mk(["environment" => 123, "ambiente" => "sandbox"]));
expect($r->raw === "sandbox" && $r->environment_source === "ambiente", "non-string environment skipped");
$r = runP(mk(["environment" => null, "ambiente" => 5, "account_mode" => "live"]));
expect($r->raw === "live" && $r->environment_source === "account_mode", "null and int skipped to account_mode");
$r = runP(mk(["environment" => ["x"], "ambiente" => true]));
expect(isDefaultUnknown($r), "array and bool values yield unknown");
$r = runP(mk(["environment" => "", "ambiente" => "live"]));
// empty string is a string so it wins even though unknown
$rx = runP(mk(["environment" => ""]));
expect($rx->raw === "" && $rx->has_environment === true && $rx->normalized === "unknown" && $rx->environment_source === "environment", "empty string counts as present");

// Exact mapping test group
foreach (["test", "teste", "sandbox", "homologacao"] as $v) {
  $o = runP(mk(["environment" => $v]));
  expect($o->normalized === "test" && $o->category === "non_live" && $o->is_live === false && $o->is_known === true, "maps to test: " . $v);
}
foreach (["live", "production", "producao"] as $v) {
  $o = runP(mk(["environment" => $v]));
  expect($o->normalized === "live" && $o->category === "live" && $o->is_live === true && $o->is_known === true, "maps to live: " . $v);
}
foreach (["Test", "TEST", " sandbox", "sandbox ", "Live", "LIVE", "Production", "staging", "homologação", " homologacao", "TESTE"] as $v) {
  $o = runP(mk(["environment" => $v]));
  expect($o->normalized === "unknown" && $o->category === "unknown" && $o->is_live === false && $o->is_known === false, "unknown for inexact: [" . $v . "]");
}

// Output shape only 7 keys with enumerated values
$r = runP(mk(["environment" => "live", "foo" => "bar"]));
$vars = get_object_vars($r);
expect(count($vars) === 7, "output has exactly 7 keys");
$keys = array_keys($vars);
sort($keys);
expect($keys === ["category", "environment_source", "has_environment", "is_known", "is_live", "normalized", "raw"], "output keys exact");
expect(in_array($r->normalized, ["test", "live", "unknown"], true), "normalized enum");
expect(in_array($r->environment_source, ["environment", "ambiente", "account_mode", "none"], true), "source enum");
expect(in_array($r->category, ["non_live", "live", "unknown"], true), "category enum");
expect(is_bool($r->has_environment) && is_bool($r->is_live) && is_bool($r->is_known), "booleans");
expect(is_string($r->raw), "raw string");

// Pure deterministic, no mutation
$in = mk(["environment" => "sandbox", "ambiente" => "live"]);
$before = get_object_vars(clone $in);
$a = runP($in);
$b = runP($in);
expect(get_object_vars($in) == $before, "input not mutated");
expect(get_object_vars($a) == get_object_vars($b), "identical input yields identical output");
$in2 = mk(["ambiente" => "producao"]);
$c1 = runP($in2);
$c2 = runP(mk(["ambiente" => "producao"]));
expect(get_object_vars($c1) == get_object_vars($c2), "deterministic across equal objects");

echo "checks=" . $checks . " failures=" . $failures . "\n";
exit($failures > 0 ? 1 : 0);
