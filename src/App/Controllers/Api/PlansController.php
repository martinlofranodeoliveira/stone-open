<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\Stone\StoneAccount;

final class PlansController
{
    private static function resolveEnv(array $site): string
    {
        $env = 'sandbox';
        try {
            $acc = StoneAccount::resolveForSite($site);
            $env = strtolower((string)($acc['active_env'] ?? 'sandbox'));
        } catch (\Throwable $e) {
            $env = strtolower((string)($site['active_env'] ?? 'sandbox'));
        }
        if ($env === 'homolog') $env = 'sandbox';
        return in_array($env, ['sandbox','production'], true) ? $env : 'sandbox';
    }

    // GET /api/v1/plans
    public function index(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['status' => 'error', 'message' => 'Não autorizado', 'status_code' => 'unauthorized', 'http_status' => 401], 401);
        $env = self::resolveEnv($site);
        $status = (string)($req->query['status'] ?? '');
        $q = trim((string)($req->query['q'] ?? ''));
        $limit = min(200, max(1, (int)($req->query['limit'] ?? 100)));
        $offset = max(0, (int)($req->query['offset'] ?? 0));

        $sql = 'SELECT * FROM plans WHERE site_id = ? AND env = ? AND status = "active" AND stone_plan_id IS NOT NULL';
        $params = [$site['id'], $env];
        if ($status !== '') { $sql .= ' AND status = ?'; $params[] = $status; }
        if ($q !== '') { $sql .= ' AND (name LIKE ? OR code LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        $sql .= ' ORDER BY id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
        $rows = Database::run($sql, $params)->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int)$r['id'],
                'nome' => $r['name'],
                'codigo' => $r['code'],
                'descricao' => $r['description'] ?? null,
                'valor_centavos' => (int)$r['amount_cents'],
                'moeda' => $r['currency'],
                'intervalo' => $r['interval_unit'],
                'intervalo_qtd' => (int)$r['interval_count'],
                'ciclos' => isset($r['billing_cycles']) ? (int)$r['billing_cycles'] : 1,
                'status' => $r['status'],
                'id_plano_stone' => $r['stone_plan_id'] ?? null,
            ];
        }
        return Response::json(['status' => 'success', 'message' => 'OK', 'status_code' => 'ok', 'data' => $out]);
    }
    // POST /api/v1/plans
    public function create(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['status' => 'error', 'message' => 'Não autorizado', 'status_code' => 'unauthorized', 'http_status' => 401], 401);
        $env = self::resolveEnv($site);
        $in = $req->json ?? [];
        if (!is_array($in)) return Response::json(['status' => 'error', 'message' => 'JSON inválido', 'status_code' => 'invalid_json', 'http_status' => 400], 400);

        // Validar campos (PT -> EN) e montar payload para a Stone
        $v = \App\Support\Validator::plan($in);
        if (!$v['ok']) {
            $msg = 'Corrija os campos obrigatórios';
            $errs = $v['errors_pt'] ?? ($v['errors_en'] ?? ($v['errors'] ?? []));
            if (is_array($errs) && !empty($errs)) {
                $first = reset($errs);
                if (is_array($first)) { $first = reset($first); }
                if (is_string($first) && $first !== '') $msg = $first;
            } elseif (!empty($v['required'])) {
                $msg = 'Campos obrigatórios: ' . implode(', ', (array)$v['required']);
            }
            return Response::json([
                'status' => 'error',
                'message' => $msg,
                'status_code' => 'validation_error',
                'http_status' => 422,
                'required' => $v['required'],
                'errors' => $v['errors_pt'] ?? [],
                'errors_en' => $v['errors_en'] ?? ($v['errors'] ?? []),
                'errors_pt' => $v['errors_pt'] ?? [],
            ], 422);
        }

        $payload = $v['payload'];
        // Código opcional para plano local
        $code = (string)($in['codigo'] ?? ($in['code'] ?? ''));
        if ($code === '') {
            $code = 'pln_' . substr(bin2hex(random_bytes(6)), 0, 12);
        }

        try {
            $client = \App\Infrastructure\Stone\StoneClient::forSite($site);
            $resp = $client->createPlan($payload);
            $stonePlanId = (string)($resp['id'] ?? '');
            if ($stonePlanId === '') {
                return Response::json(['status' => 'error', 'message' => 'Resposta sem id do plano', 'status_code' => 'provider_error', 'http_status' => 500], 500);
            }

            // Persistir plano local
            $name = (string)$payload['name'];
            $desc = (string)($payload['description'] ?? '');
            $currency = (string)($payload['currency'] ?? 'BRL');
            $interval = (string)$payload['interval'];
            $intervalCount = (int)$payload['interval_count'];
            // Somatório dos itens (price * quantity); cycles é one-off, mas mantemos simples
            $amount = 0;
            if (isset($payload['items']) && is_array($payload['items'])) {
                foreach ($payload['items'] as $it) {
                    $qty = (int)($it['quantity'] ?? 1);
                    $price = (int)($it['pricing_scheme']['price'] ?? 0);
                    $amount += max(0, $price) * max(1, $qty);
                }
            }

            $billCycles = (int)($in['billing_cycles'] ?? ($in['ciclos'] ?? 1)); if ($billCycles <= 0) $billCycles = 1;
            Database::run('INSERT INTO plans (site_id, name, code, env, description, amount_cents, currency, interval_unit, interval_count, billing_cycles, stone_plan_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE env=VALUES(env), description=VALUES(description), amount_cents=VALUES(amount_cents), currency=VALUES(currency), interval_unit=VALUES(interval_unit), interval_count=VALUES(interval_count), billing_cycles=VALUES(billing_cycles), stone_plan_id=VALUES(stone_plan_id), status=VALUES(status), updated_at=NOW()', [
                $site['id'], $name, $code, $env, $desc ?: null, $amount, $currency, $interval, $intervalCount, $billCycles, $stonePlanId, ($resp['status'] ?? 'active')
            ]);
            $row = Database::run('SELECT * FROM plans WHERE site_id = ? AND code = ? AND env = ? LIMIT 1', [$site['id'], $code, $env])->fetch();

            return Response::json([
                'status' => 'success',
                'message' => 'Plano criado',
                'status_code' => 'created',
                'plano' => [
                    'id' => (int)($row['id'] ?? 0),
                    'id_plano_stone' => $stonePlanId,
                    'nome' => $name,
                    'codigo' => $code,
                    'descricao' => $desc ?: null,
                    'valor_centavos' => $amount,
                    'moeda' => $currency,
                    'intervalo' => $interval,
                    'intervalo_qtd' => $intervalCount,
                    'ciclos' => (int)($row['billing_cycles'] ?? 1),
                    'status' => $resp['status'] ?? 'active',
                ],
            ], 201);
        } catch (\App\Infrastructure\Stone\StoneApiException $e) {
            $errs = $e->getErrors();
            $ptMsg = \App\Support\I18n::ptHttpMessage($e->getStatus(), 'Erro ao criar plano');
            return Response::json([
                'status' => 'error',
                'message' => $ptMsg,
                'status_code' => $e->getErrorCode() ?: (string)$e->getStatus(),
                'http_status' => $e->getStatus(),
                'provider_message' => $e->getErrorMessage(),
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getErrorMessage(),
                'errors' => $errs,
                'errors_en' => $errs,
                'errors_pt' => $errs,
            ], 422);
        } catch (\Throwable $e) {
            return Response::json(['status' => 'error', 'message' => $e->getMessage(), 'status_code' => 'internal_error', 'http_status' => 500], 500);
        }
    }
}
