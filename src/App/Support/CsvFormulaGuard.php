<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Guarda puro contra prefixos de fórmulas em exportações CSV.
 *
 * Função pura: sem configuração, I/O, subprocessos, banco, serviços
 * ou efeitos de estado. Nunca apara, normaliza ou altera o restante
 * do conteúdo; retorna o original ou exatamente um apóstrofo ASCII
 * antes de todos os bytes originais.
 */
final class CsvFormulaGuard
{
    /**
     * @param string $value Valor original.
     * @return string Original preservado ou "'" . $value.
     */
    public static function protect(string $value): string
    {
        if ($value === '') {
            return $value;
        }
        $len = strlen($value);
        $pos = 0;
        while ($pos < $len && $value[$pos] === " ") {
            $pos++;
        }
        if ($pos + 3 <= $len && $value[$pos] === "\xEF" && $value[$pos + 1] === "\xBB" && $value[$pos + 2] === "\xBF") {
            $pos += 3;
        }
        while ($pos < $len && $value[$pos] === " ") {
            $pos++;
        }
        if ($pos >= $len) {
            return $value;
        }
        $c = $value[$pos];
        if ($c === '=' || $c === '+' || $c === '-' || $c === '@' || $c === "\x09" || $c === "\x0D" || $c === "\x0A") {
            return "'" . $value;
        }
        if ($pos + 3 <= $len) {
            $tri = substr($value, $pos, 3);
            if ($tri === "\xEF\xBC\x9D" || $tri === "\xEF\xBC\x8B" || $tri === "\xEF\xBC\x8D" || $tri === "\xEF\xBC\xA0") {
                return "'" . $value;
            }
        }
        return $value;
    }
}
