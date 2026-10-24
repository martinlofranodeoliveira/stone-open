<?php
require __DIR__ . '/../src/App/Support/Community/pix_key_type_tag.php';

$failures = 0;
function check($cond, $msg) {
    global $failures;
    if (!$cond) {
        $failures = $failures + 1;
        echo "FAIL: " . $msg . "\n";
    }
}
function mk($arr) {
    $o = new \stdClass();
    foreach ($arr as $k => $v) {
        $o->$k = $v;
    }
    return $o;
}
function expect($payload, $expRaw, $expNorm, $expSource, $expHas, $expCat, $expDoc, $expKnown, $msg) {
    $out = \App\Support\Community\PixKeyTypeTag\runFeature($payload);
    check($out instanceof \stdClass, $msg . ' output object');
    check($out->raw === $expRaw, $msg . ' raw');
    check($out->normalized === $expNorm, $msg . ' normalized');
    check($out->key_source === $expSource, $msg . ' key_source');
    check($out->has_key_type === $expHas, $msg . ' has_key_type');
    check($out->category === $expCat, $msg . ' category');
    check($out->is_document === $expDoc, $msg . ' is_document');
    check($out->is_known === $expKnown, $msg . ' is_known');
}

expect(mk(['key_type' => 'email']), 'email', 'email', 'key_type', true, 'contact', false, true, 'email');
expect(mk(['tipo_chave' => 'telefone']), 'telefone', 'phone', 'tipo_chave', true, 'contact', false, true, 'telefone');
expect(mk([]), '', 'unknown', 'none', false, 'unknown', false, false, 'empty');
expect(null, '', 'unknown', 'none', false, 'unknown', false, false, 'null');
expect('email', '', 'unknown', 'none', false, 'unknown', false, false, 'string');
expect(42, '', 'unknown', 'none', false, 'unknown', false, false, 'int');
expect([], '', 'unknown', 'none', false, 'unknown', false, false, 'array');
expect(mk(['key_type' => 'cpf']), 'cpf', 'cpf', 'key_type', true, 'document', true, true, 'cpf');
expect(mk(['key_type' => 'cnpj']), 'cnpj', 'cnpj', 'key_type', true, 'document', true, true, 'cnpj');
expect(mk(['key_type' => 'e-mail']), 'e-mail', 'email', 'key_type', true, 'contact', false, true, 'e-mail');
expect(mk(['key_type' => 'phone']), 'phone', 'phone', 'key_type', true, 'contact', false, true, 'phone');
expect(mk(['key_type' => 'celular']), 'celular', 'phone', 'key_type', true, 'contact', false, true, 'celular');
expect(mk(['key_type' => 'random']), 'random', 'random', 'key_type', true, 'generated', false, true, 'random');
expect(mk(['key_type' => 'aleatoria']), 'aleatoria', 'random', 'key_type', true, 'generated', false, true, 'aleatoria');
expect(mk(['key_type' => 'aleatorio']), 'aleatorio', 'random', 'key_type', true, 'generated', false, true, 'aleatorio');
expect(mk(['pix_key_type' => 'cpf']), 'cpf', 'cpf', 'pix_key_type', true, 'document', true, true, 'pix_key_type source');
expect(mk(['tipo_chave' => 'cpf']), 'cpf', 'cpf', 'tipo_chave', true, 'document', true, true, 'tipo_chave source');
$p = mk(['key_type' => 'cnpj', 'tipo_chave' => 'cpf', 'pix_key_type' => 'email']);
expect($p, 'cnpj', 'cnpj', 'key_type', true, 'document', true, true, 'precedence key_type');
$p2 = mk(['tipo_chave' => 'cpf', 'pix_key_type' => 'email']);
expect($p2, 'cpf', 'cpf', 'tipo_chave', true, 'document', true, true, 'precedence tipo_chave');
$p3 = mk(['key_type' => 123, 'tipo_chave' => 'email', 'pix_key_type' => 'cpf']);
expect($p3, 'email', 'email', 'tipo_chave', true, 'contact', false, true, 'skip non-string key_type');
$p4 = mk(['key_type' => null, 'tipo_chave' => 5, 'pix_key_type' => 'phone']);
expect($p4, 'phone', 'phone', 'pix_key_type', true, 'contact', false, true, 'skip non-strings');
$p5 = mk(['key_type' => null, 'tipo_chave' => null, 'pix_key_type' => null]);
expect($p5, '', 'unknown', 'none', false, 'unknown', false, false, 'all non-string');
expect(mk(['key_type' => 'CPF']), 'CPF', 'unknown', 'key_type', true, 'unknown', false, false, 'case sensitive');
expect(mk(['key_type' => ' cpf']), ' cpf', 'unknown', 'key_type', true, 'unknown', false, false, 'no trimming');
expect(mk(['key_type' => 'Email']), 'Email', 'unknown', 'key_type', true, 'unknown', false, false, 'case email');
expect(mk(['key_type' => '']), '', 'unknown', 'key_type', true, 'unknown', false, false, 'empty raw keeps source');
expect(mk(['key_type' => 'xyz']), 'xyz', 'unknown', 'key_type', true, 'unknown', false, false, 'unknown raw');
$extra = mk(['key_type' => 'email', 'other' => 'zzz', 'n' => 1]);
expect($extra, 'email', 'email', 'key_type', true, 'contact', false, true, 'extra ignored');
check(!isset($extra->normalized), 'extra output not in input');
check($extra->other === 'zzz', 'input not mutated extra');
$mut = mk(['key_type' => 'cpf']);
$before = get_object_vars($mut);
$a1 = \App\Support\Community\PixKeyTypeTag\runFeature($mut);
$a2 = \App\Support\Community\PixKeyTypeTag\runFeature($mut);
check(get_object_vars($mut) == $before, 'input never mutated');
check($a1 == $a2, 'deterministic');
check($a1->raw === $a2->raw && $a1->normalized === $a2->normalized, 'deterministic fields');
if ($failures === 0) {
    echo "OK community_pix_key_type_tag\n";
} else {
    echo $failures . " failures\n";
    exit(1);
}
