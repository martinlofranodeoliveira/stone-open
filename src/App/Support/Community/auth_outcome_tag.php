<?php
namespace App\Support\Community\AuthOutcomeTag;

function runFeature($payload) {
  $a0 = "";
  $auth_source = "none";
  $has_outcome = false;
  if ($payload instanceof \stdClass) {
    $bag = get_object_vars($payload);
    foreach ($bag as $k => $v) {
      if ($k === "outcome") {
        if (is_string($v)) {
          $a0 = $v;
        }
      }
    }
    if (isset($payload->outcome) && is_string($payload->outcome)) {
      $a0 = $payload->outcome;
      $auth_source = "outcome";
      $has_outcome = true;
    } else {
      if (isset($payload->resultado) && is_string($payload->resultado)) {
        $a0 = $payload->resultado;
        $auth_source = "resultado";
        $has_outcome = true;
      } else {
        if (isset($payload->auth_result) && is_string($payload->auth_result)) {
          $a0 = $payload->auth_result;
          $auth_source = "auth_result";
          $has_outcome = true;
        }
      }
    }
  }
  if ($has_outcome) {
    $raw = $a0;
  } else {
    $raw = "";
  }
  if ($raw === "success" || $raw === "sucesso" || $raw === "ok") {
    $normalized = "success";
  } else {
    if ($raw === "failure" || $raw === "failed" || $raw === "falha" || $raw === "erro") {
      $normalized = "failure";
    } else {
      if ($raw === "locked" || $raw === "bloqueado" || $raw === "bloqueada") {
        $normalized = "locked";
      } else {
        if ($raw === "expired" || $raw === "expirado" || $raw === "expirada") {
          $normalized = "expired";
        } else {
          if ($raw === "pending" || $raw === "pendente") {
            $normalized = "pending";
          } else {
            $normalized = "unknown";
          }
        }
      }
    }
  }
  if ($normalized === "success") {
    $category = "ok";
  } else {
    if ($normalized === "locked") {
      $category = "blocked";
    } else {
      if ($normalized === "pending") {
        $category = "waiting";
      } else {
        if ($normalized === "failure" || $normalized === "expired") {
          $category = "attention";
        } else {
          $category = "unknown";
        }
      }
    }
  }
  if ($normalized === "success") {
    $is_success = true;
  } else {
    $is_success = false;
  }
  if ($normalized === "success" || $normalized === "failure" || $normalized === "locked" || $normalized === "expired") {
    $is_terminal = true;
  } else {
    $is_terminal = false;
  }
  if ($normalized === "unknown") {
    $is_known = false;
  } else {
    $is_known = true;
  }
  $out = new \stdClass;
  $out->raw = $raw;
  $out->normalized = $normalized;
  $out->auth_source = $auth_source;
  $out->has_outcome = $has_outcome;
  $out->category = $category;
  $out->is_success = $is_success;
  $out->is_terminal = $is_terminal;
  $out->is_known = $is_known;
  return $out;
}
