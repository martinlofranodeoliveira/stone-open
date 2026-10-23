<?php
namespace App\Support\Community\SiteStatusTag;

function runFeature($payload) {
    $raw = "";
    $normalized = "unknown";
    $site_source = "none";
    $has_site_status = false;
    $category = "unknown";
    $is_active = false;
    $is_known = false;
    $s0_value = "";
    $s0_source = "none";
    $s0_present = false;
    if ($payload instanceof \stdClass) {
        if (isset($payload->status)) {
            $v_status = $payload->status;
            if (is_string($v_status)) {
                $s0_value = $v_status;
                $s0_source = "status";
                $s0_present = true;
            }
        }
        if ($s0_present === false) {
            if (isset($payload->situacao)) {
                $v_situacao = $payload->situacao;
                if (is_string($v_situacao)) {
                    $s0_value = $v_situacao;
                    $s0_source = "situacao";
                    $s0_present = true;
                }
            }
        }
        if ($s0_present === false) {
            if (isset($payload->site_status)) {
                $v_site_status = $payload->site_status;
                if (is_string($v_site_status)) {
                    $s0_value = $v_site_status;
                    $s0_source = "site_status";
                    $s0_present = true;
                }
            }
        }
    }
    if ($s0_present === true) {
        $raw = $s0_value;
        $site_source = $s0_source;
        $has_site_status = true;
    }
    if ($raw === "active") {
        $normalized = "active";
    }
    if ($raw === "ativo") {
        $normalized = "active";
    }
    if ($raw === "ativa") {
        $normalized = "active";
    }
    if ($raw === "inactive") {
        $normalized = "inactive";
    }
    if ($raw === "inativo") {
        $normalized = "inactive";
    }
    if ($raw === "inativa") {
        $normalized = "inactive";
    }
    if ($raw === "suspended") {
        $normalized = "suspended";
    }
    if ($raw === "suspenso") {
        $normalized = "suspended";
    }
    if ($raw === "suspensa") {
        $normalized = "suspended";
    }
    if ($raw === "archived") {
        $normalized = "archived";
    }
    if ($raw === "arquivado") {
        $normalized = "archived";
    }
    if ($raw === "arquivada") {
        $normalized = "archived";
    }
    if ($normalized === "active") {
        $category = "live";
        $is_active = true;
        $is_known = true;
    }
    if ($normalized === "inactive") {
        $category = "dormant";
        $is_known = true;
    }
    if ($normalized === "archived") {
        $category = "dormant";
        $is_known = true;
    }
    if ($normalized === "suspended") {
        $category = "blocked";
        $is_known = true;
    }
    $out = new \stdClass;
    $out->raw = $raw;
    $out->normalized = $normalized;
    $out->site_source = $site_source;
    $out->has_site_status = $has_site_status;
    $out->category = $category;
    $out->is_active = $is_active;
    $out->is_known = $is_known;
    return $out;
}
