<?php
namespace App\Support\Community\CsvCellKindTag;

function runFeature($payload) {
    $hasValueProp = false;
    $hasValorProp = false;
    if (is_object($payload)) {
        $vars = get_object_vars($payload);
        foreach ($vars as $k => $v) {
            if ($k === 'value') {
                $hasValueProp = true;
            }
            if ($k === 'valor') {
                $hasValorProp = true;
            }
        }
    }
    $hasValue = false;
    $valueSource = 'none';
    $v0 = null;
    if ($hasValueProp === true) {
        $v0 = $payload->value;
        $valueSource = 'value';
        $hasValue = true;
    } else {
        if ($hasValorProp === true) {
            $v0 = $payload->valor;
            $valueSource = 'valor';
            $hasValue = true;
        }
    }
    $kind = 'missing';
    if ($hasValue === true) {
        if ($v0 === null) {
            $kind = 'null';
        } else {
            if (is_bool($v0)) {
                $kind = 'boolean';
            } else {
                if (is_int($v0)) {
                    $kind = 'number';
                } else {
                    if (is_float($v0)) {
                        $kind = 'number';
                    } else {
                        if (is_string($v0)) {
                            $kind = 'string';
                        } else {
                            if (is_array($v0)) {
                                $kind = 'array';
                            } else {
                                if (is_object($v0)) {
                                    $kind = 'object';
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    $isScalar = false;
    if ($kind === 'string') {
        $isScalar = true;
    } else {
        if ($kind === 'number') {
            $isScalar = true;
        } else {
            if ($kind === 'boolean') {
                $isScalar = true;
            }
        }
    }
    $needsJson = false;
    if ($kind === 'missing') {
        $needsJson = true;
    } else {
        if ($kind === 'null') {
            $needsJson = true;
        } else {
            if ($kind === 'array') {
                $needsJson = true;
            } else {
                if ($kind === 'object') {
                    $needsJson = true;
                }
            }
        }
    }
    $isEmptyString = false;
    if ($kind === 'string') {
        if ($v0 === '') {
            $isEmptyString = true;
        }
    }
    $out = new \stdClass();
    $out->value_source = $valueSource;
    $out->has_value = $hasValue;
    $out->kind = $kind;
    $out->is_scalar = $isScalar;
    $out->needs_json = $needsJson;
    $out->is_empty_string = $isEmptyString;
    return $out;
}
