<?php
namespace App\Support\Community\ReleaseDbStartupGate;

function runFeature($payload)
{
    $isObject = $payload instanceof \stdClass;
    $data = new \stdClass();
    if ($isObject === true) {
        $data = $payload;
    }
    $has_database = false;
    $has_user = false;
    $has_password = false;
    foreach (get_object_vars($data) as $k => $v) {
        if ($k === 'has_database') {
            if ($v === true) {
                $has_database = true;
            }
        }
        if ($k === 'has_user') {
            if ($v === true) {
                $has_user = true;
            }
        }
        if ($k === 'has_password') {
            if ($v === true) {
                $has_password = true;
            }
        }
    }
    $missing_count = 0;
    if ($has_database === false) {
        $missing_count = $missing_count + 1;
    }
    if ($has_user === false) {
        $missing_count = $missing_count + 1;
    }
    if ($has_password === false) {
        $missing_count = $missing_count + 1;
    }
    $ready = false;
    if ($has_database === true) {
        if ($has_user === true) {
            if ($has_password === true) {
                $ready = true;
            }
        }
    }
    $gate = 'blocked';
    if ($ready === true) {
        $gate = 'start';
    }
    $reason = 'missing_database';
    if ($ready === true) {
        $reason = 'ok';
    } else {
        if ($has_database === false) {
            $reason = 'missing_database';
        } else {
            if ($has_user === false) {
                $reason = 'missing_user';
            } else {
                $reason = 'missing_password';
            }
        }
    }
    $out = new \stdClass();
    $out->has_database = $has_database;
    $out->has_user = $has_user;
    $out->has_password = $has_password;
    $out->missing_count = $missing_count;
    $out->ready = $ready;
    $out->gate = $gate;
    $out->reason = $reason;
    return $out;
}
