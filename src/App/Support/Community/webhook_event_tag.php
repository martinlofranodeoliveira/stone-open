<?php
namespace App\Support\Community\WebhookEventTag;

function runFeature($payload)
{
    $e0 = '';
    $event_source = 'none';
    $has_event = false;
    if ($payload instanceof \stdClass) {
        foreach (get_object_vars($payload) as $k => $v) {
            $e0 = $e0;
        }
        if (isset($payload->event)) {
            $t = $payload->event;
            if (is_string($t)) {
                $e0 = $t;
                $event_source = 'event';
                $has_event = true;
            }
        }
        if ($has_event === false) {
            if (isset($payload->evento)) {
                $t = $payload->evento;
                if (is_string($t)) {
                    $e0 = $t;
                    $event_source = 'evento';
                    $has_event = true;
                }
            }
        }
        if ($has_event === false) {
            if (isset($payload->webhook_event)) {
                $t = $payload->webhook_event;
                if (is_string($t)) {
                    $e0 = $t;
                    $event_source = 'webhook_event';
                    $has_event = true;
                }
            }
        }
    }
    $raw = '';
    if ($has_event === true) {
        $raw = $e0;
    }
    $normalized = 'unknown';
    if ($raw === 'payment_created') {
        $normalized = 'payment_created';
    }
    if ($raw === 'pagamento_criado') {
        $normalized = 'payment_created';
    }
    if ($raw === 'payment_paid') {
        $normalized = 'payment_paid';
    }
    if ($raw === 'pagamento_pago') {
        $normalized = 'payment_paid';
    }
    if ($raw === 'payment_failed') {
        $normalized = 'payment_failed';
    }
    if ($raw === 'pagamento_falhou') {
        $normalized = 'payment_failed';
    }
    if ($raw === 'subscription_created') {
        $normalized = 'subscription_created';
    }
    if ($raw === 'assinatura_criada') {
        $normalized = 'subscription_created';
    }
    if ($raw === 'subscription_canceled') {
        $normalized = 'subscription_canceled';
    }
    if ($raw === 'subscription_cancelled') {
        $normalized = 'subscription_canceled';
    }
    if ($raw === 'assinatura_cancelada') {
        $normalized = 'subscription_canceled';
    }
    $category = 'unknown';
    if ($normalized === 'payment_created') {
        $category = 'payment';
    }
    if ($normalized === 'payment_paid') {
        $category = 'payment';
    }
    if ($normalized === 'payment_failed') {
        $category = 'payment';
    }
    if ($normalized === 'subscription_created') {
        $category = 'subscription';
    }
    if ($normalized === 'subscription_canceled') {
        $category = 'subscription';
    }
    $is_payment = false;
    if ($category === 'payment') {
        $is_payment = true;
    }
    $is_known = false;
    if ($normalized !== 'unknown') {
        $is_known = true;
    }
    $out = new \stdClass();
    $out->raw = $raw;
    $out->normalized = $normalized;
    $out->event_source = $event_source;
    $out->has_event = $has_event;
    $out->category = $category;
    $out->is_payment = $is_payment;
    $out->is_known = $is_known;
    return $out;
}
