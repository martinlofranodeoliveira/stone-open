<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Redacao sintetica de contexto estruturado para logs.
 *
 * Funcao pura: nao acessa banco, disco, rede, contas ou APIs de pagamento.
 * Gera copia redigida sem alterar a entrada.
 */
final class SafeLogContext
{
    private const REDACTED = '[REDACTED]';

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'authorization',
        'api_key',
        'token',
        'secret',
        'card_number',
        'cvv',
    ];

    private const MAX_DEPTH = 8;

    /**
     * @param array $context Contexto estruturado.
     * @return array Copia redigida.
     * @throws \InvalidArgumentException Mensagem 'Invalid log context' para objetos ou profundidade maior que 8.
     */
    public static function redact(array $context): array
    {
        return self::redactLevel($context, 1);
    }

    /**
     * @param array $data Nivel atual.
     * @param int $depth Profundidade atual (topo e 1).
     */
    private static function redactLevel(array $data, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('Invalid log context');
        }
        $safe = [];
        foreach ($data as $key => $value) {
            $lower = strtolower((string)$key);
            if (in_array($lower, self::SENSITIVE_KEYS, true)) {
                $safe[$key] = self::REDACTED;
                continue;
            }
            if (is_array($value)) {
                $safe[$key] = self::redactLevel($value, $depth + 1);
            } elseif (is_object($value)) {
                throw new \InvalidArgumentException('Invalid log context');
            } else {
                $safe[$key] = $value;
            }
        }
        return $safe;
    }
}
