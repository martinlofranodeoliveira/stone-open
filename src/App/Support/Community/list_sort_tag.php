<?php
namespace App\Support\Community\ListSortTag;

function runFeature($payload) {
  $s0 = "";
  $sort_source = "none";
  $has_sort = false;
  $raw = "";
  $normalized = "unknown";
  $field = "unknown";
  $direction = "unknown";
  $is_known = false;
  $vars = [];
  if (is_object($payload)) {
    $vars = get_object_vars($payload);
  }
  $hasSortKey = false;
  $hasOrdenacaoKey = false;
  $hasOrderByKey = false;
  foreach ($vars as $k => $v) {
    if ($k === "sort") {
      $hasSortKey = true;
    }
    if ($k === "ordenacao") {
      $hasOrdenacaoKey = true;
    }
    if ($k === "order_by") {
      $hasOrderByKey = true;
    }
  }
  if (is_object($payload)) {
    if ($hasSortKey) {
      $candSort = $payload->sort;
      if (is_string($candSort)) {
        $s0 = $candSort;
        $sort_source = "sort";
        $has_sort = true;
      }
    }
    if ($has_sort === false) {
      if ($hasOrdenacaoKey) {
        $candOrdenacao = $payload->ordenacao;
        if (is_string($candOrdenacao)) {
          $s0 = $candOrdenacao;
          $sort_source = "ordenacao";
          $has_sort = true;
        }
      }
    }
    if ($has_sort === false) {
      if ($hasOrderByKey) {
        $candOrderBy = $payload->order_by;
        if (is_string($candOrderBy)) {
          $s0 = $candOrderBy;
          $sort_source = "order_by";
          $has_sort = true;
        }
      }
    }
  }
  if ($has_sort) {
    $raw = $s0;
  } else {
    $raw = "";
  }
  if ($raw === "recent" || $raw === "recentes" || $raw === "newest") {
    $normalized = "recent";
  } else {
    if ($raw === "oldest" || $raw === "mais_antigos") {
      $normalized = "oldest";
    } else {
      if ($raw === "amount_desc" || $raw === "valor_desc" || $raw === "maior_valor") {
        $normalized = "amount_high";
      } else {
        if ($raw === "amount_asc" || $raw === "valor_asc" || $raw === "menor_valor") {
          $normalized = "amount_low";
        } else {
          if ($raw === "name_asc" || $raw === "nome_asc" || $raw === "nome_az") {
            $normalized = "name_az";
          } else {
            if ($raw === "name_desc" || $raw === "nome_desc" || $raw === "nome_za") {
              $normalized = "name_za";
            } else {
              $normalized = "unknown";
            }
          }
        }
      }
    }
  }
  if ($normalized === "recent" || $normalized === "oldest") {
    $field = "date";
  } else {
    if ($normalized === "amount_high" || $normalized === "amount_low") {
      $field = "amount";
    } else {
      if ($normalized === "name_az" || $normalized === "name_za") {
        $field = "name";
      } else {
        $field = "unknown";
      }
    }
  }
  if ($normalized === "recent" || $normalized === "amount_high" || $normalized === "name_za") {
    $direction = "descending";
  } else {
    if ($normalized === "oldest" || $normalized === "amount_low" || $normalized === "name_az") {
      $direction = "ascending";
    } else {
      $direction = "unknown";
    }
  }
  if ($normalized === "unknown") {
    $is_known = false;
  } else {
    $is_known = true;
  }
  $out = new \stdClass;
  $out->raw = $raw;
  $out->normalized = $normalized;
  $out->sort_source = $sort_source;
  $out->has_sort = $has_sort;
  $out->field = $field;
  $out->direction = $direction;
  $out->is_known = $is_known;
  return $out;
}
