<?php
declare(strict_types=1);

namespace App\Support\Security;

final class Captcha
{
    private const SESSION_KEY = 'admin_captcha';
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function generate(int $length = 6): string
    {
        $length = max(4, min(8, $length));
        $max = strlen(self::ALPHABET) - 1;
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }
        $_SESSION[self::SESSION_KEY] = [
            'code' => $code,
            'ts' => time(),
        ];
        return $code;
    }

    public static function verify(string $input): bool
    {
        $stored = $_SESSION[self::SESSION_KEY]['code'] ?? null;
        unset($_SESSION[self::SESSION_KEY]);
        if (!is_string($stored) || $stored === '') {
            return false;
        }
        $input = strtoupper(trim($input));
        return hash_equals($stored, $input);
    }

    public static function svg(string $code, string $theme = 'light'): string
    {
        $w = 160;
        $h = 52;
        $isDark = ($theme === 'dark');
        $bg = $isDark ? '#0f172a' : '#f8fafc';
        $text = $isDark ? '#f8fafc' : '#0f172a';
        $noise = $isDark ? '#334155' : '#cbd5e1';

        $lines = [];
        for ($i = 0; $i < 8; $i++) {
            $x1 = random_int(0, $w);
            $y1 = random_int(0, $h);
            $x2 = random_int(0, $w);
            $y2 = random_int(0, $h);
            $lines[] = '<line x1="' . $x1 . '" y1="' . $y1 . '" x2="' . $x2 . '" y2="' . $y2 . '" stroke="' . $noise . '" stroke-opacity="0.35" stroke-width="1" />';
        }

        $dots = [];
        for ($i = 0; $i < 80; $i++) {
            $cx = random_int(0, $w);
            $cy = random_int(0, $h);
            $r = random_int(0, 1);
            $dots[] = '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="' . $noise . '" fill-opacity="0.4" />';
        }

        $chars = str_split($code);
        $letters = [];
        $step = (int)floor(($w - 24) / max(1, count($chars)));
        foreach ($chars as $i => $ch) {
            $x = 14 + ($i * $step) + random_int(-3, 3);
            $y = 34 + random_int(-5, 5);
            $rot = random_int(-12, 12);
            $letters[] = '<text x="' . $x . '" y="' . $y . '" fill="' . $text . '" font-size="26" font-weight="700" font-family="Inter, Arial, sans-serif" transform="rotate(' . $rot . ' ' . $x . ' ' . $y . ')">' . $ch . '</text>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">'
            . '<rect x="0" y="0" width="' . $w . '" height="' . $h . '" rx="8" fill="' . $bg . '"/>'
            . implode('', $lines)
            . implode('', $dots)
            . implode('', $letters)
            . '</svg>';
    }
}
