<?php
require __DIR__ . '/../src/App/Support/Community/utils_tool_tag.php';
$passes = 0;
$failures = 0;
function t_check($cond, $msg) {
  global $passes, $failures;
  if ($cond === true) {
    $passes = $passes + 1;
    echo "ok: " . $msg . "\n";
  } else {
    $failures = $failures + 1;
    echo "FAIL: " . $msg . "\n";
  }
}
function t_obj($arr) {
  $o = new \stdClass;
  foreach ($arr as $k => $v) {
    $o->$k = $v;
  }
  return $o;
}
$a = t_obj(['tool' => 'boleto']);
$r = \App\Support\Community\UtilsToolTag\runFeature($a);
t_check($r->raw === 'boleto', 'boleto raw');
t_check($r->normalized === 'boleto', 'boleto normalized');
t_check($r->tool_source === 'tool', 'boleto source');
t_check($r->has_tool === true, 'boleto has_tool');
t_check($r->category === 'slip', 'boleto category');
t_check($r->is_known === true, 'boleto is_known');
$b = t_obj(['ferramenta' => 'pix']);
$r2 = \App\Support\Community\UtilsToolTag\runFeature($b);
t_check($r2->raw === 'pix', 'pix raw');
t_check($r2->normalized === 'pix', 'pix normalized');
t_check($r2->tool_source === 'ferramenta', 'pix source');
t_check($r2->has_tool === true, 'pix has_tool');
t_check($r2->category === 'instant', 'pix category');
t_check($r2->is_known === true, 'pix is_known');
$c = t_obj(['utils_tool' => 'card']);
$r3 = \App\Support\Community\UtilsToolTag\runFeature($c);
t_check($r3->raw === 'card', 'card raw');
t_check($r3->normalized === 'card', 'card normalized');
t_check($r3->tool_source === 'utils_tool', 'card source');
t_check($r3->has_tool === true, 'card has_tool');
t_check($r3->category === 'card', 'card category');
t_check($r3->is_known === true, 'card is_known');
$e = new \stdClass;
$r4 = \App\Support\Community\UtilsToolTag\runFeature($e);
t_check($r4->raw === '', 'empty raw');
t_check($r4->normalized === 'unknown', 'empty normalized');
t_check($r4->tool_source === 'none', 'empty source');
t_check($r4->has_tool === false, 'empty has_tool');
t_check($r4->category === 'unknown', 'empty category');
t_check($r4->is_known === false, 'empty is_known');
$u = t_obj(['tool' => 'PIX']);
$ru = \App\Support\Community\UtilsToolTag\runFeature($u);
t_check($ru->raw === 'PIX', 'upper raw kept');
t_check($ru->normalized === 'unknown', 'upper PIX unknown');
t_check($ru->is_known === false, 'upper PIX not known');
t_check($ru->category === 'unknown', 'upper PIX category unknown');
$u2 = t_obj(['ferramenta' => 'PIX']);
$ru2 = \App\Support\Community\UtilsToolTag\runFeature($u2);
t_check($ru2->normalized === 'unknown', 'upper ferramenta PIX unknown');
$x = t_obj(['tool' => 'pix', 'extra' => 'hello', 'other' => 123]);
$rx = \App\Support\Community\UtilsToolTag\runFeature($x);
t_check($rx->normalized === 'pix', 'extra ignored normalized');
t_check($rx->raw === 'pix', 'extra ignored raw');
t_check($rx->tool_source === 'tool', 'extra ignored source');
$p = t_obj(['tool' => 'pix', 'note' => 'x']);
$before = json_encode($p);
$rp = \App\Support\Community\UtilsToolTag\runFeature($p);
$after = json_encode($p);
t_check($before === $after, 'input never mutated');
$q = t_obj(['ferramenta' => 'link']);
$rq1 = \App\Support\Community\UtilsToolTag\runFeature($q);
$rq2 = \App\Support\Community\UtilsToolTag\runFeature($q);
t_check(json_encode($rq1) === json_encode($rq2), 'deterministic identical output');
$pr = t_obj(['tool' => 'card', 'ferramenta' => 'pix', 'utils_tool' => 'boleto']);
$rpr = \App\Support\Community\UtilsToolTag\runFeature($pr);
t_check($rpr->raw === 'card' && $rpr->tool_source === 'tool', 'priority tool first');
$pr2 = t_obj(['ferramenta' => 'pix', 'utils_tool' => 'boleto']);
$rpr2 = \App\Support\Community\UtilsToolTag\runFeature($pr2);
t_check($rpr2->raw === 'pix' && $rpr2->tool_source === 'ferramenta', 'priority ferramenta second');
$nn = \App\Support\Community\UtilsToolTag\runFeature(null);
t_check($nn->normalized === 'unknown' && $nn->has_tool === false && $nn->raw === '', 'null behaves as empty');
$na = \App\Support\Community\UtilsToolTag\runFeature(['tool' => 'pix']);
t_check($na->normalized === 'unknown' && $na->has_tool === false, 'array behaves as empty');
$ns = t_obj(['tool' => 123]);
$rns = \App\Support\Community\UtilsToolTag\runFeature($ns);
t_check($rns->has_tool === false && $rns->raw === '' && $rns->normalized === 'unknown', 'non-string tool ignored');
$bl = t_obj(['tool' => 'boletos']);
$rbl = \App\Support\Community\UtilsToolTag\runFeature($bl);
t_check($rbl->normalized === 'boleto' && $rbl->category === 'slip', 'boletos maps boleto');
$ct = t_obj(['tool' => 'cartao']);
$rct = \App\Support\Community\UtilsToolTag\runFeature($ct);
t_check($rct->normalized === 'card' && $rct->category === 'card', 'cartao maps card');
$cs = t_obj(['tool' => 'cards']);
$rcs = \App\Support\Community\UtilsToolTag\runFeature($cs);
t_check($rcs->normalized === 'card', 'cards maps card');
$lk = t_obj(['tool' => 'links']);
$rlk = \App\Support\Community\UtilsToolTag\runFeature($lk);
t_check($rlk->normalized === 'link' && $rlk->category === 'shareable', 'links maps link');
$pl = t_obj(['tool' => 'payment_link']);
$rpl = \App\Support\Community\UtilsToolTag\runFeature($pl);
t_check($rpl->normalized === 'link' && $rpl->category === 'shareable', 'payment_link maps link');
echo "passes=" . $passes . " failures=" . $failures . "\n";
if ($failures > 0) {
  exit(1);
}
