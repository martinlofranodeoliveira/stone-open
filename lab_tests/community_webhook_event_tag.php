<?php
require __DIR__ . '/../src/App/Support/Community/webhook_event_tag.php';

$failures = 0;
$total = 0;

function check($name, $cond, &$failures, &$total)
{
    $total = $total + 1;
    if ($cond === true) {
        echo "PASS: " . $name . "\n";
    } else {
        echo "FAIL: " . $name . "\n";
        $failures = $failures + 1;
    }
}

function allFields($r, $raw, $normalized, $source, $has, $cat, $ispay, $isknown)
{
    if ($r->raw !== $raw) {
        return false;
    }
    if ($r->normalized !== $normalized) {
        return false;
    }
    if ($r->event_source !== $source) {
        return false;
    }
    if ($r->has_event !== $has) {
        return false;
    }
    if ($r->category !== $cat) {
        return false;
    }
    if ($r->is_payment !== $ispay) {
        return false;
    }
    if ($r->is_known !== $isknown) {
        return false;
    }
    return true;
}

$p = new \stdClass();
$p->event = 'payment_paid';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('example payment_paid', allFields($r, 'payment_paid', 'payment_paid', 'event', true, 'payment', true, true), $failures, $total);

$p = new \stdClass();
$p->evento = 'assinatura_cancelada';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('example assinatura_cancelada', allFields($r, 'assinatura_cancelada', 'subscription_canceled', 'evento', true, 'subscription', false, true), $failures, $total);

$p = new \stdClass();
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('example empty object', allFields($r, '', 'unknown', 'none', false, 'unknown', false, false), $failures, $total);

$p = new \stdClass();
$p->event = 'payment_paid';
$p->evento = 'assinatura_cancelada';
$p->webhook_event = 'payment_created';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('priority event over evento over webhook_event', allFields($r, 'payment_paid', 'payment_paid', 'event', true, 'payment', true, true), $failures, $total);

$p = new \stdClass();
$p->evento = 'payment_created';
$p->webhook_event = 'payment_paid';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('priority evento over webhook_event', allFields($r, 'payment_created', 'payment_created', 'evento', true, 'payment', true, true), $failures, $total);

$p = new \stdClass();
$p->webhook_event = 'subscription_created';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('webhook_event alone', allFields($r, 'subscription_created', 'subscription_created', 'webhook_event', true, 'subscription', false, true), $failures, $total);

$p = new \stdClass();
$p->event = 123;
$p->evento = 'payment_paid';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('non-string event falls through to evento', allFields($r, 'payment_paid', 'payment_paid', 'evento', true, 'payment', true, true), $failures, $total);

$r = \App\Support\Community\WebhookEventTag\runFeature(null);
check('null payload is empty', allFields($r, '', 'unknown', 'none', false, 'unknown', false, false), $failures, $total);

$r = \App\Support\Community\WebhookEventTag\runFeature(array('event' => 'payment_paid'));
check('array payload is empty', allFields($r, '', 'unknown', 'none', false, 'unknown', false, false), $failures, $total);

$r = \App\Support\Community\WebhookEventTag\runFeature('payment_paid');
check('string payload is empty', allFields($r, '', 'unknown', 'none', false, 'unknown', false, false), $failures, $total);

$r = \App\Support\Community\WebhookEventTag\runFeature(42);
check('int payload is empty', allFields($r, '', 'unknown', 'none', false, 'unknown', false, false), $failures, $total);

$p = new \stdClass();
$p->event = 'payment_created';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('payment_created maps', allFields($r, 'payment_created', 'payment_created', 'event', true, 'payment', true, true), $failures, $total);

$p = new \stdClass();
$p->event = 'pagamento_criado';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('pagamento_criado maps', allFields($r, 'pagamento_criado', 'payment_created', 'event', true, 'payment', true, true), $failures, $total);

$p = new \stdClass();
$p->evento = 'pagamento_pago';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('pagamento_pago maps', allFields($r, 'pagamento_pago', 'payment_paid', 'evento', true, 'payment', true, true), $failures, $total);

$p = new \stdClass();
$p->event = 'payment_failed';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('payment_failed maps', allFields($r, 'payment_failed', 'payment_failed', 'event', true, 'payment', true, true), $failures, $total);

$p = new \stdClass();
$p->event = 'pagamento_falhou';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('pagamento_falhou maps', allFields($r, 'pagamento_falhou', 'payment_failed', 'event', true, 'payment', true, true), $failures, $total);

