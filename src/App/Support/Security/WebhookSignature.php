<?php
declare(strict_types=1);

namespace App\Support\Security;

final class WebhookSignature
{
    public static function verify(string $rawBody, string $secret, ?string $provided): bool
    {
        if ($secret === '' || $provided === null || $provided === '') return false;
        // Accept formats: "sha256=..." or raw hex
        $sig = $provided;
        if (str_contains($sig, '=')) {
            [$algo, $val] = explode('=', $sig, 2);
            if ($algo !== 'sha256') return false;
            $sig = $val;
        }
        $calc = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($calc, $sig);
    }
}

