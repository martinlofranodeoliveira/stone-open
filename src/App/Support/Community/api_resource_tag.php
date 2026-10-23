<?php
namespace App\Support\Community\ApiResourceTag;

function runFeature($payload) {
    $r0 = "";
    $resource_source = "none";
    $has_resource = false;
    $raw = "";
    $normalized = "unknown";
    $category = "unknown";
    $is_collection = false;
    $is_known = false;
    $vars = [];
    if (is_object($payload)) {
        $vars = get_object_vars($payload);
    }
    foreach ($vars as $k => $v) {
        if ($k === "_noop") {
            $raw = $raw;
        }
    }
    if (is_object($payload)) {
        if (isset($payload->resource) && is_string($payload->resource)) {
            $r0 = $payload->resource;
            $resource_source = "resource";
            $has_resource = true;
        } else {
            if (isset($payload->recurso) && is_string($payload->recurso)) {
                $r0 = $payload->recurso;
                $resource_source = "recurso";
                $has_resource = true;
            } else {
                if (isset($payload->api_resource) && is_string($payload->api_resource)) {
                    $r0 = $payload->api_resource;
                    $resource_source = "api_resource";
                    $has_resource = true;
                }
            }
        }
    }
    if ($has_resource) {
        $raw = $r0;
    } else {
        $raw = "";
    }
    if ($raw === "customers" || $raw === "clientes") {
        $normalized = "customers";
    } else {
        if ($raw === "plans" || $raw === "planos") {
            $normalized = "plans";
        } else {
            if ($raw === "subscriptions" || $raw === "assinaturas" || $raw === "assinatura") {
                $normalized = "subscriptions";
            } else {
                if ($raw === "boletos" || $raw === "boleto") {
                    $normalized = "boletos";
                } else {
                    if ($raw === "payment_links" || $raw === "links" || $raw === "links_pagamento") {
                        $normalized = "payment_links";
                    } else {
                        if ($raw === "queries" || $raw === "consultas" || $raw === "consulta") {
                            $normalized = "queries";
                        } else {
                            $normalized = "unknown";
                        }
                    }
                }
            }
        }
    }
    if ($normalized === "customers" || $normalized === "plans") {
        $category = "directory";
    } else {
        if ($normalized === "subscriptions" || $normalized === "boletos" || $normalized === "payment_links") {
            $category = "billing";
        } else {
            if ($normalized === "queries") {
                $category = "lookup";
            } else {
                $category = "unknown";
            }
        }
    }
    if ($normalized === "customers" || $normalized === "plans" || $normalized === "subscriptions" || $normalized === "boletos" || $normalized === "payment_links") {
        $is_collection = true;
    } else {
        $is_collection = false;
    }
    if ($normalized === "unknown") {
        $is_known = false;
    } else {
        $is_known = true;
    }
    $out = new \stdClass;
    $out->raw = $raw;
    $out->normalized = $normalized;
    $out->resource_source = $resource_source;
    $out->has_resource = $has_resource;
    $out->category = $category;
    $out->is_collection = $is_collection;
    $out->is_known = $is_known;
    return $out;
}
