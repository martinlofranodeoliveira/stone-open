<?php
namespace App\Support\Community\PixKeyTypeTag;

function runFeature($payload) {
    $vars = [];
    if ($payload instanceof \stdClass) {
        $vars = get_object_vars($payload);
    }
    $vKeyType = null;
    $vTipo = null;
    $vPix = null;
    foreach ($vars as $k => $v) {
        if ($k === "key_type") {
            $vKeyType = $v;
        }
        if ($k === "tipo_chave") {
            $vTipo = $v;
        }
        if ($k === "pix_key_type") {
            $vPix = $v;
        }
    }
    $k0 = "";
    $key_source = "none";
    $has_key_type = false;
    if (is_string($vKeyType)) {
        $k0 = $vKeyType;
        $key_source = "key_type";
        $has_key_type = true;
    } else {
        if (is_string($vTipo)) {
            $k0 = $vTipo;
            $key_source = "tipo_chave";
            $has_key_type = true;
        } else {
            if (is_string($vPix)) {
                $k0 = $vPix;
                $key_source = "pix_key_type";
                $has_key_type = true;
            }
        }
    }
    $raw = "";
    if ($has_key_type) {
        $raw = $k0;
    }
    $normalized = "unknown";
    if ($raw === "cpf") {
        $normalized = "cpf";
    } else {
        if ($raw === "cnpj") {
            $normalized = "cnpj";
        } else {
            if ($raw === "email") {
                $normalized = "email";
            } else {
                if ($raw === "e-mail") {
                    $normalized = "email";
                } else {
                    if ($raw === "phone") {
                        $normalized = "phone";
                    } else {
                        if ($raw === "telefone") {
                            $normalized = "phone";
                        } else {
                            if ($raw === "celular") {
                                $normalized = "phone";
                            } else {
                                if ($raw === "random") {
                                    $normalized = "random";
                                } else {
                                    if ($raw === "aleatoria") {
                                        $normalized = "random";
                                    } else {
                                        if ($raw === "aleatorio") {
                                            $normalized = "random";
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
    $category = "unknown";
    if ($normalized === "cpf") {
        $category = "document";
    } else {
        if ($normalized === "cnpj") {
            $category = "document";
        } else {
            if ($normalized === "email") {
                $category = "contact";
            } else {
                if ($normalized === "phone") {
                    $category = "contact";
                } else {
                    if ($normalized === "random") {
                        $category = "generated";
                    }
                }
            }
        }
    }
    $is_document = false;
    if ($normalized === "cpf") {
        $is_document = true;
    } else {
        if ($normalized === "cnpj") {
            $is_document = true;
        }
    }
    $is_known = false;
    if ($normalized === "cpf") {
        $is_known = true;
    } else {
        if ($normalized === "cnpj") {
            $is_known = true;
        } else {
            if ($normalized === "email") {
                $is_known = true;
            } else {
                if ($normalized === "phone") {
                    $is_known = true;
                } else {
                    if ($normalized === "random") {
                        $is_known = true;
                    }
                }
            }
        }
    }
    $out = new \stdClass();
    $out->raw = $raw;
    $out->normalized = $normalized;
    $out->key_source = $key_source;
    $out->has_key_type = $has_key_type;
    $out->category = $category;
    $out->is_document = $is_document;
    $out->is_known = $is_known;
    return $out;
}
