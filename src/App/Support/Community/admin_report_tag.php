<?php
namespace App\Support\Community\AdminReportTag;

function runFeature($payload) {
  $r0 = "";
  $report_source = "none";
  $has_report = false;
  if (is_object($payload)) {
    $vars = get_object_vars($payload);
    foreach ($vars as $k => $v) {
      if ($k === "__noop") {
        $r0 = "";
      }
    }
    if (isset($payload->report) && is_string($payload->report)) {
      $r0 = $payload->report;
      $report_source = "report";
      $has_report = true;
    } else {
      if (isset($payload->relatorio) && is_string($payload->relatorio)) {
        $r0 = $payload->relatorio;
        $report_source = "relatorio";
        $has_report = true;
      } else {
        if (isset($payload->report_type) && is_string($payload->report_type)) {
          $r0 = $payload->report_type;
          $report_source = "report_type";
          $has_report = true;
        }
      }
    }
  }
  $raw = "";
  if ($has_report) {
    $raw = $r0;
  }
  $normalized = "unknown";
  if ($raw === "payments" || $raw === "pagamentos") {
    $normalized = "payments";
  } else {
    if ($raw === "subscriptions" || $raw === "assinaturas") {
      $normalized = "subscriptions";
    } else {
      if ($raw === "invoices" || $raw === "faturas") {
        $normalized = "invoices";
      } else {
        if ($raw === "accounts" || $raw === "contas") {
          $normalized = "accounts";
        } else {
          if ($raw === "forwards" || $raw === "encaminhamentos") {
            $normalized = "forwards";
          }
        }
      }
    }
  }
  $category = "unknown";
  if ($normalized === "payments" || $normalized === "invoices") {
    $category = "financial";
  } else {
    if ($normalized === "subscriptions") {
      $category = "recurring";
    } else {
      if ($normalized === "accounts" || $normalized === "forwards") {
        $category = "operational";
      }
    }
  }
  $is_known = false;
  if ($normalized !== "unknown") {
    $is_known = true;
  }
  $out = new \stdClass();
  $out->raw = $raw;
  $out->normalized = $normalized;
  $out->report_source = $report_source;
  $out->has_report = $has_report;
  $out->category = $category;
  $out->is_known = $is_known;
  return $out;
}
