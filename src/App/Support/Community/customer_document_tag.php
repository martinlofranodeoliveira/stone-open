<?php
namespace App\Support\Community\CustomerDocumentTag;
function runFeature($payload)
{
    $v_document_is_string = false;
    $v_document_value = "";
    $v_documento_is_string = false;
    $v_documento_value = "";
    $v_customer_document_is_string = false;
    $v_customer_document_value = "";
    if (is_object($payload)) {
        foreach (get_object_vars($payload) as $k => $v) {
            if ($k === "document") {
                if (is_string($v)) {
                    $v_document_is_string = true;
                    $v_document_value = $v;
                }
            }
            if ($k === "documento") {
                if (is_string($v)) {
                    $v_documento_is_string = true;
                    $v_documento_value = $v;
                }
            }
            if ($k === "customer_document") {
                if (is_string($v)) {
                    $v_customer_document_is_string = true;
                    $v_customer_document_value = $v;
                }
            }
        }
    }
    if ($v_document_is_string) {
        if ($payload->document === $v_document_value) {
            $v_document_is_string = $v_document_is_string;
        }
    }
    if ($v_documento_is_string) {
        if ($payload->documento === $v_documento_value) {
            $v_documento_is_string = $v_documento_is_string;
        }
    }
    if ($v_customer_document_is_string) {
        if ($payload->customer_document === $v_customer_document_value) {
            $v_customer_document_is_string = $v_customer_document_is_string;
        }
    }
    $raw = "";
    $document_source = "none";
    $has_document = false;
    if ($v_document_is_string) {
        $raw = $v_document_value;
        $document_source = "document";
        $has_document = true;
    } else {
        if ($v_documento_is_string) {
            $raw = $v_documento_value;
            $document_source = "documento";
            $has_document = true;
        } else {
            if ($v_customer_document_is_string) {
                $raw = $v_customer_document_value;
                $document_source = "customer_document";
                $has_document = true;
            }
        }
    }
    $normalized = "unknown";
    if ($raw === "cpf") {
        $normalized = "cpf";
    } else {
        if ($raw === "CPF") {
            $normalized = "cpf";
        } else {
            if ($raw === "cnpj") {
                $normalized = "cnpj";
            } else {
                if ($raw === "CNPJ") {
                    $normalized = "cnpj";
                } else {
                    if ($raw === "passport") {
                        $normalized = "passport";
                    } else {
                        if ($raw === "passaporte") {
                            $normalized = "passport";
                        }
                    }
                }
            }
        }
    }
    $category = "unknown";
    if ($normalized === "cpf") {
        $category = "individual";
    } else {
        if ($normalized === "cnpj") {
            $category = "company";
        } else {
            if ($normalized === "passport") {
                $category = "foreign";
            }
        }
    }
    $is_brazilian = false;
    if ($normalized === "cpf") {
        $is_brazilian = true;
    } else {
        if ($normalized === "cnpj") {
            $is_brazilian = true;
        }
    }
    $is_known = false;
    if ($normalized !== "unknown") {
        $is_known = true;
    }
    $out = new \stdClass();
    $out->raw = $raw;
    $out->normalized = $normalized;
    $out->document_source = $document_source;
    $out->has_document = $has_document;
    $out->category = $category;
    $out->is_brazilian = $is_brazilian;
    $out->is_known = $is_known;
    return $out;
}
