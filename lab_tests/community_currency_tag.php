<?php
require __DIR__ . '/../src/App/Support/Community/currency_tag.php';
$failures = 0;
function check($label, $actual, $expected) {
  global $failures;
  if ($actual !== $expected) {
    $failures++;
    echo "FAIL " . $label . ": expected " . var_export($expected, true) . " got " . var_export($actual, true) . "\n";
  } else {
    echo "PASS " . $label . "\n";
  }
}
function checkObj($label, $out, $exp) {
  foreach ($exp as $k => $v) {
    check($label . '.' . $k, $out->$k, $v);
  }
  check($label . '.keys', array_keys(get_object_vars($out)), array_keys($exp));
}
// 1: non-object payload behaves as empty object
$expEmpty = array('raw' => '', 'normalized' => 'unknown', 'currency_source' => 'none', 'has_currency' => false, 'category' => 'unknown', 'is_domestic' => false, 'is_known' => false);
foreach (array(null, 123, 'brl', true, array('currency' => 'brl')) as $i => $bad) {
  $o = \App\Support\Community\CurrencyTag\runFeature($bad);
  checkObj('empty.' . $i, $o, $expEmpty);
}
$emptyObj = new \stdClass();
checkObj('empty.obj', \App\Support\Community\CurrencyTag\runFeature($emptyObj), $expEmpty);
// 2: currency brl
$in = new \stdClass();
$in->currency = 'brl';
checkObj('brl', \App\Support\Community\CurrencyTag\runFeature($in), array('raw' => 'brl', 'normalized' => 'brl', 'currency_source' => 'currency', 'has_currency' => true, 'category' => 'domestic', 'is_domestic' => true, 'is_known' => true));
// 3: prefers currency over moeda over currency_code, dolar to usd
$a = new \stdClass();
$a->currency = 'usd';
$a->moeda = 'brl';
$a->currency_code = 'eur';
$o = \App\Support\Community\CurrencyTag\runFeature($a);
check('prefer.currency.raw', $o->raw, 'usd');
check('prefer.currency.source', $o->currency_source, 'currency');
check('prefer.currency.normalized', $o->normalized, 'usd');
check('prefer.currency.category', $o->category, 'international');
check('prefer.currency.is_domestic', $o->is_domestic, false);
check('prefer.currency.is_known', $o->is_known, true);
$b = new \stdClass();
$b->moeda = 'dolar';
$b->currency_code = 'eur';
$o2 = \App\Support\Community\CurrencyTag\runFeature($b);
checkObj('prefer.moeda', $o2, array('raw' => 'dolar', 'normalized' => 'usd', 'currency_source' => 'moeda', 'has_currency' => true, 'category' => 'international', 'is_domestic' => false, 'is_known' => true));
$c = new \stdClass();
$c->currency_code = 'dolar';
$o3 = \App\Support\Community\CurrencyTag\runFeature($c);
check('prefer.code.source', $o3->currency_source, 'currency_code');
check('prefer.code.normalized', $o3->normalized, 'usd');
check('prefer.code.category', $o3->category, 'international');
$d = new \stdClass();
$d->currency = 123;
$d->moeda = 'brl';
$o4 = \App\Support\Community\CurrencyTag\runFeature($d);
check('prefer.nonstr.source', $o4->currency_source, 'moeda');
check('prefer.nonstr.raw', $o4->raw, 'brl');
check('prefer.nonstr.normalized', $o4->normalized, 'brl');
// 4: euro to eur and unknown values
$e = new \stdClass();
$e->currency = 'euro';
checkObj('euro', \App\Support\Community\CurrencyTag\runFeature($e), array('raw' => 'euro', 'normalized' => 'eur', 'currency_source' => 'currency', 'has_currency' => true, 'category' => 'international', 'is_domestic' => false, 'is_known' => true));
$u = new \stdClass();
$u->currency = 'JPY';
checkObj('unknown.upper', \App\Support\Community\CurrencyTag\runFeature($u), array('raw' => 'JPY', 'normalized' => 'unknown', 'currency_source' => 'currency', 'has_currency' => true, 'category' => 'unknown', 'is_domestic' => false, 'is_known' => false));
$u2 = new \stdClass();
$u2->currency = 'BRL';
check('case.sensitive', \App\Support\Community\CurrencyTag\runFeature($u2)->normalized, 'unknown');
$u3 = new \stdClass();
$u3->currency = ' brl';
check('no.trim', \App\Support\Community\CurrencyTag\runFeature($u3)->normalized, 'unknown');
// 5: ignores extra fields, never mutates, identical output
$in5 = new \stdClass();
$in5->currency = 'brl';
$in5->extra = 'keep';
$in5->amount = 10;
$before = json_encode($in5);
$out5a = \App\Support\Community\CurrencyTag\runFeature($in5);
$out5b = \App\Support\Community\CurrencyTag\runFeature($in5);
check('extra.normalized', $out5a->normalized, 'brl');
check('extra.keys', array_keys(get_object_vars($out5a)), array('raw', 'normalized', 'currency_source', 'has_currency', 'category', 'is_domestic', 'is_known'));
check('nomutate', json_encode($in5), $before);
check('deterministic', json_encode($out5a), json_encode($out5b));
if ($failures > 0) {
  echo $failures . " FAILURES\n";
  exit(1);
}
echo "ALL PASS\n";
