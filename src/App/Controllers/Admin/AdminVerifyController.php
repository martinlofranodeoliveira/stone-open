<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\View\View;
use App\Infrastructure\DB\Database;

final class AdminVerifyController
{
    private function prefix(): string
    {
        return rtrim(\App\Support\Env::get('ADMIN_PREFIX', '/_admin'), '/');
    }

    // GET {prefix}/verify
    public function index(Request $req): Response
    {
        // Lista de contas para escolher a origem das chaves
        $accounts = Database::run('SELECT id, name, active_env FROM pagarme_accounts WHERE status="active" ORDER BY name')->fetchAll();
        $html = View::render('admin/verify_index', [ 'prefix' => $this->prefix(), 'accounts' => $accounts ]);
        return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
    }

    // GET {prefix}/verify/subscriptions
    public function subscriptions(Request $req): Response
    {
        $q = trim((string)($req->query['q'] ?? '')); // por id local, id stone ou referência
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $accId = isset($req->query['account_id']) ? (int)$req->query['account_id'] : null;

        $local = null; $remote = null; $err = null;
        if ($q !== '') {
            // Tenta achar assinatura localmente
            if ($siteId) {
                $local = Database::run('SELECT * FROM subscriptions WHERE site_id = ? AND (id = ? OR stone_subscription_id = ?)', [$siteId, (int)$q, $q])->fetch();
            } else {
                $local = Database::run('SELECT * FROM subscriptions WHERE id = ? OR stone_subscription_id = ? LIMIT 1', [(int)$q, $q])->fetch();
            }
            // Resolve conta a partir do site se não enviada
            if (!$accId && $local) {
                $site = Database::run('SELECT * FROM sites WHERE id = ? LIMIT 1', [$local['site_id']])->fetch();
                $accId = (int)($site['pagarme_account_id'] ?? 0) ?: null;
            }
            try {
                if ($accId) {
                    $acc = Database::run('SELECT * FROM pagarme_accounts WHERE id=? LIMIT 1', [$accId])->fetch() ?: [];
                    if ($acc) {
                        $client = new \App\Infrastructure\Stone\StoneClient($acc);
                        // ID remoto: direto; senão, tenta via order/payment ref
                        $sid = (string)($local['stone_subscription_id'] ?? $q);
                        if ($sid !== '') {
                            $url = rtrim((string)\App\Support\Env::get($acc['active_env']==='production'?'STONE_BASE_URL_PRODUCTION':'STONE_BASE_URL_SANDBOX','https://api.pagar.me/core/v5'),'/') . '/subscriptions/' . rawurlencode($sid);
                            $remote = $this->get($client, $url);
                        }
                    }
                }
            } catch (\Throwable $e) { $err = $e->getMessage(); }
        }
        $html = View::render('admin/verify_subscriptions', [ 'prefix'=>$this->prefix(), 'q'=>$q, 'local'=>$local, 'remote'=>$remote, 'error'=>$err ]);
        return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
    }

