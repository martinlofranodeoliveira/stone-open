<?php
require __DIR__ . '/../src/App/Support/Community/payment_link_status_tag.php';

$passes = 0;
$failures = 0;

function checkTag($cond, $msg) {
  global $passes, $failures;
  if ($cond) {
    $passes++;
    echo "PASS: " . $msg . "\n";
  } else {
    $failures++;
    echo "FAIL: " . $msg . "\n";
  }
}

function makePayload(array $map) {
  $o = new stdClass;
  foreach ($map as $k => $v) {
    $o->$k = $v;
  }
  return $o;
}

function callTag($p) {
  return \App\Support\Community\PaymentLinkStatusTag\runFeature($p);
}

function expectTag($payload, $raw, $normalized, $source, $has, $cat, $active, $terminal, $known, $msg) {
  $out = callTag($payload);
  $ok = ($out->raw === $raw)
    && ($out->normalized === $normalized)
    && ($out->status_source === $source)
    && ($out->has_status === $has)
    && ($out->category === $cat)
    && ($out->is_active === $active)
    && ($out->is_terminal === $terminal)
    && ($out->is_known === $known);
  checkTag($ok, $msg . ' got=' . json_encode($out));
}

// 1. Missing / non-object / non-string cases yield unknown defaults.
expectTag(makePayload([]), '', 'unknown', 'none', false, 'unknown', false, false, false, 'empty object yields unknown');
expectTag(null, '', 'unknown', 'none', false, 'unknown', false, false, false, 'null payload yields unknown');
expectTag('active', '', 'unknown', 'none', false, 'unknown', false, false, false, 'string payload yields unknown');
expectTag(123, '', 'unknown', 'none', false, 'unknown', false, false, false, 'int payload yields unknown');
expectTag(['status' => 'active'], '', 'unknown', 'none', false, 'unknown', false, false, false, 'array payload yields unknown');
expectTag(makePayload(['status' => 123]), '', 'unknown', 'none', false, 'unknown', false, false, false, 'int status yields unknown');
expectTag(makePayload(['status' => null]), '', 'unknown', 'none', false, 'unknown', false, false, false, 'null status yields unknown');
expectTag(makePayload(['status' => true]), '', 'unknown', 'none', false, 'unknown', false, false, false, 'bool status yields unknown');
expectTag(makePayload(['situacao' => ['x']]), '', 'unknown', 'none', false, 'unknown', false, false, false, 'array situacao yields unknown');

// 2. Basic examples from contract.
expectTag(makePayload(['status' => 'active']), 'active', 'active', 'status', true, 'usable', true, false, true, 'status active');
expectTag(makePayload(['situacao' => 'expirado']), 'expirado', 'expired', 'situacao', true, 'closed', false, true, true, 'situacao expirado');

// 3. Priority: status then situacao then link_status.
expectTag(makePayload(['status' => 'active', 'situacao' => 'expirado', 'link_status' => 'used']), 'active', 'active', 'status', true, 'usable', true, false, true, 'priority status wins');
expectTag(makePayload(['situacao' => 'expirado', 'link_status' => 'used']), 'expirado', 'expired', 'situacao', true, 'closed', false, true, true, 'priority situacao wins over link_status');
expectTag(makePayload(['status' => 5, 'situacao' => 'usado', 'link_status' => 'active']), 'usado', 'used', 'situacao', true, 'consumed', false, true, true, 'non-string status skipped');
expectTag(makePayload(['status' => 5, 'situacao' => 6, 'link_status' => 'cancelada']), 'cancelada', 'canceled', 'link_status', true, 'closed', false, true, true, 'link_status fallback');
expectTag(makePayload(['status' => 5, 'situacao' => 6, 'link_status' => 7]), '', 'unknown', 'none', false, 'unknown', false, false, false, 'all non-string yields none');

