<?php
namespace App\Support\Community\OfflineSandboxGate;

function runFeature($payload) {
    $source_readonly = false;
    $runtime_readonly = false;
    $network_isolated = false;
    $no_effective_caps = false;
    $no_new_privs = false;
    if ($payload instanceof \stdClass) {
        foreach (get_object_vars($payload) as $k => $v) {
            if ($k === 'source_readonly') {
                if ($v === true) {
                    $source_readonly = true;
                }
            }
            if ($k === 'runtime_readonly') {
                if ($v === true) {
                    $runtime_readonly = true;
                }
            }
            if ($k === 'network_isolated') {
                if ($v === true) {
                    $network_isolated = true;
                }
            }
            if ($k === 'no_effective_caps') {
                if ($v === true) {
                    $no_effective_caps = true;
                }
            }
            if ($k === 'no_new_privs') {
                if ($v === true) {
                    $no_new_privs = true;
                }
            }
        }
    }
    $filesystem_ok = false;
    if ($source_readonly === true) {
        if ($runtime_readonly === true) {
            $filesystem_ok = true;
        }
    }
    $network_ok = false;
    if ($network_isolated === true) {
        $network_ok = true;
    }
    $privilege_ok = false;
    if ($no_effective_caps === true) {
        if ($no_new_privs === true) {
            $privilege_ok = true;
        }
    }
    $missing_count = 0;
    if ($source_readonly === false) {
        $missing_count = $missing_count + 1;
    }
    if ($runtime_readonly === false) {
        $missing_count = $missing_count + 1;
    }
    if ($network_isolated === false) {
        $missing_count = $missing_count + 1;
    }
    if ($no_effective_caps === false) {
        $missing_count = $missing_count + 1;
    }
    if ($no_new_privs === false) {
        $missing_count = $missing_count + 1;
    }
    $ready = false;
    if ($filesystem_ok === true) {
        if ($network_ok === true) {
            if ($privilege_ok === true) {
                $ready = true;
            }
        }
    }
    $gate = 'blocked';
    if ($ready === true) {
        $gate = 'ready';
    }
    $reason = 'missing_privileges';
    if ($ready === true) {
        $reason = 'ok';
    } else {
        if ($filesystem_ok === false) {
            $reason = 'missing_filesystem';
        } else {
            if ($network_ok === false) {
                $reason = 'missing_network';
            }
        }
    }
    $out = new \stdClass;
    $out->source_readonly = $source_readonly;
    $out->runtime_readonly = $runtime_readonly;
    $out->network_isolated = $network_isolated;
    $out->no_effective_caps = $no_effective_caps;
    $out->no_new_privs = $no_new_privs;
    $out->filesystem_ok = $filesystem_ok;
    $out->network_ok = $network_ok;
    $out->privilege_ok = $privilege_ok;
    $out->missing_count = $missing_count;
    $out->ready = $ready;
    $out->gate = $gate;
    $out->reason = $reason;
    return $out;
}
