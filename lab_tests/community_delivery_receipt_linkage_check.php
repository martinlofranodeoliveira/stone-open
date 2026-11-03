<?php
require __DIR__ . '/../src/App/Support/Community/delivery_receipt_linkage_check.php';

$fails = 0;
function expect($cond, $msg) {
  global $fails;
  if ($cond) {
    echo "OK " . $msg . "\n";
  } else {
    echo "FAIL " . $msg . "\n";
    $fails = $fails + 1;
  }
}
function mk($trace, $event, $client) {
  $o = new \stdClass;
  if ($trace !== null) { $o->trace_id = $trace; }
  if ($event !== null) { $o->event_ref = $event; }
  if ($client !== null) { $o->client_ref = $client; }
  return $o;
}
function runP($p) {
  return \App\Support\Community\DeliveryReceiptLinkageCheck\runFeature($p);
}

$r = runP(new \stdClass);
expect($r->trace_id === "", "empty trace_id");
expect($r->event_ref === "", "empty event_ref");
expect($r->client_ref === "", "empty client_ref");
expect($r->has_trace === false, "empty has_trace false");
expect($r->has_event === false, "empty has_event false");
expect($r->has_client === false, "empty has_client false");
expect($r->present_count === 0, "empty present_count 0");
expect($r->linked === false, "empty linked false");
expect($r->has_duplicate === false, "empty has_duplicate false");
expect($r->is_unique === false, "empty is_unique false");
expect($r->needs_review === true, "empty needs_review true");

$in = new \stdClass;
$before = json_encode($in);
$r2 = runP($in);
expect(json_encode($in) === $before, "empty input unmutated");

$r = runP(mk("T1", "E1", "C1"));
expect($r->trace_id === "T1", "distinct trace preserved");
expect($r->event_ref === "E1", "distinct event preserved");
expect($r->client_ref === "C1", "distinct client preserved");
expect($r->has_trace === true, "distinct has_trace");
expect($r->has_event === true, "distinct has_event");
expect($r->has_client === true, "distinct has_client");
expect($r->present_count === 3, "distinct present_count 3");
expect($r->linked === true, "distinct linked");
expect($r->has_duplicate === false, "distinct no duplicate");
expect($r->is_unique === true, "distinct is_unique");
expect($r->needs_review === false, "distinct no review");

$r = runP(mk("R7", "R7", "C9"));
expect($r->present_count === 3, "trace=event count 3");
expect($r->linked === true, "trace=event linked");
expect($r->has_duplicate === true, "trace=event duplicate");
expect($r->is_unique === false, "trace=event not unique");
expect($r->needs_review === true, "trace=event review");

$r = runP(mk("X1", "E2", "X1"));
expect($r->has_duplicate === true, "trace=client duplicate");
expect($r->is_unique === false, "trace=client not unique");
expect($r->needs_review === true, "trace=client review");

$r = runP(mk("T5", "D4", "D4"));
expect($r->has_duplicate === true, "event=client duplicate");
expect($r->is_unique === false, "event=client not unique");
expect($r->needs_review === true, "event=client review");

$r = runP(mk("SAME", "SAME", "SAME"));
expect($r->has_duplicate === true, "all equal duplicate");
expect($r->is_unique === false, "all equal not unique");
expect($r->needs_review === true, "all equal review");

$r = runP(mk("", "", ""));
expect($r->has_trace === false, "empty strings missing trace");
expect($r->has_event === false, "empty strings missing event");
expect($r->has_client === false, "empty strings missing client");
expect($r->present_count === 0, "empty strings count 0");
expect($r->has_duplicate === false, "empty strings never match");
expect($r->linked === false, "empty strings not linked");

$p = new \stdClass;
$p->trace_id = 123;
$p->event_ref = null;
$p->client_ref = array("C1");
$r = runP($p);
expect($r->trace_id === "", "non-string trace missing");
expect($r->event_ref === "", "non-string event missing");
expect($r->client_ref === "", "non-string client missing");
expect($r->present_count === 0, "non-string count 0");
expect($r->has_duplicate === false, "non-string no duplicate");

$p = new \stdClass;
$p->trace_id = "A";
$p->event_ref = "a";
$p->client_ref = "C1";
$r = runP($p);
expect($r->has_duplicate === false, "case sensitive no match");
expect($r->is_unique === true, "case sensitive unique");

$p = new \stdClass;
$p->trace_id = "A";
$p->event_ref = "A ";
$p->client_ref = "C1";
$r = runP($p);
expect($r->has_duplicate === false, "no trimming");

$p = new \stdClass;
$p->trace_id = "T1";
$p->event_ref = "E1";
$p->extra = "IGNORED";
$r = runP($p);
expect($r->trace_id === "T1", "partial trace kept");
expect($r->has_client === false, "partial missing client");
expect($r->present_count === 2, "partial count 2");
expect($r->linked === false, "partial not linked");
expect($r->has_duplicate === false, "partial no duplicate");
expect($r->is_unique === false, "partial not unique");
expect($r->needs_review === true, "partial review");
expect(isset($r->extra) === false, "extra ignored in output");

$p = new \stdClass;
$p->trace_id = "T1";
$p->event_ref = "E1";
$p->client_ref = "C1";
$p->extra_field = "X";
$before = json_encode($p);
$r = runP($p);
expect(json_encode($p) === $before, "input unmutated with extra");
$a = runP($p);
$b = runP($p);
expect(json_encode($a) === json_encode($b), "deterministic output");

$r = runP(null);
expect($r->present_count === 0, "null behaves as empty");
expect($r->needs_review === true, "null needs review");
$r = runP("nope");
expect($r->present_count === 0, "string behaves as empty");
$r = runP(42);
expect($r->present_count === 0, "int behaves as empty");
$r = runP(array("trace_id" => "T1"));
expect($r->present_count === 0, "array behaves as empty");

if ($fails === 0) {
  echo "PASS community_delivery_receipt_linkage_check\n";
} else {
  echo "FAILURES " . $fails . "\n";
  exit(1);
}
