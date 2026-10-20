<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\View\View;
use App\Support\Env;

final class AdminDiagnosticsController
{
    public function stoneAuth(Request $req): Response
    {
        \App\Infrastructure\Stone\StoneAccount::ensureSchema();
        $result = null;
        $ran = isset($req->query['run']) && (string)$req->query['run'] === '1';
        $env = strtolower((string)($req->query['env'] ?? ''));
        if ($env === 'homolog') $env = 'sandbox';
        if (!in_array($env, ['production','sandbox'], true)) $env = '';
        $accId = isset($req->query['account_id']) ? (int)$req->query['account_id'] : 0;
        if ($ran && $accId > 0) {
            $result = \App\Infrastructure\Stone\StoneDiagnostics::authCheckAccount($accId, $env ?: null);
        }
        $accounts = \App\Infrastructure\DB\Database::run('SELECT id, name, active_env FROM pagarme_accounts ORDER BY name')->fetchAll();
        $html = View::render('admin/diagnostics_stone', [
            'prefix' => $this->prefix(),
            'accounts' => $accounts,
            'envCurrent' => Env::get('STONE_ENV', 'homolog'),
            'ran' => $ran,
            'result' => $result,
            'testedEnv' => $result['testedEnv'] ?? $env,
            'selectedAccountId' => $accId,
        ]);
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    // runAuthCheck agora está em StoneDiagnostics

    private function prefix(): string
    {
        return rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');
    }
}
