<?php
require __DIR__ . '/../src/App/Support/Community/auth_outcome_tag.php';

$passes = 0;
$failures = 0;

function t_check($cond, $msg) {
  global $passes, $failures;
  if ($cond) {
    $passes++;
    echo "PASS: " . $msg . "\n";
  } else {
    $failures++;
    echo "FAIL: " . $msg . "\n";
  }
}

function t_obj($pairs) {
  $o = new \stdClass;
  foreach ($pairs as $k => $v) {
    $o->$k = $v;
  }
  return $o;
}

function t_call($payload) {
  return \App\Support\Community\AuthOutcomeTag\runFeature($payload);
}

function t_expect($payload, $exp, $msg) {
  $out = t_call($payload);
  $ok = ($out->raw === $exp['raw'])
    && ($out->normalized === $exp['normalized'])
    && ($out->auth_source === $exp['auth_source'])
    && ($out->has_outcome === $exp['has_outcome'])
    && ($out->category === $exp['category'])
    && ($out->is_success === $exp['is_success'])
    && ($out->is_terminal === $exp['is_terminal'])
    && ($out->is_known === $exp['is_known']);
  t_check($ok, $msg);
  if (!$ok) {
    echo "  got: " . json_encode($out) . "\n";
    echo "  want: " . json_encode($exp) . "\n";
  }
}

t_expect(t_obj([]), ['raw' => '', 'normalized' => 'unknown', 'auth_source' => 'none', 'has_outcome' => false, 'category' => 'unknown', 'is_success' => false, 'is_terminal' => false, 'is_known' => false], 'empty object yields unknown');
t_expect(null, ['raw' => '', 'normalized' => 'unknown', 'auth_source' => 'none', 'has_outcome' => false, 'category' => 'unknown', 'is_success' => false, 'is_terminal' => false, 'is_known' => false], 'null payload yields unknown');
t_expect('success', ['raw' => '', 'normalized' => 'unknown', 'auth_source' => 'none', 'has_outcome' => false, 'category' => 'unknown', 'is_success' => false, 'is_terminal' => false, 'is_known' => false], 'string payload yields unknown');
t_expect(123, ['raw' => '', 'normalized' => 'unknown', 'auth_source' => 'none', 'has_outcome' => false, 'category' => 'unknown', 'is_success' => false, 'is_terminal' => false, 'is_known' => false], 'numeric payload yields unknown');
t_expect(t_obj(['outcome' => 'success']), ['raw' => 'success', 'normalized' => 'success', 'auth_source' => 'outcome', 'has_outcome' => true, 'category' => 'ok', 'is_success' => true, 'is_terminal' => true, 'is_known' => true], 'outcome success');
t_expect(t_obj(['resultado' => 'bloqueado']), ['raw' => 'bloqueado', 'normalized' => 'locked', 'auth_source' => 'resultado', 'has_outcome' => true, 'category' => 'blocked', 'is_success' => false, 'is_terminal' => true, 'is_known' => true], 'resultado bloqueado');
t_expect(t_obj(['auth_result' => 'pendente']), ['raw' => 'pendente', 'normalized' => 'pending', 'auth_source' => 'auth_result', 'has_outcome' => true, 'category' => 'waiting', 'is_success' => false, 'is_terminal' => false, 'is_known' => true], 'auth_result pendente');
t_expect(t_obj(['outcome' => 'sucesso']), ['raw' => 'sucesso', 'normalized' => 'success', 'auth_source' => 'outcome', 'has_outcome' => true, 'category' => 'ok', 'is_success' => true, 'is_terminal' => true, 'is_known' => true], 'sucesso maps to success');
t_expect(t_obj(['outcome' => 'ok']), ['raw' => 'ok', 'normalized' => 'success', 'auth_source' => 'outcome', 'has_outcome' => true, 'category' => 'ok', 'is_success' => true, 'is_terminal' => true, 'is_known' => true], 'ok maps to success');
t_expect(t_obj(['outcome' => 'falha']), ['raw' => 'falha', 'normalized' => 'failure', 'auth_source' => 'outcome', 'has_outcome' => true, 'category' => 'attention', 'is_success' => false, 'is_terminal' => true, 'is_known' => true], 'falha maps to failure');
t_expect(t_obj(['outcome' => 'erro']), ['raw' => 'erro', 'normalized' => 'failure', 'auth_source' => 'outcome', 'has_outcome' => true, 'category' => 'attention', 'is_success' => false, 'is_terminal' => true, 'is_known' => true], 'erro maps to failure');
t_expect(t_obj(['outcome' => 'failed']), ['raw' => 'failed', 'normalized' => 'failure', 'auth_source' => 'outcome', 'has_outcome' => true, 'category' => 'attention', 'is_success' => false, 'is_terminal' => true, 'is_known' => true], 'failed maps to failure');
t_expect(t_obj(['outcome' => 'bloqueada']), ['raw' => 'bloqueada', 'normalized' => 'locked', 'auth_source' => 'outcome', 'has_outcome' => true, 'category' => 'blocked', 'is_success' => false, 'is_terminal' => true, 'is_known' => true], 'bloqueada maps to locked');
t_expect(t_obj(['outcome' => 'expirado']), ['raw' => 'expirado', 'normalized' => 'expired', 'auth_source' => 'outcome', 'has_outcome' => true, 'category' => 'attention', 'is_success' => false, 'is_terminal' => true, 'is_known' => true], 'expirado maps to expired');
t_expect(t_obj(['outcome' => 'expirada']), ['raw' => 'expirada', 'normalized' => 'expired', 'auth_source' => 'outcome', 'has_outcome' => true, 'category' => 'attention', 'is_success' => false, 'is_terminal' => true, 'is_known' => true], 'expirada maps to expired');
t_expect(t_obj(['outcome' => 'pending']), ['raw' => 'pending', 'normalized' => 'pending', 'auth_source' => 'outcome', 'has_outcome' => true, 'category' => 'waiting', 'is_success' => false, 'is_terminal' => false, 'is_known' => true], 'pending maps to pending');
t_expect(t_obj(['outcome' => 'Success']), ['raw' => 'Success', 'normalized' => 'unknown', 'auth_source' => 'outcome', 'has_outcome' => true, 'category' => 'unknown', 'is_success' => false, 'is_terminal' => false, 'is_known' => false], 'case sensitive Success is unknown');
t_expect(t_obj(['outcome' => 'SUCESSO']), ['raw' => 'SUCESSO', 'normalized' => 'unknown', 'auth_source' => 'outcome', 'has_outcome' => true, 'category' => 'unknown', 'is_success' => false, 'is_terminal' => false, 'is_known' => false], 'case sensitive SUCESSO is unknown');
t_expect(t_obj(['outcome' => ' success']), ['raw' => ' success', 'normalized' => 'unknown', 'auth_source' => 'outcome', 'has_outcome' => true, 'category' => 'unknown', 'is_success' => false, 'is_terminal' => false, 'is_known' => false], 'no trimming');

