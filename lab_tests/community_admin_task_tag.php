<?php
require __DIR__ . '/../src/App/Support/Community/admin_task_tag.php';
$failures = 0;
$passes = 0;
function check($cond, $msg) {
  global $failures, $passes;
  if ($cond) {
    $passes = $passes + 1;
    echo "PASS: " . $msg . "\n";
  } else {
    $failures = $failures + 1;
    echo "FAIL: " . $msg . "\n";
  }
}
function mk($pairs) {
  $o = new \stdClass;
  foreach ($pairs as $k => $v) {
    $o->$k = $v;
  }
  return $o;
}
function runP($p) {
  return \App\Support\Community\AdminTaskTag\runFeature($p);
}
function expectOut($out, $raw, $norm, $src, $has, $cat, $idel, $isys, $ikn) {
  return is_object($out)
    && $out->raw === $raw
    && $out->normalized === $norm
    && $out->task_source === $src
    && $out->has_task === $has
    && $out->category === $cat
    && $out->is_delivery === $idel
    && $out->is_system === $isys
    && $out->is_known === $ikn;
}
$o = runP(new \stdClass);
check(expectOut($o, '', 'unknown', 'none', false, 'unknown', false, false, false), 'empty object yields unknown/none');
$o = runP(null);
check(expectOut($o, '', 'unknown', 'none', false, 'unknown', false, false, false), 'null payload yields empty');
$o = runP('process_webhooks');
check(expectOut($o, '', 'unknown', 'none', false, 'unknown', false, false, false), 'string payload yields empty');
$o = runP(123);
check(expectOut($o, '', 'unknown', 'none', false, 'unknown', false, false, false), 'int payload yields empty');
$o = runP(["task" => "health"]);
check(expectOut($o, '', 'unknown', 'none', false, 'unknown', false, false, false), 'array payload yields empty');
$o = runP(true);
check(expectOut($o, '', 'unknown', 'none', false, 'unknown', false, false, false), 'bool payload yields empty');
$o = runP(mk(["task" => "process_webhooks"]));
check(expectOut($o, 'process_webhooks', 'process_webhooks', 'task', true, 'delivery', true, false, true), 'task process_webhooks delivery');
$o = runP(mk(["tarefa" => "diagnostico"]));
check(expectOut($o, 'diagnostico', 'diagnostics', 'tarefa', true, 'system', false, true, true), 'tarefa diagnostico system');
$o = runP(mk(["task" => "health", "tarefa" => "retention", "admin_task" => "diagnostics"]));
check($o->raw === 'health' && $o->task_source === 'task' && $o->normalized === 'health', 'primary task wins over others');
$o = runP(mk(["tarefa" => "saude", "admin_task" => "retencao"]));
check($o->raw === 'saude' && $o->task_source === 'tarefa' && $o->normalized === 'health', 'tarefa wins over admin_task');
$o = runP(mk(["admin_task" => "retencao"]));
check(expectOut($o, 'retencao', 'retention', 'admin_task', true, 'maintenance', false, false, true), 'admin_task retencao maintenance');
$o = runP(mk(["task" => 5, "tarefa" => "health"]));
check($o->raw === 'health' && $o->task_source === 'tarefa', 'non-string task falls through to tarefa');
$o = runP(mk(["task" => null, "tarefa" => 7, "admin_task" => "health"]));
check($o->raw === 'health' && $o->task_source === 'admin_task', 'null/int fall through to admin_task');
$o = runP(mk(["task" => "processar_webhooks"]));
check($o->normalized === 'process_webhooks' && $o->raw === 'processar_webhooks' && $o->category === 'delivery' && $o->is_known === true, 'alias processar_webhooks');
$o = runP(mk(["task" => "reenviar_forwards"]));
check($o->normalized === 'retry_forwards' && $o->category === 'delivery', 'alias reenviar_forwards');
$o = runP(mk(["task" => "saude"]));
check($o->normalized === 'health' && $o->category === 'system' && $o->is_system === true, 'alias saude');
$o = runP(mk(["task" => "diagnostico"]));
check($o->normalized === 'diagnostics' && $o->category === 'system', 'alias diagnostico');
$o = runP(mk(["task" => "retencao"]));
check($o->normalized === 'retention' && $o->category === 'maintenance', 'alias retencao');
$o = runP(mk(["task" => "retry_forwards"]));
check($o->normalized === 'retry_forwards' && $o->category === 'delivery' && $o->is_delivery === true && $o->is_system === false, 'retry_forwards delivery flags');
$o = runP(mk(["task" => "diagnostics"]));
check($o->normalized === 'diagnostics' && $o->category === 'system' && $o->is_delivery === false && $o->is_system === true, 'diagnostics system flags');
$o = runP(mk(["task" => "retention"]));
check($o->normalized === 'retention' && $o->category === 'maintenance' && $o->is_delivery === false && $o->is_system === false && $o->is_known === true, 'retention maintenance flags');
$o = runP(mk(["task" => "Health"]));
check($o->normalized === 'unknown' && $o->category === 'unknown' && $o->is_known === false, 'case-sensitive Health unknown');
$o = runP(mk(["task" => " health"]));
check($o->normalized === 'unknown', 'leading space not trimmed');
$o = runP(mk(["task" => "health "]));
check($o->normalized === 'unknown', 'trailing space not trimmed');
$o = runP(mk(["task" => "PROCESS_WEBHOOKS"]));
check($o->normalized === 'unknown', 'upper case not folded');
$o = runP(mk(["task" => "bogus"]));
check(expectOut($o, 'bogus', 'unknown', 'task', true, 'unknown', false, false, false), 'unknown task keeps raw with unknown flags');
$in = mk(["task" => "health", "extra" => "x", "n" => 1]);
$before = json_encode($in);
$out1 = runP($in);
$after = json_encode($in);
check($before === $after, 'input never mutated');
check($out1->raw === 'health' && $out1->normalized === 'health', 'extra fields ignored');
$vars = get_object_vars($out1);
check(count($vars) === 8, 'output has exactly eight fields');
check(isset($vars['raw']) && isset($vars['normalized']) && isset($vars['task_source']) && isset($vars['has_task']) && isset($vars['category']) && isset($vars['is_delivery']) && isset($vars['is_system']) && isset($vars['is_known']), 'output field names exact');
check(is_string($out1->raw) && is_string($out1->normalized) && is_string($out1->task_source) && is_string($out1->category), 'string types');
check(is_bool($out1->has_task) && is_bool($out1->is_delivery) && is_bool($out1->is_system) && is_bool($out1->is_known), 'bool types');
check(in_array($out1->normalized, ["process_webhooks", "retry_forwards", "health", "diagnostics", "retention", "unknown"], true), 'normalized enum');
check(in_array($out1->task_source, ["task", "tarefa", "admin_task", "none"], true), 'task_source enum');
check(in_array($out1->category, ["delivery", "system", "maintenance", "unknown"], true), 'category enum');
$a = runP(mk(["tarefa" => "saude"]));
$b = runP(mk(["tarefa" => "saude"]));
check(json_encode($a) === json_encode($b), 'deterministic same input same output');
echo "passes=" . $passes . " failures=" . $failures . "\n";
exit($failures > 0 ? 1 : 0);
