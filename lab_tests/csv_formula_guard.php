<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/App/Support/CsvFormulaGuard.php';
require_once dirname(__DIR__) . '/src/App/Infrastructure/Http/Response.php';

use App\Support\CsvFormulaGuard;
use App\Infrastructure\Http\Response;

$failures = 0;

function lab_check(bool $cond, string $msg): void
{
    global $failures;
    if ($cond) {
        echo "PASS: {$msg}\n";
    } else {
        $failures++;
        echo "FAIL: {$msg}\n";
    }
}

lab_check(CsvFormulaGuard::protect('=1+1') === "'=1+1", 'prefix equals');
lab_check(CsvFormulaGuard::protect('+1+1') === "'+1+1", 'prefix plus');
lab_check(CsvFormulaGuard::protect('-42') === "'-42", 'prefix minus string');
lab_check(CsvFormulaGuard::protect('@user') === "'@user", 'prefix at');
lab_check(CsvFormulaGuard::protect("\tfoo") === "'\tfoo", 'prefix leading tab');
lab_check(CsvFormulaGuard::protect("\rfoo") === "'\rfoo", 'prefix leading cr');
lab_check(CsvFormulaGuard::protect("\nfoo") === "'\nfoo", 'prefix leading lf');
lab_check(CsvFormulaGuard::protect('  =cmd') === "'  =cmd", 'prefix after spaces keeps original bytes');
lab_check(CsvFormulaGuard::protect(" \t=cmd") === "' \t=cmd", 'prefix tab after spaces');
lab_check(CsvFormulaGuard::protect("\xEF\xBB\xBF=cmd") === "'\xEF\xBB\xBF=cmd", 'prefix bom equals');
lab_check(CsvFormulaGuard::protect("  \xEF\xBB\xBF  +cmd") === "'  \xEF\xBB\xBF  +cmd", 'prefix spaced bom plus');
lab_check(CsvFormulaGuard::protect("\xEF\xBB\xBF\xEF\xBB\xBF=cmd") === "\xEF\xBB\xBF\xEF\xBB\xBF=cmd", 'does not strip double bom');
lab_check(CsvFormulaGuard::protect("\xEF\xBC\x9Dcmd") === "'\xEF\xBC\x9Dcmd", 'prefix fullwidth equals');
lab_check(CsvFormulaGuard::protect("\xEF\xBC\x8Bcmd") === "'\xEF\xBC\x8Bcmd", 'prefix fullwidth plus');
lab_check(CsvFormulaGuard::protect("\xEF\xBC\x8Dcmd") === "'\xEF\xBC\x8Dcmd", 'prefix fullwidth minus');
lab_check(CsvFormulaGuard::protect("\xEF\xBC\xA0cmd") === "'\xEF\xBC\xA0cmd", 'prefix fullwidth at');
lab_check(CsvFormulaGuard::protect('') === '', 'empty stays');
lab_check(CsvFormulaGuard::protect('   ') === '   ', 'spaces stay');
lab_check(CsvFormulaGuard::protect('olá') === 'olá', 'benign utf8 stays');
lab_check(CsvFormulaGuard::protect("'=cmd") === "'=cmd", 'existing apostrophe stays');
lab_check(CsvFormulaGuard::protect(CsvFormulaGuard::protect('=cmd')) === CsvFormulaGuard::protect('=cmd'), 'idempotent equals');
lab_check(CsvFormulaGuard::protect(CsvFormulaGuard::protect('  -x')) === "'  -x", 'idempotent spaced minus');

$rm = new ReflectionMethod(CsvFormulaGuard::class, 'protect');
lab_check($rm->isPublic() && $rm->isStatic(), 'guard is public static');
lab_check($rm->getNumberOfParameters() === 1, 'guard has one param');
$p0 = $rm->getParameters()[0];
lab_check($p0->getType() !== null && (string)$p0->getType() === 'string', 'guard param is string');
lab_check($rm->getReturnType() !== null && (string)$rm->getReturnType() === 'string', 'guard returns string');

$csvMethod = new ReflectionMethod(Response::class, 'csv');
$params = $csvMethod->getParameters();
lab_check(count($params) === 4, 'csv has four params');
lab_check($params[0]->getName() === 'rows', 'csv first param rows');
lab_check($params[1]->getName() === 'columns', 'csv second param columns');
lab_check($params[2]->getName() === 'filename', 'csv third param filename');
lab_check($params[3]->getName() === 'spreadsheetSafe', 'csv fourth param spreadsheetSafe');
lab_check($params[3]->getType() !== null && (string)$params[3]->getType() === 'bool', 'csv fourth param is bool');
lab_check($params[3]->isDefaultValueAvailable() && $params[3]->getDefaultValue() === true, 'csv default safe true');
lab_check($params[2]->isDefaultValueAvailable() && $params[2]->getDefaultValue() === 'export.csv', 'csv default filename');

if ($failures > 0) {
    echo "{$failures} failure(s)\n";
    exit(1);
}
echo "All CsvFormulaGuard checks passed\n";
