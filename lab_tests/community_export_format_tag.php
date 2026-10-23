<?php
require __DIR__ . '/../src/App/Support/Community/export_format_tag.php';

function checkTag($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, 'FAIL: ' . $msg . "\n");
        exit(1);
    }
}

function tagPayload($arr) {
    $o = new \stdClass();
    foreach ($arr as $k => $v) {
        $o->$k = $v;
    }
    return $o;
}

// 1: empty object
$r = \App\Support\Community\ExportFormatTag\runFeature(new \stdClass());
checkTag($r->raw === '', 'empty raw');
checkTag($r->normalized === 'unknown', 'empty normalized');
checkTag($r->format_source === 'none', 'empty source');
checkTag($r->has_format === false, 'empty has_format');
checkTag($r->category === 'unknown', 'empty category');
checkTag($r->is_tabular === false, 'empty is_tabular');
checkTag($r->is_known === false, 'empty is_known');

// 2: format csv
$r = \App\Support\Community\ExportFormatTag\runFeature(tagPayload(['format' => 'csv']));
checkTag($r->raw === 'csv', 'csv raw');
checkTag($r->normalized === 'csv', 'csv normalized');
checkTag($r->format_source === 'format', 'csv source');
checkTag($r->has_format === true, 'csv has');
checkTag($r->category === 'tabular', 'csv category');
checkTag($r->is_tabular === true, 'csv tabular');
checkTag($r->is_known === true, 'csv known');

// 3: formato planilha
$r = \App\Support\Community\ExportFormatTag\runFeature(tagPayload(['formato' => 'planilha']));
checkTag($r->raw === 'planilha', 'planilha raw');
checkTag($r->normalized === 'xls', 'planilha normalized');
checkTag($r->format_source === 'formato', 'planilha source');
checkTag($r->category === 'tabular', 'planilha category');
checkTag($r->is_tabular === true, 'planilha tabular');
checkTag($r->is_known === true, 'planilha known');

// 4: export_format json
$r = \App\Support\Community\ExportFormatTag\runFeature(tagPayload(['export_format' => 'json']));
checkTag($r->raw === 'json', 'json raw');
checkTag($r->normalized === 'json', 'json normalized');
checkTag($r->format_source === 'export_format', 'json source');
checkTag($r->has_format === true, 'json has');
checkTag($r->category === 'structured', 'json category');
checkTag($r->is_tabular === false, 'json tabular');
checkTag($r->is_known === true, 'json known');

// 5a: priority format over formato over export_format
$r = \App\Support\Community\ExportFormatTag\runFeature(tagPayload(['format' => 'pdf', 'formato' => 'csv', 'export_format' => 'json']));
checkTag($r->raw === 'pdf' && $r->format_source === 'format' && $r->normalized === 'pdf' && $r->category === 'document', 'priority format first');
$r = \App\Support\Community\ExportFormatTag\runFeature(tagPayload(['formato' => 'csv', 'export_format' => 'json']));
checkTag($r->raw === 'csv' && $r->format_source === 'formato' && $r->normalized === 'csv', 'priority formato second');

// 5b: non-string skipped
$r = \App\Support\Community\ExportFormatTag\runFeature(tagPayload(['format' => 123, 'formato' => 'csv']));
checkTag($r->raw === 'csv' && $r->format_source === 'formato' && $r->normalized === 'csv', 'skip non-string format');
$r = \App\Support\Community\ExportFormatTag\runFeature(tagPayload(['format' => null, 'formato' => 5, 'export_format' => 'json']));
checkTag($r->raw === 'json' && $r->format_source === 'export_format', 'skip null/non-string');
$r = \App\Support\Community\ExportFormatTag\runFeature(tagPayload(['format' => 1, 'formato' => 2, 'export_format' => 3]));
checkTag($r->raw === '' && $r->normalized === 'unknown' && $r->format_source === 'none' && $r->has_format === false, 'all non-string empty');

// 5c: non-object behaves as empty without mutation
foreach ([null, 123, 'csv', [], true] as $i => $bad) {
    $r = \App\Support\Community\ExportFormatTag\runFeature($bad);
    checkTag($r->raw === '' && $r->normalized === 'unknown' && $r->format_source === 'none' && $r->has_format === false && $r->category === 'unknown' && $r->is_tabular === false && $r->is_known === false, 'non-object empty ' . $i);
}
$p = tagPayload(['format' => 'csv', 'extra' => 'keep']);
$before = json_encode($p);
$r = \App\Support\Community\ExportFormatTag\runFeature($p);
checkTag(json_encode($p) === $before, 'no mutation');
checkTag($r->normalized === 'csv', 'extra ignored');

// determinism
$a = \App\Support\Community\ExportFormatTag\runFeature(tagPayload(['format' => 'csv']));
$b = \App\Support\Community\ExportFormatTag\runFeature(tagPayload(['format' => 'csv']));
checkTag(json_encode($a) === json_encode($b), 'deterministic');

// 6: unknown / uppercase exact matching
$r = \App\Support\Community\ExportFormatTag\runFeature(tagPayload(['format' => 'CSV']));
checkTag($r->raw === 'CSV' && $r->normalized === 'unknown' && $r->category === 'unknown' && $r->is_tabular === false && $r->is_known === false, 'uppercase CSV unknown');
$r = \App\Support\Community\ExportFormatTag\runFeature(tagPayload(['format' => 'txt']));
checkTag($r->raw === 'txt' && $r->normalized === 'unknown' && $r->category === 'unknown' && $r->is_tabular === false && $r->is_known === false, 'txt unknown');
$r = \App\Support\Community\ExportFormatTag\runFeature(tagPayload(['format' => ' csv']));
checkTag($r->normalized === 'unknown' && $r->raw === ' csv', 'no trimming');

// aliases: xlsx/excel/xls + pdf document
$r = \App\Support\Community\ExportFormatTag\runFeature(tagPayload(['format' => 'xlsx']));
checkTag($r->normalized === 'xls' && $r->category === 'tabular' && $r->is_tabular === true && $r->is_known === true, 'xlsx alias');
$r = \App\Support\Community\ExportFormatTag\runFeature(tagPayload(['format' => 'excel']));
checkTag($r->normalized === 'xls' && $r->category === 'tabular', 'excel alias');
$r = \App\Support\Community\ExportFormatTag\runFeature(tagPayload(['format' => 'pdf']));
checkTag($r->normalized === 'pdf' && $r->category === 'document' && $r->is_tabular === false && $r->is_known === true, 'pdf document');

fwrite(STDOUT, "OK community_export_format_tag\n");
