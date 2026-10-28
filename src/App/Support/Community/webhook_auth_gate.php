<?php
namespace App\Support\Community\WebhookAuthGate;

function runFeature($payload)
{
    $has_basic = false;
    $has_hmac = false;
    $basic_valid = false;
    $signature_valid = false;
    if ($payload instanceof \stdClass) {
        $vars = get_object_vars($payload);
        foreach ($vars as $k => $v) {
            if ($k === 'has_basic') {
                if ($v === true) {
                    $has_basic = true;
                }
            }
            if ($k === 'has_hmac') {
                if ($v === true) {
                    $has_hmac = true;
                }
            }
            if ($k === 'basic_valid') {
                if ($v === true) {
                    $basic_valid = true;
                }
            }
            if ($k === 'signature_valid') {
                if ($v === true) {
                    $signature_valid = true;
                }
            }
        }
    }
    $has_auth_config = false;
    if ($has_basic === true) {
        $has_auth_config = true;
    }
    if ($has_hmac === true) {
        $has_auth_config = true;
    }
    $requires_signature = false;
    if ($has_hmac === true) {
        $requires_signature = true;
    }
    $basic_ok = true;
    if ($has_basic === true) {
        if ($basic_valid === true) {
            $basic_ok = true;
        } else {
            $basic_ok = false;
        }
    }
    $sig_ok = true;
    if ($has_hmac === true) {
        if ($signature_valid === true) {
            $sig_ok = true;
        } else {
            $sig_ok = false;
        }
    }
    $allowed = false;
    if ($has_auth_config === true) {
        if ($basic_ok === true) {
            if ($sig_ok === true) {
                $allowed = true;
            }
        }
    }
    $http_status = 503;
    if ($allowed === true) {
        $http_status = 200;
    }
    $decision = 'blocked_503';
    if ($allowed === true) {
        $decision = 'proceed';
    }
    $reason = 'ok';
    if ($has_auth_config === false) {
        $reason = 'missing_config';
    } else {
        if ($requires_signature === true) {
            if ($signature_valid === false) {
                $reason = 'missing_signature';
            } else {
                if ($has_basic === true) {
                    if ($basic_valid === false) {
                        $reason = 'invalid_basic';
                    }
                }
            }
        } else {
            if ($has_basic === true) {
                if ($basic_valid === false) {
                    $reason = 'invalid_basic';
                }
            }
        }
    }
    $block_persist = true;
    if ($allowed === true) {
        $block_persist = false;
    }
    $out = new \stdClass;
    $out->has_basic = $has_basic;
    $out->has_hmac = $has_hmac;
    $out->basic_valid = $basic_valid;
    $out->signature_valid = $signature_valid;
    $out->has_auth_config = $has_auth_config;
    $out->requires_signature = $requires_signature;
    $out->allowed = $allowed;
    $out->http_status = $http_status;
    $out->decision = $decision;
    $out->reason = $reason;
    $out->block_persist = $block_persist;
    return $out;
}
