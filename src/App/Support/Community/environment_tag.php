<?php
namespace App\Support\Community\EnvironmentTag;

function runFeature($payload) {
  $raw = "";
  $normalized = "unknown";
  $environmentSource = "none";
  $hasEnvironment = false;
  $category = "unknown";
  $isLive = false;
  $isKnown = false;
  $e0 = "";
  $e0Source = "none";
  $e0Found = false;
  if (is_object($payload)) {
    $props = get_object_vars($payload);
    $hasPropEnvironment = false;
    $hasPropAmbiente = false;
    $hasPropAccountMode = false;
    foreach ($props as $propKey => $propVal) {
      if ($propKey === "environment") {
        $hasPropEnvironment = true;
      }
      if ($propKey === "ambiente") {
        $hasPropAmbiente = true;
      }
      if ($propKey === "account_mode") {
        $hasPropAccountMode = true;
      }
    }
    if ($hasPropEnvironment) {
      if (is_string($payload->environment)) {
        $e0 = $payload->environment;
        $e0Source = "environment";
        $e0Found = true;
      } else {
        if ($hasPropAmbiente) {
          if (is_string($payload->ambiente)) {
            $e0 = $payload->ambiente;
            $e0Source = "ambiente";
            $e0Found = true;
          } else {
            if ($hasPropAccountMode) {
              if (is_string($payload->account_mode)) {
                $e0 = $payload->account_mode;
                $e0Source = "account_mode";
                $e0Found = true;
              }
            }
          }
        } else {
          if ($hasPropAccountMode) {
            if (is_string($payload->account_mode)) {
              $e0 = $payload->account_mode;
              $e0Source = "account_mode";
              $e0Found = true;
            }
          }
        }
      }
    } else {
      if ($hasPropAmbiente) {
        if (is_string($payload->ambiente)) {
          $e0 = $payload->ambiente;
          $e0Source = "ambiente";
          $e0Found = true;
        } else {
          if ($hasPropAccountMode) {
            if (is_string($payload->account_mode)) {
              $e0 = $payload->account_mode;
              $e0Source = "account_mode";
              $e0Found = true;
            }
          }
        }
      } else {
        if ($hasPropAccountMode) {
          if (is_string($payload->account_mode)) {
            $e0 = $payload->account_mode;
            $e0Source = "account_mode";
            $e0Found = true;
          }
        }
      }
    }
  }
  if ($e0Found) {
    $raw = $e0;
    $environmentSource = $e0Source;
    $hasEnvironment = true;
  } else {
    $raw = "";
    $environmentSource = "none";
    $hasEnvironment = false;
  }
  if ($raw === "test") {
    $normalized = "test";
  } else {
    if ($raw === "teste") {
      $normalized = "test";
    } else {
      if ($raw === "sandbox") {
        $normalized = "test";
      } else {
        if ($raw === "homologacao") {
          $normalized = "test";
        } else {
          if ($raw === "live") {
            $normalized = "live";
          } else {
            if ($raw === "production") {
              $normalized = "live";
            } else {
              if ($raw === "producao") {
                $normalized = "live";
              } else {
                $normalized = "unknown";
              }
            }
          }
        }
      }
    }
  }
  if ($normalized === "test") {
    $category = "non_live";
  } else {
    if ($normalized === "live") {
      $category = "live";
    } else {
      $category = "unknown";
    }
  }
  if ($normalized === "live") {
    $isLive = true;
  } else {
    $isLive = false;
  }
  if ($normalized === "unknown") {
    $isKnown = false;
  } else {
    $isKnown = true;
  }
  $out = new \stdClass();
  $out->raw = $raw;
  $out->normalized = $normalized;
  $out->environment_source = $environmentSource;
  $out->has_environment = $hasEnvironment;
  $out->category = $category;
  $out->is_live = $isLive;
  $out->is_known = $isKnown;
  return $out;
}
