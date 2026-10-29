<?php
namespace App\Support\Community\ReleaseHygieneGate;

function runFeature($payload)
{
    $has_env = false;
    $has_logs = false;
    $has_storage_cache = false;
    $has_postman_capture = false;
    $has_webhook_capture = false;
    $has_operational_docs = false;
    if ($payload instanceof \stdClass) {
        $vars = get_object_vars($payload);
        foreach ($vars as $k => $v) {
            if ($k === 'has_env') {
                if ($v === true) {
                    $has_env = true;
                }
            }
            if ($k === 'has_logs') {
                if ($v === true) {
                    $has_logs = true;
                }
            }
            if ($k === 'has_storage_cache') {
                if ($v === true) {
                    $has_storage_cache = true;
                }
            }
            if ($k === 'has_postman_capture') {
                if ($v === true) {
                    $has_postman_capture = true;
                }
            }
            if ($k === 'has_webhook_capture') {
                if ($v === true) {
                    $has_webhook_capture = true;
                }
            }
            if ($k === 'has_operational_docs') {
                if ($v === true) {
                    $has_operational_docs = true;
                }
            }
        }
    }
    $present_count = 0;
    if ($has_env === true) {
        $present_count = $present_count + 1;
    }
    if ($has_logs === true) {
        $present_count = $present_count + 1;
    }
    if ($has_storage_cache === true) {
        $present_count = $present_count + 1;
    }
    if ($has_postman_capture === true) {
        $present_count = $present_count + 1;
    }
    if ($has_webhook_capture === true) {
        $present_count = $present_count + 1;
    }
    if ($has_operational_docs === true) {
        $present_count = $present_count + 1;
    }
    $clean = false;
    if ($present_count === 0) {
        $clean = true;
    }
    $gate = 'blocked';
    if ($clean === true) {
        $gate = 'clean';
    }
    $reason = 'has_operational_docs';
    if ($clean === true) {
        $reason = 'ok';
    } else {
        if ($has_env === true) {
            $reason = 'has_env';
        } else {
            if ($has_logs === true) {
                $reason = 'has_logs';
            } else {
                if ($has_storage_cache === true) {
                    $reason = 'has_storage_cache';
                } else {
                    if ($has_postman_capture === true) {
                        $reason = 'has_postman_capture';
                    } else {
                        if ($has_webhook_capture === true) {
                            $reason = 'has_webhook_capture';
                        } else {
                            $reason = 'has_operational_docs';
                        }
                    }
                }
            }
        }
    }
    $result = new \stdClass();
    $result->has_env = $has_env;
    $result->has_logs = $has_logs;
    $result->has_storage_cache = $has_storage_cache;
    $result->has_postman_capture = $has_postman_capture;
    $result->has_webhook_capture = $has_webhook_capture;
    $result->has_operational_docs = $has_operational_docs;
    $result->present_count = $present_count;
    $result->clean = $clean;
    $result->gate = $gate;
    $result->reason = $reason;
    return $result;
}
