<?php
require __DIR__ . '/../src/App/Support/Community/user_role_tag.php';

$failures = 0;
function ok($cond, $msg) {
    global $failures;
    if ($cond) {
        echo "ok - " . $msg . "\n";
    } else {
        $failures++;
        echo "FAIL - " . $msg . "\n";
    }
}
function tag($p) {
    return \App\Support\Community\UserRoleTag\runFeature($p);
}
function isDefaults($o) {
    return $o->raw === "" && $o->normalized === "unknown" && $o->role_source === "none" && $o->has_role === false && $o->category === "unknown" && $o->is_privileged === false && $o->is_known === false;
}

// Empty object defaults
$e = new \stdClass;
$r = tag($e);
ok(isDefaults($r), "empty object yields unknown defaults");

// Non-object inputs behave as empty
ok(isDefaults(tag(null)), "null behaves as empty");
ok(isDefaults(tag([])), "array behaves as empty");
ok(isDefaults(tag("admin")), "string behaves as empty");
ok(isDefaults(tag(42)), "int behaves as empty");
ok(isDefaults(tag(true)), "bool behaves as empty");

// Contract examples
$a = new \stdClass;
$a->role = "admin";
$ra = tag($a);
ok($ra->raw === "admin" && $ra->normalized === "admin" && $ra->role_source === "role" && $ra->has_role === true && $ra->category === "full" && $ra->is_privileged === true && $ra->is_known === true, "role admin maps to full privileged");

$b = new \stdClass;
$b->papel = "operador";
$rb = tag($b);
ok($rb->raw === "operador" && $rb->normalized === "operator" && $rb->role_source === "papel" && $rb->has_role === true && $rb->category === "operational" && $rb->is_privileged === false && $rb->is_known === true, "papel operador maps to operational");

// Precedence: role first
$p = new \stdClass;
$p->role = "manager";
$p->papel = "admin";
$p->user_role = "viewer";
$rp = tag($p);
ok($rp->raw === "manager" && $rp->role_source === "role", "precedence role wins");

// Precedence: papel second when role missing
$p2 = new \stdClass;
$p2->papel = "admin";
$p2->user_role = "viewer";
$rp2 = tag($p2);
ok($rp2->raw === "admin" && $rp2->role_source === "papel", "precedence papel wins over user_role");

// Precedence: user_role third
$p3 = new \stdClass;
$p3->user_role = "viewer";
$rp3 = tag($p3);
ok($rp3->raw === "viewer" && $rp3->role_source === "user_role" && $rp3->normalized === "viewer", "user_role used when others missing");

// Precedence skips non-string role
$p4 = new \stdClass;
$p4->role = 123;
$p4->papel = "gerente";
$rp4 = tag($p4);
ok($rp4->raw === "gerente" && $rp4->role_source === "papel" && $rp4->normalized === "manager", "non-string role falls through to papel");

$p5 = new \stdClass;
$p5->role = null;
$p5->user_role = "leitor";
$rp5 = tag($p5);
ok($rp5->raw === "leitor" && $rp5->role_source === "user_role" && $rp5->normalized === "viewer", "null role falls through to user_role");

// Multilingual exact mapping
$cases = [
    ["administrador", "admin"],
    ["gerente", "manager"],
    ["operador", "operator"],
    ["operator", "operator"],
    ["leitor", "viewer"],
    ["visualizador", "viewer"],
    ["manager", "manager"],
    ["viewer", "viewer"],
];
foreach ($cases as $c) {
    $o = new \stdClass;
    $o->role = $c[0];
    $o2 = tag($o);
    ok($o2->normalized === $c[1] && $o2->is_known === true, "maps " . $c[0] . " to " . $c[1]);
}

// Case-sensitive, no trimming or folding
foreach (["Admin", "ADMIN", " admin", "admin ", "Gerente", "OPERADOR", "Leitor"] as $v) {
    $o = new \stdClass;
    $o->role = $v;
    $o2 = tag($o);
    ok($o2->normalized === "unknown" && $o2->is_known === false && $o2->category === "unknown" && $o2->raw === $v && $o2->has_role === true, "exact match rejects " . $v);
}

// Unknown stays unknown
$u = new \stdClass;
$u->role = "root";
$ru = tag($u);
ok($ru->normalized === "unknown" && $ru->category === "unknown" && $ru->is_privileged === false && $ru->is_known === false && $ru->has_role === true && $ru->raw === "root", "unknown role yields unknown category");

// Category and privilege matrix
$cat = new \stdClass;
$cat->role = "administrador";
ok(tag($cat)->category === "full" && tag($cat)->is_privileged === true, "admin category full privileged");
$cat->role = "manager";
ok(tag($cat)->category === "elevated" && tag($cat)->is_privileged === true, "manager category elevated privileged");
$cat->role = "operator";
ok(tag($cat)->category === "operational" && tag($cat)->is_privileged === false, "operator category operational not privileged");
$cat->role = "viewer";
ok(tag($cat)->category === "readonly" && tag($cat)->is_privileged === false, "viewer category readonly not privileged");

// Extra fields ignored
$x = new \stdClass;
$x->role = "admin";
$x->extra = "zzz";
$x->other = 123;
$rx = tag($x);
ok($rx->normalized === "admin" && $rx->role_source === "role", "extra fields ignored");

// Input never mutated
$m = new \stdClass;
$m->role = "admin";
$m->extra = 1;
$before = get_object_vars($m);
$rm = tag($m);
$after = get_object_vars($m);
ok($before === $after && count($after) === 2 && $m->role === "admin", "input not mutated");

// Deterministic identical output
$d = new \stdClass;
$d->papel = "gerente";
$d1 = tag($d);
$d2 = tag($d);
ok($d1 == $d2 && $d1->normalized === "manager" && $d2->normalized === "manager", "identical input yields identical output");

if ($failures === 0) {
    echo "ALL PASS\n";
} else {
    echo $failures . " FAILURES\n";
}
exit($failures === 0 ? 0 : 1);
