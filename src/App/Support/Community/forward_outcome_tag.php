<?php
namespace App\Support\Community\ForwardOutcomeTag;

function runFeature($payload)
{
    $raw = '';
    $outcome_source = 'none';
    $has_outcome = false;
    $f0 = '';
    $found = false;
    if (is_object($payload)) {
        $vars = get_object_vars($payload);
        $hasOutcomeProp = false;
        $hasResultadoProp = false;
        $hasForwardProp = false;
        foreach ($vars as $k => $v) {
            if ($k === 'outcome') {
                $hasOutcomeProp = true;
            }
            if ($k === 'resultado') {
                $hasResultadoProp = true;
            }
            if ($k === 'forward_status') {
                $hasForwardProp = true;
            }
        }
        $tmpOutcome = null;
        $tmpResultado = null;
        $tmpForward = null;
        if ($hasOutcomeProp) {
            $tmpOutcome = $payload->outcome;
        }
        if ($hasResultadoProp) {
            $tmpResultado = $payload->resultado;
        }
        if ($hasForwardProp) {
            $tmpForward = $payload->forward_status;
        }
        if (is_string($tmpOutcome)) {
            $f0 = $tmpOutcome;
            $outcome_source = 'outcome';
            $found = true;
        } else {
            if (is_string($tmpResultado)) {
                $f0 = $tmpResultado;
                $outcome_source = 'resultado';
                $found = true;
            } else {
                if (is_string($tmpForward)) {
                    $f0 = $tmpForward;
                    $outcome_source = 'forward_status';
                    $found = true;
                }
            }
        }
    }
    if ($found) {
        $raw = $f0;
        $has_outcome = true;
    } else {
        $raw = '';
        $has_outcome = false;
        $outcome_source = 'none';
    }
    $normalized = 'unknown';
    if ($raw === 'delivered') {
        $normalized = 'delivered';
    } else {
        if ($raw === 'entregue') {
            $normalized = 'delivered';
        } else {
            if ($raw === 'pending') {
                $normalized = 'pending';
            } else {
                if ($raw === 'pendente') {
                    $normalized = 'pending';
                } else {
                    if ($raw === 'failed') {
                        $normalized = 'failed';
                    } else {
                        if ($raw === 'falha') {
                            $normalized = 'failed';
                        } else {
                            if ($raw === 'retrying') {
                                $normalized = 'retrying';
                            } else {
                                if ($raw === 'retry') {
                                    $normalized = 'retrying';
                                } else {
                                    if ($raw === 'tentando_novamente') {
                                        $normalized = 'retrying';
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
    $category = 'unknown';
    if ($normalized === 'delivered') {
        $category = 'success';
    } else {
        if ($normalized === 'pending') {
            $category = 'open';
        } else {
            if ($normalized === 'retrying') {
                $category = 'open';
            } else {
                if ($normalized === 'failed') {
                    $category = 'failure';
                } else {
                    $category = 'unknown';
                }
            }
        }
    }
    $is_terminal = false;
    if ($normalized === 'delivered') {
        $is_terminal = true;
    } else {
        if ($normalized === 'failed') {
            $is_terminal = true;
        } else {
            $is_terminal = false;
        }
    }
    $is_known = false;
    if ($normalized !== 'unknown') {
        $is_known = true;
    } else {
        $is_known = false;
    }
    $out = new \stdClass;
    $out->raw = $raw;
    $out->normalized = $normalized;
    $out->outcome_source = $outcome_source;
    $out->has_outcome = $has_outcome;
    $out->category = $category;
    $out->is_terminal = $is_terminal;
    $out->is_known = $is_known;
    return $out;
}
