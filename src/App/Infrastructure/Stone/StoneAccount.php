<?php
declare(strict_types=1);

namespace App\Infrastructure\Stone;

use App\Infrastructure\DB\Database;
use App\Support\Env;

final class StoneAccount
{
    public static function ensureSchema(): void
    {
        try {
            $row = Database::run('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "pagarme_accounts"')->fetch();
            if (!$row) {
                Database::run('CREATE TABLE IF NOT EXISTS pagarme_accounts (
                  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  name VARCHAR(150) NOT NULL,
                  stone_account_uid VARCHAR(64) NULL,
                  active_env ENUM("sandbox","production") NOT NULL DEFAULT "sandbox",
                  api_key_sandbox VARCHAR(255) NULL,
                  api_key_production VARCHAR(255) NULL,
                  public_key_sandbox VARCHAR(255) NULL,
                  public_key_production VARCHAR(255) NULL,
                  webhook_secret_sandbox VARCHAR(255) NULL,
                  webhook_secret_production VARCHAR(255) NULL,
                  status ENUM("active","disabled") NOT NULL DEFAULT "active",
                  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            }
        } catch (\Throwable $e) { /* ignore */ }
        try {
            $col = Database::run('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "sites" AND COLUMN_NAME = "pagarme_account_id"')->fetch();
            if (!$col) {
                Database::run('ALTER TABLE sites ADD COLUMN pagarme_account_id BIGINT UNSIGNED NULL AFTER stone_account_id');
            }
        } catch (\Throwable $e) { /* ignore */ }
    }

    public static function resolveForSite(array $site): array
    {
        self::ensureSchema();
        $accId = (int)($site['pagarme_account_id'] ?? 0);
        if ($accId > 0) {
            $acc = Database::run('SELECT * FROM pagarme_accounts WHERE id = ? AND status = "active" LIMIT 1', [$accId])->fetch() ?: [];
            if (!$acc) {
                throw new StoneAccountRequiredException('Conta Pagar.me inválida ou desativada');
            }
            return self::validateAccount($acc);
        }
        // Fallback: usar chaves do .env (single-account)
        return self::validateAccount(self::envAccount());
    }

    /**
     * Resolve conta a partir do API token do site (Authorization: Bearer ...)
     */
    public static function resolveForApiToken(string $apiToken): array
    {
        self::ensureSchema();
        $apiToken = trim($apiToken);
        if ($apiToken === '') {
            throw new StoneAccountRequiredException('Token de API ausente');
        }
        $site = Database::run('SELECT * FROM sites WHERE api_token = ? AND status = "active" LIMIT 1', [$apiToken])->fetch() ?: [];
        if (!$site) {
            throw new StoneAccountRequiredException('Conta Pagar.me não encontrada para este token');
        }
        return self::resolveForSite($site);
    }

    private static function validateAccount(array $acc): array
    {
        // sanity: ensure keys exist for selected env
        $env = (string)($acc['active_env'] ?? 'sandbox');
        $api = $env === 'production' ? (string)($acc['api_key_production'] ?? '') : (string)($acc['api_key_sandbox'] ?? '');
        $pub = $env === 'production' ? (string)($acc['public_key_production'] ?? '') : (string)($acc['public_key_sandbox'] ?? '');
        if ($api === '' || $pub === '') {
            throw new StoneAccountRequiredException('Chaves API/Public key ausentes para o ambiente ativo da conta');
        }
        // Heurísticas para ajudar a evitar 401 causados por troca de ambiente/chaves
        if (str_starts_with($api, 'pk_')) {
            throw new StoneAccountRequiredException('Chave pública detectada no campo de chave secreta. Use a CHAVE SECRETA (sk_...).');
        }
        $apiLower = strtolower($api);
        if ($env === 'production' && str_contains($apiLower, 'test')) {
            throw new StoneAccountRequiredException('Chave de teste detectada enquanto o ambiente ativo é produção. Altere o active_env para sandbox ou informe a sk_live.');
        }
        if ($env === 'sandbox' && (str_contains($apiLower, 'live') || str_contains($apiLower, 'prod'))) {
            throw new StoneAccountRequiredException('Chave de produção detectada enquanto o ambiente ativo é sandbox. Altere o active_env para produção ou informe a sk_test.');
        }
        return $acc;
    }

    private static function envAccount(): array
    {
        $env = strtolower((string)Env::get('STONE_ENV', 'homolog'));
        if ($env === 'homolog') $env = 'sandbox';
        if (!in_array($env, ['sandbox','production'], true)) $env = 'sandbox';
        return [
            'id' => 0,
            'name' => 'env',
            'stone_account_uid' => null,
            'active_env' => $env,
            'api_key_sandbox' => (string)Env::get('STONE_API_KEY_SANDBOX', ''),
            'api_key_production' => (string)Env::get('STONE_API_KEY_PRODUCTION', ''),
            'public_key_sandbox' => (string)Env::get('STONE_PUBLIC_KEY_SANDBOX', ''),
            'public_key_production' => (string)Env::get('STONE_PUBLIC_KEY_PRODUCTION', ''),
            'webhook_secret_sandbox' => (string)Env::get('STONE_WEBHOOK_SECRET_SANDBOX', ''),
            'webhook_secret_production' => (string)Env::get('STONE_WEBHOOK_SECRET_PRODUCTION', ''),
            'status' => 'active',
        ];
    }

    public static function webhookSecretForAccount(array $acc): ?string
    {
        $env = (string)($acc['active_env'] ?? 'sandbox');
        if ($env === 'production') return (string)($acc['webhook_secret_production'] ?? '') ?: null;
        return (string)($acc['webhook_secret_sandbox'] ?? '') ?: null;
    }
}

class StoneAccountException extends \RuntimeException {}
class StoneAccountRequiredException extends StoneAccountException {}
