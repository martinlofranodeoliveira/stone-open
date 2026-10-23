<?php
require_once __DIR__ . '/../src/App/Support/Community/site_status_tag.php';

$failures = 0;
$passes = 0;

function t_check($label, $cond, &$failures, &$passes) {
    if ($cond) {
        $passes = $passes + 1;
        echo "PASS " . $label . "\n";
    } else {
        $failures = $failures + 1;
        echo "FAIL " . $label . "\n";
    }
}

function t_call($payload) {
    return \App\Support\Community\SiteStatusTag\runFeature($payload);
}

function t_ok($r, $raw, $normalized, $site_source, $has, $category, $active, $known) {
    if (!($r instanceof \stdClass)) {
        return false;
    }
    if ($r->raw !== $raw) {
        return false;
    }
    if ($r->normalized !== $normalized) {
        return false;
    }
    if ($r->site_source !== $site_source) {
        return false;
    }
    if ($r->has_site_status !== $has) {
        return false;
    }
    if ($r->category !== $category) {
        return false;
    }
    if ($r->is_active !== $active) {
        return false;
    }
    if ($r->is_known !== $known) {
        return false;
    }
    return true;
}

$p = new \stdClass;
$r = t_call($p);
t_check("empty-object", t_ok($r, "", "unknown", "none", false, "unknown", false, false), $failures, $passes);

$r = t_call(null);
t_check("null-payload", t_ok($r, "", "unknown", "none", false, "unknown", false, false), $failures, $passes);

$r = t_call(123);
t_check("int-payload", t_ok($r, "", "unknown", "none", false, "unknown", false, false), $failures, $passes);

$r = t_call("active");
t_check("string-payload", t_ok($r, "", "unknown", "none", false, "unknown", false, false), $failures, $passes);

$r = t_call(true);
t_check("bool-payload", t_ok($r, "", "unknown", "none", false, "unknown", false, false), $failures, $passes);

$r = t_call(array("status" => "active"));
t_check("array-payload", t_ok($r, "", "unknown", "none", false, "unknown", false, false), $failures, $passes);

$p = new \stdClass;
$p->status = "active";
$r = t_call($p);
t_check("example-status-active", t_ok($r, "active", "active", "status", true, "live", true, true), $failures, $passes);

$p = new \stdClass;
$p->situacao = "suspensa";
$r = t_call($p);
t_check("example-situacao-suspensa", t_ok($r, "suspensa", "suspended", "situacao", true, "blocked", false, true), $failures, $passes);

$p = new \stdClass;
$r = t_call($p);
t_check("example-empty", t_ok($r, "", "unknown", "none", false, "unknown", false, false), $failures, $passes);

$p = new \stdClass;
$p->status = "active";
$p->situacao = "suspensa";
$p->site_status = "arquivado";
$r = t_call($p);
t_check("priority-status-first", t_ok($r, "active", "active", "status", true, "live", true, true), $failures, $passes);

$p = new \stdClass;
$p->situacao = "suspensa";
$p->site_status = "arquivado";
$r = t_call($p);
t_check("priority-situacao-second", t_ok($r, "suspensa", "suspended", "situacao", true, "blocked", false, true), $failures, $passes);

$p = new \stdClass;
$p->site_status = "arquivado";
$r = t_call($p);
t_check("priority-site_status-third", t_ok($r, "arquivado", "archived", "site_status", true, "dormant", false, true), $failures, $passes);

$p = new \stdClass;
$p->status = 123;
$p->situacao = "ativa";
$p->site_status = "active";
$r = t_call($p);
t_check("priority-skip-non-string-status", t_ok($r, "ativa", "active", "situacao", true, "live", true, true), $failures, $passes);

$p = new \stdClass;
$p->status = null;
$p->situacao = 42;
$p->site_status = "inativo";
$r = t_call($p);
t_check("priority-skip-null-and-int", t_ok($r, "inativo", "inactive", "site_status", true, "dormant", false, true), $failures, $passes);

$p = new \stdClass;
$p->status = 1;
$p->situacao = 2;
$p->site_status = 3;
$r = t_call($p);
t_check("all-non-string", t_ok($r, "", "unknown", "none", false, "unknown", false, false), $failures, $passes);

