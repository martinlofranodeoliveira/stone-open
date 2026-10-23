<?php
namespace App\Support\Community\CardBrandTag;

function runFeature($payload) {
  $b0 = null;
  $brand_source = 'none';
  $has_brand = false;
  $raw = '';
  $normalized = 'unknown';
  $category = 'unknown';
  $is_known = false;
  if (is_object($payload) === true) {
    $vars = get_object_vars($payload);
    $brandVal = null;
    $hasBrand = false;
    $bandeiraVal = null;
    $hasBandeira = false;
    $cardBrandVal = null;
    $hasCardBrand = false;
    foreach ($vars as $k => $v) {
      if ($k === 'brand') {
        $brandVal = $v;
        if (is_string($v) === true) {
          $hasBrand = true;
        }
      }
      if ($k === 'bandeira') {
        $bandeiraVal = $v;
        if (is_string($v) === true) {
          $hasBandeira = true;
        }
      }
      if ($k === 'card_brand') {
        $cardBrandVal = $v;
        if (is_string($v) === true) {
          $hasCardBrand = true;
        }
      }
    }
    if ($hasBrand === true) {
      $b0 = $brandVal;
      $brand_source = 'brand';
      $has_brand = true;
    } else {
      if ($hasBandeira === true) {
        $b0 = $bandeiraVal;
        $brand_source = 'bandeira';
        $has_brand = true;
      } else {
        if ($hasCardBrand === true) {
          $b0 = $cardBrandVal;
          $brand_source = 'card_brand';
          $has_brand = true;
        }
      }
    }
  }
  if ($has_brand === true) {
    $raw = $b0;
  } else {
    $raw = '';
  }
  if ($raw === 'visa') {
    $normalized = 'visa';
  } else {
    if ($raw === 'mastercard') {
      $normalized = 'mastercard';
    } else {
      if ($raw === 'master') {
        $normalized = 'mastercard';
      } else {
        if ($raw === 'elo') {
          $normalized = 'elo';
        } else {
          if ($raw === 'amex') {
            $normalized = 'amex';
          } else {
            if ($raw === 'american_express') {
              $normalized = 'amex';
            } else {
              if ($raw === 'hipercard') {
                $normalized = 'hipercard';
              } else {
                if ($raw === 'generic') {
                  $normalized = 'generic';
                } else {
                  if ($raw === 'generica') {
                    $normalized = 'generic';
                  } else {
                    if ($raw === 'generico') {
                      $normalized = 'generic';
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
  if ($normalized === 'visa') {
    $category = 'international';
  } else {
    if ($normalized === 'mastercard') {
      $category = 'international';
    } else {
      if ($normalized === 'amex') {
        $category = 'international';
      } else {
        if ($normalized === 'elo') {
          $category = 'domestic';
        } else {
          if ($normalized === 'hipercard') {
            $category = 'domestic';
          } else {
            if ($normalized === 'generic') {
              $category = 'fallback';
            } else {
              $category = 'unknown';
            }
          }
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
  $out->brand_source = $brand_source;
  $out->has_brand = $has_brand;
  $out->category = $category;
  $out->is_known = $is_known;
  return $out;
}
