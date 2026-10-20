<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Infrastructure\Http\{Request, Response};
use App\Application\PaymentService;
use App\Infrastructure\Stone\StoneApiException;
use App\Infrastructure\DB\Database;
use App\Support\Env;

final class PaymentsController
{
    // POST /api/v1/payments/card
    // Recebe dados do cartão e do comprador, tokeniza e continua o pagamento.
    public function createWithCardData(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        $data = $req->parsedBody ?? $req->json() ?? [];
        if (!$site) {
            return Response::json(['status' => 'error', 'message' => 'Não autorizado', 'status_code' => 'unauthorized', 'http_status' => 401], 401);
        }
        if (!is_array($data)) {
            return Response::json(['status' => 'error', 'message' => 'JSON inválido', 'status_code' => 'invalid_json', 'http_status' => 400], 400);
        }

        $envDebug = '';
        try {
            $acc = \App\Infrastructure\Stone\StoneAccount::resolveForSite($site);
            $envDebug = (string)($acc['active_env'] ?? '');
        } catch (\App\Infrastructure\Stone\StoneAccountRequiredException $e) {
            return Response::json([
                'status' => 'error',
                'message' => 'Conta Pagar.me não configurada para este site',
                'status_code' => 'pagarme_account_required',
                'http_status' => 409,
                'reason' => $e->getMessage(),
            ], 409);
        }

        $cardInput = (isset($data['card']) && is_array($data['card'])) ? $data['card'] : [];
        $pick = static function(array $candidates): ?string {
            foreach ($candidates as $v) {
                if (is_string($v) || is_numeric($v)) {
                    $s = trim((string)$v);
                    if ($s !== '') return $s;
                }
            }
            return null;
        };
        $number = $pick([
            $cardInput['number'] ?? null,
            $data['number'] ?? null,
            $data['card_number'] ?? null,
            $data['numero_cartao'] ?? null,
            $data['numeroCartao'] ?? null,
        ]) ?? '';
        $holder = $pick([
            $cardInput['holder_name'] ?? null,
            $data['holder_name'] ?? null,
            $data['card_holder'] ?? null,
            $data['nome_portador'] ?? null,
            $data['nomeTitular'] ?? null,
        ]) ?? '';
        $expMonth = $pick([
            $cardInput['exp_month'] ?? null,
            $data['exp_month'] ?? null,
            $data['card_exp_month'] ?? null,
            $data['mes_validade'] ?? null,
            $data['mesValidade'] ?? null,
        ]) ?? '';
        $expYear = $pick([
            $cardInput['exp_year'] ?? null,
            $data['exp_year'] ?? null,
            $data['card_exp_year'] ?? null,
            $data['ano_validade'] ?? null,
            $data['anoValidade'] ?? null,
        ]) ?? '';
        $cvv = $pick([
            $cardInput['cvv'] ?? null,
            $data['cvv'] ?? null,
            $data['card_cvv'] ?? null,
            $data['cvc'] ?? null,
        ]) ?? '';
        if (($expMonth === '' || $expYear === '') && ($cardInput['validade'] ?? $data['validade'] ?? $data['validade_cartao'] ?? null)) {
            $rawVal = (string)($cardInput['validade'] ?? $data['validade'] ?? $data['validade_cartao'] ?? '');
            if (preg_match('/^(\d{2})\s*\/\s*(\d{2,4})$/', trim($rawVal), $m)) {
                if ($expMonth === '') $expMonth = $m[1];
                if ($expYear === '') $expYear = $m[2];
            }
        }

        $cardPayload = [
            'number' => preg_replace('/\D+/', '', (string)$number),
            'holder_name' => (string)$holder,
            'exp_month' => (int)$expMonth,
            'exp_year' => (int)$expYear,
            'cvv' => preg_replace('/\D+/', '', (string)$cvv),
        ];
        if (!isset($data['payment_method_type']) && !isset($data['tipo_metodo_pagamento'])) {
            $data['payment_method_type'] = 'credit_card';
        }
        if (empty($data['bin']) && $cardPayload['number'] !== '' && strlen($cardPayload['number']) >= 6) {
            $data['bin'] = substr($cardPayload['number'], 0, 6);
        }
        if ($cardPayload['exp_year'] > 999) $cardPayload['exp_year'] = (int)($cardPayload['exp_year'] % 100);

        $errorsPt = [];
        $errorsEn = [];
        if ($cardPayload['number'] === '') {
            $errorsPt['card.number'] = 'Número do cartão é obrigatório.';
            $errorsEn['card.number'] = 'Card number is required.';
        } elseif (strlen($cardPayload['number']) < 13 || strlen($cardPayload['number']) > 19) {
            $errorsPt['card.number'] = 'Número do cartão inválido (13 a 19 dígitos).';
            $errorsEn['card.number'] = 'Invalid card number length (13 to 19 digits).';
        }
        if (trim($cardPayload['holder_name']) === '') {
            $errorsPt['card.holder_name'] = 'Nome do titular é obrigatório.';
            $errorsEn['card.holder_name'] = 'Card holder name is required.';
        }
        if ($cardPayload['exp_month'] < 1 || $cardPayload['exp_month'] > 12) {
            $errorsPt['card.exp_month'] = 'Mês de validade inválido (1-12).';
            $errorsEn['card.exp_month'] = 'Invalid exp_month (1-12).';
        }
        if ($cardPayload['exp_year'] <= 0) {
            $errorsPt['card.exp_year'] = 'Ano de validade inválido.';
            $errorsEn['card.exp_year'] = 'Invalid exp_year.';
        }
        if ($cardPayload['cvv'] === '') {
            $errorsPt['card.cvv'] = 'CVV é obrigatório.';
            $errorsEn['card.cvv'] = 'CVV is required.';
        } elseif (strlen($cardPayload['cvv']) < 3 || strlen($cardPayload['cvv']) > 4) {
            $errorsPt['card.cvv'] = 'CVV inválido (3 ou 4 dígitos).';
            $errorsEn['card.cvv'] = 'Invalid CVV length (3 or 4 digits).';
        }
        if (!empty($errorsPt)) {
            return Response::json([
                'status' => 'error',
                'message' => 'Corrija os campos obrigatórios',
                'status_code' => 'validation_error',
                'http_status' => 422,
                'required' => array_keys($errorsPt),
                'errors' => $errorsPt,
                'errors_en' => $errorsEn,
                'errors_pt' => $errorsPt,
            ], 422);
        }

        $tok = $this->tokenizeCardForAccount($acc, $cardPayload);
        if (!$tok['ok']) {
            $status = (int)($tok['status'] ?? 502);
            $msg = (string)($tok['message'] ?? 'Erro ao tokenizar o cartão');
            $ptMsg = self::translateProviderMessagePt($msg);
            $ptMsg = $this->appendEnvDebug($ptMsg, $envDebug);
            $errsRaw = $tok['errors'] ?? null;
            $errsPt = self::translateErrorsToPt($errsRaw);
            return Response::json([
                'status' => 'error',
                'message' => $ptMsg,
                'status_code' => 'tokenize_error',
                'http_status' => $status,
                'errors' => $errsPt ?? [],
                'errors_en' => $errsRaw ?? [],
                'errors_pt' => $errsPt ?? [],
            ], $status);
        }
        $token = (string)($tok['token'] ?? '');
        if ($token === '') {
            return Response::json([
                'status' => 'error',
                'message' => $this->appendEnvDebug('Falha ao tokenizar o cartão', $envDebug),
                'status_code' => 'tokenize_error',
                'http_status' => 502,
            ], 502);
        }

        $billing = $this->extractBillingAddressFromInput($data, $cardInput);
        $newCard = ['token' => $token];
        if ($cardPayload['holder_name'] !== '') $newCard['holder_name'] = $cardPayload['holder_name'];
        if (is_array($billing)) $newCard['billing_address'] = $billing;

        $data['card'] = $newCard;
        $data['card_token'] = $token;
        $this->stripRawCardFields($data);

        $req->parsedBody = $data;
        return $this->create($req);
    }

