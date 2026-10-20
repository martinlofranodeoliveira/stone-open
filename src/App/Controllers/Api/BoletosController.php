<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Infrastructure\Http\{Request, Response};
use App\Application\PaymentService;
use App\Infrastructure\Stone\StoneApiException;
use App\Infrastructure\DB\Database;
use App\Infrastructure\Stone\StoneClient;
use App\Support\Validator;

final class BoletosController
{
    public function create(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        $data = $req->parsedBody ?? $req->json() ?? [];
        if (!$site) return Response::json(['status'=>'error','message'=>'Não autorizado','status_code'=>'unauthorized','http_status'=>401], 401);
        if (!is_array($data)) return Response::json(['status'=>'error','message'=>'JSON inválido','status_code'=>'invalid_json','http_status'=>400], 400);

        $data['payment_method_type'] = $data['payment_method_type'] ?? 'boleto';
        $v = Validator::payment($data);
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

        try {
            $service = new PaymentService();
            $payment = $service->create($site, $data);
            $resp = ['payment' => $payment];
            $boleto = self::extractBoletoFromPayment($payment);
            if ($boleto) $resp['boleto'] = $boleto;
            $resp['summary'] = self::buildSummary($payment, $boleto);
            $resp['status'] = 'success';
            $resp['message'] = self::friendlyMessageFromStatus((string)($payment['status'] ?? '')) ?? 'Boleto gerado';
            $resp['status_code'] = strtolower((string)($payment['status'] ?? '')) ?: 'processing';
            return Response::json($resp, 201);
        } catch (StoneApiException $e) {
            return Response::json([
                'status' => 'error',
                'message' => 'Boleto creation failed',
                'status_code' => $e->getErrorCode() ?: 'provider_error',
                'http_status' => $e->getStatus(),
                'errors' => $e->getErrors(),
                'provider_message' => $e->getErrorMessage(),
                'raw' => $e->getRaw(),
            ], 422);
        } catch (\Throwable $e) {
            return Response::json(['status'=>'error','message'=>$e->getMessage(),'status_code'=>'internal_error','http_status'=>500], 500);
        }
    }

