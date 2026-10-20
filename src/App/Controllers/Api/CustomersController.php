<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Infrastructure\Http\{Request, Response};
use App\Infrastructure\DB\Database;
use App\Infrastructure\Stone\StoneApiException;
use App\Infrastructure\Stone\StoneAccount;

final class CustomersController
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

    // GET /api/v1/customers/search?email=&document=&stone_customer_id=
    public function search(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['status' => 'error', 'message' => 'Não autorizado', 'status_code' => 'unauthorized', 'http_status' => 401], 401);
        $env = self::resolveEnv($site);
        $email = (string)($req->query['email'] ?? '');
        $document = (string)($req->query['document'] ?? ($req->query['cpf'] ?? ''));
        $stoneCid = (string)($req->query['stone_customer_id'] ?? '');
        $sql = 'SELECT * FROM customers WHERE site_id = ? AND env = ?';
        $params = [$site['id'], $env];
        if ($email !== '') { $sql .= ' AND email = ?'; $params[] = $email; }
        if ($document !== '') { $sql .= ' AND document = ?'; $params[] = $document; }
        if ($stoneCid !== '') { $sql .= ' AND stone_customer_id = ?'; $params[] = $stoneCid; }
        $sql .= ' ORDER BY id DESC LIMIT 100';
        $rows = Database::run($sql, $params)->fetchAll();
        return Response::json(['status' => 'success', 'message' => 'OK', 'status_code' => 'ok', 'data' => $rows]);
    }

    // POST /api/v1/customers/upsert
    public function upsert(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['status' => 'error', 'message' => 'Não autorizado', 'status_code' => 'unauthorized', 'http_status' => 401], 401);
        $in = $req->json ?? [];
        if (!is_array($in)) return Response::json(['status' => 'error', 'message' => 'JSON inválido', 'status_code' => 'invalid_json', 'http_status' => 400], 400);

        // Normalizações para compatibilidade e mensagens melhores
        if (isset($in['document'])) {
            $in['document'] = preg_replace('/\D+/', '', (string)$in['document']);
            if (empty($in['type'])) {
                $len = strlen($in['document']);
                if ($len === 11) $in['type'] = 'individual';
                elseif ($len === 14) $in['type'] = 'company';
            }
        }
        if (isset($in['address']) && is_array($in['address']) && isset($in['address']['country'])) {
            $c = trim((string)$in['address']['country']);
            $cUp = strtoupper($c);
            if ($cUp !== '' && strlen($cUp) !== 2) {
                if (in_array($cUp, ['BRASIL','BRAZIL','BRASÍLIA','BR - BRAZIL'], true)) {
                    $in['address']['country'] = 'BR';
                } else {
                    $in['address']['country'] = substr($cUp, 0, 2);
                }
            } else {
                $in['address']['country'] = $cUp;
            }
        }
        if (!isset($in['phones']['mobile_phone']) && !empty($in['phone'])) {
            $digits = preg_replace('/\D+/', '', (string)$in['phone']);
            if (strlen($digits) >= 10) {
                $in['phones']['mobile_phone'] = [
                    'country_code' => substr($digits, 0, 2),
                    'area_code' => substr($digits, 2, 2),
                    'number' => substr($digits, 4),
                ];
            }
        }

        $stoneId = (string)($in['stone_customer_id'] ?? '');
        $env = 'sandbox';
        try {
            $acc = \App\Infrastructure\Stone\StoneAccount::resolveForSite($site);
            $env = strtolower((string)($acc['active_env'] ?? 'sandbox'));
        } catch (\Throwable $e) {
            $env = 'sandbox';
        }
        if ($env === 'homolog') $env = 'sandbox';
        if (!in_array($env, ['sandbox','production'], true)) $env = 'sandbox';
        // When creating (no stone_customer_id), enforce required fields
        if ($stoneId === '') {
            $v = \App\Support\Validator::customerCreate($in);
            if (!$v['ok']) {
                $hints = [];
                $errsAll = array_merge($v['errors_pt'] ?? [], $v['errors_en'] ?? [], $v['errors'] ?? []);
                $addr = $in['address'] ?? [];
                if (isset($errsAll['document']) || (isset($in['document']) && !in_array(strlen((string)$in['document']), [11,14], true))) {
                    $hints[] = 'document (CPF/CNPJ) deve conter apenas dígitos: CPF 11, CNPJ 14.';
                }
                if (isset($addr['zip_code']) && strlen(preg_replace('/\D+/','', (string)$addr['zip_code'])) !== 8) {
                    $hints[] = 'address.zip_code (CEP) deve ter 8 dígitos. Ex: 01001000';
                }
                if (isset($addr['country']) && strlen((string)$addr['country']) !== 2) {
                    $hints[] = 'address.country deve ser a sigla com 2 letras (ISO). Ex: BR';
                }
                if (empty($in['phones']['mobile_phone']) && empty($in['phones']['home_phone']) && empty($in['phone'])) {
                    $hints[] = 'Informe ao menos um telefone: phones.mobile_phone { country_code, area_code, number }';
                }
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
                    'hints' => $hints,
                ], 422);
            }
            // Lookup na Stone por email/document antes de criar
            try {
                $found = (\App\Infrastructure\Stone\StoneClient::forSite($site))->findCustomer(null, ($in['email'] ?? null), ($in['document'] ?? null));
                if (is_array($found) && !empty($found['id'])) {
                    $stoneId = (string)$found['id'];
                    // upsert local e seguir via update
                    Database::run('INSERT INTO customers (site_id, stone_customer_id, env, name, email, document, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name), email=VALUES(email), document=VALUES(document), updated_at=NOW()', [
                        $site['id'], $stoneId, $env, ($in['name'] ?? ($found['name'] ?? null)), ($in['email'] ?? ($found['email'] ?? null)), ($in['document'] ?? ($found['document'] ?? null)),
                    ]);
                }
            } catch (\Throwable $e) { /* ignore lookup errors */ }
        }
        try {
            $stone = \App\Infrastructure\Stone\StoneClient::forSite($site);
            $resp = $stoneId !== ''
                ? $stone->updateCustomer($stoneId, $in)
                : $stone->createCustomer($in);
            $stoneId = (string)($resp['id'] ?? $stoneId);
            if ($stoneId === '') return Response::json(['status' => 'error', 'message' => 'Stone customer id missing', 'status_code' => 'provider_error', 'http_status' => 500], 500);
            // Upsert local
            $name = $in['name'] ?? ($resp['name'] ?? null);
            $email = $in['email'] ?? ($resp['email'] ?? null);
            $document = $in['document'] ?? ($resp['document'] ?? null);
            Database::run('INSERT INTO customers (site_id, stone_customer_id, env, name, email, document, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name), email=VALUES(email), document=VALUES(document), updated_at=NOW()', [
                $site['id'], $stoneId, $env, $name, $email, $document,
            ]);
            $row = Database::run('SELECT * FROM customers WHERE site_id = ? AND stone_customer_id = ? AND env = ? LIMIT 1', [$site['id'], $stoneId, $env])->fetch();
            return Response::json(['status' => 'success', 'message' => 'Cliente gravado', 'status_code' => ($stoneId ? 'updated' : 'created'), 'customer' => $row ?: ['stone_customer_id' => $stoneId]], 200);
        } catch (StoneApiException $e) {
            $errs = $e->getErrors();
            $ptMsg = \App\Support\I18n::ptHttpMessage($e->getStatus(), 'Erro ao gravar cliente');
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

    // PUT /api/v1/customers/{id}
    public function update(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['status' => 'error', 'message' => 'Não autorizado', 'status_code' => 'unauthorized', 'http_status' => 401], 401);
        $id = (int)($req->attributes['routeParams']['id'] ?? 0);
        $in = $req->json ?? [];
        if ($id <= 0) return Response::json(['status' => 'error', 'message' => 'ID inválido', 'status_code' => 'validation_error', 'http_status' => 422], 422);
        $row = Database::run('SELECT * FROM customers WHERE id = ? AND site_id = ? LIMIT 1', [$id, $site['id']])->fetch();
        if (!$row) return Response::json(['status' => 'error', 'message' => 'Not found', 'status_code' => 'not_found', 'http_status' => 404], 404);
        try {
            $stone = \App\Infrastructure\Stone\StoneClient::forSite($site);
            if (!empty($row['stone_customer_id'])) {
                $stone->updateCustomer((string)$row['stone_customer_id'], $in);
            } else {
                $resp = $stone->createCustomer($in);
                $stoneCid = (string)($resp['id'] ?? '');
                if ($stoneCid) {
                    Database::run('UPDATE customers SET stone_customer_id = ?, updated_at = NOW() WHERE id = ?', [$stoneCid, $id]);
                }
            }
            $name = $in['name'] ?? null; $email = $in['email'] ?? null; $document = $in['document'] ?? null;
            if ($name || $email || $document) {
                Database::run('UPDATE customers SET name = COALESCE(?, name), email = COALESCE(?, email), document = COALESCE(?, document), updated_at = NOW() WHERE id = ? AND site_id = ?', [
                    $name, $email, $document, $id, $site['id']
                ]);
            }
            $row = Database::run('SELECT * FROM customers WHERE id = ? AND site_id = ? LIMIT 1', [$id, $site['id']])->fetch();
            return Response::json(['status' => 'success', 'message' => 'Cliente atualizado', 'status_code' => 'updated', 'customer' => $row], 200);
        } catch (StoneApiException $e) {
            $errs = $e->getErrors();
            $ptMsg = \App\Support\I18n::ptHttpMessage($e->getStatus(), 'Erro ao atualizar cliente');
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

    // POST /api/v1/customers/update
    public function updateByLookup(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['status' => 'error', 'message' => 'Não autorizado', 'status_code' => 'unauthorized', 'http_status' => 401], 401);
        $in = $req->json ?? [];
        if (!is_array($in)) return Response::json(['status' => 'error', 'message' => 'JSON inválido', 'status_code' => 'invalid_json', 'http_status' => 400], 400);

        $env = 'sandbox';
        try {
            $acc = \App\Infrastructure\Stone\StoneAccount::resolveForSite($site);
            $env = strtolower((string)($acc['active_env'] ?? 'sandbox'));
        } catch (\Throwable $e) {
            $env = 'sandbox';
        }
        if ($env === 'homolog') $env = 'sandbox';
        if (!in_array($env, ['sandbox','production'], true)) $env = 'sandbox';

        $stoneId = (string)($in['stone_customer_id'] ?? '');
        $document = preg_replace('/\D+/', '', (string)($in['document'] ?? ($in['cpf'] ?? '')));
        $email = (string)($in['email'] ?? '');

        if ($stoneId === '' && $document === '' && $email === '') {
            return Response::json([
                'status' => 'error',
                'message' => 'Informe stone_customer_id ou document (cpf) ou email',
                'status_code' => 'validation_error',
                'http_status' => 422,
            ], 422);
        }

        if ($stoneId === '') {
            $sql = 'SELECT * FROM customers WHERE site_id = ? AND env = ?';
            $params = [$site['id'], $env];
            if ($email !== '') { $sql .= ' AND email = ?'; $params[] = $email; }
            if ($document !== '') { $sql .= ' AND document = ?'; $params[] = $document; }
            $sql .= ' ORDER BY id DESC LIMIT 1';
            $row = Database::run($sql, $params)->fetch();
            if ($row && !empty($row['stone_customer_id'])) {
                $stoneId = (string)$row['stone_customer_id'];
            }
        }

        if ($stoneId === '') {
            try {
                $found = (\App\Infrastructure\Stone\StoneClient::forSite($site))
                    ->findCustomer(null, ($email !== '' ? $email : null), ($document !== '' ? $document : null));
                if (is_array($found) && !empty($found['id'])) {
                    $stoneId = (string)$found['id'];
                }
            } catch (\Throwable $e) {
                // ignore lookup errors
            }
        }

        if ($stoneId === '') {
            return Response::json([
                'status' => 'error',
                'message' => 'Cliente não encontrado para atualizar',
                'status_code' => 'not_found',
                'http_status' => 404,
            ], 404);
        }

        if (isset($in['document']) || isset($in['cpf'])) {
            $doc = $document !== '' ? $document : preg_replace('/\D+/', '', (string)($in['document'] ?? ''));
            if ($doc !== '') {
                $in['document'] = $doc;
                if (empty($in['type'])) {
                    $len = strlen($doc);
                    if ($len === 11) $in['type'] = 'individual';
                    elseif ($len === 14) $in['type'] = 'company';
                }
            }
        }

        $payload = $in;
        unset($payload['stone_customer_id'], $payload['id']);
        if (count($payload) === 0) {
            return Response::json([
                'status' => 'error',
                'message' => 'Nenhum dado para atualizar',
                'status_code' => 'validation_error',
                'http_status' => 422,
            ], 422);
        }

        try {
            $stone = \App\Infrastructure\Stone\StoneClient::forSite($site);
            $resp = $stone->updateCustomer($stoneId, $payload);
            $name = $payload['name'] ?? ($resp['name'] ?? null);
            $email = $payload['email'] ?? ($resp['email'] ?? null);
            $document = $payload['document'] ?? ($resp['document'] ?? null);
            Database::run('INSERT INTO customers (site_id, stone_customer_id, env, name, email, document, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name), email=VALUES(email), document=VALUES(document), updated_at=NOW()', [
                $site['id'], $stoneId, $env, $name, $email, $document,
            ]);
            $row = Database::run('SELECT * FROM customers WHERE site_id = ? AND stone_customer_id = ? AND env = ? LIMIT 1', [$site['id'], $stoneId, $env])->fetch();
            return Response::json([
                'status' => 'success',
                'message' => 'Cliente atualizado',
                'status_code' => 'updated',
                'customer' => $row ?: ['stone_customer_id' => $stoneId],
            ], 200);
        } catch (StoneApiException $e) {
            $errs = $e->getErrors();
            $ptMsg = \App\Support\I18n::ptHttpMessage($e->getStatus(), 'Erro ao atualizar cliente');
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
