<?php
namespace App\Support\Community\AdminListWindow;
function runFeature($payload) {
  $p0 = 0;
  $p0Found = false;
  $pageSource = "none";
  $pp0 = 0;
  $pp0Found = false;
  $perPageSource = "none";
  $s0 = "";
  $s0Found = false;
  $sortSource = "none";
  $d0 = "";
  $d0Found = false;
  $directionSource = "none";
  if (is_object($payload)) {
    if (isset($payload->page) && is_int($payload->page)) {
      $p0 = $payload->page;
      $p0Found = true;
      $pageSource = "page";
    } else {
      if (isset($payload->pagina) && is_int($payload->pagina)) {
        $p0 = $payload->pagina;
        $p0Found = true;
        $pageSource = "pagina";
      }
    }
    if (isset($payload->per_page) && is_int($payload->per_page)) {
      $pp0 = $payload->per_page;
      $pp0Found = true;
      $perPageSource = "per_page";
    } else {
      if (isset($payload->por_pagina) && is_int($payload->por_pagina)) {
        $pp0 = $payload->por_pagina;
        $pp0Found = true;
        $perPageSource = "por_pagina";
      }
    }
    if (isset($payload->sort) && is_string($payload->sort)) {
      $s0 = $payload->sort;
      $s0Found = true;
      $sortSource = "sort";
    } else {
      if (isset($payload->ordenacao) && is_string($payload->ordenacao)) {
        $s0 = $payload->ordenacao;
        $s0Found = true;
        $sortSource = "ordenacao";
      }
    }
    if (isset($payload->direction) && is_string($payload->direction)) {
      $d0 = $payload->direction;
      $d0Found = true;
      $directionSource = "direction";
    } else {
      if (isset($payload->direcao) && is_string($payload->direcao)) {
        $d0 = $payload->direcao;
        $d0Found = true;
        $directionSource = "direcao";
      }
    }
  }
  $hasPage = false;
  if ($p0Found) {
    $hasPage = true;
  }
  $page = 1;
  if ($p0Found) {
    if ($p0 < 1) {
      $page = 1;
    } else {
      if ($p0 > 1000) {
        $page = 1000;
      } else {
        $page = $p0;
      }
    }
  }
  $hasPerPage = false;
  if ($pp0Found) {
    $hasPerPage = true;
  }
  $perPage = 20;
  if ($pp0Found) {
    if ($pp0 < 1) {
      $perPage = 1;
    } else {
      if ($pp0 > 100) {
        $perPage = 100;
      } else {
        $perPage = $pp0;
      }
    }
  }
  $hasSort = false;
  if ($s0Found) {
    $hasSort = true;
  }
  $rawSort = "";
  if ($s0Found) {
    $rawSort = $s0;
  }
  $sort = "created_at";
  if ($rawSort === "created_at") {
    $sort = "created_at";
  } else {
    if ($rawSort === "criado_em") {
      $sort = "created_at";
    } else {
      if ($rawSort === "amount") {
        $sort = "amount";
      } else {
        if ($rawSort === "valor") {
          $sort = "amount";
        } else {
          if ($rawSort === "status") {
            $sort = "status";
          } else {
            if ($rawSort === "situacao") {
              $sort = "status";
            } else {
              $sort = "created_at";
            }
          }
        }
      }
    }
  }
  $hasDirection = false;
  if ($d0Found) {
    $hasDirection = true;
  }
  $rawDirection = "";
  if ($d0Found) {
    $rawDirection = $d0;
  }
  $direction = "desc";
  if ($rawDirection === "asc") {
    $direction = "asc";
  } else {
    if ($rawDirection === "crescente") {
      $direction = "asc";
    } else {
      if ($rawDirection === "desc") {
        $direction = "desc";
      } else {
        if ($rawDirection === "decrescente") {
          $direction = "desc";
        } else {
          $direction = "desc";
        }
      }
    }
  }
  $offset = ($page - 1) * $perPage;
  $out = new \stdClass();
  $out->page = $page;
  $out->per_page = $perPage;
  $out->offset = $offset;
  $out->sort = $sort;
  $out->sort_source = $sortSource;
  $out->has_sort = $hasSort;
  $out->raw_sort = $rawSort;
  $out->direction = $direction;
  $out->direction_source = $directionSource;
  $out->has_direction = $hasDirection;
  $out->raw_direction = $rawDirection;
  $out->page_source = $pageSource;
  $out->has_page = $hasPage;
  $out->per_page_source = $perPageSource;
  $out->has_per_page = $hasPerPage;
  return $out;
}