$p = new \stdClass();
$p->event = 'subscription_created';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('subscription_created maps', allFields($r, 'subscription_created', 'subscription_created', 'event', true, 'subscription', false, true), $failures, $total);

$p = new \stdClass();
$p->event = 'assinatura_criada';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('assinatura_criada maps', allFields($r, 'assinatura_criada', 'subscription_created', 'event', true, 'subscription', false, true), $failures, $total);

$p = new \stdClass();
$p->event = 'subscription_canceled';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('subscription_canceled maps', allFields($r, 'subscription_canceled', 'subscription_canceled', 'event', true, 'subscription', false, true), $failures, $total);

$p = new \stdClass();
$p->event = 'subscription_cancelled';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('subscription_cancelled maps', allFields($r, 'subscription_cancelled', 'subscription_canceled', 'event', true, 'subscription', false, true), $failures, $total);

$p = new \stdClass();
$p->webhook_event = 'assinatura_cancelada';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('assinatura_cancelada via webhook_event', allFields($r, 'assinatura_cancelada', 'subscription_canceled', 'webhook_event', true, 'subscription', false, true), $failures, $total);

$p = new \stdClass();
$p->event = 123;
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('int event is missing', allFields($r, '', 'unknown', 'none', false, 'unknown', false, false), $failures, $total);

$p = new \stdClass();
$p->event = null;
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('null event is missing', allFields($r, '', 'unknown', 'none', false, 'unknown', false, false), $failures, $total);

$p = new \stdClass();
$p->event = array('x');
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('array event is missing', allFields($r, '', 'unknown', 'none', false, 'unknown', false, false), $failures, $total);

$p = new \stdClass();
$inner = new \stdClass();
$inner->x = 1;
$p->event = $inner;
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('object event is missing', allFields($r, '', 'unknown', 'none', false, 'unknown', false, false), $failures, $total);

$p = new \stdClass();
$p->event = 'mystery_event_xyz';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('unknown echoes raw', allFields($r, 'mystery_event_xyz', 'unknown', 'event', true, 'unknown', false, false), $failures, $total);

$p = new \stdClass();
$p->event = 'Payment_Paid';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('case sensitive no fold', allFields($r, 'Payment_Paid', 'unknown', 'event', true, 'unknown', false, false), $failures, $total);

$p = new \stdClass();
$p->event = ' payment_paid';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('no trimming leading space', allFields($r, ' payment_paid', 'unknown', 'event', true, 'unknown', false, false), $failures, $total);

$p = new \stdClass();
$p->event = 'payment_paid ';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('no trimming trailing space', allFields($r, 'payment_paid ', 'unknown', 'event', true, 'unknown', false, false), $failures, $total);

$p = new \stdClass();
$p->event = 'payment_paid';
$p->extra = 123;
$p->foo = 'bar';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('extra fields ignored', allFields($r, 'payment_paid', 'payment_paid', 'event', true, 'payment', true, true), $failures, $total);

$p = new \stdClass();
$p->event = '';
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
check('empty string is present but unknown', allFields($r, '', 'unknown', 'event', true, 'unknown', false, false), $failures, $total);

$p = new \stdClass();
$p->event = 'payment_paid';
$p->extra = 'keep';
$beforeEvent = $p->event;
$beforeExtra = $p->extra;
$r = \App\Support\Community\WebhookEventTag\runFeature($p);
$mutOk = ($p->event === $beforeEvent);
if ($mutOk === true) {
    $mutOk = ($p->extra === $beforeExtra);
}
if ($mutOk === true) {
    $mutOk = (isset($p->raw) === false);
}
check('input never mutated', $mutOk === true, $failures, $total);

$p1 = new \stdClass();
$p1->evento = 'pagamento_pago';
$r1 = \App\Support\Community\WebhookEventTag\runFeature($p1);
$p2 = new \stdClass();
$p2->evento = 'pagamento_pago';
$r2 = \App\Support\Community\WebhookEventTag\runFeature($p2);
$detOk = allFields($r1, 'pagamento_pago', 'payment_paid', 'evento', true, 'payment', true, true);
if ($detOk === true) {
    $detOk = allFields($r2, 'pagamento_pago', 'payment_paid', 'evento', true, 'payment', true, true);
}
if ($detOk === true) {
    $detOk = ($r1->raw === $r2->raw);
}
if ($detOk === true) {
    $detOk = ($r1->normalized === $r2->normalized);
}
check('deterministic same input same output', $detOk === true, $failures, $total);

echo "TOTAL: " . $total . " FAILURES: " . $failures . "\n";
if ($failures !== 0) {
    exit(1);
}
