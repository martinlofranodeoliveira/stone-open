<?php
namespace App\Support\Community\RuntimeExtensionGate;

function runFeature($payload) {
    $has_pdo_mysql = false;
    $has_curl = false;
    $has_openssl = false;
    $has_session = false;
    if ($payload instanceof \stdClass) {
        $vars = get_object_vars($payload);
        foreach ($vars as $k => $v) {
            if ($k === 'has_pdo_mysql') {
                if ($v === true) {
                    $has_pdo_mysql = true;
                }
            }
            if ($k === 'has_curl') {
                if ($v === true) {
                    $has_curl = true;
                }
            }
            if ($k === 'has_openssl') {
                if ($v === true) {
                    $has_openssl = true;
                }
            }
            if ($k === 'has_session') {
                if ($v === true) {
                    $has_session = true;
                }
            }
        }
    }
    $storage_ok = false;
    if ($has_pdo_mysql === true) {
        $storage_ok = true;
    }
    $transport_ok = false;
    if ($has_curl === true) {
        if ($has_openssl === true) {
            $transport_ok = true;
        }
    }
    $session_ok = false;
    if ($has_session === true) {
        $session_ok = true;
    }
    $missing_count = 0;
    if ($has_pdo_mysql === false) {
        $missing_count = $missing_count + 1;
    }
    if ($has_curl === false) {
        $missing_count = $missing_count + 1;
    }
    if ($has_openssl === false) {
        $missing_count = $missing_count + 1;
    }
    if ($has_session === false) {
        $missing_count = $missing_count + 1;
    }
    $ready = false;
    if ($storage_ok === true) {
        if ($transport_ok === true) {
            if ($session_ok === true) {
                $ready = true;
            }
        }
    }
    $gate = 'blocked';
    if ($ready === true) {
        $gate = 'ready';
    }
    $reason = 'missing_session';
    if ($ready === true) {
        $reason = 'ok';
    } else {
        if ($storage_ok === false) {
            $reason = 'missing_storage';
        } else {
            if ($transport_ok === false) {
                $reason = 'missing_transport';
            }
        }
    }
    $out = new \stdClass;
    $out->has_pdo_mysql = $has_pdo_mysql;
    $out->has_curl = $has_curl;
    $out->has_openssl = $has_openssl;
    $out->has_session = $has_session;
    $out->storage_ok = $storage_ok;
    $out->transport_ok = $transport_ok;
    $out->session_ok = $session_ok;
    $out->missing_count = $missing_count;
    $out->ready = $ready;
    $out->gate = $gate;
    $out->reason = $reason;
    return $out;
}
