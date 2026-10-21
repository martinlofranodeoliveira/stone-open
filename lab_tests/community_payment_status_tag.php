<?php
require __DIR__ . '/../src/App/Support/Community/payment_status_tag.php';

function expectTag($cond, $msg) {
  if (!$cond) {
    echo "FAIL ". $msg ."\n";
    exit(1);
  }
  echo "PASS ". $msg ."\n";
}

function expectTagEq($actual, $expected, $msg) {
  if ($actual !== $expected) {
    echo "FAIL ". $msg ." expected ". var_export($expected, true) ." got ". var_export($actual, true) ."\n";
    exit(1);
  }
  echo "PASS ". $msg ."\n";
}

function mkTag($pairs) {
  $o = new \stdClass;
  foreach ($pairs as $k => $v) {
    $o->$k = $v;
  }
  return $o;
}

function runTag($payload) {
  return \App\Support\Community\PaymentStatusTag\runFeature($payload);
}

function checkTag($payload, $expRaw, $expNorm, $expSrc, $expHas, $expCat, $expTerm, $expSucc, $expKnown, $msg) {
  $r = runTag($payload);
  expectTag($r instanceof \stdClass, $msg . ' is object');
  expectTagEq($r->raw, $expRaw, $msg . ' raw');
  expectTagEq($r->normalized, $expNorm, $msg . ' normalized');
  expectTagEq($r->status_source, $expSrc, $msg . ' source');
  expectTagEq($r->has_status, $expHas, $msg . ' has_status');
  expectTagEq($r->category, $expCat, $msg . ' category');
  expectTagEq($r->is_terminal, $expTerm, $msg . ' is_terminal');
  expectTagEq($r->is_success, $expSucc, $msg . ' is_success');
  expectTagEq($r->is_known, $expKnown, $msg . ' is_known');
}

checkTag(new \stdClass, "", "unknown", "none", false, "unknown", false, false, false, 'empty object');
checkTag(null, "", "unknown", "none", false, "unknown", false, false, false, 'null payload');
checkTag("paid", "", "unknown", "none", false, "unknown", false, false, false, 'string payload');
checkTag(123, "", "unknown", "none", false, "unknown", false, false, false, 'int payload');
checkTag(false, "", "unknown", "none", false, "unknown", false, false, false, 'bool payload');
checkTag(array("status" => "paid"), "", "unknown", "none", false, "unknown", false, false, false, 'array payload');

checkTag(mkTag(array('status' => 'paid')), 'paid', 'paid', 'status', true, 'success', true, true, true, 'example status paid');
checkTag(mkTag(array('situacao' => 'pendente')), 'pendente', 'pending', 'situacao', true, 'open', false, false, true, 'example situacao pendente');

checkTag(mkTag(array('status' => 'paid', 'situacao' => 'pendente', 'payment_status' => 'failed')), 'paid', 'paid', 'status', true, 'success', true, true, true, 'precedence status wins');
checkTag(mkTag(array('situacao' => 'pago', 'payment_status' => 'falha')), 'pago', 'paid', 'situacao', true, 'success', true, true, true, 'precedence situacao wins');
checkTag(mkTag(array('payment_status' => 'failed')), 'failed', 'failed', 'payment_status', true, 'failure', true, false, true, 'payment_status fallback');
checkTag(mkTag(array('status' => 123, 'situacao' => 'paga')), 'paga', 'paid', 'situacao', true, 'success', true, true, true, 'skip non-string status');
checkTag(mkTag(array('status' => null, 'situacao' => 7, 'payment_status' => 'reembolsado')), 'reembolsado', 'refunded', 'payment_status', true, 'reversal', true, false, true, 'skip null and int');
checkTag(mkTag(array('status' => array('x'))), '', 'unknown', 'none', false, 'unknown', false, false, false, 'non-string only yields none');

checkTag(mkTag(array('status' => 'pending')), 'pending', 'pending', 'status', true, 'open', false, false, true, 'alias pending');
checkTag(mkTag(array('status' => 'pendente')), 'pendente', 'pending', 'status', true, 'open', false, false, true, 'alias pendente');
checkTag(mkTag(array('status' => 'pago')), 'pago', 'paid', 'status', true, 'success', true, true, true, 'alias pago');
checkTag(mkTag(array('status' => 'paga')), 'paga', 'paid', 'status', true, 'success', true, true, true, 'alias paga');
checkTag(mkTag(array('status' => 'falha')), 'falha', 'failed', 'status', true, 'failure', true, false, true, 'alias falha');
checkTag(mkTag(array('status' => 'refunded')), 'refunded', 'refunded', 'status', true, 'reversal', true, false, true, 'alias refunded');
checkTag(mkTag(array('status' => 'reembolsado')), 'reembolsado', 'refunded', 'status', true, 'reversal', true, false, true, 'alias reembolsado');
checkTag(mkTag(array('status' => 'canceled')), 'canceled', 'canceled', 'status', true, 'reversal', true, false, true, 'alias canceled');
checkTag(mkTag(array('status' => 'cancelled')), 'cancelled', 'canceled', 'status', true, 'reversal', true, false, true, 'alias cancelled');
checkTag(mkTag(array('status' => 'cancelado')), 'cancelado', 'canceled', 'status', true, 'reversal', true, false, true, 'alias cancelado');
checkTag(mkTag(array('status' => 'Paid')), 'Paid', 'unknown', 'status', true, 'unknown', false, false, false, 'case sensitive Paid');
checkTag(mkTag(array('status' => 'PAGO')), 'PAGO', 'unknown', 'status', true, 'unknown', false, false, false, 'case sensitive PAGO');
checkTag(mkTag(array('status' => ' paid')), ' paid', 'unknown', 'status', true, 'unknown', false, false, false, 'no trimming leading');
checkTag(mkTag(array('status' => 'paid ')), 'paid ', 'unknown', 'status', true, 'unknown', false, false, false, 'no trimming trailing');
checkTag(mkTag(array('status' => 'other')), 'other', 'unknown', 'status', true, 'unknown', false, false, false, 'unknown string');
checkTag(mkTag(array('status' => '')), '', 'unknown', 'status', true, 'unknown', false, false, false, 'empty string is known string');

$a = mkTag(array('status' => 'paid', 'extra' => 'keep', 'amount' => 5));
checkTag($a, 'paid', 'paid', 'status', true, 'success', true, true, true, 'extra fields ignored');

$in = mkTag(array('situacao' => 'falha', 'note' => 'x'));
$before = json_encode($in);
$r1 = runTag($in);
$r2 = runTag(mkTag(array('situacao' => 'falha', 'note' => 'x')));
$after = json_encode($in);
expectTagEq($before, $after, 'input never mutated');
expectTagEq(json_encode($r1), json_encode($r2), 'deterministic identical output');

$in2 = mkTag(array('payment_status' => 'cancelado'));
$b2 = json_encode($in2);
runTag($in2);
runTag($in2);
expectTagEq(json_encode($in2), $b2, 'repeat calls preserve input');

echo "DONE community_payment_status_tag\n";
