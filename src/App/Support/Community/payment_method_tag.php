<?php
namespace App\Support\Community\PaymentMethodTag;

function runFeature($payload) {
  $vars = [];
  if (is_object($payload)) {
    $vars = get_object_vars($payload);
  }
  $methodVal = null;
  $metodoVal = null;
  $paymentMethodVal = null;
  $hasMethodKey = false;
  $hasMetodoKey = false;
  $hasPaymentMethodKey = false;
  foreach ($vars as $k => $v) {
    if ($k === "method") {
      $methodVal = $v;
      $hasMethodKey = true;
    }
    if ($k === "metodo") {
      $metodoVal = $v;
      $hasMetodoKey = true;
    }
    if ($k === "payment_method") {
      $paymentMethodVal = $v;
      $hasPaymentMethodKey = true;
    }
  }
  $m0 = "";
  $method_source = "none";
  $has_method = false;
  if ($hasMethodKey) {
    if (is_string($methodVal)) {
      $m0 = $methodVal;
      $method_source = "method";
      $has_method = true;
    }
  }
  if ($has_method === false) {
    if ($hasMetodoKey) {
      if (is_string($metodoVal)) {
        $m0 = $metodoVal;
        $method_source = "metodo";
        $has_method = true;
      }
    }
  }
  if ($has_method === false) {
    if ($hasPaymentMethodKey) {
      if (is_string($paymentMethodVal)) {
        $m0 = $paymentMethodVal;
        $method_source = "payment_method";
        $has_method = true;
      }
    }
  }
  $raw = "";
  if ($has_method === true) {
    $raw = $m0;
  }
  $normalized = "unknown";
  if ($raw === "pix") {
    $normalized = "pix";
  }
  if ($raw === "card") {
    $normalized = "card";
  }
  if ($raw === "cartao") {
    $normalized = "card";
  }
  if ($raw === "boleto") {
    $normalized = "boleto";
  }
  if ($raw === "bank_slip") {
    $normalized = "boleto";
  }
  $category = "unknown";
  if ($normalized === "pix") {
    $category = "instant";
  }
  if ($normalized === "card") {
    $category = "card";
  }
  if ($normalized === "boleto") {
    $category = "slip";
  }
  $is_card = false;
  if ($normalized === "card") {
    $is_card = true;
  }
  $is_instant = false;
  if ($normalized === "pix") {
    $is_instant = true;
  }
  $is_known = false;
  if ($normalized !== "unknown") {
    $is_known = true;
  }
  $out = new \stdClass;
  $out->raw = $raw;
  $out->normalized = $normalized;
  $out->method_source = $method_source;
  $out->has_method = $has_method;
  $out->category = $category;
  $out->is_card = $is_card;
  $out->is_instant = $is_instant;
  $out->is_known = $is_known;
  return $out;
}
