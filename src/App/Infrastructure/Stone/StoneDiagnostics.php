<?php
declare(strict_types=1);

namespace App\Infrastructure\Stone;

use App\Support\Env;

final class StoneDiagnostics
{
    /**
     * Executa um teste de autenticação (GET /customers) no ambiente informado.
     * @param string $env 'production' ou 'sandbox' (homolog)
     * @return array { status: 'ok'|'unauthorized'|'error', http:int, message:string, errorCode?:string, count?:int, advice?:string, testedEnv:string }
     */
    public static function authCheck(string $env): array
    {
        $env = strtolower($env);
        if (!in_array($env, ['production','sandbox','homolog'], true)) $env = 'production';
        if ($env === 'homolog') $env = 'sandbox';

        $prev = Env::get('STONE_ENV', 'homolog');
        // Força ambiente apenas durante esta chamada
        putenv('STONE_ENV=' . $env);
        $_ENV['STONE_ENV'] = $env;
        $_SERVER['STONE_ENV'] = $env;

        if ($env === 'production') {
            $baseUrl = rtrim(Env::get('STONE_BASE_URL_PRODUCTION', 'https://api.pagar.me/core/v5'), '/');
            $apiKey  = trim(Env::get('STONE_API_KEY_PRODUCTION', ''));
        } else { // sandbox
            $baseUrl = rtrim(Env::get('STONE_BASE_URL_SANDBOX', 'https://api.pagar.me/core/v5'), '/');
            $apiKey  = trim(Env::get('STONE_API_KEY_SANDBOX', ''));
        }

        $status = 'error';
        $httpCode = 0;
        $message = '';
        $errorCode = null;

        if ($apiKey === '') {
            $which = $env === 'production' ? 'STONE_API_KEY_PRODUCTION' : 'STONE_API_KEY_SANDBOX';
            $message = $which . ' não está definida no .env';
            // Restaura e retorna
            self::restoreEnv($prev);
            return [ 'status' => $status, 'http' => $httpCode, 'message' => $message, 'errorCode' => $errorCode, 'advice' => 'Configure a chave secreta (sk_...) para ' . $env . '.', 'testedEnv' => $env ];
        }

        $advice = null;
        if (str_starts_with($apiKey, 'pk_')) {
            $advice = 'A chave parece pública (pk_...). Use a CHAVE SECRETA (sk_...) para autenticação Basic.';
        }

        $url = $baseUrl . '/customers';
        $headers = [
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($apiKey . ':'),
        ];

        $timeout = (int)Env::get('HTTP_TIMEOUT', '10');
        $connectTimeout = (int)Env::get('HTTP_CONNECT_TIMEOUT', '5');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        ]);
        $resp = curl_exec($ch);
        $curlErr = curl_error($ch) ?: null;
        $httpCode = (int)(curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0);
        curl_close($ch);

        // Restaura env anterior
        self::restoreEnv($prev);

        if ($resp === false || $curlErr) {
            return [ 'status' => 'error', 'http' => 0, 'message' => 'Falha cURL: ' . ($curlErr ?: 'desconhecida'), 'errorCode' => null, 'advice' => $advice, 'testedEnv' => $env ];
        }

        $json = json_decode((string)$resp, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            $count = 0;
            if (isset($json['data']) && is_array($json['data'])) $count = count($json['data']);
            elseif (is_array($json) && array_is_list($json)) $count = count($json);
            return [ 'status' => 'ok', 'http' => $httpCode, 'message' => 'Conexão autenticada com sucesso', 'count' => $count, 'advice' => $advice, 'testedEnv' => $env ];
        }

        $errorCode = null; $message = null;
        if (is_array($json)) {
            if (isset($json['errors']) && is_array($json['errors']) && isset($json['errors'][0])) {
                $e0 = $json['errors'][0];
                if (is_array($e0)) {
                    $errorCode = isset($e0['code']) && is_scalar($e0['code']) ? (string)$e0['code'] : null;
                    $message   = isset($e0['message']) && is_scalar($e0['message']) ? (string)$e0['message'] : null;
                } elseif (is_string($e0)) {
                    $errorCode = $e0;
                }
            }
            if (!$errorCode && isset($json['error'])) {
                $er = $json['error'];
                if (is_string($er)) $errorCode = $er; elseif (is_array($er) && isset($er['code'])) $errorCode = (string)$er['code'];
            }
            if (!$message && isset($json['message']) && is_scalar($json['message'])) {
                $message = (string)$json['message'];
            }
        }
        $message = $message ?: 'Falha de autenticação ou permissão';
        $status = ($httpCode === 401 || $httpCode === 403) ? 'unauthorized15' : 'error';
        return [ 'status' => $status, 'http' => $httpCode, 'message' => $message, 'errorCode' => $errorCode, 'advice' => $advice, 'testedEnv' => $env ];
    }

    /**
     * Testa autenticação (GET /customers) para uma conta específica (pagarme_accounts.id),
     * usando o ambiente informado ou o active_env da conta.
     * Retorna o mesmo formato de authCheck.
     */
    public static function authCheckAccount(int $accountId, ?string $env = null): array
    {
        try {
            $acc = \App\Infrastructure\DB\Database::run('SELECT * FROM pagarme_accounts WHERE id=? LIMIT 1', [$accountId])->fetch() ?: [];
        } catch (\Throwable $e) {
            return ['status'=>'error','http'=>0,'message'=>'DB error: '.$e->getMessage(),'testedEnv'=>$env?:''];
        }
        if (!$acc) return ['status'=>'error','http'=>0,'message'=>'Conta não encontrada','testedEnv'=>$env?:''];

        $useEnv = strtolower((string)($env ?: ($acc['active_env'] ?? 'sandbox')));
        if (!in_array($useEnv, ['production','sandbox'], true)) $useEnv = 'sandbox';

        $apiKey = $useEnv === 'production' ? (string)($acc['api_key_production'] ?? '') : (string)($acc['api_key_sandbox'] ?? '');
        $apiKey = preg_replace('/\s+/', '', trim($apiKey)) ?? trim($apiKey);
        $baseUrl = $useEnv === 'production'
            ? rtrim(Env::get('STONE_BASE_URL_PRODUCTION', 'https://api.pagar.me/core/v5'), '/')
            : rtrim(Env::get('STONE_BASE_URL_SANDBOX', 'https://api.pagar.me/core/v5'), '/');
        if ($apiKey === '') {
            $which = $useEnv === 'production' ? 'api_key_production' : 'api_key_sandbox';
            return ['status'=>'error','http'=>0,'message'=>'Chave ausente na conta ('.$which.')','advice'=>'Preencha a chave secreta (sk_...) na conta selecionada','testedEnv'=>$useEnv];
        }
        $advice = null;
        if (str_starts_with($apiKey, 'pk_')) $advice = 'A chave parece pública (pk_...). Use a CHAVE SECRETA (sk_...) para autenticação Basic.';

        $url = $baseUrl . '/customers';
        $headers = [ 'Accept: application/json', 'Authorization: Basic ' . base64_encode($apiKey . ':') ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => (int)Env::get('HTTP_TIMEOUT', '10'),
            CURLOPT_CONNECTTIMEOUT => (int)Env::get('HTTP_CONNECT_TIMEOUT', '5'),
        ]);
        $resp = curl_exec($ch);
        $curlErr = curl_error($ch) ?: null;
        $httpCode = (int)(curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0);
        curl_close($ch);
        if ($resp === false || $curlErr) {
            return ['status'=>'error','http'=>0,'message'=>'Falha cURL: '.($curlErr?:'desconhecida'),'advice'=>$advice,'testedEnv'=>$useEnv];
        }
        $json = json_decode((string)$resp, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            $count = 0; if (isset($json['data']) && is_array($json['data'])) $count = count($json['data']); elseif (is_array($json) && array_is_list($json)) $count = count($json);
            return ['status'=>'ok','http'=>$httpCode,'message'=>'Conexão autenticada com sucesso','count'=>$count,'advice'=>$advice,'testedEnv'=>$useEnv];
        }
        $errorCode = null; $message = null;
        if (is_array($json)) {
            if (isset($json['errors'][0])) {
                $e0 = $json['errors'][0];
                if (is_array($e0)) { $errorCode = isset($e0['code']) ? (string)$e0['code'] : null; $message = isset($e0['message']) ? (string)$e0['message'] : null; }
                elseif (is_string($e0)) { $errorCode = $e0; }
            }
            if (!$errorCode && isset($json['error'])) { $er=$json['error']; if (is_string($er)) $errorCode=$er; elseif (is_array($er)&&isset($er['code'])) $errorCode=(string)$er['code']; }
            if (!$message && isset($json['message']) && is_scalar($json['message'])) $message=(string)$json['message'];
        }
        $message = $message ?: 'Falha de autenticação ou permissão';
        $status = ($httpCode === 401 || $httpCode === 403) ? 'unauthorized15' : 'error';
        return ['status'=>$status,'http'=>$httpCode,'message'=>$message,'errorCode'=>$errorCode,'advice'=>$advice,'testedEnv'=>$useEnv];
    }

    private static function restoreEnv(string $prev): void
    {
        putenv('STONE_ENV=' . $prev);
        $_ENV['STONE_ENV'] = $prev;
        $_SERVER['STONE_ENV'] = $prev;
    }
}
