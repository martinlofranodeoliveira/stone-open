<?php
require_once __DIR__ . '/../src/App/Support/Community/payment_method_tag.php';

$failures = 0;
$total = 0;
function check($name, $cond) {
  global $failures, $total;
  $total++;
  if ($cond) {
    echo "PASS: " . $name . "\n";
  } else {
    echo "FAIL: " . $name . "\n";
    $failures++;
  }
}
function runTag($p) {
  return \App\Support\Community\PaymentMethodTag\runFeature($p);
}
function obj($arr) {
  $o = new \stdClass;
  foreach ($arr as $k => $v) {
    $o->$k = $v;
  }
  return $o;
}

// 1. Empty object
$out = runTag(new \stdClass);
check('empty raw', $out->raw === '');
check('empty normalized', $out->normalized === 'unknown');
check('empty method_source', $out->method_source === 'none');
check('empty has_method', $out->has_method === false);
check('empty category', $out->category === 'unknown');
check('empty is_card', $out->is_card === false);
check('empty is_instant', $out->is_instant === false);
check('empty is_known', $out->is_known === false);

// Non-object behaves as empty
foreach ([null, 123, 'pix', []] as $i => $bad) {
  $r = runTag($bad);
  check('non-object ' . $i . ' raw', $r->raw === '');
  check('non-object ' . $i . ' normalized', $r->normalized === 'unknown');
  check('non-object ' . $i . ' source', $r->method_source === 'none');
  check('non-object ' . $i . ' has_method', $r->has_method === false);
}

// 2. Priority method > metodo > payment_method
$r = runTag(obj(['method' => 'pix', 'metodo' => 'cartao', 'payment_method' => 'boleto']));
check('priority method wins raw', $r->raw === 'pix');
check('priority method wins source', $r->method_source === 'method');
check('priority method wins normalized', $r->normalized === 'pix');

$r = runTag(obj(['metodo' => 'cartao', 'payment_method' => 'boleto']));
check('priority metodo wins raw', $r->raw === 'cartao');
check('priority metodo wins source', $r->method_source === 'metodo');
check('priority metodo wins normalized', $r->normalized === 'card');

$r = runTag(obj(['payment_method' => 'boleto']));
check('payment_method fallback raw', $r->raw === 'boleto');
check('payment_method fallback source', $r->method_source === 'payment_method');
check('payment_method fallback normalized', $r->normalized === 'boleto');

$r = runTag(obj(['method' => 123, 'metodo' => 'pix']));
check('non-string method skipped raw', $r->raw === 'pix');
check('non-string method skipped source', $r->method_source === 'metodo');

$r = runTag(obj(['method' => null, 'metodo' => [], 'payment_method' => 'card']));
check('non-string chain skipped raw', $r->raw === 'card');
check('non-string chain skipped source', $r->method_source === 'payment_method');

$r = runTag(obj(['method' => 123]));
check('all non-string empty raw', $r->raw === '');
check('all non-string source none', $r->method_source === 'none');
check('all non-string has_method false', $r->has_method === false);
check('all non-string unknown', $r->normalized === 'unknown');

// 3. Exact pix
$r = runTag(obj(['method' => 'pix']));
check('pix raw', $r->raw === 'pix');
check('pix normalized', $r->normalized === 'pix');
check('pix category', $r->category === 'instant');
check('pix is_instant', $r->is_instant === true);
check('pix is_card', $r->is_card === false);
check('pix is_known', $r->is_known === true);
check('pix has_method', $r->has_method === true);
check('pix source', $r->method_source === 'method');

// 4. card/cartao and boleto/bank_slip
$r = runTag(obj(['method' => 'card']));
check('card normalized', $r->normalized === 'card');
check('card category', $r->category === 'card');
check('card is_card', $r->is_card === true);
check('card is_instant false', $r->is_instant === false);
check('card is_known', $r->is_known === true);

$r = runTag(obj(['metodo' => 'cartao']));
check('cartao normalized card', $r->normalized === 'card');
check('cartao category card', $r->category === 'card');
check('cartao is_card', $r->is_card === true);
check('cartao source metodo', $r->method_source === 'metodo');
check('cartao raw', $r->raw === 'cartao');

$r = runTag(obj(['method' => 'boleto']));
check('boleto normalized', $r->normalized === 'boleto');
check('boleto category slip', $r->category === 'slip');
check('boleto is_card false', $r->is_card === false);
check('boleto is_instant false', $r->is_instant === false);
check('boleto is_known', $r->is_known === true);

$r = runTag(obj(['payment_method' => 'bank_slip']));
check('bank_slip normalized boleto', $r->normalized === 'boleto');
check('bank_slip category slip', $r->category === 'slip');
check('bank_slip raw', $r->raw === 'bank_slip');

// 5. Unknown, extra fields, immutability, determinism
$r = runTag(obj(['method' => 'PIX']));
check('case-sensitive unknown', $r->normalized === 'unknown');
check('case-sensitive category unknown', $r->category === 'unknown');
check('case-sensitive is_known false', $r->is_known === false);
check('case-sensitive raw preserved', $r->raw === 'PIX');
check('case-sensitive has_method true', $r->has_method === true);

$r = runTag(obj(['method' => ' pix']));
check('no-trim unknown', $r->normalized === 'unknown');

$r = runTag(obj(['method' => 'other', 'extra' => 'x', 'foo' => 123]));
check('unknown value normalized', $r->normalized === 'unknown');
check('unknown category', $r->category === 'unknown');
check('unknown is_known false', $r->is_known === false);
$vars = get_object_vars($r);
check('extra ignored key count', count($vars) === 8);
check('extra ignored has raw', array_key_exists('raw', $vars));

$in = obj(['method' => 'pix', 'extra' => 1]);
$before = json_encode($in);
$rout = runTag($in);
$after = json_encode($in);
check('input never mutated', $before === $after);
check('output correct despite extra', $rout->normalized === 'pix');

$a = runTag(obj(['method' => 'pix']));
$b = runTag(obj(['method' => 'pix']));
check('deterministic identical', json_encode($a) === json_encode($b));

// Contract examples
$e1 = runTag(obj(['method' => 'pix']));
check('example1', $e1->raw === 'pix' && $e1->normalized === 'pix' && $e1->category === 'instant' && $e1->is_instant === true && $e1->is_card === false && $e1->is_known === true && $e1->method_source === 'method' && $e1->has_method === true);
$e2 = runTag(obj(['metodo' => 'cartao']));
check('example2', $e2->raw === 'cartao' && $e2->normalized === 'card' && $e2->category === 'card' && $e2->is_card === true && $e2->is_instant === false && $e2->is_known === true && $e2->method_source === 'metodo');
$e3 = runTag(new \stdClass);
check('example3', $e3->raw === '' && $e3->normalized === 'unknown' && $e3->category === 'unknown' && $e3->is_known === false && $e3->method_source === 'none');

echo "TOTAL: " . $total . " FAILURES: " . $failures . "\n";
exit($failures === 0 ? 0 : 1);
