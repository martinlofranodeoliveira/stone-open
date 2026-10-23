<?php
require __DIR__ . '/../src/App/Support/Community/forward_outcome_tag.php';

$failures = 0;
$passes = 0;

function t_check($cond, $msg)
{
    global $failures, $passes;
    if ($cond) {
        $passes = $passes + 1;
        echo "PASS: " . $msg . "\n";
    } else {
        $failures = $failures + 1;
        echo "FAIL: " . $msg . "\n";
    }
}

function t_out($payload)
{
    return \App\Support\Community\ForwardOutcomeTag\runFeature($payload);
}

$p1 = new \stdClass;
$p1->outcome = 'delivered';
$r1 = t_out($p1);
t_check($r1->raw === 'delivered', 'outcome delivered raw');
t_check($r1->normalized === 'delivered', 'outcome delivered normalized');
t_check($r1->outcome_source === 'outcome', 'outcome delivered source');
t_check($r1->has_outcome === true, 'outcome delivered has_outcome');
t_check($r1->category === 'success', 'outcome delivered category');
t_check($r1->is_terminal === true, 'outcome delivered terminal');
t_check($r1->is_known === true, 'outcome delivered known');

$p2 = new \stdClass;
$p2->resultado = 'pendente';
$r2 = t_out($p2);
t_check($r2->raw === 'pendente', 'resultado pendente raw');
t_check($r2->normalized === 'pending', 'resultado pendente normalized');
t_check($r2->outcome_source === 'resultado', 'resultado pendente source');
t_check($r2->has_outcome === true, 'resultado pendente has_outcome');
t_check($r2->category === 'open', 'resultado pendente category');
t_check($r2->is_terminal === false, 'resultado pendente terminal');
t_check($r2->is_known === true, 'resultado pendente known');

$p3 = new \stdClass;
$p3->forward_status = 'retry';
$r3 = t_out($p3);
t_check($r3->raw === 'retry', 'forward_status retry raw');
t_check($r3->normalized === 'retrying', 'forward_status retry normalized');
t_check($r3->outcome_source === 'forward_status', 'forward_status retry source');
t_check($r3->has_outcome === true, 'forward_status retry has_outcome');
t_check($r3->category === 'open', 'forward_status retry category');
t_check($r3->is_terminal === false, 'forward_status retry terminal');
t_check($r3->is_known === true, 'forward_status retry known');

$p4 = new \stdClass;
$r4 = t_out($p4);
t_check($r4->raw === '', 'empty raw');
t_check($r4->normalized === 'unknown', 'empty normalized');
t_check($r4->outcome_source === 'none', 'empty source');
t_check($r4->has_outcome === false, 'empty has_outcome');
t_check($r4->category === 'unknown', 'empty category');
t_check($r4->is_terminal === false, 'empty terminal');
t_check($r4->is_known === false, 'empty known');

$rNull = t_out(null);
t_check($rNull->raw === '' && $rNull->normalized === 'unknown' && $rNull->outcome_source === 'none' && $rNull->has_outcome === false, 'null behaves as empty');
$rStr = t_out('delivered');
t_check($rStr->raw === '' && $rStr->normalized === 'unknown' && $rStr->has_outcome === false, 'string input behaves as empty');
$rArr = t_out(array('outcome' => 'delivered'));
t_check($rArr->raw === '' && $rArr->normalized === 'unknown' && $rArr->outcome_source === 'none', 'array input behaves as empty');

$pExtra = new \stdClass;
$pExtra->outcome = 'failed';
$pExtra->extra = 'keep';
$pExtra->resultado = 'pendente';
$rExtra = t_out($pExtra);
t_check($rExtra->raw === 'failed' && $rExtra->normalized === 'failed' && $rExtra->outcome_source === 'outcome', 'priority outcome wins and extra ignored');
t_check($rExtra->category === 'failure' && $rExtra->is_terminal === true && $rExtra->is_known === true, 'failed category terminal');

$pPri2 = new \stdClass;
$pPri2->resultado = 'pendente';
$pPri2->forward_status = 'retry';
$rPri2 = t_out($pPri2);
t_check($rPri2->raw === 'pendente' && $rPri2->outcome_source === 'resultado', 'resultado priority over forward_status');

$pMut = new \stdClass;
$pMut->outcome = 'delivered';
$pMut->extra = 'x';
$before = json_encode($pMut);
$rMut1 = t_out($pMut);
$rMut2 = t_out($pMut);
$after = json_encode($pMut);
t_check($before === $after, 'input never mutated');
t_check(json_encode($rMut1) === json_encode($rMut2), 'identical input yields identical output');

$pCase = new \stdClass;
$pCase->outcome = 'Delivered';
$rCase = t_out($pCase);
t_check($rCase->normalized === 'unknown' && $rCase->is_known === false && $rCase->raw === 'Delivered', 'case-sensitive matching');

$pSpace = new \stdClass;
$pSpace->outcome = ' delivered';
$rSpace = t_out($pSpace);
t_check($rSpace->normalized === 'unknown', 'no trimming');

echo "passes=" . $passes . " failures=" . $failures . "\n";
if ($failures > 0) {
    exit(1);
}
