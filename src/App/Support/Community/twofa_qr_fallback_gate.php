<?php
namespace App\Support\Community\TwofaQrFallbackGate;

function runFeature($payload)
{
    $library_available = false;
    $qr_generated = false;
    $has_manual_key = false;
    $has_manual_uri = false;
    $external_image_used = false;
    if ($payload instanceof \stdClass) {
        $vars = get_object_vars($payload);
        foreach ($vars as $k => $v) {
            if ($k === 'library_available') {
                if ($v === true) {
                    $library_available = true;
                }
            }
            if ($k === 'qr_generated') {
                if ($v === true) {
                    $qr_generated = true;
                }
            }
            if ($k === 'has_manual_key') {
                if ($v === true) {
                    $has_manual_key = true;
                }
            }
            if ($k === 'has_manual_uri') {
                if ($v === true) {
                    $has_manual_uri = true;
                }
            }
            if ($k === 'external_image_used') {
                if ($v === true) {
                    $external_image_used = true;
                }
            }
        }
    }
    $local_qr_ok = false;
    if ($library_available === true) {
        if ($qr_generated === true) {
            $local_qr_ok = true;
        }
    }
    $has_manual_fallback = false;
    if ($has_manual_key === true) {
        if ($has_manual_uri === true) {
            $has_manual_fallback = true;
        }
    }
    $privacy_ok = false;
    if ($external_image_used === false) {
        $privacy_ok = true;
    }
    $display = 'missing_setup';
    if ($local_qr_ok === true) {
        $display = 'local_qr';
    } else {
        if ($has_manual_fallback === true) {
            $display = 'manual_only';
        }
    }
    $reason = 'missing_material';
    if ($privacy_ok === false) {
        $reason = 'external_image_blocked';
    } else {
        if ($local_qr_ok === true) {
            $reason = 'ok_local';
        } else {
            if ($has_manual_fallback === true) {
                $reason = 'ok_manual';
            }
        }
    }
    $ready = false;
    if ($reason === 'ok_local') {
        $ready = true;
    } else {
        if ($reason === 'ok_manual') {
            $ready = true;
        }
    }
    $out = new \stdClass;
    $out->library_available = $library_available;
    $out->qr_generated = $qr_generated;
    $out->has_manual_key = $has_manual_key;
    $out->has_manual_uri = $has_manual_uri;
    $out->external_image_used = $external_image_used;
    $out->local_qr_ok = $local_qr_ok;
    $out->has_manual_fallback = $has_manual_fallback;
    $out->privacy_ok = $privacy_ok;
    $out->display = $display;
    $out->ready = $ready;
    $out->reason = $reason;
    return $out;
}