    public function show(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['error'=>'Não autorizado'], 401);
        $id = (int)($req->attributes['id'] ?? 0);
        if ($id <= 0) return Response::json(['error'=>'Invalid id'], 422);
        $row = Database::run('SELECT * FROM payments WHERE id = ? AND site_id = ? LIMIT 1', [$id, $site['id']])->fetch();
        if (!$row) return Response::json(['error'=>'Not found'], 404);
        if (($row['payment_method_type'] ?? '') !== 'boleto') return Response::json(['error'=>'Not a boleto payment'], 422);
        $boleto = self::extractBoletoFromPayment($row);
        return Response::json([
            'payment' => $row,
            'boleto' => $boleto,
            'summary' => self::buildSummary($row, $boleto),
        ]);
    }

    public function showByStone(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['error'=>'Não autorizado'], 401);
        $sid = (string)($req->attributes['sid'] ?? '');
        if ($sid === '') return Response::json(['error'=>'Invalid id'], 422);

        $local = Database::run('SELECT * FROM payments WHERE site_id = ? AND stone_payment_id = ? LIMIT 1', [$site['id'], $sid])->fetch();
        $remote = null;
        try {
            $client = StoneClient::forSite($site);
            $remote = $client->getCharge($sid);
        } catch (StoneApiException $e) {
            if ((int)$e->getStatus() !== 404) {
                return Response::json(['error'=>'Provider error','message'=>$e->getErrorMessage(),'status_code'=>$e->getErrorCode(),'http_status'=>$e->getStatus(),'errors'=>$e->getErrors()], 422);
            }
        }
        return Response::json([
            'local' => $local ?: null,
            'remote' => $remote ?: null,
        ]);
    }

    public function index(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['error'=>'Não autorizado'], 401);
        $status = (string)($req->query['status'] ?? '');
        $q = trim((string)($req->query['q'] ?? ''));
        $from = (string)($req->query['from'] ?? '');
        $to = (string)($req->query['to'] ?? '');
        $limit = min(200, max(1, (int)($req->query['limit'] ?? 50)));
        $offset = max(0, (int)($req->query['offset'] ?? 0));

        $sql = 'SELECT * FROM payments WHERE site_id = ? AND payment_method_type = "boleto" AND status <> "deleted"';
        $params = [$site['id']];
        if ($status !== '') { $sql .= ' AND status = ?'; $params[] = $status; }
        if ($q !== '') { $sql .= ' AND (client_reference_id LIKE ? OR stone_payment_id LIKE ? OR customer_name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($from !== '') { $sql .= ' AND created_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND created_at <= ?'; $params[] = $to; }
        $sql .= ' ORDER BY id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

        $rows = Database::run($sql, $params)->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $boleto = self::extractBoletoFromPayment($r);
            $out[] = [
                'payment' => $r,
                'boleto' => $boleto,
                'summary' => self::buildSummary($r, $boleto),
            ];
        }
        return Response::json(['data' => $out, 'limit' => $limit, 'offset' => $offset]);
    }

    public function remote(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        if (!$site) return Response::json(['error'=>'Não autorizado'], 401);

        $q = [];
        foreach (['page','size','status','payment_method','customer_id','order_id','code','created_since','created_until'] as $k) {
            if (isset($req->query[$k]) && $req->query[$k] !== '') $q[$k] = $req->query[$k];
        }
        if (!isset($q['payment_method'])) $q['payment_method'] = 'boleto';

        try {
            $client = StoneClient::forSite($site);
            $remote = $client->listCharges($q);
            return Response::json(['data' => $remote]);
        } catch (StoneApiException $e) {
            return Response::json(['error'=>'Provider error','message'=>$e->getErrorMessage(),'status_code'=>$e->getErrorCode(),'http_status'=>$e->getStatus(),'errors'=>$e->getErrors()], 422);
        } catch (\Throwable $e) {
            return Response::json(['error'=>'Provider error','message'=>$e->getMessage()], 500);
        }
    }

    private static function extractBoletoFromPayment(array $payment): ?array
    {
        $meta = (string)($payment['metadata'] ?? '');
        $metaJ = $meta !== '' ? json_decode($meta, true) : null;
        if (is_array($metaJ) && isset($metaJ['boleto']) && is_array($metaJ['boleto'])) return $metaJ['boleto'];
        $rp = (string)($payment['response_payload'] ?? '');
        $rpJ = $rp !== '' ? json_decode($rp, true) : null;
        if (!is_array($rpJ)) return null;
        $charges = $rpJ['charges'] ?? null;
        if (is_array($charges) && isset($charges[0]) && is_array($charges[0])) {
            $tx = $charges[0]['last_transaction'] ?? null;
            if (is_array($tx)) {
                return [
                    'url' => $tx['url'] ?? ($tx['boleto_url'] ?? null),
                    'pdf' => $tx['pdf'] ?? ($tx['pdf_url'] ?? null),
                    'line' => $tx['line'] ?? ($tx['digitable_line'] ?? null),
                    'barcode' => $tx['barcode'] ?? ($tx['bar_code'] ?? null),
                    'qr_code' => $tx['qr_code'] ?? null,
                    'due_at' => $tx['due_at'] ?? ($tx['due_date'] ?? null),
                    'document_number' => $tx['document_number'] ?? null,
                    'bank' => $tx['bank'] ?? null,
                    'type' => $tx['type'] ?? null,
                ];
            }
        }
        return null;
    }

    private static function buildSummary(array $payment, ?array $boleto): array
    {
        $status = strtolower((string)($payment['status'] ?? ''));
        return [
            'error' => in_array($status, ['failed','canceled','chargeback'], true),
            'status' => $status ?: null,
            'reference' => $payment['client_reference_id'] ?? null,
            'stone_payment_id' => $payment['stone_payment_id'] ?? null,
            'amount_cents' => isset($payment['amount_cents']) ? (int)$payment['amount_cents'] : null,
            'currency' => $payment['currency'] ?? null,
            'created_at' => $payment['created_at'] ?? null,
            'updated_at' => $payment['updated_at'] ?? null,
            'method' => $payment['payment_method_type'] ?? null,
            'boleto_url' => $boleto['url'] ?? null,
            'boleto_pdf' => $boleto['pdf'] ?? null,
            'boleto_line' => $boleto['line'] ?? null,
            'boleto_barcode' => $boleto['barcode'] ?? null,
            'boleto_due_at' => $boleto['due_at'] ?? null,
        ];
    }

    private static function friendlyMessageFromStatus(?string $status): ?string
    {
        $s = strtolower((string)($status ?? ''));
        return match ($s) {
            'paid' => 'Pagamento aprovado',
            'authorized' => 'Pagamento autorizado',
            'processing', 'pending' => 'Pagamento em processamento',
            'canceled' => 'Pagamento cancelado',
            'refunded' => 'Pagamento estornado',
            'chargeback' => 'Pagamento em chargeback',
            'failed' => 'Pagamento não autorizado',
            default => ($s !== '' ? ucfirst($s) : null),
        };
    }
}
