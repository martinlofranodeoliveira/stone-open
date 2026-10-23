<?php
namespace App\Support\Community\InvoiceKindTag;

function runFeature($payload) {
    $k0 = '';
    $k0_found = false;
    $kind_source = 'none';
    if ($payload instanceof \stdClass) {
        if (isset($payload->kind) && is_string($payload->kind)) {
            $k0 = $payload->kind;
            $k0_found = true;
            $kind_source = 'kind';
        } else {
            if (isset($payload->tipo) && is_string($payload->tipo)) {
                $k0 = $payload->tipo;
                $k0_found = true;
                $kind_source = 'tipo';
            } else {
                if (isset($payload->invoice_kind) && is_string($payload->invoice_kind)) {
                    $k0 = $payload->invoice_kind;
                    $k0_found = true;
                    $kind_source = 'invoice_kind';
                }
            }
        }
    }
    $has_kind = false;
    if ($k0_found === true) {
        $has_kind = true;
    }
    $raw = '';
    if ($has_kind === true) {
        $raw = $k0;
    }
    $normalized = 'unknown';
    if ($raw === 'single' || $raw === 'avulsa' || $raw === 'avulso') {
        $normalized = 'single';
    } else {
        if ($raw === 'recurring' || $raw === 'recorrente' || $raw === 'recorrencia') {
            $normalized = 'recurring';
        } else {
            if ($raw === 'subscription' || $raw === 'assinatura' || $raw === 'assinaturas') {
                $normalized = 'subscription';
            }
        }
    }
    $category = 'unknown';
    if ($normalized === 'single') {
        $category = 'once';
    } else {
        if ($normalized === 'recurring' || $normalized === 'subscription') {
            $category = 'cycle';
        }
    }
    $is_recurring = false;
    if ($normalized === 'recurring' || $normalized === 'subscription') {
        $is_recurring = true;
    }
    $is_known = false;
    if ($normalized !== 'unknown') {
        $is_known = true;
    }
    $out = new \stdClass();
    $out->raw = $raw;
    $out->normalized = $normalized;
    $out->kind_source = $kind_source;
    $out->has_kind = $has_kind;
    $out->category = $category;
    $out->is_recurring = $is_recurring;
    $out->is_known = $is_known;
    return $out;
}
