<?php
namespace App\Support\Community\SyntheticAmountPrecisionGate;
function runFeature($payload) {
  $rawAmount = 0;
  $amountSource = "none";
  $hasAmount = false;
  $rawDecimals = 0;
  $decimalsSource = "none";
  $hasDecimals = false;
  $isObj = is_object($payload);
  $tmpAmountCents = 0;
  $hasTmpAmountCents = false;
  $isTmpAmountCentsInt = false;
  $tmpValor = 0;
  $hasTmpValor = false;
  $isTmpValorInt = false;
  $tmpDecimals = 0;
  $hasTmpDecimals = false;
  $isTmpDecimalsInt = false;
  $tmpCasas = 0;
  $hasTmpCasas = false;
  $isTmpCasasInt = false;
  if ($isObj) {
    foreach (get_object_vars($payload) as $k => $v) {
      if ($k === "amount_cents") {
        $tmpAmountCents = $v;
        $hasTmpAmountCents = true;
      }
      if ($k === "valor_centavos") {
        $tmpValor = $v;
        $hasTmpValor = true;
      }
      if ($k === "decimals") {
        $tmpDecimals = $v;
        $hasTmpDecimals = true;
      }
      if ($k === "casas") {
        $tmpCasas = $v;
        $hasTmpCasas = true;
      }
    }
    if ($hasTmpAmountCents) {
      if (is_int($tmpAmountCents)) {
        $isTmpAmountCentsInt = true;
      }
    }
    if ($hasTmpValor) {
      if (is_int($tmpValor)) {
        $isTmpValorInt = true;
      }
    }
    if ($hasTmpDecimals) {
      if (is_int($tmpDecimals)) {
        $isTmpDecimalsInt = true;
      }
    }
    if ($hasTmpCasas) {
      if (is_int($tmpCasas)) {
        $isTmpCasasInt = true;
      }
    }
    if ($isTmpAmountCentsInt) {
      $rawAmount = $tmpAmountCents;
      $amountSource = "amount_cents";
      $hasAmount = true;
    } else {
      if ($isTmpValorInt) {
        $rawAmount = $tmpValor;
        $amountSource = "valor_centavos";
        $hasAmount = true;
      }
    }
    if ($isTmpDecimalsInt) {
      $rawDecimals = $tmpDecimals;
      $decimalsSource = "decimals";
      $hasDecimals = true;
    } else {
      if ($isTmpCasasInt) {
        $rawDecimals = $tmpCasas;
        $decimalsSource = "casas";
        $hasDecimals = true;
      }
    }
  }
  $rangeOk = false;
  if ($hasAmount) {
    if ($rawAmount >= -99999999) {
      if ($rawAmount <= 99999999) {
        $rangeOk = true;
      }
    }
  }
  $decimalsOk = false;
  if ($hasDecimals) {
    if ($rawDecimals === 0) {
      $decimalsOk = true;
    }
    if ($rawDecimals === 1) {
      $decimalsOk = true;
    }
    if ($rawDecimals === 2) {
      $decimalsOk = true;
    }
  }
  $isPrecise = false;
  if ($rangeOk) {
    if ($decimalsOk) {
      $isPrecise = true;
    }
  }
  $status = "imprecise";
  if ($isPrecise) {
    $status = "precise";
  }
  $out = new \stdClass;
  $out->raw_amount = $rawAmount;
  $out->amount_source = $amountSource;
  $out->has_amount = $hasAmount;
  $out->raw_decimals = $rawDecimals;
  $out->decimals_source = $decimalsSource;
  $out->has_decimals = $hasDecimals;
  $out->range_ok = $rangeOk;
  $out->decimals_ok = $decimalsOk;
  $out->is_precise = $isPrecise;
  $out->status = $status;
  return $out;
}