    private static function buildPaymentSummary(array $payment, array $resp): array
    {
        $status = strtolower((string)($payment['status'] ?? ''));
        $error = in_array($status, ['failed','canceled','chargeback'], true) || !empty($resp['provider_errors'] ?? []);
        $summary = [
            'error' => $error,
            'status' => $status ?: null,
            'reference' => $payment['client_reference_id'] ?? null,
            'stone_payment_id' => $payment['stone_payment_id'] ?? null,
            'amount_cents' => isset($payment['amount_cents']) ? (int)$payment['amount_cents'] : null,
            'currency' => $payment['currency'] ?? null,
            'created_at' => $payment['created_at'] ?? null,
            'updated_at' => $payment['updated_at'] ?? null,
            'method' => $payment['payment_method_type'] ?? null,
        ];
        // Extras by method
        $rp = (string)($payment['response_payload'] ?? '');
        $rpJ = $rp !== '' ? json_decode($rp, true) : null;
        $charges = is_array($rpJ) ? ($rpJ['charges'] ?? null) : null;
        $tx = null;
        if (is_array($charges) && isset($charges[0]) && is_array($charges[0])) {
            $tx = $charges[0]['last_transaction'] ?? null;
        }
        if (($payment['payment_method_type'] ?? null) === 'pix') {
            $pix = $resp['pix'] ?? null;
            if (is_array($pix)) {
                $summary['pix_expires_at'] = $pix['expires_at'] ?? null;
                $summary['pix_qr_code_url'] = $pix['qr_code_url'] ?? null;
                $summary['pix_qr_code'] = $pix['qr_code'] ?? null;
                // Aliases para leitura amigável
                $summary['pix_code'] = $summary['pix_qr_code'];
                $summary['pix_qrcode_url'] = $summary['pix_qr_code_url'];
            } elseif (is_array($tx)) {
                $summary['pix_expires_at'] = $tx['expires_at'] ?? ($tx['expiration_date'] ?? null);
                $summary['pix_qr_code_url'] = $tx['qr_code_url'] ?? null;
                $summary['pix_qr_code'] = $tx['qr_code'] ?? ($tx['qr_code_emv'] ?? ($tx['emv'] ?? ($tx['payload'] ?? null)));
                $summary['pix_code'] = $summary['pix_qr_code'];
                $summary['pix_qrcode_url'] = $summary['pix_qr_code_url'];
            }
        } elseif (($payment['payment_method_type'] ?? null) === 'boleto') {
            $boleto = $resp['boleto'] ?? null;
            if (is_array($boleto)) {
                $summary['boleto_url'] = $boleto['url'] ?? null;
                $summary['boleto_pdf'] = $boleto['pdf'] ?? null;
                $summary['boleto_line'] = $boleto['line'] ?? null;
                $summary['boleto_barcode'] = $boleto['barcode'] ?? null;
                $summary['boleto_qr_code'] = $boleto['qr_code'] ?? null;
                $summary['boleto_due_at'] = $boleto['due_at'] ?? null;
                $summary['boleto_document_number'] = $boleto['document_number'] ?? null;
                $summary['boleto_bank'] = $boleto['bank'] ?? null;
                $summary['boleto_type'] = $boleto['type'] ?? null;
            } elseif (is_array($tx)) {
                $summary['boleto_url'] = $tx['url'] ?? ($tx['boleto_url'] ?? null);
                $summary['boleto_pdf'] = $tx['pdf'] ?? ($tx['pdf_url'] ?? null);
                $summary['boleto_line'] = $tx['line'] ?? ($tx['digitable_line'] ?? null);
                $summary['boleto_barcode'] = $tx['barcode'] ?? ($tx['bar_code'] ?? null);
                $summary['boleto_qr_code'] = $tx['qr_code'] ?? null;
                $summary['boleto_due_at'] = $tx['due_at'] ?? ($tx['due_date'] ?? null);
                $summary['boleto_document_number'] = $tx['document_number'] ?? null;
                $summary['boleto_bank'] = $tx['bank'] ?? null;
                $summary['boleto_type'] = $tx['type'] ?? null;
            }
        } elseif (($payment['payment_method_type'] ?? null) === 'credit_card') {
            if (is_array($tx)) {
                $card = $tx['card'] ?? [];
                if (is_array($card)) {
                    $summary['card_brand'] = $card['brand'] ?? null;
                    $summary['card_last4'] = $card['last_four_digits'] ?? ($card['last4'] ?? null);
                }
                $summary['installments'] = $tx['installments'] ?? ($payment['installments'] ?? null);
            }
        }
        return $summary;
    }

    private static function extractAcquirerMessageFromPaymentRow(array $payment): ?string
    {
        $rp = (string)($payment['response_payload'] ?? '');
        if ($rp === '') return null;
        $j = json_decode($rp, true);
        if (!is_array($j)) return null;
        $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
        $candidates = [];
        $candidates[] = $read($j, ['charges', 0, 'last_transaction', 'acquirer_message']);
        $candidates[] = $read($j, ['last_transaction', 'acquirer_message']);
        foreach ($candidates as $c) {
            if (is_string($c) && $c !== '') return $c;
        }
        return null;
    }

    private static function extractAntifraudStatusFromPaymentRow(array $payment): ?string
    {
        $rp = (string)($payment['response_payload'] ?? ''); if ($rp === '') return null; $j = json_decode($rp, true); if (!is_array($j)) return null;
        $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
        foreach ([['charges',0,'last_transaction','antifraud_response','status'], ['last_transaction','antifraud_response','status']] as $p) {
            $v = $read($j, $p); if (is_string($v) && $v !== '') return strtolower($v);
        }
        return null;
    }

    private static function translateProviderMessagePt(string $message): string
    {
        return \App\Support\I18n::translateProviderMessagePt($message);
    }