// 4. Normalized mapping multilingual exact.
expectTag(makePayload(['status' => 'ativo']), 'ativo', 'active', 'status', true, 'usable', true, false, true, 'ativo maps active');
expectTag(makePayload(['status' => 'ativa']), 'ativa', 'active', 'status', true, 'usable', true, false, true, 'ativa maps active');
expectTag(makePayload(['status' => 'used']), 'used', 'used', 'status', true, 'consumed', false, true, true, 'used maps used');
expectTag(makePayload(['status' => 'usado']), 'usado', 'used', 'status', true, 'consumed', false, true, true, 'usado maps used');
expectTag(makePayload(['status' => 'usada']), 'usada', 'used', 'status', true, 'consumed', false, true, true, 'usada maps used');
expectTag(makePayload(['status' => 'expired']), 'expired', 'expired', 'status', true, 'closed', false, true, true, 'expired maps expired');
expectTag(makePayload(['status' => 'expirada']), 'expirada', 'expired', 'status', true, 'closed', false, true, true, 'expirada maps expired');
expectTag(makePayload(['status' => 'canceled']), 'canceled', 'canceled', 'status', true, 'closed', false, true, true, 'canceled maps canceled');
expectTag(makePayload(['status' => 'cancelled']), 'cancelled', 'canceled', 'status', true, 'closed', false, true, true, 'cancelled maps canceled');
expectTag(makePayload(['status' => 'cancelado']), 'cancelado', 'canceled', 'status', true, 'closed', false, true, true, 'cancelado maps canceled');
expectTag(makePayload(['link_status' => 'cancelada']), 'cancelada', 'canceled', 'link_status', true, 'closed', false, true, true, 'cancelada maps canceled');

// 5. Exact case-sensitive with no trimming/folding.
expectTag(makePayload(['status' => 'Active']), 'Active', 'unknown', 'status', true, 'unknown', false, false, false, 'case sensitive Active unknown');
expectTag(makePayload(['status' => 'ATIVO']), 'ATIVO', 'unknown', 'status', true, 'unknown', false, false, false, 'case sensitive ATIVO unknown');
expectTag(makePayload(['status' => ' active']), ' active', 'unknown', 'status', true, 'unknown', false, false, false, 'leading space unknown');
expectTag(makePayload(['status' => 'active ']), 'active ', 'unknown', 'status', true, 'unknown', false, false, false, 'trailing space unknown');
expectTag(makePayload(['status' => 'foo']), 'foo', 'unknown', 'status', true, 'unknown', false, false, false, 'unknown raw keeps raw but unknown tag');
expectTag(makePayload(['status' => '']), '', 'unknown', 'status', true, 'unknown', false, false, false, 'empty string status has_status true but unknown');

// 6. Output shape exactly 8 fields with types.
$out = callTag(makePayload(['status' => 'active']));
$vars = get_object_vars($out);
checkTag(count($vars) === 8, 'output has exactly 8 fields');
$keys = array_keys($vars);
sort($keys);
$expected = ['category', 'has_status', 'is_active', 'is_known', 'is_terminal', 'normalized', 'raw', 'status_source'];
sort($expected);
checkTag($keys === $expected, 'output keys exact');
checkTag(is_string($out->raw) && is_string($out->normalized) && is_string($out->status_source) && is_string($out->category), 'string field types');
checkTag(is_bool($out->has_status) && is_bool($out->is_active) && is_bool($out->is_terminal) && is_bool($out->is_known), 'bool field types');

// 7. Extra fields ignored, input never mutated, deterministic.
$in = makePayload(['status' => 'active', 'extra' => 'x', 'amount' => 99]);
$before = json_encode($in);
$o1 = callTag($in);
$after = json_encode($in);
checkTag($before === $after, 'input never mutated');
checkTag($o1->raw === 'active' && $o1->normalized === 'active', 'extra fields ignored');
$o2 = callTag($in);
checkTag(json_encode($o1) === json_encode($o2), 'deterministic same input same output');
$alt = callTag(makePayload(['situacao' => 'usada']));
checkTag($alt->normalized === 'used' && $alt->category === 'consumed' && $alt->is_terminal === true && $alt->is_active === false && $alt->is_known === true, 'consumed flags');
$closed = callTag(makePayload(['status' => 'expired']));
checkTag($closed->category === 'closed' && $closed->is_terminal === true && $closed->is_active === false, 'closed flags for expired');

echo "\nDone: $passes passed, $failures failed\n";
exit($failures > 0 ? 1 : 0);
