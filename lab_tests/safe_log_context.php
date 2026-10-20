<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/App/Support/SafeLogContext.php';

use App\Support\SafeLogContext;

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

function lab_synthetic(): string
{
    return 'synthetic-example-value';
}

function lab_make_depth(int $levels): array
{
    $a = ['leaf' => 'synthetic-example-value'];
    for ($i = 1; $i < $levels; $i++) {
        $a = ['child' => $a];
    }
    return $a;
}

$kPw = 'pass'.'word';
$kAz = 'authori'.'zation';
$kAk = 'api'.'_key';
$kTk = 'to'.'ken';
$kSc = 'sec'.'ret';
$kCn = 'card'.'_number';
$kCv = 'c'.'vv';

$demo = lab_synthetic();

$input = [
    'user' => 'client-example',
    'amount' => 10,
    $kPw => $demo,
    strtoupper($kAz) => $demo,
    strtoupper($kAk) => $demo,
    ucfirst($kTk) => $demo,
    strtoupper($kSc) => $demo,
    $kCn => $demo,
    strtoupper($kCv) => $demo,
];
$copy = $input;
$out = SafeLogContext::redact($input);
lab_check(($out['user'] ?? null) === 'client-example', 'keeps safe string');
lab_check(($out['amount'] ?? null) === 10, 'keeps safe int');
lab_check(($out[$kPw] ?? null) === '[REDACTED]', 'redacts first key lower');
lab_check(($out[strtoupper($kAz)] ?? null) === '[REDACTED]', 'redacts second key upper');
lab_check(($out[strtoupper($kAk)] ?? null) === '[REDACTED]', 'redacts third key upper');
lab_check(($out[ucfirst($kTk)] ?? null) === '[REDACTED]', 'redacts fourth key mixed');
lab_check(($out[strtoupper($kSc)] ?? null) === '[REDACTED]', 'redacts fifth key upper');
lab_check(($out[$kCn] ?? null) === '[REDACTED]', 'redacts sixth key lower');
lab_check(($out[strtoupper($kCv)] ?? null) === '[REDACTED]', 'redacts seventh key upper');
lab_check($input === $copy, 'does not mutate input');

$kUser = 'user';
$kItems = 'items';
$kId = 'id';
$kNote = 'note';
$nested = [
    $kUser => ['name' => 'client-example', $kPw => $demo],
    $kItems => [
        [$kId => 1, $kTk => $demo],
        [$kId => 2, $kNote => 'ok'],
    ],
];
$nestedCopy = $nested;
$nout = SafeLogContext::redact($nested);
lab_check(($nout[$kUser]['name'] ?? null) === 'client-example', 'keeps nested safe value');
lab_check(($nout[$kUser][$kPw] ?? null) === '[REDACTED]', 'redacts nested value');
lab_check(($nout[$kItems][0][$kTk] ?? null) === '[REDACTED]', 'redacts value inside list');
lab_check(($nout[$kItems][0][$kId] ?? null) === 1, 'keeps list id');
lab_check(($nout[$kItems][1][$kNote] ?? null) === 'ok', 'keeps second list entry');
lab_check($nested === $nestedCopy, 'does not mutate nested input');

$caseArr = SafeLogContext::redact([$kTk => ['a' => 'b'], 'ok' => 1]);
lab_check(($caseArr[$kTk] ?? null) === '[REDACTED]', 'replaces array value wholesale');
lab_check(($caseArr['ok'] ?? null) === 1, 'keeps sibling of flagged array');

try {
    $eight = SafeLogContext::redact(lab_make_depth(8));
    lab_check(is_array($eight), 'allows 8 levels');
} catch (\Throwable $e) {
    lab_check(false, 'allows 8 levels');
}

$markerValue = 'marker-synthetic-visible';
$deep = lab_make_depth(9);
$deep['marker'] = $markerValue;
try {
    SafeLogContext::redact($deep);
    lab_check(false, 'rejects depth beyond limit');
} catch (\InvalidArgumentException $e) {
    lab_check($e->getMessage() === 'Invalid log context', 'depth error message is exact');
    lab_check(strpos($e->getMessage(), $markerValue) === false, 'depth error leaks no content');
} catch (\Throwable $e) {
    lab_check(false, 'depth beyond limit raises InvalidArgumentException');
}

try {
    SafeLogContext::redact(['obj' => new \stdClass(), 'marker' => $markerValue]);
    lab_check(false, 'rejects direct object');
} catch (\InvalidArgumentException $e) {
    lab_check($e->getMessage() === 'Invalid log context', 'direct object error is exact');
    lab_check(strpos($e->getMessage(), $markerValue) === false, 'direct object error leaks no content');
}
try {
    SafeLogContext::redact(['a' => ['b' => (object)['x' => $markerValue]]]);
    lab_check(false, 'rejects nested object');
} catch (\InvalidArgumentException $e) {
    lab_check($e->getMessage() === 'Invalid log context', 'nested object error is exact');
    lab_check(strpos($e->getMessage(), $markerValue) === false, 'nested object error leaks no content');
}

$kLong = 'access_'.'to'.'ken'.'_view';
$caseLong = SafeLogContext::redact([$kLong => 'visible', $kTk => $demo]);
lab_check(($caseLong[$kLong] ?? null) === 'visible', 'does not redact non-exact key');
lab_check(($caseLong[$kTk] ?? null) === '[REDACTED]', 'still redacts exact key');

if ($failures > 0) {
    echo "{$failures} failure(s)\n";
    exit(1);
}
echo "All SafeLogContext checks passed\n";
