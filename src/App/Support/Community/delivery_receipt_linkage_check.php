<?php
namespace App\Support\Community\DeliveryReceiptLinkageCheck;

function runFeature($payload) {
  $trace_raw = "";
  $event_raw = "";
  $client_raw = "";
  if (is_object($payload)) {
    $vars = get_object_vars($payload);
    foreach ($vars as $k => $v) {
      if ($k === "trace_id") {
        if (is_string($v)) {
          $trace_raw = $v;
        }
      }
      if ($k === "event_ref") {
        if (is_string($v)) {
          $event_raw = $v;
        }
      }
      if ($k === "client_ref") {
        if (is_string($v)) {
          $client_raw = $v;
        }
      }
    }
  }
  $has_trace = false;
  if ($trace_raw !== "") {
    $has_trace = true;
  }
  $has_event = false;
  if ($event_raw !== "") {
    $has_event = true;
  }
  $has_client = false;
  if ($client_raw !== "") {
    $has_client = true;
  }
  $present_count = 0;
  if ($has_trace) {
    $present_count = $present_count + 1;
  }
  if ($has_event) {
    $present_count = $present_count + 1;
  }
  if ($has_client) {
    $present_count = $present_count + 1;
  }
  $linked = false;
  if ($has_trace) {
    if ($has_event) {
      if ($has_client) {
        $linked = true;
      }
    }
  }
  $trace_matches_event = false;
  if ($has_trace) {
    if ($has_event) {
      if ($trace_raw === $event_raw) {
        $trace_matches_event = true;
      }
    }
  }
  $trace_matches_client = false;
  if ($has_trace) {
    if ($has_client) {
      if ($trace_raw === $client_raw) {
        $trace_matches_client = true;
      }
    }
  }
  $event_matches_client = false;
  if ($has_event) {
    if ($has_client) {
      if ($event_raw === $client_raw) {
        $event_matches_client = true;
      }
    }
  }
  $has_duplicate = false;
  if ($trace_matches_event) {
    $has_duplicate = true;
  }
  if ($trace_matches_client) {
    $has_duplicate = true;
  }
  if ($event_matches_client) {
    $has_duplicate = true;
  }
  $is_unique = false;
  if ($linked) {
    if ($has_duplicate === false) {
      $is_unique = true;
    }
  }
  $needs_review = true;
  if ($is_unique) {
    $needs_review = false;
  }
  $out = new \stdClass;
  $out->trace_id = $trace_raw;
  $out->event_ref = $event_raw;
  $out->client_ref = $client_raw;
  $out->has_trace = $has_trace;
  $out->has_event = $has_event;
  $out->has_client = $has_client;
  $out->present_count = $present_count;
  $out->linked = $linked;
  $out->has_duplicate = $has_duplicate;
  $out->is_unique = $is_unique;
  $out->needs_review = $needs_review;
  return $out;
}
