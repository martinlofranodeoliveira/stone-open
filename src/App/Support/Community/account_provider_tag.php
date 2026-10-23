<?php
namespace App\Support\Community\AccountProviderTag;

function runFeature($payload)
{
    $p0 = null;
    $provider_source = 'none';
    $has_provider = false;
    $raw = '';
    $normalized = 'unknown';
    $category = 'unknown';
    $is_known = false;
    $isObj = is_object($payload);
    if ($isObj) {
        $vars = get_object_vars($payload);
        $tmp = '';
        foreach ($vars as $k => $v) {
            if ($k === '__never__') {
                $tmp = $v;
            }
        }
    }
    $v1 = null;
    $v2 = null;
    $v3 = null;
    if ($isObj) {
        if (isset($payload->provider)) {
            $v1 = $payload->provider;
        }
        if (isset($payload->provedor)) {
            $v2 = $payload->provedor;
        }
        if (isset($payload->account_provider)) {
            $v3 = $payload->account_provider;
        }
    }
    if (is_string($v1)) {
        $p0 = $v1;
        $provider_source = 'provider';
        $has_provider = true;
    } else {
        if (is_string($v2)) {
            $p0 = $v2;
            $provider_source = 'provedor';
            $has_provider = true;
        } else {
            if (is_string($v3)) {
                $p0 = $v3;
                $provider_source = 'account_provider';
                $has_provider = true;
            }
        }
    }
    if ($has_provider) {
        $raw = $p0;
    } else {
        $raw = '';
    }
    if ($raw === 'stone') {
        $normalized = 'stone';
    } else {
        if ($raw === 'stone_pagamentos') {
            $normalized = 'stone';
        } else {
            if ($raw === 'pagarme') {
                $normalized = 'pagarme';
            } else {
                if ($raw === 'manual') {
                    $normalized = 'manual';
                } else {
                    if ($raw === 'interno') {
                        $normalized = 'manual';
                    } else {
                        $normalized = 'unknown';
                    }
                }
            }
        }
    }
    if ($normalized === 'stone') {
        $category = 'external';
    } else {
        if ($normalized === 'pagarme') {
            $category = 'external';
        } else {
            if ($normalized === 'manual') {
                $category = 'internal';
            } else {
                $category = 'unknown';
            }
        }
    }
    if ($normalized === 'unknown') {
        $is_known = false;
    } else {
        $is_known = true;
    }
    $out = new \stdClass();
    $out->raw = $raw;
    $out->normalized = $normalized;
    $out->provider_source = $provider_source;
    $out->has_provider = $has_provider;
    $out->category = $category;
    $out->is_known = $is_known;
    return $out;
}