    private static function mergeMessagesPt(string $base, string $detail): string
    {
        $b = strtolower(trim($base));
        $d = strtolower(trim($detail));
        if ($b === '') return $detail;
        if ($d === '') return $base;
        if ($b === $d || str_contains($d, $b)) return $detail;
        if (str_contains($b, $d)) return $base;
        return $base . ': ' . $detail;
    }

    private static function flattenProviderErrorMessages($errors): array
    {
        $out = [];
        $push = function($v) use (&$out, &$push): void {
            if (is_string($v)) {
                $s = trim($v);
                if ($s !== '') $out[] = $s;
                return;
            }
            if (!is_array($v)) return;
            if (isset($v['message']) && is_string($v['message'])) {
                $s = trim($v['message']);
                if ($s !== '') $out[] = $s;
            } elseif (isset($v['code']) && is_string($v['code'])) {
                $s = trim($v['code']);
                if ($s !== '') $out[] = $s;
            }
            foreach ($v as $vv) {
                if (is_array($vv) || is_string($vv)) $push($vv);
            }
        };
        $push($errors);
        $out = array_values(array_unique($out));
        return $out;
    }

    private static function translateErrorsToPt($errors)
    {
        if (!is_array($errors)) return $errors;
        $out = [];
        foreach ($errors as $k => $v) {
            if (is_string($v)) {
                $out[$k] = self::translateProviderMessagePt($v);
                continue;
            }
            if (is_array($v)) {
                $tmp = $v;
                if (isset($tmp['message']) && is_string($tmp['message'])) {
                    $tmp['message'] = self::translateProviderMessagePt($tmp['message']);
                }
                foreach ($tmp as $kk => $vv) {
                    if ($kk === 'code') continue;
                    if (is_array($vv) || is_string($vv)) $tmp[$kk] = self::translateErrorsToPt($vv);
                }
                $out[$k] = $tmp;
                continue;
            }
            $out[$k] = $v;
        }
        return $out;
    }

    private static function buildProviderErrorMessageFromPayload(array $payload): ?string
    {
        $topMsg = isset($payload['message']) && is_string($payload['message']) ? trim($payload['message']) : null;
        $errs = self::flattenProviderErrorMessages($payload['errors'] ?? null);
        $errsPt = [];
        foreach ($errs as $m) {
            $pt = self::translateProviderMessagePt($m);
            if ($pt !== '') $errsPt[] = $pt;
        }
        $errsPt = array_values(array_unique($errsPt));
        $topPt = $topMsg ? self::translateProviderMessagePt($topMsg) : null;
        if (!empty($errsPt)) {
            $detail = implode('; ', $errsPt);
            return $topPt ? self::mergeMessagesPt($topPt, $detail) : $detail;
        }
        if ($topPt) return $topPt;
        return null;
    }

    private static function extractProviderErrorsFromPayload(array $payload): array
    {
        $errs = self::flattenProviderErrorMessages($payload['errors'] ?? null);
        $errs = array_values(array_unique($errs));
        $pt = [];
        foreach ($errs as $m) {
            $t = self::translateProviderMessagePt($m);
            if ($t !== '') $pt[] = $t;
        }
        $pt = array_values(array_unique($pt));
        $out = [];
        if (!empty($errs)) $out['errors'] = array_map(static fn($m) => ['message' => $m], $errs);
        if (!empty($pt)) $out['errors_pt'] = array_map(static fn($m) => ['message' => $m], $pt);
        return $out;
    }

    private static function extractFailureReasonFromPaymentRow(array $payment): ?string
    {
        $rp = (string)($payment['response_payload'] ?? ''); if ($rp === '') return null; $j = json_decode($rp, true); if (!is_array($j)) return null;
        $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
        // 1) Antifraud reproved
        $af = self::extractAntifraudStatusFromPaymentRow($payment);
        if ($af === 'reproved' || $af === 'rejected' || $af === 'declined') return 'Transação reprovada na análise antifraude';
        // 2) Failure reason on transaction
        $fr = $read($j, ['charges',0,'last_transaction','failure_reason']) ?? $read($j, ['last_transaction','failure_reason']);
        if (is_string($fr) && $fr !== '') return $fr;
        // 3) Gateway errors
        $gw = $read($j, ['charges',0,'last_transaction','gateway_response']) ?? $read($j, ['last_transaction','gateway_response']);
        if (is_array($gw) && isset($gw['errors']) && is_array($gw['errors']) && isset($gw['errors'][0])) {
            $first = $gw['errors'][0];
            $msg = is_array($first) ? ($first['message'] ?? ($first['code'] ?? '')) : (string)$first;
            if (is_string($msg) && $msg !== '') return self::translateProviderMessagePt($msg);
        }
        // 4) Top-level errors (ex.: validações)
        $top = self::buildProviderErrorMessageFromPayload($j);
        if ($top) return $top;
        return null;
    }

    private static function extractAcquirerReturnCodeFromPaymentRow(array $payment): ?string
    {
        $rp = (string)($payment['response_payload'] ?? ''); if ($rp === '') return null; $j = json_decode($rp, true); if (!is_array($j)) return null;
        $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
        foreach ([['charges',0,'last_transaction','acquirer_return_code'], ['last_transaction','acquirer_return_code']] as $p) {
            $v = $read($j, $p); if (is_string($v) && $v !== '') return $v; if (is_numeric($v)) return (string)$v;
        }
        return null;
    }

