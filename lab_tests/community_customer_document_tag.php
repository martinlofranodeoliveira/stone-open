<?php
require __DIR__ . '/../src/App/Support/Community/customer_document_tag.php';
function check_eq($a, $b, $msg) {
    if ($a !== $b) {
        echo "FAIL: " . $msg . " expected=" . var_export($b, true) . " got=" . var_export($a, true) . "\n";
        exit(1);
    }
    echo "PASS: " . $msg . "\n";
}
function mk($pairs) {
    $o = new \stdClass();
    foreach ($pairs as $k => $v) {
        $o->$k = $v;
    }
    return $o;
}
function run($p) {
    return \App\Support\Community\CustomerDocumentTag\runFeature($p);
}
$r = run(new \stdClass());
check_eq($r->raw, "", "empty raw");
check_eq($r->normalized, "unknown", "empty normalized");
check_eq($r->document_source, "none", "empty source");
check_eq($r->has_document, false, "empty has");
check_eq($r->category, "unknown", "empty category");
check_eq($r->is_brazilian, false, "empty brazilian");
check_eq($r->is_known, false, "empty known");
foreach ([null, "cpf", 123, true, ["document" => "cpf"], 0] as $i => $bad) {
    $o = run($bad);
    check_eq($o->raw, "", "non-object $i raw");
    check_eq($o->normalized, "unknown", "non-object $i normalized");
    check_eq($o->document_source, "none", "non-object $i source");
    check_eq($o->has_document, false, "non-object $i has");
    check_eq($o->category, "unknown", "non-object $i category");
    check_eq($o->is_brazilian, false, "non-object $i brazilian");
    check_eq($o->is_known, false, "non-object $i known");
}
$r = run(mk(["document" => "cpf"]));
check_eq($r->raw, "cpf", "doc cpf raw");
check_eq($r->normalized, "cpf", "doc cpf normalized");
check_eq($r->document_source, "document", "doc cpf source");
check_eq($r->has_document, true, "doc cpf has");
check_eq($r->category, "individual", "doc cpf category");
check_eq($r->is_brazilian, true, "doc cpf brazilian");
check_eq($r->is_known, true, "doc cpf known");
$r = run(mk(["documento" => "passaporte"]));
check_eq($r->raw, "passaporte", "documento passaporte raw");
check_eq($r->normalized, "passport", "documento passaporte normalized");
check_eq($r->document_source, "documento", "documento passaporte source");
check_eq($r->category, "foreign", "documento passaporte category");
check_eq($r->is_brazilian, false, "documento passaporte brazilian");
check_eq($r->is_known, true, "documento passaporte known");
$r = run(mk(["document" => "cnpj", "documento" => "cpf", "customer_document" => "passport"]));
check_eq($r->raw, "cnpj", "priority document wins");
check_eq($r->document_source, "document", "priority source document");
$r = run(mk(["documento" => "cpf", "customer_document" => "cnpj"]));
check_eq($r->raw, "cpf", "priority documento wins");
check_eq($r->document_source, "documento", "priority source documento");
$r = run(mk(["customer_document" => "passport"]));
check_eq($r->raw, "passport", "priority customer_document");
check_eq($r->document_source, "customer_document", "priority source customer_document");
check_eq($r->normalized, "passport", "customer passport normalized");
$r = run(mk(["document" => 123, "documento" => "cnpj"]));
check_eq($r->raw, "cnpj", "non-string document skipped");
check_eq($r->document_source, "documento", "non-string source fallback");
$r = run(mk(["document" => "CPF"]));
check_eq($r->normalized, "cpf", "CPF maps to cpf");
$r = run(mk(["document" => "CNPJ"]));
check_eq($r->normalized, "cnpj", "CNPJ maps to cnpj");
$r = run(mk(["document" => "cnpj"]));
check_eq($r->normalized, "cnpj", "cnpj maps");
check_eq($r->category, "company", "cnpj company");
check_eq($r->is_brazilian, true, "cnpj brazilian");
$r = run(mk(["document" => "passaporte"]));
check_eq($r->normalized, "passport", "passaporte maps");
foreach (["Cpf", " cpf", "cpf ", "Cpf ", "CNPJ ", "PASSAPORTE", "Passaporte", "PASSPORT", "Passport", "", "rg", "unknown"] as $s) {
    $o = run(mk(["document" => $s]));
    check_eq($o->normalized, "unknown", "unknown for [$s]");
    check_eq($o->category, "unknown", "unknown category for [$s]");
    check_eq($o->is_known, false, "unknown known for [$s]");
    check_eq($o->is_brazilian, false, "unknown brazilian for [$s]");
    check_eq($o->raw, $s, "raw preserved for [$s]");
    check_eq($o->has_document, true, "has for [$s]");
}
$p = mk(["document" => "cpf", "extra" => "x", "other" => 1]);
$o = run($p);
check_eq($o->normalized, "cpf", "extra ignored");
check_eq($p->extra, "x", "input not mutated extra");
check_eq(count(get_object_vars($p)), 3, "input key count unchanged");
$a = mk(["documento" => "cnpj"]);
$before = get_object_vars($a);
$o1 = run($a);
$o2 = run(mk(["documento" => "cnpj"]));
check_eq(get_object_vars($a), $before, "input never mutated");
check_eq(get_object_vars($o1), get_object_vars($o2), "deterministic identical output");
check_eq($o1->category, "company", "deterministic category");
echo "ALL OK\n";
