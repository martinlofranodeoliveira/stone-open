<?php
namespace App\Support\Community\UtilsToolTag;
function runFeature($payload) {
  $t0 = '';
  $tool_source = 'none';
  $has_tool = false;
  $raw = '';
  $normalized = 'unknown';
  $category = 'unknown';
  $is_known = false;
  $isObj = $payload instanceof \stdClass;
  if ($isObj === true) {
    $bag = get_object_vars($payload);
    $toolVal = null;
    $ferrVal = null;
    $utilsVal = null;
    foreach ($bag as $k => $v) {
      if ($k === 'tool') {
        $toolVal = $v;
      }
      if ($k === 'ferramenta') {
        $ferrVal = $v;
      }
      if ($k === 'utils_tool') {
        $utilsVal = $v;
      }
    }
    if (is_string($toolVal) === true) {
      $t0 = $payload->tool;
      $tool_source = 'tool';
      $has_tool = true;
    } else {
      if (is_string($ferrVal) === true) {
        $t0 = $payload->ferramenta;
        $tool_source = 'ferramenta';
        $has_tool = true;
      } else {
        if (is_string($utilsVal) === true) {
          $t0 = $payload->utils_tool;
          $tool_source = 'utils_tool';
          $has_tool = true;
        }
      }
    }
  }
  if ($has_tool === true) {
    $raw = $t0;
  } else {
    $raw = '';
  }
  if ($raw === 'boleto') {
    $normalized = 'boleto';
  } else {
    if ($raw === 'boletos') {
      $normalized = 'boleto';
    } else {
      if ($raw === 'card') {
        $normalized = 'card';
      } else {
        if ($raw === 'cartao') {
          $normalized = 'card';
        } else {
          if ($raw === 'cards') {
            $normalized = 'card';
          } else {
            if ($raw === 'pix') {
              $normalized = 'pix';
            } else {
              if ($raw === 'link') {
                $normalized = 'link';
              } else {
                if ($raw === 'links') {
                  $normalized = 'link';
                } else {
                  if ($raw === 'payment_link') {
                    $normalized = 'link';
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
  if ($normalized === 'boleto') {
    $category = 'slip';
  } else {
    if ($normalized === 'card') {
      $category = 'card';
    } else {
      if ($normalized === 'pix') {
        $category = 'instant';
      } else {
        if ($normalized === 'link') {
          $category = 'shareable';
        } else {
          $category = 'unknown';
        }
      }
    }
  }
  if ($normalized === 'unknown') {
    $is_known = false;
  } else {
    $is_known = true;
  }
  $out = new \stdClass;
  $out->raw = $raw;
  $out->normalized = $normalized;
  $out->tool_source = $tool_source;
  $out->has_tool = $has_tool;
  $out->category = $category;
  $out->is_known = $is_known;
  return $out;
}
