<?php
require __DIR__ . '/../src/App/Support/Community/admin_report_tag.php';

$failures = 0;
$passes = 0;

function t_check($cond, $msg) {
  global $failures, $passes;
  if ($cond) {
    $passes++;
    echo "PASS: " . $msg . "\n";
  } else {
    $failures++;
    echo "FAIL: " . $msg . "\n";
  }
}

function t_run($payload) {
  return \App\Support\Community\AdminReportTag\runFeature($payload);
}

function t_same($a, $b) {
  return $a->raw === $b->raw && $a->normalized === $b->normalized && $a->report_source === $b->report_source && $a->has_report === $b->has_report && $a->category === $b->category && $a->is_known === $b->is_known;
}

$o = new \stdClass();
$r = t_run($o);
t_check($r->raw === "", "empty raw");
t_check($r->normalized === "unknown", "empty normalized");
t_check($r->report_source === "none", "empty source");
t_check($r->has_report === false, "empty has_report");
t_check($r->category === "unknown", "empty category");
t_check($r->is_known === false, "empty is_known");

$p = new \stdClass();
$p->relatorio = "assinaturas";
$r = t_run($p);
t_check($r->raw === "assinaturas", "relatorio raw");
t_check($r->normalized === "subscriptions", "relatorio normalized");
t_check($r->report_source === "relatorio", "relatorio source");
t_check($r->has_report === true, "relatorio has_report");
t_check($r->category === "recurring", "relatorio category");
t_check($r->is_known === true, "relatorio is_known");

$p = new \stdClass();
$p->report = "payments";
$r = t_run($p);
t_check($r->raw === "payments", "payments raw");
t_check($r->normalized === "payments", "payments normalized");
t_check($r->report_source === "report", "payments source");
t_check($r->category === "financial", "payments category");
t_check($r->is_known === true, "payments is_known");

$p = new \stdClass();
$p->report = "PAGAMENTOS";
$r = t_run($p);
t_check($r->raw === "PAGAMENTOS", "upper raw kept");
t_check($r->normalized === "unknown", "upper unknown");
t_check($r->is_known === false, "upper not known");
t_check($r->category === "unknown", "upper category unknown");
t_check($r->has_report === true, "upper has_report true");

$p2 = new \stdClass();
$p2->report = "Payments";
$r2 = t_run($p2);
t_check($r2->normalized === "unknown", "capitalized unknown");
t_check($r2->is_known === false, "capitalized not known");

$p3 = new \stdClass();
$p3->report = " payments";
$r3 = t_run($p3);
t_check($r3->normalized === "unknown", "leading space unknown no trim");

foreach ([null, "x", 123, true, ["report" => "payments"]] as $i => $bad) {
  $rb = t_run($bad);
  t_check($rb->raw === "" && $rb->normalized === "unknown" && $rb->report_source === "none" && $rb->has_report === false && $rb->category === "unknown" && $rb->is_known === false, "non-object #" . $i . " empty");
}

$ex = new \stdClass();
$ex->report = "faturas";
$ex->extra = "keepme";
$ex->other = 42;
$beforeVars = get_object_vars($ex);
$beforeReport = $ex->report;
$beforeExtra = $ex->extra;
$re = t_run($ex);
t_check($re->raw === "faturas" && $re->normalized === "invoices" && $re->report_source === "report", "extra ignored mapping");
t_check($re->category === "financial" && $re->is_known === true, "extra ignored category");
t_check($ex->report === $beforeReport && $ex->extra === $beforeExtra, "input not mutated values");
t_check(get_object_vars($ex) === $beforeVars, "input not mutated keys");
t_check(!isset($re->extra), "output has no extra");

$pp = new \stdClass();
$pp->report = "payments";
$pp->relatorio = "assinaturas";
$pp->report_type = "faturas";
$rp = t_run($pp);
t_check($rp->raw === "payments" && $rp->report_source === "report", "priority report first");

$pp2 = new \stdClass();
$pp2->report = 123;
$pp2->relatorio = "assinaturas";
$pp2->report_type = "faturas";
$rp2 = t_run($pp2);
t_check($rp2->raw === "assinaturas" && $rp2->report_source === "relatorio", "non-string report falls to relatorio");

$pp3 = new \stdClass();
$pp3->report_type = "contas";
$rp3 = t_run($pp3);
t_check($rp3->raw === "contas" && $rp3->normalized === "accounts" && $rp3->report_source === "report_type", "report_type path");
t_check($rp3->category === "operational" && $rp3->is_known === true, "accounts operational");

$pp4 = new \stdClass();
$pp4->report_type = "encaminhamentos";
$rp4 = t_run($pp4);
t_check($rp4->normalized === "forwards" && $rp4->category === "operational", "forwards operational");

$pp5 = new \stdClass();
$pp5->report = "pagamentos";
$rp5 = t_run($pp5);
t_check($rp5->normalized === "payments" && $rp5->category === "financial", "pagamentos mapping");

$d = new \stdClass();
$d->relatorio = "assinaturas";
$a1 = t_run($d);
$a2 = t_run($d);
t_check(t_same($a1, $a2), "deterministic identical output");
$d2 = new \stdClass();
$d2->relatorio = "assinaturas";
$a3 = t_run($d2);
t_check(t_same($a1, $a3), "deterministic across equal inputs");

echo "passes=" . $passes . " failures=" . $failures . "\n";
exit($failures === 0 ? 0 : 1);
