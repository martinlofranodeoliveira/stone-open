<?php
require __DIR__ . '/../src/App/Support/Community/subscription_status_tag.php';
$failures = 0;
$checks = 0;
function check($cond, $name, &$failures, &$checks) {
  $checks = $checks + 1;
  if ($cond === true) {
    echo "ok: " . $name . "\n";
  } else {
    $failures = $failures + 1;
    echo "FAIL: " . $name . "\n";
  }
}
function runTag($payload) {
  return App\Support\Community\SubscriptionStatusTag\runFeature($payload);
}
function fieldCount($o) {
  $vars = get_object_vars($o);
  $n = 0;
  foreach ($vars as $k => $v) {
    $n = $n + 1;
  }
  return $n;
}
$p = new \stdClass;
$r = runTag($p);
check($r->raw === "", "empty raw", $failures, $checks);
check($r->normalized === "unknown", "empty normalized", $failures, $checks);
check($r->status_source === "none", "empty source", $failures, $checks);
check($r->has_status === false, "empty has_status", $failures, $checks);
check($r->category === "unknown", "empty category", $failures, $checks);
check($r->is_active === false, "empty is_active", $failures, $checks);
check($r->is_ended === false, "empty is_ended", $failures, $checks);
check($r->is_known === false, "empty is_known", $failures, $checks);
check(fieldCount($r) === 8, "empty eight fields", $failures, $checks);
$rNull = runTag(null);
check($rNull->raw === "" && $rNull->normalized === "unknown" && $rNull->status_source === "none" && $rNull->has_status === false && $rNull->category === "unknown" && $rNull->is_active === false && $rNull->is_ended === false && $rNull->is_known === false, "non-object null", $failures, $checks);
$rStr = runTag("active");
check($rStr->raw === "" && $rStr->normalized === "unknown" && $rStr->status_source === "none" && $rStr->has_status === false, "non-object string", $failures, $checks);
$rInt = runTag(42);
check($rInt->raw === "" && $rInt->normalized === "unknown" && $rInt->status_source === "none" && $rInt->has_status === false, "non-object int", $failures, $checks);
$rBool = runTag(true);
check($rBool->raw === "" && $rBool->normalized === "unknown" && $rBool->has_status === false, "non-object bool", $failures, $checks);
$rArr = runTag([]);
check($rArr->raw === "" && $rArr->normalized === "unknown" && $rArr->status_source === "none" && $rArr->has_status === false, "non-object empty array", $failures, $checks);
$rArr2 = runTag(["status" => "active"]);
check($rArr2->raw === "" && $rArr2->normalized === "unknown" && $rArr2->status_source === "none" && $rArr2->has_status === false, "non-object assoc array", $failures, $checks);
$p1 = new \stdClass;
$p1->status = "active";
$r1 = runTag($p1);
check($r1->raw === "active" && $r1->normalized === "active" && $r1->status_source === "status" && $r1->has_status === true && $r1->category === "current" && $r1->is_active === true && $r1->is_ended === false && $r1->is_known === true, "example active", $failures, $checks);
$p2 = new \stdClass;
$p2->situacao = "inadimplente";
$r2 = runTag($p2);
check($r2->raw === "inadimplente" && $r2->normalized === "past_due" && $r2->status_source === "situacao" && $r2->category === "attention" && $r2->is_active === false && $r2->is_ended === false && $r2->is_known === true, "example inadimplente", $failures, $checks);
$pp = new \stdClass;
$pp->status = "active";
$pp->situacao = "inadimplente";
$pp->subscription_status = "expired";
$rp = runTag($pp);
check($rp->raw === "active" && $rp->status_source === "status", "precedence status first", $failures, $checks);
$pp2 = new \stdClass;
$pp2->situacao = "inadimplente";
$pp2->subscription_status = "expired";
$rp2 = runTag($pp2);
check($rp2->raw === "inadimplente" && $rp2->status_source === "situacao", "precedence situacao second", $failures, $checks);
$pp3 = new \stdClass;
$pp3->subscription_status = "expired";
$rp3 = runTag($pp3);
check($rp3->raw === "expired" && $rp3->status_source === "subscription_status" && $rp3->normalized === "expired", "precedence subscription_status third", $failures, $checks);
$pp4 = new \stdClass;
$pp4->status = 123;
$pp4->situacao = "ativo";
$rp4 = runTag($pp4);
check($rp4->raw === "ativo" && $rp4->status_source === "situacao" && $rp4->normalized === "active", "non-string skipped 1", $failures, $checks);
$pp5 = new \stdClass;
$pp5->status = null;
$pp5->situacao = 5;
$pp5->subscription_status = "trial";
$rp5 = runTag($pp5);
check($rp5->raw === "trial" && $rp5->status_source === "subscription_status" && $rp5->normalized === "trialing", "non-string skipped 2", $failures, $checks);
$pp6 = new \stdClass;
$pp6->status = 1;
$pp6->situacao = null;
$rp6 = runTag($pp6);
check($rp6->raw === "" && $rp6->status_source === "none" && $rp6->has_status === false && $rp6->normalized === "unknown", "all non-string empty", $failures, $checks);
$pe = new \stdClass;
$pe->status = "active";
$pe->extra = "x";
$pe->foo = 1;
$re = runTag($pe);
check($re->raw === "active" && fieldCount($re) === 8, "extra ignored", $failures, $checks);
$map = ["active" => "active", "ativo" => "active", "ativa" => "active", "trialing" => "trialing", "trial" => "trialing", "teste" => "trialing", "em_teste" => "trialing", "past_due" => "past_due", "inadimplente" => "past_due", "atrasado" => "past_due", "suspended" => "suspended", "suspenso" => "suspended", "suspensa" => "suspended", "canceled" => "canceled", "cancelled" => "canceled", "cancelado" => "canceled", "cancelada" => "canceled", "expired" => "expired", "expirado" => "expired", "expirada" => "expired"];
foreach ($map as $in => $exp) {
  $pm = new \stdClass;
  $pm->status = $in;
  $rm = runTag($pm);
  check($rm->normalized === $exp && $rm->is_known === true && $rm->has_status === true && $rm->raw === $in, "map " . $in, $failures, $checks);
}
$unknowns = ["Active", "ACTIVE", " active", "active ", "Ativo", "EM_TESTE", "Cancelado", "bogus", "unknown", "ATIVO", "Cancelada"];
foreach ($unknowns as $u) {
  $pu = new \stdClass;
  $pu->status = $u;
  $ru = runTag($pu);
  check($ru->normalized === "unknown" && $ru->category === "unknown" && $ru->is_known === false && $ru->has_status === true && $ru->raw === $u, "unknown " . $u, $failures, $checks);
}
$pes = new \stdClass;
$pes->status = "";
$res = runTag($pes);
check($res->raw === "" && $res->normalized === "unknown" && $res->has_status === true && $res->is_known === false, "empty string is string", $failures, $checks);
$pc1 = new \stdClass;
$pc1->status = "active";
$rc1 = runTag($pc1);
check($rc1->category === "current" && $rc1->is_active === true && $rc1->is_ended === false && $rc1->is_known === true, "category active", $failures, $checks);
$pc2 = new \stdClass;
$pc2->status = "trial";
$rc2 = runTag($pc2);
check($rc2->category === "current" && $rc2->is_active === false && $rc2->is_ended === false && $rc2->is_known === true, "category trial", $failures, $checks);
$pc3 = new \stdClass;
$pc3->status = "past_due";
$rc3 = runTag($pc3);
check($rc3->category === "attention" && $rc3->is_active === false && $rc3->is_ended === false, "category past_due", $failures, $checks);
$pc4 = new \stdClass;
$pc4->status = "suspenso";
$rc4 = runTag($pc4);
check($rc4->category === "attention" && $rc4->is_active === false && $rc4->is_ended === false, "category suspenso", $failures, $checks);
$pc5 = new \stdClass;
$pc5->status = "cancelado";
$rc5 = runTag($pc5);
check($rc5->category === "ended" && $rc5->is_active === false && $rc5->is_ended === true, "category cancelado", $failures, $checks);
$pc6 = new \stdClass;
$pc6->status = "expirada";
$rc6 = runTag($pc6);
check($rc6->category === "ended" && $rc6->is_active === false && $rc6->is_ended === true, "category expirada", $failures, $checks);
$inp = new \stdClass;
$inp->status = "suspenso";
$inp->other = "keep";
$beforeStatus = $inp->status;
$beforeOther = $inp->other;
$beforeCount = fieldCount($inp);
$a1 = runTag($inp);
$a2 = runTag($inp);
check($a1->raw === $a2->raw && $a1->normalized === $a2->normalized && $a1->status_source === $a2->status_source && $a1->has_status === $a2->has_status && $a1->category === $a2->category && $a1->is_active === $a2->is_active && $a1->is_ended === $a2->is_ended && $a1->is_known === $a2->is_known, "deterministic", $failures, $checks);
check($inp->status === $beforeStatus && $inp->other === $beforeOther && fieldCount($inp) === $beforeCount, "not mutated", $failures, $checks);
check(fieldCount($a1) === 8, "eight fields", $failures, $checks);
$pu2 = new \stdClass;
$pu2->subscription_status = "weird";
$ru2 = runTag($pu2);
check($ru2->has_status === true && $ru2->raw === "weird" && $ru2->normalized === "unknown" && $ru2->status_source === "subscription_status" && $ru2->is_known === false, "unknown with has_status", $failures, $checks);
echo "checks: " . $checks . " failures: " . $failures . "\n";
exit($failures === 0 ? 0 : 1);
