<?php
namespace App\Support\Community\VerifyTargetTag;

function runFeature($payload) {
    $t0 = "";
    $targetSource = "none";
    $hasTarget = false;
    $hasTargetProp = false;
    $hasAlvoProp = false;
    $hasVerifyProp = false;
    $targetValue = "";
    $alvoValue = "";
    $verifyValue = "";
    $isTargetString = false;
    $isAlvoString = false;
    $isVerifyString = false;
    $isObj = $payload instanceof \stdClass;
    if ($isObj === true) {
        $vars = get_object_vars($payload);
        foreach ($vars as $k => $v) {
            if ($k === "target") {
                $hasTargetProp = true;
            }
            if ($k === "alvo") {
                $hasAlvoProp = true;
            }
            if ($k === "verify_target") {
                $hasVerifyProp = true;
            }
        }
        if ($hasTargetProp === true) {
            $targetValue = $payload->target;
            $isTargetString = is_string($targetValue);
        }
        if ($hasAlvoProp === true) {
            $alvoValue = $payload->alvo;
            $isAlvoString = is_string($alvoValue);
        }
        if ($hasVerifyProp === true) {
            $verifyValue = $payload->verify_target;
            $isVerifyString = is_string($verifyValue);
        }
        if ($isTargetString === true) {
            $t0 = $targetValue;
            $targetSource = "target";
            $hasTarget = true;
        } else {
            if ($isAlvoString === true) {
                $t0 = $alvoValue;
                $targetSource = "alvo";
                $hasTarget = true;
            } else {
                if ($isVerifyString === true) {
                    $t0 = $verifyValue;
                    $targetSource = "verify_target";
                    $hasTarget = true;
                }
            }
        }
    }
    $raw = "";
    if ($hasTarget === true) {
        $raw = $t0;
    }
    $normalized = "unknown";
    if ($raw === "payments") {
        $normalized = "payments";
    }
    if ($raw === "pagamentos") {
        $normalized = "payments";
    }
    if ($raw === "pagamento") {
        $normalized = "payments";
    }
    if ($raw === "invoices") {
        $normalized = "invoices";
    }
    if ($raw === "invoice") {
        $normalized = "invoices";
    }
    if ($raw === "faturas") {
        $normalized = "invoices";
    }
    if ($raw === "fatura") {
        $normalized = "invoices";
    }
    if ($raw === "subscriptions") {
        $normalized = "subscriptions";
    }
    if ($raw === "subscription") {
        $normalized = "subscriptions";
    }
    if ($raw === "assinaturas") {
        $normalized = "subscriptions";
    }
    if ($raw === "assinatura") {
        $normalized = "subscriptions";
    }
    $category = "unknown";
    if ($normalized === "payments") {
        $category = "transaction";
    }
    if ($normalized === "invoices") {
        $category = "billing";
    }
    if ($normalized === "subscriptions") {
        $category = "recurring";
    }
    $isKnown = false;
    if ($normalized !== "unknown") {
        $isKnown = true;
    }
    $out = new \stdClass();
    $out->raw = $raw;
    $out->normalized = $normalized;
    $out->target_source = $targetSource;
    $out->has_target = $hasTarget;
    $out->category = $category;
    $out->is_known = $isKnown;
    return $out;
}
