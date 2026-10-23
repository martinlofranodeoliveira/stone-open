<?php
require __DIR__ . '/../src/App/Support/Community/verify_target_tag.php';

$failures = 0;
$checks = 0;
function check($cond, $msg) {
    global $failures, $checks;
    $checks++;
    if ($cond) {
        echo "PASS: " . $msg . "\n";
    } else {
        $failures++;
        echo "FAIL: " . $msg . "\n";
    }
}
function out($payload) {
    return \App\Support\Community\VerifyTargetTag\runFeature($payload);
}
function isEmptyTag($o) {
    return $o->raw === "" && $o->normalized === "unknown" && $o->target_source === "none" && $o->has_target === false && $o->category === "unknown" && $o->is_known === false;
}

$p = new \stdClass();
$o = out($p);
check(isEmptyTag($o), "empty object yields empty tag");

$p = new \stdClass();
$p->target = "payments";
$o = out($p);
check($o->raw === "payments" && $o->normalized === "payments" && $o->target_source === "target" && $o->has_target === true && $o->category === "transaction" && $o->is_known === true, "target payments maps to transaction");

$p = new \stdClass();
$p->target = "pagamentos";
$p->alvo = "faturas";
$p->verify_target = "assinatura";
$o = out($p);
check($o->raw === "pagamentos" && $o->normalized === "payments" && $o->target_source === "target" && $o->category === "transaction" && $o->is_known === true && $o->has_target === true, "target precedence with pagamentos");

$p = new \stdClass();
$p->target = "Payments";
$o = out($p);
check($o->normalized === "unknown" && $o->category === "unknown" && $o->is_known === false && $o->raw === "Payments" && $o->target_source === "target" && $o->has_target === true, "case-sensitive exact match");

$p = new \stdClass();
$p->target = " payments";
$o = out($p);
check($o->normalized === "unknown", "no trimming");

$p = new \stdClass();
$p->alvo = "faturas";
$o = out($p);
check($o->raw === "faturas" && $o->normalized === "invoices" && $o->target_source === "alvo" && $o->category === "billing" && $o->is_known === true && $o->has_target === true, "alvo faturas maps to invoices/billing");

$p = new \stdClass();
$p->alvo = "assinatura";
$o = out($p);
check($o->raw === "assinatura" && $o->normalized === "subscriptions" && $o->category === "recurring" && $o->is_known === true && $o->target_source === "alvo", "alvo assinatura maps to subscriptions/recurring");

$p = new \stdClass();
$p->verify_target = "invoice";
$o = out($p);
check($o->normalized === "invoices" && $o->category === "billing" && $o->target_source === "verify_target", "verify_target invoice fallback");

$p = new \stdClass();
$p->alvo = "fatura";
$o = out($p);
check($o->normalized === "invoices" && $o->category === "billing", "fatura singular maps to invoices");

$p = new \stdClass();
$p->verify_target = "assinaturas";
$o = out($p);
check($o->normalized === "subscriptions" && $o->category === "recurring", "assinaturas maps to subscriptions");

check(isEmptyTag(out(null)), "null behaves as empty");
check(isEmptyTag(out("payments")), "string payload behaves as empty");
check(isEmptyTag(out(123)), "int payload behaves as empty");
check(isEmptyTag(out(array("target" => "payments"))), "array payload behaves as empty");
check(isEmptyTag(out(true)), "bool payload behaves as empty");

$p = new \stdClass();
$p->target = 123;
$p->alvo = null;
$p->verify_target = array("x");
$o = out($p);
check(isEmptyTag($o), "non-string target fields ignored");

$p = new \stdClass();
$p->target = "payments";
$p->extra = "keep";
$p->other = 42;
$before = json_encode($p);
$o = out($p);
$after = json_encode($p);
check($before === $after, "input never mutated");
check($o->normalized === "payments" && $o->category === "transaction", "extra fields ignored");
check(!isset($o->extra), "output has no extra fields");

$a = new \stdClass();
$a->alvo = "faturas";
$b = new \stdClass();
$b->alvo = "faturas";
check(json_encode(out($a)) === json_encode(out($b)), "deterministic identical output");
check(json_encode(out($a)) === json_encode(out($a)), "same reference twice identical");

$p = new \stdClass();
$p->target = "pagamento";
$o = out($p);
check($o->normalized === "payments" && $o->category === "transaction", "pagamento singular");

$p = new \stdClass();
$p->target = "bogus";
$o = out($p);
check($o->normalized === "unknown" && $o->category === "unknown" && $o->is_known === false && $o->raw === "bogus" && $o->has_target === true, "unknown raw stays unknown");

echo "checks=" . $checks . " failures=" . $failures . "\n";
exit($failures === 0 ? 0 : 1);