$prio = t_obj(['outcome' => 'locked', 'resultado' => 'pending', 'auth_result' => 'expired']);
$po = t_call($prio);
t_check($po->raw === 'locked' && $po->auth_source === 'outcome', 'priority outcome wins');

$prio2 = t_obj(['resultado' => 'pending', 'auth_result' => 'expired']);
$po2 = t_call($prio2);
t_check($po2->raw === 'pending' && $po2->auth_source === 'resultado', 'priority resultado over auth_result');

$prio3 = t_obj(['outcome' => 123, 'resultado' => 'ok']);
$po3 = t_call($prio3);
t_check($po3->raw === 'ok' && $po3->auth_source === 'resultado' && $po3->normalized === 'success', 'non-string outcome falls through');

t_expect(t_obj(['outcome' => 123]), ['raw' => '', 'normalized' => 'unknown', 'auth_source' => 'none', 'has_outcome' => false, 'category' => 'unknown', 'is_success' => false, 'is_terminal' => false, 'is_known' => false], 'numeric outcome ignored');
t_expect(t_obj(['outcome' => true]), ['raw' => '', 'normalized' => 'unknown', 'auth_source' => 'none', 'has_outcome' => false, 'category' => 'unknown', 'is_success' => false, 'is_terminal' => false, 'is_known' => false], 'boolean outcome ignored');
t_expect(t_obj(['outcome' => null]), ['raw' => '', 'normalized' => 'unknown', 'auth_source' => 'none', 'has_outcome' => false, 'category' => 'unknown', 'is_success' => false, 'is_terminal' => false, 'is_known' => false], 'null outcome ignored');
$arr = t_obj(['outcome' => ['x']]);
t_expect($arr, ['raw' => '', 'normalized' => 'unknown', 'auth_source' => 'none', 'has_outcome' => false, 'category' => 'unknown', 'is_success' => false, 'is_terminal' => false, 'is_known' => false], 'array outcome ignored');
$objv = new \stdClass;
$objv->a = 1;
t_expect(t_obj(['outcome' => $objv]), ['raw' => '', 'normalized' => 'unknown', 'auth_source' => 'none', 'has_outcome' => false, 'category' => 'unknown', 'is_success' => false, 'is_terminal' => false, 'is_known' => false], 'object outcome ignored');

$extra = t_obj(['outcome' => 'success', 'extra' => 'zzz', 'other' => 42]);
$eo = t_call($extra);
t_check($eo->raw === 'success' && $eo->normalized === 'success' && !isset($eo->extra), 'extra fields ignored');

$mut = t_obj(['outcome' => 'failure', 'resultado' => 'pending']);
$before = json_encode($mut);
t_call($mut);
$after = json_encode($mut);
t_check($before === $after, 'input never mutated');

$a1 = t_call(t_obj(['resultado' => 'falha']));
$a2 = t_call(t_obj(['resultado' => 'falha']));
t_check(json_encode($a1) === json_encode($a2), 'determinism identical output');

t_expect(t_obj(['outcome' => 'expired']), ['raw' => 'expired', 'normalized' => 'expired', 'auth_source' => 'outcome', 'has_outcome' => true, 'category' => 'attention', 'is_success' => false, 'is_terminal' => true, 'is_known' => true], 'expired gives attention terminal');
t_expect(t_obj(['outcome' => 'locked']), ['raw' => 'locked', 'normalized' => 'locked', 'auth_source' => 'outcome', 'has_outcome' => true, 'category' => 'blocked', 'is_success' => false, 'is_terminal' => true, 'is_known' => true], 'locked gives blocked terminal');

echo "passes=" . $passes . " failures=" . $failures . "\n";
exit($failures > 0 ? 1 : 0);
