<?php
require __DIR__ . '/../src/App/Support/Community/admin_log_action_tag.php';
$failures = 0;
function t_check($cond, $msg) {
    global $failures;
    if ($cond) {
        echo "PASS: " . $msg . "\n";
    } else {
        $failures = $failures + 1;
        echo "FAIL: " . $msg . "\n";
    }
}
function t_out($payload) {
    return \App\Support\Community\AdminLogActionTag\runFeature($payload);
}
function t_eq($out, $raw, $normalized, $source, $has, $cat, $mut, $known) {
    if (!($out instanceof \stdClass)) { return false; }
    if ($out->raw !== $raw) { return false; }
    if ($out->normalized !== $normalized) { return false; }
    if ($out->action_source !== $source) { return false; }
    if ($out->has_action !== $has) { return false; }
    if ($out->category !== $cat) { return false; }
    if ($out->is_mutating !== $mut) { return false; }
    if ($out->is_known !== $known) { return false; }
    return true;
}
$p = new \stdClass;
$o = t_out($p);
t_check(t_eq($o, '', 'unknown', 'none', false, 'unknown', false, false), 'empty object defaults');
$o = t_out(null);
t_check(t_eq($o, '', 'unknown', 'none', false, 'unknown', false, false), 'null payload defaults');
$o = t_out('entrar');
t_check(t_eq($o, '', 'unknown', 'none', false, 'unknown', false, false), 'string payload defaults');
$o = t_out(123);
t_check(t_eq($o, '', 'unknown', 'none', false, 'unknown', false, false), 'int payload defaults');
$o = t_out(array('action' => 'entrar'));
t_check(t_eq($o, '', 'unknown', 'none', false, 'unknown', false, false), 'array payload defaults');
$p = new \stdClass;
$p->action = 'entrar';
$o = t_out($p);
t_check(t_eq($o, 'entrar', 'login', 'action', true, 'auth', false, true), 'action entrar maps to login auth');
$p = new \stdClass;
$p->acao = 'excluir';
$o = t_out($p);
t_check(t_eq($o, 'excluir', 'delete', 'acao', true, 'mutation', true, true), 'acao excluir maps to delete mutation');
$p = new \stdClass;
$p->action = 'criar';
$o = t_out($p);
t_check(t_eq($o, 'criar', 'create', 'action', true, 'mutation', true, true), 'action criar maps to create mutation');
$p = new \stdClass;
$p->acao = 'sair';
$o = t_out($p);
t_check(t_eq($o, 'sair', 'logout', 'acao', true, 'auth', false, true), 'acao sair maps to logout auth');
$p = new \stdClass;
$p->action = 'Login';
$o = t_out($p);
t_check(t_eq($o, 'Login', 'unknown', 'action', true, 'unknown', false, false), 'case variant Login is unknown');
$p = new \stdClass;
$p->action = 'CREATE';
$o = t_out($p);
t_check(t_eq($o, 'CREATE', 'unknown', 'action', true, 'unknown', false, false), 'case variant CREATE is unknown');
$p = new \stdClass;
$p->action = 'bogus-thing';
$o = t_out($p);
t_check(t_eq($o, 'bogus-thing', 'unknown', 'action', true, 'unknown', false, false), 'unknown value stays unknown');
$p = new \stdClass;
$p->action = 'view';
$p->acao = 'excluir';
$p->log_action = 'login';
$p->extra = 'keep-me';
$before = json_encode($p);
$o = t_out($p);
$after = json_encode($p);
t_check(t_eq($o, 'view', 'view', 'action', true, 'read', false, true), 'priority prefers action');
t_check($before === $after, 'extra fields ignored without mutation');
$p = new \stdClass;
$p->acao = 'criar';
$p->log_action = 'login';
$o = t_out($p);
t_check(t_eq($o, 'criar', 'create', 'acao', true, 'mutation', true, true), 'priority prefers acao over log_action');
$p = new \stdClass;
$p->log_action = 'visualizar';
$o = t_out($p);
t_check(t_eq($o, 'visualizar', 'view', 'log_action', true, 'read', false, true), 'log_action visualizar maps to view read');
$p = new \stdClass;
$p->action = 123;
$p->acao = 'sair';
$o = t_out($p);
t_check(t_eq($o, 'sair', 'logout', 'acao', true, 'auth', false, true), 'non-string action falls through to acao');
$p = new \stdClass;
$p->action = 'sair';
$first = json_encode(t_out($p));
$second = json_encode(t_out($p));
t_check($first === $second, 'deterministic identical output');
$p = new \stdClass;
$p->action = 'login';
$o = t_out($p);
t_check(t_eq($o, 'login', 'login', 'action', true, 'auth', false, true), 'action login maps to login');
$p = new \stdClass;
$p->log_action = 'atualizar';
$o = t_out($p);
t_check(t_eq($o, 'atualizar', 'update', 'log_action', true, 'mutation', true, true), 'log_action atualizar maps to update');
if ($failures > 0) {
    echo $failures . " failure(s)\n";
    exit(1);
}
echo "ALL OK\n";