$map = array(
    array("active", "active"),
    array("ativo", "active"),
    array("ativa", "active"),
    array("inactive", "inactive"),
    array("inativo", "inactive"),
    array("inativa", "inactive"),
    array("suspended", "suspended"),
    array("suspenso", "suspended"),
    array("suspensa", "suspended"),
    array("archived", "archived"),
    array("arquivado", "archived"),
    array("arquivada", "archived")
);
foreach ($map as $pair) {
    $p = new \stdClass;
    $p->status = $pair[0];
    $r = t_call($p);
    t_check("map-" . $pair[0], $r->normalized === $pair[1] && $r->raw === $pair[0] && $r->is_known === true, $failures, $passes);
}

$unknowns = array("Active", "ATIVO", " active", "active ", "Ativo", "INACTIVE", "", "other", "bloqueado", " Ativa");
foreach ($unknowns as $v) {
    $p = new \stdClass;
    $p->status = $v;
    $r = t_call($p);
    if ($v === "") {
        t_check("unknown-empty-string", t_ok($r, "", "unknown", "status", true, "unknown", false, false), $failures, $passes);
    } else {
        t_check("unknown-" . $v, t_ok($r, $v, "unknown", "status", true, "unknown", false, false), $failures, $passes);
    }
}

$p = new \stdClass;
$p->status = "inactive";
$r = t_call($p);
t_check("category-inactive-dormant", t_ok($r, "inactive", "inactive", "status", true, "dormant", false, true), $failures, $passes);

$p = new \stdClass;
$p->status = "arquivada";
$r = t_call($p);
t_check("category-archived-dormant", t_ok($r, "arquivada", "archived", "status", true, "dormant", false, true), $failures, $passes);

$p = new \stdClass;
$p->status = "suspenso";
$r = t_call($p);
t_check("category-suspended-blocked", t_ok($r, "suspenso", "suspended", "status", true, "blocked", false, true), $failures, $passes);

$p = new \stdClass;
$p->status = "zzz";
$r = t_call($p);
t_check("category-unknown", t_ok($r, "zzz", "unknown", "status", true, "unknown", false, false), $failures, $passes);

$p = new \stdClass;
$p->status = "active";
$p->extra = "keep-me";
$p->other = 99;
$r = t_call($p);
$tExtraIgnored = t_ok($r, "active", "active", "status", true, "live", true, true);
if (isset($r->extra)) {
    $tExtraIgnored = false;
}
if (isset($r->other)) {
    $tExtraIgnored = false;
}
t_check("extra-fields-ignored", $tExtraIgnored, $failures, $passes);

$p = new \stdClass;
$p->status = "active";
$beforeStatus = $p->status;
$r = t_call($p);
$tUnmutated = ($p->status === $beforeStatus);
if (isset($p->normalized)) {
    $tUnmutated = false;
}
if (isset($p->raw)) {
    $tUnmutated = false;
}
t_check("input-unmutated", $tUnmutated, $failures, $passes);

$p = new \stdClass;
$rEmptyBefore = t_call($p);
$tEmptyUnmutated = (get_object_vars($p) === array());
t_check("empty-input-unmutated", $tEmptyUnmutated && t_ok($rEmptyBefore, "", "unknown", "none", false, "unknown", false, false), $failures, $passes);

$p1 = new \stdClass;
$p1->situacao = "inativa";
$p2 = new \stdClass;
$p2->situacao = "inativa";
$r1 = t_call($p1);
$r2 = t_call($p2);
$tDeterministic = ($r1->raw === $r2->raw && $r1->normalized === $r2->normalized && $r1->site_source === $r2->site_source && $r1->has_site_status === $r2->has_site_status && $r1->category === $r2->category && $r1->is_active === $r2->is_active && $r1->is_known === $r2->is_known);
t_check("deterministic", $tDeterministic, $failures, $passes);

echo "DONE passes=" . $passes . " failures=" . $failures . "\n";
exit($failures > 0 ? 1 : 0);
