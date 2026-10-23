<?php
namespace App\Support\Community\AdminLogActionTag;
function runFeature($payload) {
    $a0 = null;
    $action_source = 'none';
    $has_action = false;
    $raw = '';
    $normalized = 'unknown';
    $category = 'unknown';
    $is_mutating = false;
    $is_known = false;
    if ($payload instanceof \stdClass) {
        $vars = get_object_vars($payload);
        $actionVal = null;
        $acaoVal = null;
        $logActionVal = null;
        $hasActionKey = false;
        $hasAcaoKey = false;
        $hasLogActionKey = false;
        foreach ($vars as $k => $v) {
            if ($k === 'action') {
                $actionVal = $v;
                $hasActionKey = true;
            }
            if ($k === 'acao') {
                $acaoVal = $v;
                $hasAcaoKey = true;
            }
            if ($k === 'log_action') {
                $logActionVal = $v;
                $hasLogActionKey = true;
            }
        }
        if ($hasActionKey) {
            if ($payload->action === $actionVal) {
                $has_action = $has_action;
            }
        }
        if ($hasAcaoKey) {
            if ($payload->acao === $acaoVal) {
                $has_action = $has_action;
            }
        }
        if ($hasLogActionKey) {
            if ($payload->log_action === $logActionVal) {
                $has_action = $has_action;
            }
        }
        if ($hasActionKey) {
            if (is_string($actionVal)) {
                $a0 = $actionVal;
                $action_source = 'action';
                $has_action = true;
            } else {
                if ($hasAcaoKey) {
                    if (is_string($acaoVal)) {
                        $a0 = $acaoVal;
                        $action_source = 'acao';
                        $has_action = true;
                    } else {
                        if ($hasLogActionKey) {
                            if (is_string($logActionVal)) {
                                $a0 = $logActionVal;
                                $action_source = 'log_action';
                                $has_action = true;
                            }
                        }
                    }
                } else {
                    if ($hasLogActionKey) {
                        if (is_string($logActionVal)) {
                            $a0 = $logActionVal;
                            $action_source = 'log_action';
                            $has_action = true;
                        }
                    }
                }
            }
        } else {
            if ($hasAcaoKey) {
                if (is_string($acaoVal)) {
                    $a0 = $acaoVal;
                    $action_source = 'acao';
                    $has_action = true;
                } else {
                    if ($hasLogActionKey) {
                        if (is_string($logActionVal)) {
                            $a0 = $logActionVal;
                            $action_source = 'log_action';
                            $has_action = true;
                        }
                    }
                }
            } else {
                if ($hasLogActionKey) {
                    if (is_string($logActionVal)) {
                        $a0 = $logActionVal;
                        $action_source = 'log_action';
                        $has_action = true;
                    }
                }
            }
        }
    }
    if ($has_action) {
        $raw = $a0;
    } else {
        $raw = '';
    }
    if ($raw === 'login') {
        $normalized = 'login';
    } else {
        if ($raw === 'entrar') {
            $normalized = 'login';
        } else {
            if ($raw === 'logout') {
                $normalized = 'logout';
            } else {
                if ($raw === 'sair') {
                    $normalized = 'logout';
                } else {
                    if ($raw === 'create') {
                        $normalized = 'create';
                    } else {
                        if ($raw === 'criar') {
                            $normalized = 'create';
                        } else {
                            if ($raw === 'update') {
                                $normalized = 'update';
                            } else {
                                if ($raw === 'atualizar') {
                                    $normalized = 'update';
                                } else {
                                    if ($raw === 'delete') {
                                        $normalized = 'delete';
                                    } else {
                                        if ($raw === 'excluir') {
                                            $normalized = 'delete';
                                        } else {
                                            if ($raw === 'view') {
                                                $normalized = 'view';
                                            } else {
                                                if ($raw === 'visualizar') {
                                                    $normalized = 'view';
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
        }
    }
    if ($normalized === 'login') {
        $category = 'auth';
    } else {
        if ($normalized === 'logout') {
            $category = 'auth';
        } else {
            if ($normalized === 'create') {
                $category = 'mutation';
            } else {
                if ($normalized === 'update') {
                    $category = 'mutation';
                } else {
                    if ($normalized === 'delete') {
                        $category = 'mutation';
                    } else {
                        if ($normalized === 'view') {
                            $category = 'read';
                        } else {
                            $category = 'unknown';
                        }
                    }
                }
            }
        }
    }
    if ($category === 'mutation') {
        $is_mutating = true;
    } else {
        $is_mutating = false;
    }
    if ($normalized === 'unknown') {
        $is_known = false;
    } else {
        $is_known = true;
    }
    $out = new \stdClass;
    $out->raw = $raw;
    $out->normalized = $normalized;
    $out->action_source = $action_source;
    $out->has_action = $has_action;
    $out->category = $category;
    $out->is_mutating = $is_mutating;
    $out->is_known = $is_known;
    return $out;
}
