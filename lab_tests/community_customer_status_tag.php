<?php
require __DIR__ . '/../src/App/Support/Community/customer_status_tag.php';

$fail = 0;
$total = 0;

function t($cond, $msg) {
  global $fail, $total;
  $total++;
  if ($cond) {
    echo "ok - " . $msg . "\n";
  } else {
    $fail++;
    echo "FAIL - " . $msg . "\n";
  }
}

function callTag($p) {
  return \App\Support\Community\CustomerStatusTag\runFeature($p);
}

function mk($pairs) {
  $o = new \stdClass;
  foreach ($pairs as $k => $v) {
    $o->$k = $v;
  }
  return $o;
}

function expectTag($payload, $exp, $label) {
  $a = callTag($payload);
  t($a instanceof \stdClass, $label . ': returns object');
  t($a->raw === $exp['raw'], $label . ': raw');
  t($a->normalized === $exp['normalized'], $label . ': normalized');
  t($a->status_source === $exp['status_source'], $label . ': status_source');
  t($a->has_status === $exp['has_status'], $label . ': has_status');
  t($a->category === $exp['category'], $label . ': category');
  t($a->is_active === $exp['is_active'], $label . ': is_active');
  t($a->is_known === $exp['is_known'], $label . ': is_known');
}

// 1. Known English status maps exactly
$p = mk(['status' => 'active']);
expectTag($p, ['raw' => 'active', 'normalized' => 'active', 'status_source' => 'status', 'has_status' => true, 'category' => 'usable', 'is_active' => true, 'is_known' => true], 'status:active');

// 2. Portuguese synonyms map exactly case-sensitive
$p = mk(['status' => 'ativo']);
expectTag($p, ['raw' => 'ativo', 'normalized' => 'active', 'status_source' => 'status', 'has_status' => true, 'category' => 'usable', 'is_active' => true, 'is_known' => true], 'status:ativo');
$p = mk(['status' => 'inativo']);
expectTag($p, ['raw' => 'inativo', 'normalized' => 'inactive', 'status_source' => 'status', 'has_status' => true, 'category' => 'dormant', 'is_active' => false, 'is_known' => true], 'status:inativo');
$p = mk(['situacao' => 'bloqueado']);
expectTag($p, ['raw' => 'bloqueado', 'normalized' => 'blocked', 'status_source' => 'situacao', 'has_status' => true, 'category' => 'restricted', 'is_active' => false, 'is_known' => true], 'situacao:bloqueado');
$p = mk(['customer_status' => 'arquivado']);
expectTag($p, ['raw' => 'arquivado', 'normalized' => 'archived', 'status_source' => 'customer_status', 'has_status' => true, 'category' => 'closed', 'is_active' => false, 'is_known' => true], 'customer_status:arquivado');
$p = mk(['status' => 'inactive']);
expectTag($p, ['raw' => 'inactive', 'normalized' => 'inactive', 'status_source' => 'status', 'has_status' => true, 'category' => 'dormant', 'is_active' => false, 'is_known' => true], 'status:inactive');
$p = mk(['status' => 'blocked']);
expectTag($p, ['raw' => 'blocked', 'normalized' => 'blocked', 'status_source' => 'status', 'has_status' => true, 'category' => 'restricted', 'is_active' => false, 'is_known' => true], 'status:blocked');
$p = mk(['status' => 'archived']);
expectTag($p, ['raw' => 'archived', 'normalized' => 'archived', 'status_source' => 'status', 'has_status' => true, 'category' => 'closed', 'is_active' => false, 'is_known' => true], 'status:archived');

// Uppercase / padded / non-string yield unknown
$p = mk(['status' => 'Active']);
expectTag($p, ['raw' => 'Active', 'normalized' => 'unknown', 'status_source' => 'status', 'has_status' => true, 'category' => 'unknown', 'is_active' => false, 'is_known' => false], 'uppercase Active');
$p = mk(['status' => ' active']);
expectTag($p, ['raw' => ' active', 'normalized' => 'unknown', 'status_source' => 'status', 'has_status' => true, 'category' => 'unknown', 'is_active' => false, 'is_known' => false], 'padded active');
$p = mk(['status' => 123]);
expectTag($p, ['raw' => '', 'normalized' => 'unknown', 'status_source' => 'none', 'has_status' => false, 'category' => 'unknown', 'is_active' => false, 'is_known' => false], 'non-string status');

// 3. Source priority status > situacao > customer_status
$p = mk(['status' => 'blocked', 'situacao' => 'active', 'customer_status' => 'active']);
$a = callTag($p);
t($a->raw === 'blocked' && $a->status_source === 'status' && $a->normalized === 'blocked', 'priority status first');
$p = mk(['situacao' => 'active', 'customer_status' => 'blocked']);
$a = callTag($p);
t($a->raw === 'active' && $a->status_source === 'situacao' && $a->normalized === 'active', 'priority situacao second');
$p = mk(['customer_status' => 'archived']);
$a = callTag($p);
t($a->raw === 'archived' && $a->status_source === 'customer_status', 'priority customer_status third');
$p = mk(['status' => 5, 'situacao' => 'active']);
$a = callTag($p);
t($a->raw === 'active' && $a->status_source === 'situacao', 'non-string status falls through to situacao');
$p = mk(['status' => 5, 'situacao' => 6, 'customer_status' => 'inativo']);
$a = callTag($p);
t($a->raw === 'inativo' && $a->status_source === 'customer_status' && $a->normalized === 'inactive', 'double non-string falls through');

// Non-object payload behaves as empty object
foreach ([null, 42, 'active', [ 'status' => 'active' ], true] as $i => $bad) {
  $a = callTag($bad);
  t($a->raw === '' && $a->status_source === 'none' && $a->has_status === false && $a->normalized === 'unknown' && $a->category === 'unknown' && $a->is_active === false && $a->is_known === false, 'non-object payload ' . $i);
}

// Missing fields
$p = new \stdClass;
expectTag($p, ['raw' => '', 'normalized' => 'unknown', 'status_source' => 'none', 'has_status' => false, 'category' => 'unknown', 'is_active' => false, 'is_known' => false], 'empty object');

// 4. Pure and deterministic: extra fields ignored, input never mutated, identical output
$p = mk(['status' => 'active', 'extra' => 'x', 'other' => 99]);
expectTag($p, ['raw' => 'active', 'normalized' => 'active', 'status_source' => 'status', 'has_status' => true, 'category' => 'usable', 'is_active' => true, 'is_known' => true], 'extra fields ignored');
$in = mk(['status' => 'active', 'extra' => 'keep']);
$before = get_object_vars($in);
$first = callTag($in);
$after = get_object_vars($in);
t($before === $after, 'input never mutated');
t(isset($in->status) && $in->status === 'active' && $in->extra === 'keep', 'input values intact');
$second = callTag($in);
t($first->raw === $second->raw && $first->normalized === $second->normalized && $first->status_source === $second->status_source && $first->has_status === $second->has_status && $first->category === $second->category && $first->is_active === $second->is_active && $first->is_known === $second->is_known, 'deterministic repeat');
$in2 = mk(['status' => 'active']);
$in3 = mk(['status' => 'active', 'zzz' => 'ignored']);
$a2 = callTag($in2);
$a3 = callTag($in3);
t($a2->raw === $a3->raw && $a2->normalized === $a3->normalized && $a2->category === $a3->category, 'extra fields do not change output');

if ($fail > 0) {
  echo $fail . '/' . $total . " FAILED\n";
  exit(1);
}
echo "all " . $total . " passed\n";
