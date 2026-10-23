<?php
namespace App\Support\Community\CustomerStatusTag;

function runFeature($payload) {
  $isObj = $payload instanceof \stdClass;
  if ($isObj) {
    foreach (get_object_vars($payload) as $k => $v) {
    }
  }
  $c0 = "";
  $c0_source = "none";
  $c0_has = false;
  if ($isObj) {
    if (isset($payload->status)) {
      if (is_string($payload->status)) {
        $c0 = $payload->status;
        $c0_source = "status";
        $c0_has = true;
      }
    }
    if ($c0_has === false) {
      if (isset($payload->situacao)) {
        if (is_string($payload->situacao)) {
          $c0 = $payload->situacao;
          $c0_source = "situacao";
          $c0_has = true;
        }
      }
    }
    if ($c0_has === false) {
      if (isset($payload->customer_status)) {
        if (is_string($payload->customer_status)) {
          $c0 = $payload->customer_status;
          $c0_source = "customer_status";
          $c0_has = true;
        }
      }
    }
  }
  $raw = "";
  if ($c0_has === true) {
    $raw = $c0;
  }
  $has_status = false;
  if ($c0_has === true) {
    $has_status = true;
  }
  $status_source = $c0_source;
  $normalized = "unknown";
  if ($raw === "active") {
    $normalized = "active";
  } else {
    if ($raw === "ativo") {
      $normalized = "active";
    } else {
      if ($raw === "inactive") {
        $normalized = "inactive";
      } else {
        if ($raw === "inativo") {
          $normalized = "inactive";
        } else {
          if ($raw === "blocked") {
            $normalized = "blocked";
          } else {
            if ($raw === "bloqueado") {
              $normalized = "blocked";
            } else {
              if ($raw === "archived") {
                $normalized = "archived";
              } else {
                if ($raw === "arquivado") {
                  $normalized = "archived";
                } else {
                  $normalized = "unknown";
                }
              }
            }
          }
        }
      }
    }
  }
  $category = "unknown";
  if ($normalized === "active") {
    $category = "usable";
  } else {
    if ($normalized === "inactive") {
      $category = "dormant";
    } else {
      if ($normalized === "blocked") {
        $category = "restricted";
      } else {
        if ($normalized === "archived") {
          $category = "closed";
        } else {
          $category = "unknown";
        }
      }
    }
  }
  $is_active = false;
  if ($normalized === "active") {
    $is_active = true;
  }
  $is_known = false;
  if ($normalized === "unknown") {
    $is_known = false;
  } else {
    $is_known = true;
  }
  $out = new \stdClass;
  $out->raw = $raw;
  $out->normalized = $normalized;
  $out->status_source = $status_source;
  $out->has_status = $has_status;
  $out->category = $category;
  $out->is_active = $is_active;
  $out->is_known = $is_known;
  return $out;
}
