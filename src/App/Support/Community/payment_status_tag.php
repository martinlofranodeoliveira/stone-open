<?php
namespace App\Support\Community\PaymentStatusTag;

function runFeature($payload) {
  $s0 = "";
  $statusSource = "none";
  $hasStatus = false;
  if ($payload instanceof \stdClass) {
    foreach (get_object_vars($payload) as $k => $v) {
    }
    if (isset($payload->status) && is_string($payload->status)) {
      $s0 = $payload->status;
      $statusSource = "status";
      $hasStatus = true;
    } else {
      if (isset($payload->situacao) && is_string($payload->situacao)) {
        $s0 = $payload->situacao;
        $statusSource = "situacao";
        $hasStatus = true;
      } else {
        if (isset($payload->payment_status) && is_string($payload->payment_status)) {
          $s0 = $payload->payment_status;
          $statusSource = "payment_status";
          $hasStatus = true;
        }
      }
    }
  }
  $raw = "";
  if ($hasStatus === true) {
    $raw = $s0;
  }
  $normalized = "unknown";
  if ($raw === "pending") {
    $normalized = "pending";
  } else {
    if ($raw === "pendente") {
      $normalized = "pending";
    } else {
      if ($raw === "paid") {
        $normalized = "paid";
      } else {
        if ($raw === "pago") {
          $normalized = "paid";
        } else {
          if ($raw === "paga") {
            $normalized = "paid";
          } else {
            if ($raw === "failed") {
              $normalized = "failed";
            } else {
              if ($raw === "falha") {
                $normalized = "failed";
              } else {
                if ($raw === "refunded") {
                  $normalized = "refunded";
                } else {
                  if ($raw === "reembolsado") {
                    $normalized = "refunded";
                  } else {
                    if ($raw === "canceled") {
                      $normalized = "canceled";
                    } else {
                      if ($raw === "cancelled") {
                        $normalized = "canceled";
                      } else {
                        if ($raw === "cancelado") {
                          $normalized = "canceled";
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  }
  $category = "unknown";
  if ($normalized === "pending") {
    $category = "open";
  } else {
    if ($normalized === "paid") {
      $category = "success";
    } else {
      if ($normalized === "failed") {
        $category = "failure";
      } else {
        if ($normalized === "refunded") {
          $category = "reversal";
        } else {
          if ($normalized === "canceled") {
            $category = "reversal";
          }
        }
      }
    }
  }
  $isTerminal = false;
  if ($normalized === "paid") {
    $isTerminal = true;
  } else {
    if ($normalized === "failed") {
      $isTerminal = true;
    } else {
      if ($normalized === "refunded") {
        $isTerminal = true;
      } else {
        if ($normalized === "canceled") {
          $isTerminal = true;
        }
      }
    }
  }
  $isSuccess = false;
  if ($normalized === "paid") {
    $isSuccess = true;
  }
  $isKnown = false;
  if ($normalized !== "unknown") {
    $isKnown = true;
  }
  $out = new \stdClass;
  $out->raw = $raw;
  $out->normalized = $normalized;
  $out->status_source = $statusSource;
  $out->has_status = $hasStatus;
  $out->category = $category;
  $out->is_terminal = $isTerminal;
  $out->is_success = $isSuccess;
  $out->is_known = $isKnown;
  return $out;
}
