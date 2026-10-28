<?php
namespace App\Support\Community\LegacyWebhookGate;

function runFeature($payload) {
    $source = new \stdClass();
    if (is_object($payload)) {
        $source = $payload;
    }
    $rawFlag = null;
    $hasFlag = false;
    foreach (get_object_vars($source) as $k => $v) {
        if ($k === 'enable_legacy') {
            $rawFlag = $v;
            $hasFlag = true;
        }
    }
    $flag_on = false;
    if ($hasFlag === true) {
        if ($rawFlag === true) {
            $flag_on = true;
        } else {
            if ($rawFlag === 'true') {
                $flag_on = true;
            }
        }
    }
    $rawEndpoints = null;
    $hasEndpointsKey = false;
    foreach (get_object_vars($source) as $k => $v) {
        if ($k === 'endpoints') {
            $rawEndpoints = $v;
            $hasEndpointsKey = true;
        }
    }
    $endpoints_list = [];
    if ($hasEndpointsKey === true) {
        if (is_array($rawEndpoints)) {
            $endpoints_list = $rawEndpoints;
        }
    }
    $endpoint_count = 0;
    foreach ($endpoints_list as $item) {
        if (is_string($item)) {
            if ($item !== '') {
                $endpoint_count = $endpoint_count + 1;
            }
        }
    }
    $has_endpoints = false;
    if ($endpoint_count > 0) {
        $has_endpoints = true;
    }
    $gate = 'disabled';
    if ($flag_on === true) {
        if ($has_endpoints === true) {
            $gate = 'enabled';
        }
    }
    $ready = false;
    if ($gate === 'enabled') {
        $ready = true;
    }
    $reason = 'disabled_flag';
    if ($gate === 'enabled') {
        $reason = 'ok';
    } else {
        if ($flag_on === true) {
            if ($has_endpoints === false) {
                $reason = 'missing_endpoints';
            }
        }
    }
    $out = new \stdClass();
    $out->flag_on = $flag_on;
    $out->has_endpoints = $has_endpoints;
    $out->endpoint_count = $endpoint_count;
    $out->gate = $gate;
    $out->ready = $ready;
    $out->reason = $reason;
    return $out;
}
