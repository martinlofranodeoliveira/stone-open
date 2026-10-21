<?php
namespace App\Support\Community\SubscriptionStatusTag;
function runFeature($payload) {
  $s0 = "";
  $status_source = "none";
  $has_status = false;
  $raw = "";
  $normalized = "unknown";
  $category = "unknown";
  $is_active = false;
  $is_ended = false;
  $is_known = false;
  $isObj = is_object($payload);
  $vars = [];
  if ($isObj === true) {
    $vars = get_object_vars($payload);
  }
  $hasStatusProp = false;
  $hasSituacaoProp = false;
  $hasSubProp = false;
  foreach ($vars as $k => $v) {
    if ($k === "status") {
      $hasStatusProp = true;
    }
    if ($k === "situacao") {
      $hasSituacaoProp = true;
    }
    if ($k === "subscription_status") {
      $hasSubProp = true;
    }
  }
  $v0 = 0;
  $v1 = 0;
  $v2 = 0;
  if ($hasStatusProp === true) {
    $v0 = $payload->status;
  }
  if ($hasSituacaoProp === true) {
    $v1 = $payload->situacao;
  }
  if ($hasSubProp === true) {
    $v2 = $payload->subscription_status;
  }
  $c0 = is_string($v0);
  $c1 = is_string($v1);
  $c2 = is_string($v2);
  if ($isObj === true) {
    if ($c0 === true) {
      $s0 = $v0;
      $status_source = "status";
      $has_status = true;
    } else {
      if ($c1 === true) {
        $s0 = $v1;
        $status_source = "situacao";
        $has_status = true;
      } else {
        if ($c2 === true) {
          $s0 = $v2;
          $status_source = "subscription_status";
          $has_status = true;
        }
      }
    }
  }
  if ($has_status === true) {
    $raw = $s0;
  } else {
    $raw = "";
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
  if ($raw === "trialing") {
    $normalized = "trialing";
  }
  if ($raw === "trial") {
    $normalized = "trialing";
  }
  if ($raw === "teste") {
    $normalized = "trialing";
  }
  if ($raw === "em_teste") {
    $normalized = "trialing";
  }
  if ($raw === "past_due") {
    $normalized = "past_due";
  }
  if ($raw === "inadimplente") {
    $normalized = "past_due";
  }
  if ($raw === "atrasado") {
    $normalized = "past_due";
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
  if ($raw === "canceled") {
    $normalized = "canceled";
  }
  if ($raw === "cancelled") {
    $normalized = "canceled";
  }
  if ($raw === "cancelado") {
    $normalized = "canceled";
  }
  if ($raw === "cancelada") {
    $normalized = "canceled";
  }
  if ($raw === "expired") {
    $normalized = "expired";
  }
  if ($raw === "expirado") {
    $normalized = "expired";
  }
  if ($raw === "expirada") {
    $normalized = "expired";
  }
  if ($normalized === "active") {
    $category = "current";
  }
  if ($normalized === "trialing") {
    $category = "current";
  }
  if ($normalized === "past_due") {
    $category = "attention";
  }
  if ($normalized === "suspended") {
    $category = "attention";
  }
  if ($normalized === "canceled") {
    $category = "ended";
  }
  if ($normalized === "expired") {
    $category = "ended";
  }
  if ($normalized === "active") {
    $is_active = true;
  }
  if ($normalized === "canceled") {
    $is_ended = true;
  }
  if ($normalized === "expired") {
    $is_ended = true;
  }
  if ($normalized === "active") {
    $is_known = true;
  }
  if ($normalized === "trialing") {
    $is_known = true;
  }
  if ($normalized === "past_due") {
    $is_known = true;
  }
  if ($normalized === "suspended") {
    $is_known = true;
  }
  if ($normalized === "canceled") {
    $is_known = true;
  }
  if ($normalized === "expired") {
    $is_known = true;
  }
  $out = new \stdClass;
  $out->raw = $raw;
  $out->normalized = $normalized;
  $out->status_source = $status_source;
  $out->has_status = $has_status;
  $out->category = $category;
  $out->is_active = $is_active;
  $out->is_ended = $is_ended;
  $out->is_known = $is_known;
  return $out;
}
