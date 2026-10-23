<?php
require __DIR__ . '/../src/App/Support/Community/api_resource_tag.php';

$failures = 0;
$passes = 0;
function check($name, $cond) {
    global $failures, $passes;
    if ($cond) {
        $passes++;
        echo "PASS " . $name . "\n";
    } else {
        $failures++;
        echo "FAIL " . $name . "\n";
    }
}
function mk($pairs) {
    $o = new \stdClass;
    foreach ($pairs as $k => $v) {
        $o->$k = $v;
    }
    return $o;
}
function eq($a, $b) {
    return $a == $b;
}
function outMatches($out, $raw, $normalized, $source, $has, $cat, $coll, $known) {
    return $out->raw === $raw && $out->normalized === $normalized && $out->resource_source === $source && $out->has_resource === $has && $out->category === $cat && $out->is_collection === $coll && $out->is_known === $known;
}

$r = \App\Support\Community\ApiResourceTag\runFeature(new \stdClass);
check('empty-object', outMatches($r, '', 'unknown', 'none', false, 'unknown', false, false));

foreach ([null, '', 'plans', 42, 3.5, true, false, [], ['resource' => 'plans']] as $i => $bad) {
    $o = \App\Support\Community\ApiResourceTag\runFeature($bad);
    check('non-object-' . $i, outMatches($o, '', 'unknown', 'none', false, 'unknown', false, false));
}

$r = \App\Support\Community\ApiResourceTag\runFeature(mk(['resource' => 'plans']));
check('example-plans', outMatches($r, 'plans', 'plans', 'resource', true, 'directory', true, true));

$r = \App\Support\Community\ApiResourceTag\runFeature(mk(['recurso' => 'assinaturas']));
check('example-assinaturas', outMatches($r, 'assinaturas', 'subscriptions', 'recurso', true, 'billing', true, true));

$r = \App\Support\Community\ApiResourceTag\runFeature(new \stdClass);
check('example-empty', outMatches($r, '', 'unknown', 'none', false, 'unknown', false, false));

$r = \App\Support\Community\ApiResourceTag\runFeature(mk(['resource' => 'customers', 'recurso' => 'plans', 'api_resource' => 'queries']));
check('priority-resource-first', outMatches($r, 'customers', 'customers', 'resource', true, 'directory', true, true));

$r = \App\Support\Community\ApiResourceTag\runFeature(mk(['recurso' => 'plans', 'api_resource' => 'queries']));
check('priority-recurso-second', outMatches($r, 'plans', 'plans', 'recurso', true, 'directory', true, true));

$r = \App\Support\Community\ApiResourceTag\runFeature(mk(['api_resource' => 'queries']));
check('priority-api_resource-third', outMatches($r, 'queries', 'queries', 'api_resource', true, 'lookup', false, true));

$r = \App\Support\Community\ApiResourceTag\runFeature(mk(['resource' => 123, 'recurso' => 'plans']));
check('skip-non-string-resource', outMatches($r, 'plans', 'plans', 'recurso', true, 'directory', true, true));

$r = \App\Support\Community\ApiResourceTag\runFeature(mk(['resource' => null, 'recurso' => ['x'], 'api_resource' => 'boleto']));
check('skip-non-string-chain', outMatches($r, 'boleto', 'boletos', 'api_resource', true, 'billing', true, true));

$r = \App\Support\Community\ApiResourceTag\runFeature(mk(['resource' => ' Customers ']));
check('no-trim', outMatches($r, ' Customers ', 'unknown', 'resource', true, 'unknown', false, false));

$r = \App\Support\Community\ApiResourceTag\runFeature(mk(['resource' => 'Customers']));
check('case-sensitive', outMatches($r, 'Customers', 'unknown', 'resource', true, 'unknown', false, false));

$cases = [
    ['clientes', 'customers', 'directory', true],
    ['planos', 'plans', 'directory', true],
    ['assinatura', 'subscriptions', 'billing', true],
    ['assinaturas', 'subscriptions', 'billing', true],
    ['subscriptions', 'subscriptions', 'billing', true],
    ['boleto', 'boletos', 'billing', true],
    ['boletos', 'boletos', 'billing', true],
    ['links', 'payment_links', 'billing', true],
    ['links_pagamento', 'payment_links', 'billing', true],
    ['payment_links', 'payment_links', 'billing', true],
    ['consultas', 'queries', 'lookup', false],
    ['consulta', 'queries', 'lookup', false],
    ['queries', 'queries', 'lookup', false],
    ['customers', 'customers', 'directory', true],
];
foreach ($cases as $i => $c) {
    $o = \App\Support\Community\ApiResourceTag\runFeature(mk(['resource' => $c[0]]));
    check('synonym-' . $c[0], outMatches($o, $c[0], $c[1], 'resource', true, $c[2], $c[3], true));
}

$r = \App\Support\Community\ApiResourceTag\runFeature(mk(['resource' => 'foobar']));
check('unknown-raw', outMatches($r, 'foobar', 'unknown', 'resource', true, 'unknown', false, false));

$in = mk(['resource' => 'plans', 'extra' => 'x', 'other' => 1]);
$before = get_object_vars($in);
$r = \App\Support\Community\ApiResourceTag\runFeature($in);
check('extra-ignored', outMatches($r, 'plans', 'plans', 'resource', true, 'directory', true, true));
check('only-seven-keys', count(get_object_vars($r)) === 7);
check('input-not-mutated', eq(get_object_vars($in), $before));

$a = \App\Support\Community\ApiResourceTag\runFeature(mk(['recurso' => 'clientes', 'extra' => 1]));
$b = \App\Support\Community\ApiResourceTag\runFeature(mk(['recurso' => 'clientes', 'extra' => 1]));
check('deterministic', eq(get_object_vars($a), get_object_vars($b)));

$r = \App\Support\Community\ApiResourceTag\runFeature(mk(['resource' => '']));
check('empty-string-raw', outMatches($r, '', 'unknown', 'resource', true, 'unknown', false, false));

echo "passes=" . $passes . " failures=" . $failures . "\n";
exit($failures === 0 ? 0 : 1);
