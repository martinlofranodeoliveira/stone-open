<?php
namespace App\Support\Community\ExportFormatTag;

function runFeature($payload) {
    $raw = "";
    $normalized = "unknown";
    $formatSource = "none";
    $hasFormat = false;
    $category = "unknown";
    $isTabular = false;
    $isKnown = false;
    if ($payload instanceof \stdClass) {
        if (isset($payload->format) && is_string($payload->format)) {
            $raw = $payload->format;
            $formatSource = "format";
            $hasFormat = true;
        } else {
            if (isset($payload->formato) && is_string($payload->formato)) {
                $raw = $payload->formato;
                $formatSource = "formato";
                $hasFormat = true;
            } else {
                if (isset($payload->export_format) && is_string($payload->export_format)) {
                    $raw = $payload->export_format;
                    $formatSource = "export_format";
                    $hasFormat = true;
                }
            }
        }
    }
    if ($raw === "csv") {
        $normalized = "csv";
    } else {
        if ($raw === "json") {
            $normalized = "json";
        } else {
            if ($raw === "xls" || $raw === "xlsx" || $raw === "excel" || $raw === "planilha") {
                $normalized = "xls";
            } else {
                if ($raw === "pdf") {
                    $normalized = "pdf";
                } else {
                    $normalized = "unknown";
                }
            }
        }
    }
    if ($normalized === "csv" || $normalized === "xls") {
        $category = "tabular";
    } else {
        if ($normalized === "json") {
            $category = "structured";
        } else {
            if ($normalized === "pdf") {
                $category = "document";
            } else {
                $category = "unknown";
            }
        }
    }
    if ($category === "tabular") {
        $isTabular = true;
    } else {
        $isTabular = false;
    }
    if ($normalized !== "unknown") {
        $isKnown = true;
    } else {
        $isKnown = false;
    }
    $out = new \stdClass();
    $out->raw = $raw;
    $out->normalized = $normalized;
    $out->format_source = $formatSource;
    $out->has_format = $hasFormat;
    $out->category = $category;
    $out->is_tabular = $isTabular;
    $out->is_known = $isKnown;
    return $out;
}
