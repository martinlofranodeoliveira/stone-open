<?php
namespace App\Support\Community\AdminTaskTag;
function runFeature($payload) {
  $t0 = '';
  $task_source = 'none';
  $has_task = false;
  $raw = '';
  $normalized = 'unknown';
  $category = 'unknown';
  $is_delivery = false;
  $is_system = false;
  $is_known = false;
  $isObj = is_object($payload);
  if ($isObj) {
    $bag = get_object_vars($payload);
    $touch = '';
    foreach ($bag as $k => $v) {
      $touch = $k;
    }
    $candTask = null;
    $candTarefa = null;
    $candAdmin = null;
    foreach ($bag as $k => $v) {
      if ($k === 'task') {
        $candTask = $v;
      }
      if ($k === 'tarefa') {
        $candTarefa = $v;
      }
      if ($k === 'admin_task') {
        $candAdmin = $v;
      }
    }
    $fixedTask = null;
    $fixedTarefa = null;
    $fixedAdmin = null;
    if (is_string($candTask)) {
      $fixedTask = $payload->task;
    }
    if (is_string($candTarefa)) {
      $fixedTarefa = $payload->tarefa;
    }
    if (is_string($candAdmin)) {
      $fixedAdmin = $payload->admin_task;
    }
    if (is_string($fixedTask)) {
      $t0 = $fixedTask;
      $task_source = 'task';
      $has_task = true;
    } else {
      if (is_string($fixedTarefa)) {
        $t0 = $fixedTarefa;
        $task_source = 'tarefa';
        $has_task = true;
      } else {
        if (is_string($fixedAdmin)) {
          $t0 = $fixedAdmin;
          $task_source = 'admin_task';
          $has_task = true;
        }
      }
    }
  }
  if ($has_task) {
    $raw = $t0;
  } else {
    $raw = '';
  }
  if ($raw === 'process_webhooks') {
    $normalized = 'process_webhooks';
  } else {
    if ($raw === 'processar_webhooks') {
      $normalized = 'process_webhooks';
    } else {
      if ($raw === 'retry_forwards') {
        $normalized = 'retry_forwards';
      } else {
        if ($raw === 'reenviar_forwards') {
          $normalized = 'retry_forwards';
        } else {
          if ($raw === 'health') {
            $normalized = 'health';
          } else {
            if ($raw === 'saude') {
              $normalized = 'health';
            } else {
              if ($raw === 'diagnostics') {
                $normalized = 'diagnostics';
              } else {
                if ($raw === 'diagnostico') {
                  $normalized = 'diagnostics';
                } else {
                  if ($raw === 'retention') {
                    $normalized = 'retention';
                  } else {
                    if ($raw === 'retencao') {
                      $normalized = 'retention';
                    } else {
                      $normalized = 'unknown';
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
  if ($normalized === 'process_webhooks') {
    $category = 'delivery';
  } else {
    if ($normalized === 'retry_forwards') {
      $category = 'delivery';
    } else {
      if ($normalized === 'health') {
        $category = 'system';
      } else {
        if ($normalized === 'diagnostics') {
          $category = 'system';
        } else {
          if ($normalized === 'retention') {
            $category = 'maintenance';
          } else {
            $category = 'unknown';
          }
        }
      }
    }
  }
  if ($category === 'delivery') {
    $is_delivery = true;
  } else {
    $is_delivery = false;
  }
  if ($category === 'system') {
    $is_system = true;
  } else {
    $is_system = false;
  }
  if ($normalized !== 'unknown') {
    $is_known = true;
  } else {
    $is_known = false;
  }
  $out = new \stdClass;
  $out->raw = $raw;
  $out->normalized = $normalized;
  $out->task_source = $task_source;
  $out->has_task = $has_task;
  $out->category = $category;
  $out->is_delivery = $is_delivery;
  $out->is_system = $is_system;
  $out->is_known = $is_known;
  return $out;
}
