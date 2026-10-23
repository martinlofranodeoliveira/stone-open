<?php
namespace App\Support\Community\PaymentLinkStatusTag;

function runFeature($payload) {
  $vars = [];
  $isObj = $payload instanceof \stdClass;
  if ($isObj) {
    $vars = get_object_vars($payload);
  }
  $hasStatusKey = false;
  $hasSituacaoKey = false;
  $hasLinkKey = false;
  foreach ($vars as $k => $v) {
    if ($k === "status") {
      $hasStatusKey = true;
    }
    if ($k === "situacao") {
      $hasSituacaoKey = true;
    }
    if ($k === "link_status") {
      $hasLinkKey = true;
    }
  }
  $statusVal = null;
  $situacaoVal = null;
  $linkVal = null;
  if ($hasStatusKey) {
    $statusVal = $payload->status;
  }
  if ($hasSituacaoKey) {
    $situacaoVal = $payload->situacao;
  }
  if ($hasLinkKey) {
    $linkVal = $payload->link_status;
  }
  $s0 = "";
  $status_source = "none";
  $has_status = false;
  if (is_string($statusVal)) {
    $s0 = $statusVal;
    $status_source = "status";
    $has_status = true;
  } else {
    if (is_string($situacaoVal)) {
      $s0 = $situacaoVal;
      $status_source = "situacao";
      $has_status = true;
    } else {
      if (is_string($linkVal)) {
        $s0 = $linkVal;
        $status_source = "link_status";
        $has_status = true;
      }
    }
  }
  $raw = "";
  if ($has_status) {
    $raw = $s0;
  }
  $normalized = "unknown";
  if ($raw === "active" || $raw === "ativo" || $raw === "ativa") {
    $normalized = "active";
  } else {
    if ($raw === "used" || $raw === "usado" || $raw === "usada") {
      $normalized = "used";
    } else {
      if ($raw === "expired" || $raw === "expirado" || $raw === "expirada") {
        $normalized = "expired";
      } else {
        if ($raw === "canceled" || $raw === "cancelled" || $raw === "cancelado" || $raw === "cancelada") {
          $normalized = "canceled";
        }
      }
    }
  }
  $category = "unknown";
  if ($normalized === "active") {
    $category = "usable";
  } else {
    if ($normalized === "used") {
      $category = "consumed";
    } else {
      if ($normalized === "expired" || $normalized === "canceled") {
        $category = "closed";
      }
    }
  }
  $is_active = false;
  if ($normalized === "active") {
    $is_active = true;
  }
  $is_terminal = false;
  if ($normalized === "used" || $normalized === "expired" || $normalized === "canceled") {
    $is_terminal = true;
  }
  $is_known = false;
  if ($normalized === "active" || $normalized === "used" || $normalized === "expired" || $normalized === "canceled") {
    $is_known = true;
  }
  $out = new \stdClass;
  $out->raw = $raw;
  $out->normalized = $normalized;
  $out->status_source = $status_source;
  $out->has_status = $has_status;
  $out->category = $category;
  $out->is_active = $is_active;
  $out->is_terminal = $is_terminal;
  $out->is_known = $is_known;
  return $out;
}