    private static function extractTxStatusFromPaymentRow(array $payment): ?string
    {
        $rp = (string)($payment['response_payload'] ?? ''); if ($rp === '') return null; $j = json_decode($rp, true); if (!is_array($j)) return null;
        $read = function(array $a, array $path){ $cur=$a; foreach($path as $k){ if(is_array($cur) && array_key_exists($k,$cur)){ $cur=$cur[$k]; } else { return null; } } return $cur; };
        foreach ([['charges',0,'last_transaction','status'], ['last_transaction','status']] as $p) {
            $v = $read($j, $p); if (is_string($v) && $v !== '') return $v;
        }
        return null;
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

    private static function providerHint(array $provErrs): array
    {
        $pt = null; $en = null;
        foreach ($provErrs as $e) {
            $msg = strtolower((string)($e['message'] ?? ''));
            $code = strtolower((string)($e['code'] ?? ''));
            if (str_contains($msg, 'action_forbidden') || $code === 'action_forbidden' || str_contains($msg, 'sem ambiente configurado')) {
                $pt = 'Parece que este tipo de transação não está habilitado para a sua conta/ambiente. Verifique no painel da Pagar.me se o produto está ativo (ex.: PIX em produção) e se as credenciais (sk_live) e STONE_ENV=production estão corretos.';
                $en = 'It seems this transaction type is not enabled for your account/environment. Ensure the product (e.g., PIX in production) is active in Pagar.me and credentials (sk_live) with STONE_ENV=production are correct.';
                break;
            } elseif (str_contains($msg, 'authorization has been denied')) {
                $pt = 'A API negou autorização. Verifique se a conta Pagar.me vinculada ao site está correta, se o ambiente ativo (sandbox/production) corresponde à chave secreta informada (sk_...) e se o produto PIX está habilitado para esse ambiente.';
                $en = 'Authorization denied. Check if the linked Pagar.me account and active environment (sandbox/production) match the provided secret key (sk_...) and if PIX is enabled for that environment.';
                break;
            }
        }
        return ['pt'=>$pt,'en'=>$en];
    }
    public function create(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;

        $data = $req->parsedBody ?? $req->json() ?? [];
        if (!$site) return Response::json(['status' => 'error', 'message' => 'Não autorizado', 'status_code' => 'unauthorized', 'http_status' => 401], 401);
        if (!is_array($data)) return Response::json(['status' => 'error', 'message' => 'JSON inválido', 'status_code' => 'invalid_json', 'http_status' => 400], 400);

        // Fail-fast: validar conta Pagar.me vinculada ao site (usa tabela pagarme_accounts)
        $envDebug = '';
        try { $acc = \App\Infrastructure\Stone\StoneAccount::resolveForSite($site); $envDebug = (string)($acc['active_env'] ?? ''); }
        catch (\App\Infrastructure\Stone\StoneAccountRequiredException $e) {
            return Response::json([
                'status' => 'error',
                'message' => 'Conta Pagar.me não configurada para este site',
                'status_code' => 'pagarme_account_required',
                'http_status' => 409,
                'reason' => $e->getMessage(),
            ], 409);
        }

        // Observação: validação da conta já é feita acima; nenhum modo "X-Debug" é necessário

        // Validate required fields before creating
        $skipCardNumberValidation = $this->isSandboxEnv((string)($acc['active_env'] ?? ''));
        $requireBilling = $this->resolveRequireBillingAddress($site, $acc ?? null);
        $v = \App\Support\Validator::payment($data, [
            'skip_card_number_validation' => $skipCardNumberValidation,
            'require_billing_address' => $requireBilling,
        ]);
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
            if (($payment['payment_method_type'] ?? null) === 'pix') {
                $pix = null;
                // Prefer metadata.pix if available
                $meta = (string)($payment['metadata'] ?? '');
                $metaJ = $meta !== '' ? json_decode($meta, true) : null;
                if (is_array($metaJ) && isset($metaJ['pix'])) $pix = $metaJ['pix'];
                if (!$pix) {
                    $rp = (string)($payment['response_payload'] ?? '');
                    $rpJ = $rp !== '' ? json_decode($rp, true) : null;
                    if (is_array($rpJ)) {
                        // Try to extract minimally: charges[0].last_transaction
                        $charges = $rpJ['charges'] ?? null;
                        if (is_array($charges) && isset($charges[0]) && is_array($charges[0])) {
                            $tx = $charges[0]['last_transaction'] ?? [];
                            if (is_array($tx)) {
                                $pix = [
                                    'qr_code' => $tx['qr_code'] ?? ($tx['qr_code_emv'] ?? ($tx['emv'] ?? null)),
                                    'qr_code_url' => $tx['qr_code_url'] ?? null,
                                    'expires_at' => $tx['expires_at'] ?? ($tx['expiration_date'] ?? null),
                                    'transaction_id' => $tx['id'] ?? null,
                                    'status' => $tx['status'] ?? null,
                                ];
                            }
                        }
                    }
                }
                if ($pix) $resp['pix'] = $pix;
            } elseif (($payment['payment_method_type'] ?? null) === 'boleto') {
                $boleto = null;
                $meta = (string)($payment['metadata'] ?? '');
                $metaJ = $meta !== '' ? json_decode($meta, true) : null;
                if (is_array($metaJ) && isset($metaJ['boleto'])) $boleto = $metaJ['boleto'];
                if (!$boleto) {
                    $rp = (string)($payment['response_payload'] ?? '');
                    $rpJ = $rp !== '' ? json_decode($rp, true) : null;
                    if (is_array($rpJ)) {
                        $charges = $rpJ['charges'] ?? null;
                        if (is_array($charges) && isset($charges[0]) && is_array($charges[0])) {
                            $tx = $charges[0]['last_transaction'] ?? [];
                            if (is_array($tx)) {
                                $boleto = [
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
                    }
                }
                if ($boleto) $resp['boleto'] = $boleto;
            }
            // If provider failed inside 200, expose provider errors too
            if (strtolower((string)($payment['status'] ?? '')) === 'failed') {
                $prov = null;
                $provPt = null;
                $meta = (string)($payment['metadata'] ?? '');
                $metaJ = $meta !== '' ? json_decode($meta, true) : null;
                if (is_array($metaJ) && isset($metaJ['provider_errors'])) $prov = $metaJ['provider_errors'];
                if (is_array($metaJ) && isset($metaJ['provider_errors_pt'])) $provPt = $metaJ['provider_errors_pt'];
                $rpJ = null;
                $rp = (string)($payment['response_payload'] ?? '');
                if ($rp !== '') {
                    $rpJ = json_decode($rp, true);
                    if (!is_array($rpJ)) $rpJ = null;
                }
                if (!$prov) {
                    if (is_array($rpJ)) {
                        $charges = $rpJ['charges'] ?? null;
                        if (is_array($charges) && isset($charges[0]) && is_array($charges[0])) {
                            $tx = $charges[0]['last_transaction'] ?? [];
                            if (is_array($tx)) {
                                $gw = $tx['gateway_response'] ?? [];
                                if (is_array($gw)) {
                                    if (isset($gw['errors']) && is_array($gw['errors'])) {
                                        $prov = [];
                                        foreach ($gw['errors'] as $e) {
                                            if (is_array($e)) $prov[] = ['code' => $e['code'] ?? null, 'message' => $e['message'] ?? null];
                                        }
                                    } elseif (isset($gw['message'])) {
                                        $prov = [['code' => $gw['code'] ?? null, 'message' => $gw['message']]];
                                    }
                                }
                                if (!$prov && isset($tx['failure_reason'])) {
                                    $prov = [['message' => (string)$tx['failure_reason']]];
                                }
                            }
                        }
                    }
                }
                if ((!$prov || !$provPt) && is_array($rpJ)) {
                    $extra = self::extractProviderErrorsFromPayload($rpJ);
                    if (!$prov && isset($extra['errors'])) $prov = $extra['errors'];
                    if (!$provPt && isset($extra['errors_pt'])) $provPt = $extra['errors_pt'];
                }
                if ($prov) $resp['provider_errors'] = $prov;
                if ($provPt) $resp['provider_errors_pt'] = $provPt;
                if ($prov || $provPt) {
                    $hint = self::providerHint($provPt ?: $prov);
                    if ($hint['pt']) $resp['provider_hint_pt'] = $hint['pt'];
                    if ($hint['en']) $resp['provider_hint'] = $hint['en'];
                }
            }
            // Add summary block for easy reading by end users
            $resp['summary'] = self::buildPaymentSummary($payment, $resp);
            // Unified root status/message/status_code for clients
            $norm = strtolower((string)($payment['status'] ?? ''));
            $isError = in_array($norm, ['failed','canceled','chargeback'], true);
            $acq = self::extractAcquirerMessageFromPaymentRow($payment);
            $txStatus = self::extractTxStatusFromPaymentRow($payment);
            $afStatus = self::extractAntifraudStatusFromPaymentRow($payment);
            // status_code: em erro, prioriza antifraude/txStatus; em sucesso, mantém return code
            if ($isError) {
                if (in_array((string)$afStatus, ['reproved','rejected','declined'], true)) {
                    $code = 'antifraud_reproved';
                } elseif (!empty($txStatus)) {
                    $code = $txStatus;
                } else {
                    $code = self::extractAcquirerReturnCodeFromPaymentRow($payment) ?? ($norm ?: null);
                }
            } else {
                $code = self::extractAcquirerReturnCodeFromPaymentRow($payment) ?? ($txStatus ?? $norm ?: null);
            }
            // Preferir mensagem de falha real (antifraude, failure_reason, gateway errors) quando status é erro, evitando "aprovada com sucesso"
            $failMsg = $isError ? (self::extractFailureReasonFromPaymentRow($payment) ?: null) : null;
            if ($isError) {
                // Se antifraude reprovou, substitui mensagem
                $af = self::extractAntifraudStatusFromPaymentRow($payment);
                if (in_array($af, ['reproved','rejected','declined'], true)) $failMsg = $failMsg ?: 'Transação reprovada na análise antifraude';
            }
            // Mensagem raiz sempre em PT; expor acquirer_message separadamente
            $msg = $isError ? ($failMsg ?: self::friendlyMessageFromStatus($norm)) : self::friendlyMessageFromStatus($norm);
            if ($isError) $msg = $this->appendEnvDebug($msg, $envDebug);
            $resp['status'] = $isError ? 'error' : 'success';
            $resp['message'] = $msg;
            $resp['status_code'] = $code;
            // Optional debug aid
            $acq = $this->sanitizeAcquirerMessage($acq, $isError, $afStatus, $txStatus);
            if ($acq !== null && $acq !== '') $resp['acquirer_message'] = $acq;
            if ($isError) {
                $tokenErr = false;
                if (!$tokenErr && $provPt) $tokenErr = $this->hasTokenErrorInErrors($provPt);
                if (!$tokenErr && $prov) $tokenErr = $this->hasTokenErrorInErrors($prov);
                if (!$tokenErr && is_string($failMsg ?? null)) $tokenErr = $this->isTokenErrorMessage($failMsg ?? '');
                if ($tokenErr) {
                    $this->logDebugHint('card_token_invalid', [
                        'request_id' => (string)($req->attributes['request_id'] ?? ''),
                        'site_id' => $site['id'] ?? null,
                        'payment_id' => $payment['id'] ?? null,
                        'client_reference_id' => $data['client_reference_id'] ?? null,
                        'idempotency_key' => $data['idempotency_key'] ?? null,
                        'message' => $failMsg ?? null,
                        'provider_errors' => $prov ?? null,
                        'provider_errors_pt' => $provPt ?? null,
                    ]);
                }
            }
            return Response::json($resp, 201);
        } catch (\App\Infrastructure\Stone\StoneAccountRequiredException $e) {
            return Response::json([
                'status' => 'error',
                'message' => 'Conta Pagar.me não configurada para este site',
                'status_code' => 'pagarme_account_required',
                'http_status' => 409,
                'reason' => $e->getMessage(),
            ], 409);
        } catch (StoneApiException $e) {
            $errs = $e->getErrors();
            $providerMsg = (string)$e->getErrorMessage();
            $code = $e->getErrorCode() ?: (string)$e->getStatus();
            $ptMsg = \App\Support\I18n::ptHttpMessage($e->getStatus(), 'Erro ao processar pagamento');
            $raw = $e->getRaw();
            if (is_array($raw)) {
                $detail = self::buildProviderErrorMessageFromPayload($raw);
                if ($detail) $ptMsg = self::mergeMessagesPt($ptMsg, $detail);
            }
            $ptMsg = $this->appendEnvDebug($ptMsg, $envDebug);
            // Mapear erros comuns para mensagens mais úteis
            if ($providerMsg !== '' && stripos($providerMsg, 'token not found') !== false) {
                $ptMsg = 'Token de cartão não encontrado ou já utilizado. Gere um novo token.';
                $code = 'token_not_found';
            }
            $errsPt = self::translateErrorsToPt($errs);
            if ($this->isTokenErrorMessage($providerMsg) || $this->hasTokenErrorInErrors($errs)) {
                $this->logDebugHint('card_token_invalid', [
                    'request_id' => (string)($req->attributes['request_id'] ?? ''),
                    'site_id' => $site['id'] ?? null,
                    'client_reference_id' => $data['client_reference_id'] ?? null,
                    'idempotency_key' => $data['idempotency_key'] ?? null,
                    'provider_message' => $providerMsg,
                    'errors' => $errs,
                ]);
            }
            return Response::json([
                'status' => 'error',
                'message' => $ptMsg,
                'status_code' => $code,
                'http_status' => $e->getStatus(),
                'provider_status' => $e->getStatus(),
                'provider_message' => $providerMsg,
                'error_code' => $e->getErrorCode(),
                'error_message' => $providerMsg,
                'errors' => $errs,
                'errors_en' => $errs,
                'errors_pt' => $errsPt,
            ], 422);
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $lc = strtolower($msg);
            $code = (str_contains($lc, 'token de cartão') || str_contains($lc, 'token de cartao') || str_contains($lc, 'token')) ? 'token_reused' : 'validation_error';
            return Response::json(['status' => 'error', 'message' => $msg, 'status_code' => $code, 'http_status' => 422], 422);
        } catch (\Throwable $e) {
            return Response::json(['status' => 'error', 'message' => $e->getMessage(), 'status_code' => 'internal_error', 'http_status' => 500], 500);
        }
    }

    // POST /api/v1/payments/for-customer
    public function createForCustomer(Request $req): Response
    {
        $site = $req->attributes['site'] ?? null;
        $data = $req->parsedBody ?? $req->json() ?? [];
        if (!$site) return Response::json(['status' => 'error', 'message' => 'Não autorizado', 'status_code' => 'unauthorized', 'http_status' => 401], 401);
        if (!is_array($data)) return Response::json(['status' => 'error', 'message' => 'JSON inválido', 'status_code' => 'invalid_json', 'http_status' => 400], 400);

        // Validate minimal fields first
        $skipCardNumberValidation = false;
        $envDebug = '';
        $acc = null;
        try {
            $acc = \App\Infrastructure\Stone\StoneAccount::resolveForSite($site);
            $envDebug = (string)($acc['active_env'] ?? '');
            $skipCardNumberValidation = $this->isSandboxEnv((string)($acc['active_env'] ?? ''));
        } catch (\Throwable $e) {
            $skipCardNumberValidation = false;
        }
        $requireBilling = $this->resolveRequireBillingAddress($site, $acc);
        $v = \App\Support\Validator::paymentForCustomer($data, [
            'skip_card_number_validation' => $skipCardNumberValidation,
            'require_billing_address' => $requireBilling,
        ]);
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

        if (!isset($data['document']) && isset($data['documento'])) {
            $data['document'] = $data['documento'];
        }
        if (!isset($data['cpf']) && isset($data['documento'])) {
            $data['cpf'] = $data['documento'];
        }
        $env = strtolower((string)($acc['active_env'] ?? 'sandbox'));
        if ($env === 'homolog') $env = 'sandbox';
        if (!in_array($env, ['sandbox','production'], true)) $env = 'sandbox';

        $stoneCid = (string)($data['stone_customer_id'] ?? '');
        $email = (string)($data['email'] ?? ($data['customer_email'] ?? ''));
        $documentRaw = (string)($data['document'] ?? ($data['cpf'] ?? ''));
        $document = preg_replace('/\D+/', '', $documentRaw);

        $row = null;
        if ($stoneCid !== '') {
            $row = \App\Infrastructure\DB\Database::run('SELECT * FROM customers WHERE site_id = ? AND stone_customer_id = ? AND env = ? LIMIT 1', [$site['id'], $stoneCid, $env])->fetch();
            if ((!$row || empty($row['stone_customer_id'])) && $stoneCid !== '') {
                // Try resolve from Stone by ID, then upsert locally
                try {
                    $found = (\App\Infrastructure\Stone\StoneClient::forSite($site))->findCustomer($stoneCid, null, null);
                    if (is_array($found) && !empty($found['id'])) {
                        $name = (string)($found['name'] ?? '');
                        $eml = (string)($found['email'] ?? '');
                        $doc = (string)($found['document'] ?? '');
                        \App\Infrastructure\DB\Database::run('INSERT INTO customers (site_id, stone_customer_id, env, name, email, document, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name), email=VALUES(email), document=VALUES(document), updated_at=NOW()', [
                            $site['id'], $found['id'], $env, $name ?: null, $eml ?: null, $doc ?: null,
                        ]);
                        $row = \App\Infrastructure\DB\Database::run('SELECT * FROM customers WHERE site_id = ? AND stone_customer_id = ? AND env = ? LIMIT 1', [$site['id'], $found['id'], $env])->fetch();
                    }
                } catch (\Throwable $e) { /* ignore */ }
            }
        } elseif ($email !== '' || $document !== '') {
            if ($email !== '') {
                $row = \App\Infrastructure\DB\Database::run('SELECT * FROM customers WHERE site_id = ? AND email = ? AND env = ? LIMIT 1', [$site['id'], $email, $env])->fetch();
            }
            if ((!$row || empty($row['stone_customer_id'])) && $document !== '') {
                $row = \App\Infrastructure\DB\Database::run('SELECT * FROM customers WHERE site_id = ? AND document = ? AND env = ? LIMIT 1', [$site['id'], $document, $env])->fetch();
                if (!$row && $documentRaw !== '' && $documentRaw !== $document) {
                    $row = \App\Infrastructure\DB\Database::run('SELECT * FROM customers WHERE site_id = ? AND document = ? AND env = ? LIMIT 1', [$site['id'], $documentRaw, $env])->fetch();
                }
            }
            if (!$row || empty($row['stone_customer_id'])) {
                // Try resolve remotely on Stone by email/document and upsert
                try {
                    $found = (\App\Infrastructure\Stone\StoneClient::forSite($site))->findCustomer(null, $email ?: null, $document ?: null);
                    if (is_array($found) && !empty($found['id'])) {
                        $name = (string)($found['name'] ?? '');
                        $eml = (string)($found['email'] ?? '');
                        $doc = (string)($found['document'] ?? '');
                        \App\Infrastructure\DB\Database::run('INSERT INTO customers (site_id, stone_customer_id, env, name, email, document, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name), email=VALUES(email), document=VALUES(document), updated_at=NOW()', [
                            $site['id'], $found['id'], $env, $name ?: null, $eml ?: null, $doc ?: null,
                        ]);
                        $row = \App\Infrastructure\DB\Database::run('SELECT * FROM customers WHERE site_id = ? AND stone_customer_id = ? AND env = ? LIMIT 1', [$site['id'], $found['id'], $env])->fetch();
                    }
                } catch (\Throwable $e) { /* ignore */ }
            }
        }
        if ($row && empty($row['stone_customer_id'])) {
            // Cliente local existe, mas não está vinculado na Stone: tenta criar
            try {
                $payload = [
                    'name' => $row['name'] ?? null,
                    'email' => $row['email'] ?? null,
                    'document' => isset($row['document']) ? preg_replace('/\D+/', '', (string)$row['document']) : null,
                ];
                if (empty($payload['type']) && !empty($payload['document'])) {
                    $len = strlen((string)$payload['document']);
                    if ($len === 11) $payload['type'] = 'individual';
                    elseif ($len === 14) $payload['type'] = 'company';
                }
                if (!empty($row['phone'])) {
                    $payload['phone'] = $row['phone'];
                }
                $stone = \App\Infrastructure\Stone\StoneClient::forSite($site);
                $created = $stone->createCustomer($payload);
                $stoneId = (string)($created['id'] ?? '');
                if ($stoneId !== '') {
                    \App\Infrastructure\DB\Database::run('UPDATE customers SET stone_customer_id = ?, updated_at = NOW() WHERE id = ?', [$stoneId, $row['id']]);
                    $row['stone_customer_id'] = $stoneId;
                }
            } catch (\Throwable $e) {
                // ignore and fall through to not_found
            }
        }

        if (!$row || empty($row['stone_customer_id'])) {
            return Response::json(['status' => 'error', 'message' => 'Customer not found', 'status_code' => 'not_found', 'http_status' => 404], 404);
        }

        // Force order to use existing customer id
        $data['customer'] = ['id' => (string)$row['stone_customer_id']];
        unset($data['email'], $data['document'], $data['cpf'], $data['stone_customer_id']);

        try {
            $service = new PaymentService();
            $payment = $service->create($site, $data);
            $resp = ['payment' => $payment];
            if (($payment['payment_method_type'] ?? null) === 'pix') {
                $pix = null;
                $meta = (string)($payment['metadata'] ?? '');
                $metaJ = $meta !== '' ? json_decode($meta, true) : null;
                if (is_array($metaJ) && isset($metaJ['pix'])) $pix = $metaJ['pix'];
                if (!$pix) {
                    $rp = (string)($payment['response_payload'] ?? '');
                    $rpJ = $rp !== '' ? json_decode($rp, true) : null;
                    if (is_array($rpJ)) {
                        $charges = $rpJ['charges'] ?? null;
                        if (is_array($charges) && isset($charges[0]) && is_array($charges[0])) {
                            $tx = $charges[0]['last_transaction'] ?? [];
                            if (is_array($tx)) {
                                $pix = [
                                    'qr_code' => $tx['qr_code'] ?? ($tx['qr_code_emv'] ?? ($tx['emv'] ?? null)),
                                    'qr_code_url' => $tx['qr_code_url'] ?? null,
                                    'expires_at' => $tx['expires_at'] ?? ($tx['expiration_date'] ?? null),
                                    'transaction_id' => $tx['id'] ?? null,
                                    'status' => $tx['status'] ?? null,
                                ];
                            }
                        }
                    }
                }
                if ($pix) $resp['pix'] = $pix;
            } elseif (($payment['payment_method_type'] ?? null) === 'boleto') {
                $boleto = null;
                $meta = (string)($payment['metadata'] ?? '');
                $metaJ = $meta !== '' ? json_decode($meta, true) : null;
                if (is_array($metaJ) && isset($metaJ['boleto'])) $boleto = $metaJ['boleto'];
                if (!$boleto) {
                    $rp = (string)($payment['response_payload'] ?? '');
                    $rpJ = $rp !== '' ? json_decode($rp, true) : null;
                    if (is_array($rpJ)) {
                        $charges = $rpJ['charges'] ?? null;
                        if (is_array($charges) && isset($charges[0]) && is_array($charges[0])) {
                            $tx = $charges[0]['last_transaction'] ?? [];
                            if (is_array($tx)) {
                                $boleto = [
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
                    }
                }
                if ($boleto) $resp['boleto'] = $boleto;
            }
            if (strtolower((string)($payment['status'] ?? '')) === 'failed') {
                $prov = null; $provPt = null;
                $meta = (string)($payment['metadata'] ?? '');
                $metaJ = $meta !== '' ? json_decode($meta, true) : null;
                if (is_array($metaJ) && isset($metaJ['provider_errors'])) $prov = $metaJ['provider_errors'];
                if (is_array($metaJ) && isset($metaJ['provider_errors_pt'])) $provPt = $metaJ['provider_errors_pt'];
                $rpJ = null;
                $rp = (string)($payment['response_payload'] ?? '');
                if ($rp !== '') {
                    $rpJ = json_decode($rp, true);
                    if (!is_array($rpJ)) $rpJ = null;
                }
                if ((!$prov || !$provPt) && is_array($rpJ)) {
                    $extra = self::extractProviderErrorsFromPayload($rpJ);
                    if (!$prov && isset($extra['errors'])) $prov = $extra['errors'];
                    if (!$provPt && isset($extra['errors_pt'])) $provPt = $extra['errors_pt'];
                }
                if ($prov) $resp['provider_errors'] = $prov;
                if ($provPt) $resp['provider_errors_pt'] = $provPt;
            }
            $resp['summary'] = self::buildPaymentSummary($payment, $resp);
            // Root status/message/status_code
            $norm = strtolower((string)($payment['status'] ?? ''));
            $isError = in_array($norm, ['failed','canceled','chargeback'], true);
            $acq = self::extractAcquirerMessageFromPaymentRow($payment);
            $txStatus = self::extractTxStatusFromPaymentRow($payment);
            $afStatus = self::extractAntifraudStatusFromPaymentRow($payment);
            if ($isError) {
                if (in_array((string)$afStatus, ['reproved','rejected','declined'], true)) {
                    $code = 'antifraud_reproved';
                } elseif (!empty($txStatus)) {
                    $code = $txStatus;
                } else {
                    $code = self::extractAcquirerReturnCodeFromPaymentRow($payment) ?? ($norm ?: null);
                }
            } else {
                $code = self::extractAcquirerReturnCodeFromPaymentRow($payment) ?? ($txStatus ?? $norm ?: null);
            }
            $failMsg = $isError ? (self::extractFailureReasonFromPaymentRow($payment) ?: null) : null;
            if ($isError) {
                $af = self::extractAntifraudStatusFromPaymentRow($payment);
                if (in_array($af, ['reproved','rejected','declined'], true)) $failMsg = $failMsg ?: 'Transação reprovada na análise antifraude';
            }
            // Mensagem raiz sempre em PT; expor acquirer_message separadamente
            $msg = $isError ? ($failMsg ?: self::friendlyMessageFromStatus($norm)) : self::friendlyMessageFromStatus($norm);
            if ($isError) $msg = $this->appendEnvDebug($msg, $envDebug);
            $resp['status'] = $isError ? 'error' : 'success';
            $resp['message'] = $msg;
            $resp['status_code'] = $code;
            $acq = $this->sanitizeAcquirerMessage($acq, $isError, $afStatus, $txStatus);
            if ($acq !== null && $acq !== '') $resp['acquirer_message'] = $acq;
            return Response::json($resp, 201);
        } catch (StoneApiException $e) {
            $errs = $e->getErrors();
            $ptMsg = \App\Support\I18n::ptHttpMessage($e->getStatus(), 'Erro ao processar pagamento');
            $raw = $e->getRaw();
            if (is_array($raw)) {
                $detail = self::buildProviderErrorMessageFromPayload($raw);
                if ($detail) $ptMsg = self::mergeMessagesPt($ptMsg, $detail);
            }
            $ptMsg = $this->appendEnvDebug($ptMsg, $envDebug);
            $errsPt = self::translateErrorsToPt($errs);
            $out = [
                'status' => 'error',
                'message' => $ptMsg,
                'status_code' => $e->getErrorCode() ?: (string)$e->getStatus(),
                'http_status' => $e->getStatus(),
                'provider_message' => $e->getErrorMessage(),
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getErrorMessage(),
                'errors' => $errs,
                'errors_en' => $errs,
                'errors_pt' => $errsPt,
            ];
            return Response::json($out, 422);
        } catch (\InvalidArgumentException $e) {
            $out = ['status' => 'error', 'message' => $e->getMessage(), 'status_code' => 'validation_error', 'http_status' => 422];
            return Response::json($out, 422);
        } catch (\Throwable $e) {
            $out = ['status' => 'error', 'message' => $e->getMessage(), 'status_code' => 'internal_error', 'http_status' => 500];
            return Response::json($out, 500);
        }
    }

    private function isSandboxEnv(string $env): bool
    {
        $env = strtolower(trim($env));
        if ($env === 'homolog') $env = 'sandbox';
        return $env === 'sandbox';
    }

    private function appendEnvDebug(string $message, ?string $env): string
    {
        $debug = strtoupper(trim((string)\App\Support\Env::get('DEBUG', '')));
        if ($debug !== 'S') return trim($message);
        $msg = trim($message);
        $e = strtolower(trim((string)$env));
        if ($e === '') return $msg;
        if ($e === 'homolog') $e = 'sandbox';
        if (str_contains(strtolower($msg), 'ambiente')) return $msg;
        return $msg . ' (Ambiente de validação: ' . $e . ')';
    }

    private function resolveRequireBillingAddress(array $site, ?array $acc): bool
    {
        $env = (string)($acc['active_env'] ?? '');
        if ($this->isSandboxEnv($env)) return false;
        if (array_key_exists('require_billing_address', $site)) {
            $v = $site['require_billing_address'];
            if ($v !== null && $v !== '') return ((int)$v) === 1;
        }
        return (string)Env::get('REQUIRE_BILLING_ADDRESS_FOR_CARD', '0') === '1';
    }

    private function tokenizeCardForAccount(array $acc, array $cardPayload): array
    {
        $env = strtolower((string)($acc['active_env'] ?? 'sandbox'));
        if ($env === 'homolog') $env = 'sandbox';
        $publicKey = $env === 'production'
            ? (string)($acc['public_key_production'] ?? '')
            : (string)($acc['public_key_sandbox'] ?? '');
        if ($publicKey === '') {
            return ['ok' => false, 'status' => 409, 'message' => 'Chave pública ausente para o ambiente ativo da conta'];
        }
        $def = 'https://api.pagar.me/core/v5';
        $envKey = $env === 'production' ? 'STONE_BASE_URL_PRODUCTION' : 'STONE_BASE_URL_SANDBOX';
        $base = rtrim(Env::get($envKey, $def), '/');
        $url = $base . '/tokens?appId=' . urlencode($publicKey);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $body = json_encode(['type' => 'card', 'card' => $cardPayload], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => (int)Env::get('HTTP_TIMEOUT', '10'),
            CURLOPT_CONNECTTIMEOUT => (int)Env::get('HTTP_CONNECT_TIMEOUT', '5'),
            CURLOPT_POSTFIELDS => $body,
        ];
        curl_setopt_array($ch, $opts);
        $respBody = curl_exec($ch);
        $err = curl_error($ch);
        $errno = curl_errno($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        curl_close($ch);

        if ($respBody === false || $err) {
            $msg = $err ?: 'Erro de conexão com a Stone';
            $code = ($errno === CURLE_OPERATION_TIMEDOUT || str_contains(strtolower($msg), 'timed out')) ? 504 : 502;
            return ['ok' => false, 'status' => $code, 'message' => $msg];
        }
        $json = json_decode((string)$respBody, true);
        if (!is_array($json)) {
            return ['ok' => false, 'status' => 502, 'message' => 'Resposta inválida da Stone'];
        }
        if ($status >= 400) {
            $msg = '';
            if (isset($json['message']) && is_string($json['message'])) $msg = $json['message'];
            if ($msg === '' && isset($json['errors'][0])) {
                $e0 = $json['errors'][0];
                if (is_array($e0) && isset($e0['message'])) $msg = (string)$e0['message'];
                elseif (is_string($e0)) $msg = $e0;
            }
            if ($msg === '') $msg = 'Stone API error';
            return ['ok' => false, 'status' => $status, 'message' => $msg, 'errors' => ($json['errors'] ?? null)];
        }
        $token = (string)($json['id'] ?? ($json['token'] ?? ''));
        return ['ok' => true, 'status' => $status, 'token' => $token, 'data' => $json];
    }

    private function extractBillingAddressFromInput(array $data, array $cardInput): ?array
    {
        if (isset($cardInput['billing_address']) && is_array($cardInput['billing_address'])) return $cardInput['billing_address'];
        if (isset($data['billing_address']) && is_array($data['billing_address'])) return $data['billing_address'];
        if (isset($data['endereco_cobranca']) && is_array($data['endereco_cobranca'])) return $data['endereco_cobranca'];
        if (isset($cardInput['endereco_cobranca']) && is_array($cardInput['endereco_cobranca'])) return $cardInput['endereco_cobranca'];
        if (isset($data['customer']['billing_address']) && is_array($data['customer']['billing_address'])) return $data['customer']['billing_address'];
        if (isset($data['customer']['address']) && is_array($data['customer']['address'])) return $data['customer']['address'];
        return null;
    }

    private function stripRawCardFields(array &$data): void
    {
        $top = [
            'number','card_number','numero_cartao','numeroCartao','cvv','cvc',
            'exp_month','exp_year','card_exp_month','card_exp_year','mes_validade','mesValidade','ano_validade','anoValidade',
            'validade','validade_cartao',
        ];
        foreach ($top as $k) {
            if (array_key_exists($k, $data)) unset($data[$k]);
        }
        if (isset($data['card']) && is_array($data['card'])) {
            foreach (['number','cvv','cvc','exp_month','exp_year','validade','validade_cartao'] as $k) {
                if (array_key_exists($k, $data['card'])) unset($data['card'][$k]);
            }
        }
    }

    private function sanitizeAcquirerMessage(?string $message, bool $isError, ?string $afStatus, ?string $txStatus): ?string
    {
        if (!$message) return null;
        $msg = trim($message);
        if ($msg === '') return null;
        $lc = strtolower($msg);
        $af = strtolower((string)($afStatus ?? ''));
        $tx = strtolower((string)($txStatus ?? ''));
        $approved = str_contains($lc, 'aprovada') || str_contains($lc, 'approved') || str_contains($lc, 'success') || str_contains($lc, 'authorized');
        if ($isError) {
            if (in_array($af, ['reproved','rejected','declined'], true)) return null;
            if (in_array($tx, ['not_authorized','failed','rejected','declined'], true) && $approved) return null;
            if ($approved) return null;
        }
        return $msg;
    }

    private function isTokenErrorMessage(string $message): bool
    {
        $m = strtolower(trim($message));
        if ($m === '') return false;
        if (!str_contains($m, 'token')) return false;
        return str_contains($m, 'not found')
            || str_contains($m, 'invalid')
            || str_contains($m, 'expired')
            || str_contains($m, 'already')
            || str_contains($m, 'used')
            || str_contains($m, 'reuse')
            || str_contains($m, 'reused');
    }

    private function hasTokenErrorInErrors($errors): bool
    {
        if (!is_array($errors)) return false;
        $msgs = self::flattenProviderErrorMessages($errors);
        foreach ($msgs as $m) {
            if ($this->isTokenErrorMessage((string)$m)) return true;
        }
        return false;
    }

    private function logDebugHint(string $type, array $context = []): void
    {
        try {
            $root = dirname(__DIR__, 3);
            $dir = $root . '/storage/logs/api';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            if (!is_dir($dir)) return;
            $file = $dir . '/token-hints-' . date('Y-m-d') . '.log';
            $row = [
                'time' => date('Y-m-d H:i:s'),
                'type' => $type,
                'context' => $context,
            ];
            @file_put_contents($file, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
        } catch (\Throwable $e) {
            // ignore logging errors
        }
    }
}
