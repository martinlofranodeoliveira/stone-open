<?php
require __DIR__ . '/../src/App/Support/Community/csv_cell_kind_tag.php';

$failures = 0;
$passes = 0;

function check($name, $cond, &$failures, &$passes) {
    if ($cond) {
        $passes++;
        echo "ok: " . $name . "\n";
    } else {
        $failures++;
        echo "FAIL: " . $name . "\n";
    }
}

function mk($arr) {
    $o = new \stdClass();
    foreach ($arr as $k => $v) {
        $o->$k = $v;
    }
    return $o;
}

$p1 = mk(['value' => '=1+1']);
$r1 = \App\Support\Community\CsvCellKindTag\runFeature($p1);
check('string value source', $r1->value_source === 'value', $failures, $passes);
check('string has_value', $r1->has_value === true, $failures, $passes);
check('string kind', $r1->kind === 'string', $failures, $passes);
check('string is_scalar', $r1->is_scalar === true, $failures, $passes);
check('string needs_json', $r1->needs_json === false, $failures, $passes);
check('string is_empty_string', $r1->is_empty_string === false, $failures, $passes);

$p2 = mk(['valor' => -42]);
$r2 = \App\Support\Community\CsvCellKindTag\runFeature($p2);
check('number valor source', $r2->value_source === 'valor', $failures, $passes);
check('number kind', $r2->kind === 'number', $failures, $passes);
check('number scalar', $r2->is_scalar === true, $failures, $passes);
check('number needs_json false', $r2->needs_json === false, $failures, $passes);
check('number empty false', $r2->is_empty_string === false, $failures, $passes);

$p3 = new \stdClass();
$r3 = \App\Support\Community\CsvCellKindTag\runFeature($p3);
check('missing source none', $r3->value_source === 'none', $failures, $passes);
check('missing has_value false', $r3->has_value === false, $failures, $passes);
check('missing kind', $r3->kind === 'missing', $failures, $passes);
check('missing scalar false', $r3->is_scalar === false, $failures, $passes);
check('missing needs_json true', $r3->needs_json === true, $failures, $passes);
check('missing empty false', $r3->is_empty_string === false, $failures, $passes);

$p4 = mk(['value' => null]);
$r4 = \App\Support\Community\CsvCellKindTag\runFeature($p4);
check('null kind', $r4->kind === 'null' && $r4->has_value === true && $r4->value_source === 'value', $failures, $passes);
check('null flags', $r4->is_scalar === false && $r4->needs_json === true && $r4->is_empty_string === false, $failures, $passes);

$p5 = mk(['valor' => true]);
$r5 = \App\Support\Community\CsvCellKindTag\runFeature($p5);
check('bool true kind', $r5->kind === 'boolean' && $r5->is_scalar === true && $r5->needs_json === false, $failures, $passes);
$p5b = mk(['value' => false]);
$r5b = \App\Support\Community\CsvCellKindTag\runFeature($p5b);
check('bool false kind', $r5b->kind === 'boolean' && $r5b->is_scalar === true, $failures, $passes);

$p6 = mk(['value' => 3.5]);
$r6 = \App\Support\Community\CsvCellKindTag\runFeature($p6);
check('float number', $r6->kind === 'number' && $r6->is_scalar === true && $r6->needs_json === false, $failures, $passes);

$p7 = mk(['value' => '']);
$r7 = \App\Support\Community\CsvCellKindTag\runFeature($p7);
check('empty string flag', $r7->kind === 'string' && $r7->is_empty_string === true && $r7->is_scalar === true && $r7->needs_json === false, $failures, $passes);

$p8 = mk(['value' => [1, 2]]);
$r8 = \App\Support\Community\CsvCellKindTag\runFeature($p8);
check('array kind', $r8->kind === 'array' && $r8->is_scalar === false && $r8->needs_json === true && $r8->is_empty_string === false, $failures, $passes);

$inner = new \stdClass();
$inner->a = 1;
$p9 = mk(['value' => $inner]);
$r9 = \App\Support\Community\CsvCellKindTag\runFeature($p9);
check('object kind', $r9->kind === 'object' && $r9->needs_json === true && $r9->is_scalar === false, $failures, $passes);

$both = mk(['value' => 'hi', 'valor' => 123]);
$rb = \App\Support\Community\CsvCellKindTag\runFeature($both);
check('alias priority value wins', $rb->value_source === 'value' && $rb->kind === 'string', $failures, $passes);

$extra = mk(['valor' => 'x', 'other' => 999]);
$re = \App\Support\Community\CsvCellKindTag\runFeature($extra);
check('extra ignored', $re->value_source === 'valor' && $re->kind === 'string', $failures, $passes);

$rn1 = \App\Support\Community\CsvCellKindTag\runFeature(null);
check('non-object null missing', $rn1->kind === 'missing' && $rn1->value_source === 'none' && $rn1->has_value === false, $failures, $passes);
$rn2 = \App\Support\Community\CsvCellKindTag\runFeature([1, 2]);
check('non-object array missing', $rn2->kind === 'missing' && $rn2->value_source === 'none', $failures, $passes);
$rn3 = \App\Support\Community\CsvCellKindTag\runFeature('x');
check('non-object string missing', $rn3->kind === 'missing', $failures, $passes);

$mut = mk(['value' => 'keep']);
$before = json_encode($mut);
$rm = \App\Support\Community\CsvCellKindTag\runFeature($mut);
$after = json_encode($mut);
check('input not mutated', $before === $after && $mut->value === 'keep', $failures, $passes);

$detP = mk(['valor' => 7]);
$d1 = \App\Support\Community\CsvCellKindTag\runFeature($detP);
$d2 = \App\Support\Community\CsvCellKindTag\runFeature($detP);
check('deterministic', json_encode($d1) === json_encode($d2), $failures, $passes);

$uni = mk(['value' => 'caf\u00e9 \u2603']);
$ru = \App\Support\Community\CsvCellKindTag\runFeature($uni);
check('unicode string', $ru->kind === 'string' && $ru->is_scalar === true, $failures, $passes);

echo "passes: " . $passes . " failures: " . $failures . "\n";
if ($failures !== 0) {
    exit(1);
}
exit(0);
