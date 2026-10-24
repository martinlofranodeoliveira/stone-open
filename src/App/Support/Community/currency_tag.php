<?php
namespace App\Support\Community\CurrencyTag;
function runFeature($payload) {
  $c0 = "";
  $currency_source = "none";
  $has_currency = false;
  if (is_object($payload)) {
    if (isset($payload->currency) && is_string($payload->currency)) {
      $c0 = $payload->currency;
      $currency_source = "currency";
      $has_currency = true;
    } else {
      if (isset($payload->moeda) && is_string($payload->moeda)) {
        $c0 = $payload->moeda;
        $currency_source = "moeda";
        $has_currency = true;
      } else {
        if (isset($payload->currency_code) && is_string($payload->currency_code)) {
          $c0 = $payload->currency_code;
          $currency_source = "currency_code";
          $has_currency = true;
        }
      }
    }
  }
  $raw = "";
  if ($has_currency) {
    $raw = $c0;
  }
  $normalized = "unknown";
  if ($raw === "brl" || $raw === "real" || $raw === "reais") {
    $normalized = "brl";
  } else {
    if ($raw === "usd" || $raw === "dolar" || $raw === "dollar") {
      $normalized = "usd";
    } else {
      if ($raw === "eur" || $raw === "euro") {
        $normalized = "eur";
      }
    }
  }
  $category = "unknown";
  if ($normalized === "brl") {
    $category = "domestic";
  } else {
    if ($normalized === "usd" || $normalized === "eur") {
      $category = "international";
    }
  }
  $is_domestic = $normalized === "brl";
  $is_known = $normalized !== "unknown";
  $out = new \stdClass();
  $out->raw = $raw;
  $out->normalized = $normalized;
  $out->currency_source = $currency_source;
  $out->has_currency = $has_currency;
  $out->category = $category;
  $out->is_domestic = $is_domestic;
  $out->is_known = $is_known;
  return $out;
}