    // GET {prefix}/verify/payments
    public function payments(Request $req): Response
    {
        $ref = trim((string)($req->query['reference'] ?? ($req->query['ref'] ?? '')));
        $stoneId = trim((string)($req->query['stone_id'] ?? ''));
        $siteId = isset($req->query['site_id']) ? (int)$req->query['site_id'] : null;
        $accId = isset($req->query['account_id']) ? (int)$req->query['account_id'] : null;

        $local = null; $remote = null; $remoteOrder = null; $err = null;
        if ($ref !== '' || $stoneId !== '') {
            if ($siteId && $ref !== '') {
                $local = Database::run('SELECT * FROM payments WHERE site_id = ? AND client_reference_id = ? ORDER BY id DESC LIMIT 1', [$siteId, $ref])->fetch();
            } elseif ($ref !== '') {
                $local = Database::run('SELECT * FROM payments WHERE client_reference_id = ? ORDER BY id DESC LIMIT 1', [$ref])->fetch();
            }
            if (!$local && $stoneId !== '') {
                $local = Database::run('SELECT * FROM payments WHERE stone_payment_id = ? LIMIT 1', [$stoneId])->fetch();
            }
            if (!$accId && $local) {
                $site = Database::run('SELECT * FROM sites WHERE id = ? LIMIT 1', [$local['site_id']])->fetch();
                $accId = (int)($site['pagarme_account_id'] ?? 0) ?: null;
            }
            try {
                if ($accId) {
                    $acc = Database::run('SELECT * FROM pagarme_accounts WHERE id=? LIMIT 1', [$accId])->fetch() ?: [];
                    if ($acc) {
                        $client = new \App\Infrastructure\Stone\StoneClient($acc);
                        // Tenta buscar como charge, senao order
                        $sid = $stoneId ?: (string)($local['stone_payment_id'] ?? '');
                        if ($sid !== '') {
                            $base = rtrim((string)\App\Support\Env::get($acc['active_env']==='production'?'STONE_BASE_URL_PRODUCTION':'STONE_BASE_URL_SANDBOX','https://api.pagar.me/core/v5'),'/');
                            $urlCh = $base . '/charges/' . rawurlencode($sid);
                            $remote = $this->get($client, $urlCh, true);
                            if (!$remote) {
                                $urlOr = $base . '/orders/' . rawurlencode($sid);
                                $remoteOrder = $this->get($client, $urlOr, true);
                            }
                        }
                        // Se somente referência disponível, tentar via order code
                        if (!$remote && !$remoteOrder && ($ref !== '' || ($local && $local['client_reference_id']))) {
                            $code = $ref ?: (string)$local['client_reference_id'];
                            $base = rtrim((string)\App\Support\Env::get($acc['active_env']==='production'?'STONE_BASE_URL_PRODUCTION':'STONE_BASE_URL_SANDBOX','https://api.pagar.me/core/v5'),'/');
                            $url = $base . '/orders?code=' . rawurlencode($code);
                            $list = $this->get($client, $url, true);
                            if (is_array($list) && isset($list['data'][0])) $remoteOrder = $list['data'][0];
                            elseif (is_array($list) && isset($list[0])) $remoteOrder = $list[0];
                        }
                    }
                }
            } catch (\Throwable $e) { $err = $e->getMessage(); }
        }
        $html = View::render('admin/verify_payments', [ 'prefix'=>$this->prefix(), 'ref'=>$ref, 'stoneId'=>$stoneId, 'local'=>$local, 'remote'=>$remote, 'remoteOrder'=>$remoteOrder, 'error'=>$err ]);
        return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
    }

    // GET {prefix}/verify/invoices
    public function invoices(Request $req): Response
    {
        $subId = trim((string)($req->query['subscription_id'] ?? ($req->query['sid'] ?? '')));
        $accId = isset($req->query['account_id']) ? (int)$req->query['account_id'] : null;
        $remote = null; $err = null;
        if ($subId !== '' && $accId) {
            try {
                $acc = Database::run('SELECT * FROM pagarme_accounts WHERE id=? LIMIT 1', [$accId])->fetch() ?: [];
                if ($acc) {
                    $client = new \App\Infrastructure\Stone\StoneClient($acc);
                    $base = rtrim((string)\App\Support\Env::get($acc['active_env']==='production'?'STONE_BASE_URL_PRODUCTION':'STONE_BASE_URL_SANDBOX','https://api.pagar.me/core/v5'),'/');
                    $url = $base . '/subscriptions/' . rawurlencode($subId) . '/invoices';
                    $remote = $this->get($client, $url, true);
                }
            } catch (\Throwable $e) { $err = $e->getMessage(); }
        }
        $html = View::render('admin/verify_invoices', [ 'prefix'=>$this->prefix(), 'sid'=>$subId, 'remote'=>$remote, 'error'=>$err ]);
        return new Response(200, ['Content-Type'=>'text/html; charset=utf-8'], $html);
    }

    private function get(\App\Infrastructure\Stone\StoneClient $client, string $url, bool $nullOn404 = false): ?array
    {
        $hdrs = [ 'Accept: application/json', $this->authHeaderFrom($client) ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $hdrs,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        $http = (int)(curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0);
        curl_close($ch);
        if ($body === false) return null;
        if ($nullOn404 && $http === 404) return null;
        $json = json_decode((string)$body, true);
        return is_array($json) ? $json : null;
    }

    private function authHeaderFrom(\App\Infrastructure\Stone\StoneClient $client): string
    {
        $ref = new \ReflectionClass($client);
        $m = $ref->getMethod('authHeader'); $m->setAccessible(true);
        /** @var string */
        $hdr = $m->invoke($client);
        return $hdr;
    }
}
