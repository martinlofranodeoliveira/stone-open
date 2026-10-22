<?php
namespace App\Support\Community\UserRoleTag;

function runFeature($payload) {
    $raw = "";
    $roleSource = "none";
    $hasRole = false;
    if ($payload instanceof \stdClass) {
        if (isset($payload->role) && is_string($payload->role)) {
            $raw = $payload->role;
            $roleSource = "role";
            $hasRole = true;
        } elseif (isset($payload->papel) && is_string($payload->papel)) {
            $raw = $payload->papel;
            $roleSource = "papel";
            $hasRole = true;
        } elseif (isset($payload->user_role) && is_string($payload->user_role)) {
            $raw = $payload->user_role;
            $roleSource = "user_role";
            $hasRole = true;
        }
    }
    $normalized = "unknown";
    if ($raw === "admin" || $raw === "administrador") {
        $normalized = "admin";
    } elseif ($raw === "manager" || $raw === "gerente") {
        $normalized = "manager";
    } elseif ($raw === "operator" || $raw === "operador") {
        $normalized = "operator";
    } elseif ($raw === "viewer" || $raw === "leitor" || $raw === "visualizador") {
        $normalized = "viewer";
    }
    $category = "unknown";
    if ($normalized === "admin") {
        $category = "full";
    } elseif ($normalized === "manager") {
        $category = "elevated";
    } elseif ($normalized === "operator") {
        $category = "operational";
    } elseif ($normalized === "viewer") {
        $category = "readonly";
    }
    $isPrivileged = false;
    if ($normalized === "admin" || $normalized === "manager") {
        $isPrivileged = true;
    }
    $isKnown = false;
    if ($normalized !== "unknown") {
        $isKnown = true;
    }
    $out = new \stdClass;
    $out->raw = $raw;
    $out->normalized = $normalized;
    $out->role_source = $roleSource;
    $out->has_role = $hasRole;
    $out->category = $category;
    $out->is_privileged = $isPrivileged;
    $out->is_known = $isKnown;
    return $out;
}
