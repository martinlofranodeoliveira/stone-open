<?php
declare(strict_types=1);

namespace App\Support\Security;

final class Totp
{
    public static function verify(string $base32Secret, string $code, int $window = 1, int $period = 30, string $algo = 'sha1'): bool
    {
        $secret = self::base32Decode($base32Secret);
        if ($secret === '') return false;
        $time = time();
        for ($i = -$window; $i <= $window; $i++) {
            $t = (int) floor(($time / $period)) + $i;
            $calc = self::hotp($secret, $t, 6, $algo);
            if (hash_equals($calc, str_pad($code, 6, '0', STR_PAD_LEFT))) return true;
        }
        return false;
    }

    public static function provisioningUri(string $label, string $issuer, string $base32Secret, int $period = 30): string
    {
        $params = http_build_query([
            'secret' => $base32Secret,
            'issuer' => $issuer,
            'period' => $period,
        ]);
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $label) . '?' . $params;
    }

    public static function generateBase32Secret(int $bytes = 20): string
    {
        $raw = random_bytes($bytes);
        return self::base32Encode($raw);
    }

    private static function hotp(string $key, int $counter, int $digits = 6, string $algo = 'sha1'): string
    {
        $binCounter = pack('N*', 0) . pack('N*', $counter); // 64-bit
        $hash = hash_hmac($algo, $binCounter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = (unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF);
        $code = $truncated % (10 ** $digits);
        return str_pad((string)$code, $digits, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $s): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $s = strtoupper(preg_replace('/[^A-Z2-7=]/', '', $s));
        $buffer = 0; $bitsLeft = 0; $result = '';
        for ($i = 0, $len = strlen($s); $i < $len; $i++) {
            $ch = $s[$i];
            if ($ch === '=') break;
            $val = strpos($alphabet, $ch);
            if ($val === false) continue;
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $result;
    }

    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = 0; $value = 0; $output = '';
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $value = ($value << 8) | ord($data[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $output .= $alphabet[($value >> ($bits - 5)) & 31];
                $bits -= 5;
            }
        }
        if ($bits > 0) {
            $output .= $alphabet[($value << (5 - $bits)) & 31];
        }
        return $output;
    }
}

