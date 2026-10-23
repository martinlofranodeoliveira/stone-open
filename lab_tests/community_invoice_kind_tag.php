<?php
require_once __DIR__ . '/../src/App/Support/Community/invoice_kind_tag.php';
function ck_eq($a, $b, $label) {
    if ($a !== $b) {
        echo 'FAIL ';
        echo $label;
        echo ' expected=';
        echo var_export($b, true);
        echo ' got=';
        echo var_export($a, true);
        echo PHP_EOL;
        exit(1);
    }
}
function ck($cond, $label) {
    if (!$cond) {
        echo 'FAIL ';
        echo $label;
        echo PHP_EOL;
        exit(1);
    }
}
function ck_out($out, $raw, $normalized, $kind_source, $has_kind, $category, $is_recurring, $is_known, $label) {
    ck($out instanceof \stdClass, $label);
    $vars = get_object_vars($out);
    ck(count($vars) === 7, $label);
    ck_eq($out->raw, $raw, $label);
    ck_eq($out->normalized, $normalized, $label);
    ck_eq($out->kind_source, $kind_source, $label);
    ck_eq($out->has_kind, $has_kind, $label);
    ck_eq($out->category, $category, $label);
    ck_eq($out->is_recurring, $is_recurring, $label);
    ck_eq($out->is_known, $is_known, $label);
}
$e = new \stdClass();
$r = \App\Support\Community\InvoiceKindTag\runFeature($e);
ck_out($r, '', 'unknown', 'none', false, 'unknown', false, false, 'empty');
$r = \App\Support\Community\InvoiceKindTag\runFeature(null);
ck_out($r, '', 'unknown', 'none', false, 'unknown', false, false, 'null');
$r = \App\Support\Community\InvoiceKindTag\runFeature('avulsa');
ck_out($r, '', 'unknown', 'none', false, 'unknown', false, false, 'string');
$r = \App\Support\Community\InvoiceKindTag\runFeature(123);
ck_out($r, '', 'unknown', 'none', false, 'unknown', false, false, 'int');
$r = \App\Support\Community\InvoiceKindTag\runFeature(true);
ck_out($r, '', 'unknown', 'none', false, 'unknown', false, false, 'bool');
$a = array('kind' => 'single');
$r = \App\Support\Community\InvoiceKindTag\runFeature($a);
ck_out($r, '', 'unknown', 'none', false, 'unknown', false, false, 'array');
$o = new \stdClass();
$o->kind = 'avulsa';
$r = \App\Support\Community\InvoiceKindTag\runFeature($o);
ck_out($r, 'avulsa', 'single', 'kind', true, 'once', false, true, 'ex1');
$o = new \stdClass();
$o->tipo = 'assinatura';
$r = \App\Support\Community\InvoiceKindTag\runFeature($o);
ck_out($r, 'assinatura', 'subscription', 'tipo', true, 'cycle', true, true, 'ex2');
$o = new \stdClass();
$o->kind = 'single';
$o->tipo = 'subscription';
$o->invoice_kind = 'recurring';
$r = \App\Support\Community\InvoiceKindTag\runFeature($o);
ck_out($r, 'single', 'single', 'kind', true, 'once', false, true, 'prec-kind');
$o = new \stdClass();
$o->kind = 5;
$o->tipo = 'recorrente';
$o->invoice_kind = 'single';
$r = \App\Support\Community\InvoiceKindTag\runFeature($o);
ck_out($r, 'recorrente', 'recurring', 'tipo', true, 'cycle', true, true, 'prec-tipo');
$o = new \stdClass();
$o->kind = 5;
$o->tipo = 6;
$o->invoice_kind = 'assinaturas';
$r = \App\Support\Community\InvoiceKindTag\runFeature($o);
ck_out($r, 'assinaturas', 'subscription', 'invoice_kind', true, 'cycle', true, true, 'prec-invoice');
$o = new \stdClass();
$o->kind = 5;
$o->tipo = 6;
$o->invoice_kind = 7;
$r = \App\Support\Community\InvoiceKindTag\runFeature($o);
ck_out($r, '', 'unknown', 'none', false, 'unknown', false, false, 'prec-none');
$singles = array('single', 'avulsa', 'avulso');
foreach ($singles as $v) {
    $o = new \stdClass();
    $o->kind = $v;
    $r = \App\Support\Community\InvoiceKindTag\runFeature($o);
    ck_out($r, $v, 'single', 'kind', true, 'once', false, true, 'single-map');
}
$recs = array('recurring', 'recorrente', 'recorrencia');
foreach ($recs as $v) {
    $o = new \stdClass();
    $o->kind = $v;
    $r = \App\Support\Community\InvoiceKindTag\runFeature($o);
    ck_out($r, $v, 'recurring', 'kind', true, 'cycle', true, true, 'rec-map');
}
$subs = array('subscription', 'assinatura', 'assinaturas');
foreach ($subs as $v) {
    $o = new \stdClass();
    $o->kind = $v;
    $r = \App\Support\Community\InvoiceKindTag\runFeature($o);
    ck_out($r, $v, 'subscription', 'kind', true, 'cycle', true, true, 'sub-map');
}
$unks = array('Single', 'SINGLE', ' single', 'single ', 'Avulsa', 'foo', 'unknown', ' ciclo');
foreach ($unks as $v) {
    $o = new \stdClass();
    $o->kind = $v;
    $r = \App\Support\Community\InvoiceKindTag\runFeature($o);
    ck_out($r, $v, 'unknown', 'kind', true, 'unknown', false, false, 'unknown-map');
}
$o = new \stdClass();
$o->kind = 'avulsa';
$o->extra = 'keep';
$o->n = 1;
$before = var_export(get_object_vars($o), true);
$r = \App\Support\Community\InvoiceKindTag\runFeature($o);
ck_out($r, 'avulsa', 'single', 'kind', true, 'once', false, true, 'extra');
ck_eq(var_export(get_object_vars($o), true), $before, 'preserve');
$r2 = \App\Support\Community\InvoiceKindTag\runFeature($o);
ck_eq(var_export(get_object_vars($r), true), var_export(get_object_vars($r2), true), 'deterministic');
echo 'OK invoice_kind_tag';
echo PHP_EOL;
