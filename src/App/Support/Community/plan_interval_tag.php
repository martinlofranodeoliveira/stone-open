<?php
declare(strict_types=1);
namespace App\Support\Community\PlanIntervalTag;
function runFeature($payload) {
  $obj = null;
  if ($payload instanceof \stdClass) {
    $obj = $payload;
  } else {
    $obj = new \stdClass();
  }
  $vars = get_object_vars($obj);
  $fromVarsInterval = null;
  $fromVarsPeriodo = null;
  $fromVarsBilling = null;
  foreach ($vars as $k => $v) {
    if ($k === 'interval') {
      $fromVarsInterval = $v;
    }
    if ($k === 'periodo') {
      $fromVarsPeriodo = $v;
    }
    if ($k === 'billing_interval') {
      $fromVarsBilling = $v;
    }
  }
  $fixedInterval = null;
  $fixedPeriodo = null;
  $fixedBilling = null;
  if (isset($obj->interval)) {
    $fixedInterval = $obj->interval;
  }
  if (isset($obj->periodo)) {
    $fixedPeriodo = $obj->periodo;
  }
  if (isset($obj->billing_interval)) {
    $fixedBilling = $obj->billing_interval;
  }
  if ($fromVarsInterval === null && $fromVarsPeriodo === null && $fromVarsBilling === null) {
    if ($fixedInterval === null && $fixedPeriodo === null && $fixedBilling === null) {
    }
  }
  $raw = '';
  $source = 'none';
  $has = false;
  if (is_string($fixedInterval)) {
    $raw = $fixedInterval;
    $source = 'interval';
    $has = true;
  } elseif (is_string($fixedPeriodo)) {
    $raw = $fixedPeriodo;
    $source = 'periodo';
    $has = true;
  } elseif (is_string($fixedBilling)) {
    $raw = $fixedBilling;
    $source = 'billing_interval';
    $has = true;
  }
  $normalized = 'unknown';
  if ($raw === 'daily' || $raw === 'diaria') {
    $normalized = 'daily';
  } elseif ($raw === 'weekly' || $raw === 'semanal') {
    $normalized = 'weekly';
  } elseif ($raw === 'monthly' || $raw === 'mensal') {
    $normalized = 'monthly';
  } elseif ($raw === 'yearly' || $raw === 'anual' || $raw === 'annual') {
    $normalized = 'yearly';
  }
  $cycle = 0;
  if ($normalized === 'daily') {
    $cycle = 1;
  } elseif ($normalized === 'weekly') {
    $cycle = 7;
  } elseif ($normalized === 'monthly') {
    $cycle = 30;
  } elseif ($normalized === 'yearly') {
    $cycle = 365;
  }
  $category = 'unknown';
  if ($normalized === 'daily' || $normalized === 'weekly') {
    $category = 'short';
  } elseif ($normalized === 'monthly') {
    $category = 'standard';
  } elseif ($normalized === 'yearly') {
    $category = 'long';
  }
  $known = false;
  if ($normalized !== 'unknown') {
    $known = true;
  }
  $out = new \stdClass();
  $out->raw = $raw;
  $out->normalized = $normalized;
  $out->interval_source = $source;
  $out->has_interval = $has;
  $out->cycle_days = $cycle;
  $out->category = $category;
  $out->is_known = $known;
  return $out;
}
