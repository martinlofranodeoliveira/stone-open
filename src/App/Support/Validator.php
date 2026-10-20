<?php
declare(strict_types=1);

namespace App\Support;

final class Validator
{
    private static function digitsOnly(string $s): string
    {
        return preg_replace('/\D+/', '', $s) ?? '';
    }

    private static function isValidDateTime(mixed $v): bool
    {
        if ($v instanceof \DateTimeInterface) return true;
        $raw = trim((string)($v ?? ''));
        if ($raw === '' || is_numeric($raw)) return false;
        try {
            new \DateTimeImmutable($raw);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Validate Brazilian CPF (11 digits, with check digits) */
    public static function isValidCpf(string $cpf): bool
    {
        $d = self::digitsOnly($cpf);
        if (strlen($d) !== 11) return false;
        if (preg_match('/^(\d)\1{10}$/', $d)) return false; // repeated digits
        $sum = 0; for ($i = 0, $w = 10; $i < 9; $i++, $w--) { $sum += (int)$d[$i] * $w; }
        $r = ($sum * 10) % 11; if ($r === 10) $r = 0; if ($r !== (int)$d[9]) return false;
        $sum = 0; for ($i = 0, $w = 11; $i < 10; $i++, $w--) { $sum += (int)$d[$i] * $w; }
        $r = ($sum * 10) % 11; if ($r === 10) $r = 0; if ($r !== (int)$d[10]) return false;
        return true;
    }

    /** Validate card expiry month/year (supports 2- or 4-digit year) */
    public static function isValidCardExpiry(int $expMonth, int $expYear): bool
    {
        if ($expMonth < 1 || $expMonth > 12) return false;
        $y = $expYear;
        if ($y < 100) { $y += 2000; }
        $nowY = (int)date('Y');
        $nowM = (int)date('n');
        if ($y < $nowY) return false;
        if ($y === $nowY && $expMonth < $nowM) return false;
        if ($y > $nowY + 30) return false;
        return true;
    }
    /**
     * Validate payment creation payload (POST /api/v1/payments)
     * Returns ['ok'=>bool, 'errors'=>array, 'required'=>array]
     */
    public static function payment(array $in, array $opts = []): array
    {
        $errorsEn = [];
        $errorsPt = [];
        $required = [];
        $skipCardNumberValidation = (bool)($opts['skip_card_number_validation'] ?? false);
        $requireBillingOpt = $opts['require_billing_address'] ?? null;

        // Normalize PT keys (do not mutate caller)
        $inp = $in;
        if (!isset($inp['amount_cents']) && isset($inp['valor_centavos'])) $inp['amount_cents'] = (int)$inp['valor_centavos'];
        if (!isset($inp['client_reference_id']) && isset($inp['referencia_cliente'])) $inp['client_reference_id'] = (string)$inp['referencia_cliente'];
        if (!isset($inp['idempotency_key']) && isset($inp['chave_idempotencia'])) $inp['idempotency_key'] = (string)$inp['chave_idempotencia'];
        if (!isset($inp['currency']) && isset($inp['moeda'])) $inp['currency'] = (string)$inp['moeda'];
        if (!isset($inp['payment_method_type']) && isset($inp['tipo_metodo_pagamento'])) $inp['payment_method_type'] = (string)$inp['tipo_metodo_pagamento'];
        if (!isset($inp['expires_at']) && isset($inp['expira_em'])) {
            if (is_numeric($inp['expira_em'])) $inp['expires_in'] = (int)$inp['expira_em'];
            else $inp['expires_at'] = (string)$inp['expira_em'];
        }
        if (!isset($inp['due_at'])) {
            if (isset($inp['data_vencimento'])) $inp['due_at'] = $inp['data_vencimento'];
            elseif (isset($inp['vencimento'])) $inp['due_at'] = $inp['vencimento'];
        }
        if (!isset($inp['due_at'])) {
            if (isset($inp['data_vencimento'])) $inp['due_at'] = $inp['data_vencimento'];
            elseif (isset($inp['vencimento'])) $inp['due_at'] = $inp['vencimento'];
        }

        // Common requirements
        if ((int)($inp['amount_cents'] ?? 0) <= 0) {
            $required[] = 'amount_cents|valor_centavos';
            $errorsEn['amount_cents'] = 'amount_cents (valor_centavos) must be > 0';
            $errorsPt['amount_cents'] = 'amount_cents (valor_centavos) deve ser maior que 0';
        }
        if (trim((string)($inp['client_reference_id'] ?? '')) === '') {
            $required[] = 'client_reference_id|referencia_cliente';
            $errorsEn['client_reference_id'] = 'client_reference_id (referencia_cliente) is required';
            $errorsPt['client_reference_id'] = 'client_reference_id (referencia_cliente) é obrigatório';
        }
        if (trim((string)($inp['payment_method_type'] ?? '')) === '') {
            $required[] = 'payment_method_type|tipo_metodo_pagamento';
            $errorsEn['payment_method_type'] = 'payment_method_type (tipo_metodo_pagamento) is required';
            $errorsPt['payment_method_type'] = 'payment_method_type (tipo_metodo_pagamento) é obrigatório';
        }
        if (isset($inp['idempotency_key']) && trim((string)$inp['idempotency_key']) !== '' && strlen(trim((string)$inp['idempotency_key'])) > 96) {
            $errorsEn['idempotency_key'] = 'idempotency_key must have at most 96 characters';
            $errorsPt['idempotency_key'] = 'idempotency_key deve ter no máximo 96 caracteres';
        }

        $method = (string)($inp['payment_method_type'] ?? '');
        // PIX: exigir customer com name, email, document e phones
        if ($method === 'pix') {
            if (empty($inp['customer']) || !is_array($inp['customer'])) {
                $required[] = 'customer';
                $errorsPt['customer'] = 'Para PIX, envie o objeto customer com nome, email, documento e telefone';
            } else {
                $c = $inp['customer'];
                if (trim((string)($c['name'] ?? '')) === '') { $required[]='customer.name'; $errorsPt['customer.name']='Nome é obrigatório para PIX'; }
                if (trim((string)($c['email'] ?? '')) === '') { $required[]='customer.email'; $errorsPt['customer.email']='Email é obrigatório para PIX'; }
                if (trim((string)($c['document'] ?? '')) === '') { $required[]='customer.document'; $errorsPt['customer.document']='Documento (CPF/CNPJ) é obrigatório para PIX'; }
                $phones = $c['phones'] ?? null; $hasPhone = is_array($phones) && !empty($phones['mobile_phone']);
                if (!$hasPhone && trim((string)($c['phone'] ?? '')) === '') {
                    $required[] = 'customer.phones.mobile_phone|customer.phone';
                    $errorsPt['customer.phones.mobile_phone'] = 'Telefone (country_code, area_code, number) é obrigatório para PIX';
                }
            }
        }
        if ($method === 'pix') {
            // Customer with name/email/document and at least one phone
            if (!isset($inp['customer']) || !is_array($inp['customer'])) {
                $required[] = 'customer';
                $errorsEn['customer'] = 'customer object is required for PIX';
                $errorsPt['customer'] = 'O objeto customer é obrigatório para PIX';
            } else {
                $c = $inp['customer'];
                if (trim((string)($c['name'] ?? '')) === '') {
                    $required[] = 'customer.name';
                    $errorsEn['customer.name'] = 'customer.name is required';
                    $errorsPt['customer.name'] = 'customer.name é obrigatório';
                }
                if (trim((string)($c['email'] ?? '')) === '') {
                    $required[] = 'customer.email';
                    $errorsEn['customer.email'] = 'customer.email is required';
                    $errorsPt['customer.email'] = 'customer.email é obrigatório';
                }
                if (trim((string)($c['document'] ?? ($c['cpf'] ?? ''))) === '') {
                    $required[] = 'customer.document|customer.cpf';
                    $errorsEn['customer.document'] = 'customer.document (cpf) is required';
                    $errorsPt['customer.document'] = 'customer.document (cpf) é obrigatório';
                } else {
                    $docPix = (string)($c['document'] ?? ($c['cpf'] ?? ''));
                    $digits = self::digitsOnly($docPix);
                    if (strlen($digits) === 11 && !self::isValidCpf($digits)) {
                        $errorsEn['customer.document'] = 'customer.document (cpf) is invalid';
                        $errorsPt['customer.document'] = 'customer.document (cpf) é inválido';
                    }
                }
                $phones = $c['phones'] ?? null;
                $hasHome = is_array($phones) && !empty($phones['home_phone']);
                $hasMobile = is_array($phones) && !empty($phones['mobile_phone']);
                $aliased = isset($c['phone']) || isset($c['telefone']) || isset($c['celular']);
                if (!$hasHome && !$hasMobile && !$aliased) {
                    $required[] = 'customer.phones.mobile_phone|customer.phones.home_phone (ou telefone/celular + ddd/ddi)';
                    $errorsEn['customer.phones'] = 'At least one customer phone is required for PIX (mobile_phone or home_phone)';
                    $errorsPt['customer.phones'] = 'Pelo menos um telefone do cliente é obrigatório para PIX (mobile_phone ou home_phone)';
                }
                // Validate address if provided
                if (isset($c['address']) && is_array($c['address'])) {
                    [$reqA, $errEnA, $errPtA] = self::validateAddress($c['address'], 'customer.address');
                    $required = array_merge($required, $reqA);
                    $errorsEn = array_merge($errorsEn, $errEnA);
                    $errorsPt = array_merge($errorsPt, $errPtA);
                }
            }
            // expires_at (date-time) or expires_in (seconds) optional, but validate when present
            if (isset($inp['expires_at'])) {
                if (!self::isValidDateTime($inp['expires_at'])) {
                    $errorsEn['expires_at'] = 'expires_at (expira_em) must be a valid date-time';
                    $errorsPt['expires_at'] = 'expires_at (expira_em) deve ser um date-time válido';
                }
            } elseif (isset($inp['expires_in']) && (int)$inp['expires_in'] <= 0) {
                $errorsEn['expires_in'] = 'expires_in (expira_em) must be > 0';
                $errorsPt['expires_in'] = 'expires_in (expira_em) deve ser maior que 0';
            }
        } elseif ($method === 'boleto') {
            // Customer required (name/email/document)
            if (!isset($inp['customer']) || !is_array($inp['customer'])) {
                $required[] = 'customer';
                $errorsEn['customer'] = 'customer object is required for boleto';
                $errorsPt['customer'] = 'O objeto customer é obrigatório para boleto';
            } else {
                $c = $inp['customer'];
                if (trim((string)($c['name'] ?? '')) === '') {
                    $required[] = 'customer.name';
                    $errorsEn['customer.name'] = 'customer.name is required';
                    $errorsPt['customer.name'] = 'customer.name é obrigatório';
                }
                if (trim((string)($c['email'] ?? '')) === '') {
                    $required[] = 'customer.email';
                    $errorsEn['customer.email'] = 'customer.email is required';
                    $errorsPt['customer.email'] = 'customer.email é obrigatório';
                }
                if (trim((string)($c['document'] ?? ($c['cpf'] ?? ''))) === '') {
                    $required[] = 'customer.document|customer.cpf';
                    $errorsEn['customer.document'] = 'customer.document (cpf) is required';
                    $errorsPt['customer.document'] = 'customer.document (cpf) é obrigatório';
                } else {
                    $docBol = (string)($c['document'] ?? ($c['cpf'] ?? ''));
                    $digits = self::digitsOnly($docBol);
                    if (strlen($digits) === 11 && !self::isValidCpf($digits)) {
                        $errorsEn['customer.document'] = 'customer.document (cpf) is invalid';
                        $errorsPt['customer.document'] = 'customer.document (cpf) é inválido';
                    }
                }
            }
            // due_at is required for boleto
            $dueAt = $inp['due_at'] ?? ($inp['boleto']['due_at'] ?? null);
            if (!$dueAt) {
                $required[] = 'due_at|data_vencimento|boleto.due_at';
                $errorsEn['due_at'] = 'due_at is required for boleto';
                $errorsPt['due_at'] = 'due_at é obrigatório para boleto';
            } elseif (!self::isValidDateTime($dueAt)) {
                $errorsEn['due_at'] = 'due_at must be a valid date-time';
                $errorsPt['due_at'] = 'due_at deve ser um date-time válido';
            }
        } elseif ($method === 'credit_card' || $method === 'debit_card') {
            $inst = (int)($inp['installments'] ?? 1);
            if ($inst <= 0) {
                $required[] = 'installments|parcelas';
                $errorsEn['installments'] = 'installments (parcelas) must be >= 1';
                $errorsPt['installments'] = 'installments (parcelas) deve ser >= 1';
            }
            $hasCard = false;
            if (!empty($inp['card_token']) || !empty($inp['card_id'])) $hasCard = true;
            if (!$hasCard && isset($inp['card']) && is_array($inp['card'])) {
                if (!empty($inp['card']['token']) || !empty($inp['card']['id']) || !empty($inp['card']['number'])) $hasCard = true;
                if (!empty($inp['card']['token'])) {
                    $tok = (string)$inp['card']['token'];
                    if (strlen($tok) > 36) {
                        $errorsEn['card.token'] = 'card.token must be a string with a maximum length of 36 (use token id from /tokens)';
                        $errorsPt['card.token'] = 'card.token deve ter no máximo 36 caracteres (use o id do token retornado por /tokens)';
                    }
                    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $tok)) {
                        $errorsEn['card.token_format'] = 'card.token contains invalid characters';
                        $errorsPt['card.token_format'] = 'card.token contém caracteres inválidos';
                    }
                }
                // Map possible billing_address sources into card.billing_address
                if (!isset($inp['card']['billing_address'])) {
                    $ba = null;
                    if (isset($inp['billing_address']) && is_array($inp['billing_address'])) $ba = $inp['billing_address'];
                    elseif (isset($inp['endereco_cobranca']) && is_array($inp['endereco_cobranca'])) $ba = $inp['endereco_cobranca'];
                    elseif (isset($inp['card']['endereco_cobranca']) && is_array($inp['card']['endereco_cobranca'])) $ba = $inp['card']['endereco_cobranca'];
                    elseif (isset($inp['customer']['billing_address']) && is_array($inp['customer']['billing_address'])) $ba = $inp['customer']['billing_address'];
                    elseif (isset($inp['customer']['address']) && is_array($inp['customer']['address'])) $ba = $inp['customer']['address'];
                    if (is_array($ba)) {
                        // Map PT keys to EN
                        $map = ['linha1'=>'line_1','line1'=>'line_1','linha2'=>'line_2','line2'=>'line_2','cep'=>'zip_code','zip'=>'zip_code','cidade'=>'city','municipio'=>'city','estado'=>'state','uf'=>'state','pais'=>'country'];
                        foreach ($map as $pt=>$en) { if (!isset($ba[$en]) && isset($ba[$pt])) $ba[$en] = $ba[$pt]; }
                        if (empty($ba['line_1'] ?? '') && !empty($ba['street'] ?? '') && !empty($ba['number'] ?? '')) {
                            $ba['line_1'] = (string)$ba['street'] . ', ' . (string)$ba['number'];
                        }
                        $inp['card']['billing_address'] = $ba;
                    }
                }
                // If billing_address provided, validate it
                if (isset($inp['card']['billing_address']) && is_array($inp['card']['billing_address'])) {
                    [$reqB, $errEnB, $errPtB] = self::validateAddress($inp['card']['billing_address'], 'card.billing_address');
                    $required = array_merge($required, $reqB);
                    $errorsEn = array_merge($errorsEn, $errEnB);
                    $errorsPt = array_merge($errorsPt, $errPtB);
                }
            }
            if (!$hasCard && isset($inp['payments']) && is_array($inp['payments'])) {
                $p0 = $inp['payments'][0] ?? [];
                $card = $p0['credit_card']['card'] ?? [];
                if (!empty($card['token']) || !empty($card['id']) || !empty($card['number'])) $hasCard = true;
            }
            if (!$skipCardNumberValidation) {
                $cardNumber = self::extractCardNumber($inp);
                if ($cardNumber !== null) {
                    $len = strlen($cardNumber);
                    if ($len < 13 || $len > 19) {
                        $errorsEn['card.number'] = 'card.number must have between 13 and 19 digits';
                        $errorsPt['card.number'] = 'card.number deve ter entre 13 e 19 dígitos';
                    }
                }
            }
            // Validate card expiry when raw card data provided
            if (isset($inp['card']) && is_array($inp['card'])) {
                $c = $inp['card'];
                if (!empty($c['number']) || !empty($c['holder_name'])) {
                    $mm = isset($c['exp_month']) ? (int)$c['exp_month'] : null;
                    $yy = isset($c['exp_year']) ? (int)$c['exp_year'] : null;
                    if ($mm === null || $yy === null) {
                        $required[] = 'card.exp_month|card.exp_year';
                        $errorsEn['card.expiry'] = 'card.exp_month and card.exp_year are required';
                        $errorsPt['card.expiry'] = 'card.exp_month e card.exp_year são obrigatórios';
                    } elseif (!self::isValidCardExpiry($mm, $yy)) {
                        $errorsEn['card.expiry'] = 'Card expiry date is invalid or in the past';
                        $errorsPt['card.expiry'] = 'Data de vencimento do cartão inválida ou no passado';
                    }
                }
            }
            if (!$hasCard) {
                $required[] = 'card_token|card_id|payments[0].credit_card.card.{token|id|number}';
                $errorsEn['card'] = 'Provide a card_token, card_id or full payments.credit_card.card payload';
                $errorsPt['card'] = 'Envie card_token, card_id ou o objeto completo payments.credit_card.card';
            }
            // Require billing_address if antifraud is enabled (env flag or override)
            $requireBilling = is_bool($requireBillingOpt)
                ? $requireBillingOpt
                : ((string)\App\Support\Env::get('REQUIRE_BILLING_ADDRESS_FOR_CARD', '0') === '1');
            if ($requireBilling) {
                $ba = $inp['card']['billing_address'] ?? null;
                if (!is_array($ba)) {
                    $required[] = 'card.billing_address.line_1|zip_code|city|state|country';
                    $errorsEn['card.billing_address'] = 'billing_address is required when antifraud is enabled';
                    $errorsPt['card.billing_address'] = 'billing_address é obrigatório quando o antifraude está habilitado';
                } else {
                    foreach (['line_1','zip_code','city','state','country'] as $f) {
                        if (!isset($ba[$f]) || trim((string)$ba[$f]) === '') {
                            $required[] = 'card.billing_address.' . $f;
                            $errorsEn['card.billing_address.' . $f] = $f . ' is required in billing_address';
                            $errorsPt['card.billing_address.' . $f] = $f . ' é obrigatório em billing_address';
                        }
                    }
                }
            }
            // Validate customer + address if provided
            if (isset($inp['customer']) && is_array($inp['customer'])) {
                $c = $inp['customer'];
                if (isset($c['address']) && is_array($c['address'])) {
                    [$reqA, $errEnA, $errPtA] = self::validateAddress($c['address'], 'customer.address');
                    $required = array_merge($required, $reqA);
                    $errorsEn = array_merge($errorsEn, $errEnA);
                    $errorsPt = array_merge($errorsPt, $errPtA);
                }
            }
        }

        $ok = empty($errorsEn) && empty($errorsPt);
        return [
            'ok' => $ok,
            'errors' => $errorsEn,
            'errors_en' => $errorsEn,
            'errors_pt' => $errorsPt,
            'required' => array_values(array_unique($required)),
        ];
    }

    /** Validate minimal input for POST /api/v1/payments/for-customer */
    public static function paymentForCustomer(array $in, array $opts = []): array
    {
        $errorsEn = [];
        $errorsPt = [];
        $required = [];
        $skipCardNumberValidation = (bool)($opts['skip_card_number_validation'] ?? false);
        $requireBillingOpt = $opts['require_billing_address'] ?? null;
        $inp = $in;
        if (!isset($inp['amount_cents']) && isset($inp['valor_centavos'])) $inp['amount_cents'] = (int)$inp['valor_centavos'];
        if (!isset($inp['client_reference_id']) && isset($inp['referencia_cliente'])) $inp['client_reference_id'] = (string)$inp['referencia_cliente'];
        if (!isset($inp['idempotency_key']) && isset($inp['chave_idempotencia'])) $inp['idempotency_key'] = (string)$inp['chave_idempotencia'];
        if (!isset($inp['payment_method_type']) && isset($inp['tipo_metodo_pagamento'])) $inp['payment_method_type'] = (string)$inp['tipo_metodo_pagamento'];
        if (!isset($inp['expires_at']) && isset($inp['expira_em'])) {
            if (is_numeric($inp['expira_em'])) $inp['expires_in'] = (int)$inp['expira_em'];
            else $inp['expires_at'] = (string)$inp['expira_em'];
        }

        if ((int)($inp['amount_cents'] ?? 0) <= 0) { $required[]='amount_cents|valor_centavos'; $errorsEn['amount_cents']='amount_cents (valor_centavos) must be > 0'; $errorsPt['amount_cents']='amount_cents (valor_centavos) deve ser maior que 0'; }
        if (trim((string)($inp['client_reference_id'] ?? '')) === '') { $required[]='client_reference_id|referencia_cliente'; $errorsEn['client_reference_id']='client_reference_id (referencia_cliente) is required'; $errorsPt['client_reference_id']='client_reference_id (referencia_cliente) é obrigatório'; }
        if (trim((string)($inp['payment_method_type'] ?? '')) === '') { $required[]='payment_method_type|tipo_metodo_pagamento'; $errorsEn['payment_method_type']='payment_method_type (tipo_metodo_pagamento) is required'; $errorsPt['payment_method_type']='payment_method_type (tipo_metodo_pagamento) é obrigatório'; }
        if (isset($inp['idempotency_key']) && trim((string)$inp['idempotency_key']) !== '' && strlen(trim((string)$inp['idempotency_key'])) > 96) { $errorsEn['idempotency_key'] = 'idempotency_key must have at most 96 characters'; $errorsPt['idempotency_key'] = 'idempotency_key deve ter no máximo 96 caracteres'; }

        if (trim((string)($inp['stone_customer_id'] ?? '')) === ''
            && trim((string)($inp['email'] ?? ($inp['customer_email'] ?? ''))) === ''
            && trim((string)($inp['document'] ?? ($inp['cpf'] ?? ''))) === '') {
            $required[] = 'stone_customer_id|email|document (cpf)';
            $errorsEn['customer_lookup'] = 'Provide stone_customer_id or email or document (cpf)';
            $errorsPt['customer_lookup'] = 'Informe stone_customer_id ou email ou document (cpf)';
        }

        // PIX specific minimal checks
        if (($inp['payment_method_type'] ?? null) === 'pix') {
            if (isset($inp['expires_at'])) {
                if (!self::isValidDateTime($inp['expires_at'])) {
                    $errorsEn['expires_at'] = 'expires_at (expira_em) must be a valid date-time';
                    $errorsPt['expires_at'] = 'expires_at (expira_em) deve ser um date-time válido';
                }
            } elseif (isset($inp['expires_in']) && (int)$inp['expires_in'] <= 0) {
                $errorsEn['expires_in'] = 'expires_in (expira_em) must be > 0';
                $errorsPt['expires_in'] = 'expires_in (expira_em) deve ser maior que 0';
            }
        }
        if (($inp['payment_method_type'] ?? null) === 'boleto') {
            $dueAt = $inp['due_at'] ?? ($inp['boleto']['due_at'] ?? null);
            if (!$dueAt) {
                $errorsEn['due_at'] = 'due_at is required for boleto';
                $errorsPt['due_at'] = 'due_at é obrigatório para boleto';
            } elseif (!self::isValidDateTime($dueAt)) {
                $errorsEn['due_at'] = 'due_at must be a valid date-time';
                $errorsPt['due_at'] = 'due_at deve ser um date-time válido';
            }
        }

        $ok = empty($errorsEn) && empty($errorsPt);
        return ['ok'=>$ok, 'errors'=>$errorsEn, 'errors_en'=>$errorsEn, 'errors_pt'=>$errorsPt, 'required'=>array_values(array_unique($required))];
    }

    /** Validate subscription creation payload (POST /api/v1/subscriptions) */
    public static function subscription(array $in): array
    {
        $errorsEn = [];
        $errorsPt = [];
        $required = [];
        $inp = $in;
        if (!isset($inp['stone_customer_id']) && isset($inp['id_cliente_stone'])) $inp['stone_customer_id'] = $inp['id_cliente_stone'];
        if (!isset($inp['stone_plan_id']) && isset($inp['id_plano_stone'])) $inp['stone_plan_id'] = $inp['id_plano_stone'];
        if (!isset($inp['amount_cents']) && isset($inp['valor_centavos'])) $inp['amount_cents'] = $inp['valor_centavos'];
        if (!isset($inp['currency']) && isset($inp['moeda'])) $inp['currency'] = $inp['moeda'];

        if (trim((string)($inp['stone_plan_id'] ?? ($inp['plano_id'] ?? ''))) === '') {
            $required[] = 'stone_plan_id|plano_id';
            $errorsEn['stone_plan_id'] = 'stone_plan_id (ou plano_id) is required';
            $errorsPt['stone_plan_id'] = 'stone_plan_id (ou plano_id) é obrigatório';
        }
        if (trim((string)($inp['stone_customer_id'] ?? '')) === '' && !isset($inp['customer'])) {
            $required[] = 'stone_customer_id|customer';
            $errorsEn['stone_customer_id'] = 'Provide stone_customer_id or full customer object';
            $errorsPt['stone_customer_id'] = 'Informe stone_customer_id ou o objeto customer completo';
        }

        // Validate embedded customer CPF if provided
        if (isset($inp['customer']) && is_array($inp['customer'])) {
            $doc = (string)($inp['customer']['document'] ?? ($inp['customer']['cpf'] ?? ''));
            if ($doc !== '') {
                $digits = self::digitsOnly($doc);
                if (strlen($digits) === 11 && !self::isValidCpf($digits)) {
                    $errorsEn['customer.document'] = 'customer.document (cpf) is invalid';
                    $errorsPt['customer.document'] = 'customer.document (cpf) é inválido';
                }
            }
        }

        $pm = strtolower((string)($inp['payment_method'] ?? 'credit_card'));
        if ($pm === 'credit_card') {
            $hasCard = false;
            if (!empty($inp['card_id'])) $hasCard = true;
            if (isset($inp['card']) && is_array($inp['card'])) {
                $c = $inp['card'];
                if (!empty($c['token']) || !empty($c['id']) || !empty($c['number'])) $hasCard = true;
                if (!empty($c['number']) || !empty($c['holder_name'])) {
                    $mm = isset($c['exp_month']) ? (int)$c['exp_month'] : null;
                    $yy = isset($c['exp_year']) ? (int)$c['exp_year'] : null;
                    if ($mm === null || $yy === null) {
                        $required[] = 'card.exp_month|card.exp_year';
                        $errorsEn['card.expiry'] = 'card.exp_month and card.exp_year are required';
                        $errorsPt['card.expiry'] = 'card.exp_month e card.exp_year são obrigatórios';
                    } elseif (!self::isValidCardExpiry($mm, $yy)) {
                        $errorsEn['card.expiry'] = 'Card expiry date is invalid or in the past';
                        $errorsPt['card.expiry'] = 'Data de vencimento do cartão inválida ou no passado';
                    }
                }
            }
            if (!$hasCard) {
                $required[] = 'card_id|card.token|card.id|card.number';
                $errorsEn['card'] = 'Provide card_id or card.token (or send a saved default card on Stone)';
                $errorsPt['card'] = 'Informe card_id ou card.token (ou utilize um cartão salvo na Stone)';
            }
            if (!$skipCardNumberValidation) {
                $cardNumber = self::extractCardNumber($inp);
                if ($cardNumber !== null) {
                    $len = strlen($cardNumber);
                    if ($len < 13 || $len > 19) {
                        $errorsEn['card.number'] = 'card.number must have between 13 and 19 digits';
                        $errorsPt['card.number'] = 'card.number deve ter entre 13 e 19 dígitos';
                    }
                }
            }
        }
        // Require billing_address if antifraud is enabled (env flag or override)
        $requireBilling = is_bool($requireBillingOpt)
            ? $requireBillingOpt
            : ((string)\App\Support\Env::get('REQUIRE_BILLING_ADDRESS_FOR_CARD', '0') === '1');
        if ($requireBilling) {
            $ba = $inp['card']['billing_address'] ?? null;
            if (!is_array($ba)) {
                $required[] = 'card.billing_address.line_1|zip_code|city|state|country';
                $errorsEn['card.billing_address'] = 'billing_address is required when antifraud is enabled';
                $errorsPt['card.billing_address'] = 'billing_address é obrigatório quando o antifraude está habilitado';
            } else {
                foreach (['line_1','zip_code','city','state','country'] as $f) {
                    if (!isset($ba[$f]) || trim((string)$ba[$f]) === '') {
                        $required[] = 'card.billing_address.' . $f;
                        $errorsEn['card.billing_address.' . $f] = $f . ' is required in billing_address';
                        $errorsPt['card.billing_address.' . $f] = $f . ' é obrigatório em billing_address';
                    }
                }
            }
        }
        $ok = empty($errorsEn) && empty($errorsPt);
        return ['ok'=>$ok, 'errors'=>$errorsEn, 'errors_en'=>$errorsEn, 'errors_pt'=>$errorsPt, 'required'=>array_values(array_unique($required))];
    }

    private static function extractCardNumber(array $inp): ?string
    {
        $num = null;
        if (isset($inp['card']) && is_array($inp['card'])) {
            if (isset($inp['card']['number'])) $num = $inp['card']['number'];
            elseif (isset($inp['card']['numero'])) $num = $inp['card']['numero'];
        }
        if (!$num && isset($inp['payments']) && is_array($inp['payments'])) {
            $p0 = $inp['payments'][0] ?? [];
            $card = $p0['credit_card']['card'] ?? [];
            if (is_array($card) && isset($card['number'])) $num = $card['number'];
        }
        $digits = preg_replace('/\D+/', '', (string)$num);
        return $digits !== '' ? $digits : null;
    }

    /** Validate plan creation payload (POST /api/v1/plans) and build Stone payload */
    public static function plan(array $in): array
    {
        $errorsEn = [];
        $errorsPt = [];
        $required = [];
        $inp = $in;
        // Map PT -> EN
        if (!isset($inp['name']) && isset($inp['nome'])) $inp['name'] = (string)$inp['nome'];
        if (!isset($inp['description']) && isset($inp['descricao'])) $inp['description'] = (string)$inp['descricao'];
        if (!isset($inp['currency']) && isset($inp['moeda'])) $inp['currency'] = (string)$inp['moeda'];
        if (!isset($inp['interval']) && isset($inp['intervalo'])) $inp['interval'] = (string)$inp['intervalo'];
        if (!isset($inp['interval_count']) && isset($inp['intervalo_qtd'])) $inp['interval_count'] = (int)$inp['intervalo_qtd'];
        if (!isset($inp['trial_period_days']) && isset($inp['dias_teste'])) $inp['trial_period_days'] = (int)$inp['dias_teste'];
        if (!isset($inp['billing_type']) && isset($inp['tipo_cobranca'])) $inp['billing_type'] = (string)$inp['tipo_cobranca'];
        if (!isset($inp['billing_day']) && isset($inp['dia_cobranca'])) $inp['billing_day'] = (int)$inp['dia_cobranca'];
        if (!isset($inp['minimum_price']) && isset($inp['valor_minimo'])) $inp['minimum_price'] = (int)$inp['valor_minimo'];
        if (!isset($inp['statement_descriptor']) && isset($inp['descricao_fatura'])) $inp['statement_descriptor'] = (string)$inp['descricao_fatura'];
        if (!isset($inp['payment_methods']) && isset($inp['metodos_pagamento'])) $inp['payment_methods'] = (array)$inp['metodos_pagamento'];
        if (!isset($inp['installments']) && isset($inp['parcelas'])) $inp['installments'] = (array)$inp['parcelas'];

        // Required: name
        if (trim((string)($inp['name'] ?? '')) === '') { $required[]='name|nome'; $errorsEn['name']='name (nome) is required'; $errorsPt['name']='name (nome) é obrigatório'; }
        // interval
        $interval = strtolower((string)($inp['interval'] ?? ''));
        $mapIntervals = ['dia'=>'day','semana'=>'week','mes'=>'month','mês'=>'month','ano'=>'year'];
        if (isset($mapIntervals[$interval])) $interval = $mapIntervals[$interval];
        if (!in_array($interval, ['day','week','month','year'], true)) { $required[]='interval|intervalo'; $errorsEn['interval']='interval must be day|week|month|year'; $errorsPt['interval']='interval (intervalo) deve ser day|week|month|year'; }
        $count = (int)($inp['interval_count'] ?? 0);
        if ($count <= 0) { $required[]='interval_count|intervalo_qtd'; $errorsEn['interval_count']='interval_count must be > 0'; $errorsPt['interval_count']='interval_count (intervalo_qtd) deve ser > 0'; }
        // items (opcional)
        $items = $inp['items'] ?? ($inp['itens'] ?? null);

        // payment_methods mapping
        $pm = [];
        if (isset($inp['payment_methods']) && is_array($inp['payment_methods'])) {
            foreach ($inp['payment_methods'] as $m) {
                $m = strtolower((string)$m);
                if (in_array($m, ['cartao','cartão','credito','crédito'], true)) $m = 'credit_card';
                $pm[] = $m;
            }
            $pm = array_values(array_unique(array_filter($pm)));
            $inp['payment_methods'] = $pm;
        }
        // installments simple validation
        if (isset($inp['installments'])) {
            $inst = [];
            foreach ((array)$inp['installments'] as $i) {
                $n = (int)$i; if ($n > 0) $inst[] = $n;
            }
            $inp['installments'] = array_values(array_unique($inst));
        }
        // currency default BRL
        $curr = strtoupper((string)($inp['currency'] ?? 'BRL'));
        if ($curr === '') $curr = 'BRL';
        $inp['currency'] = $curr;
        if (strlen($curr) !== 3) { $errorsEn['currency']='currency must be 3-letter code'; $errorsPt['currency']='moeda deve ter 3 letras'; }

        // statement_descriptor length
        if (isset($inp['statement_descriptor']) && strlen((string)$inp['statement_descriptor']) > 13) {
            $errorsEn['statement_descriptor'] = 'statement_descriptor must be <= 13 chars';
            $errorsPt['statement_descriptor'] = 'descricao_fatura deve ter no máximo 13 caracteres';
        }
        // billing_type exact_day requires billing_day
        $bt = strtolower((string)($inp['billing_type'] ?? ''));
        if ($bt === 'exato' || $bt === 'exact' || $bt === 'dia_exato') $bt = 'exact_day';
        if ($bt !== '' && !in_array($bt, ['prepaid','postpaid','exact_day'], true)) {
            $errorsEn['billing_type'] = 'billing_type must be prepaid|postpaid|exact_day';
            $errorsPt['billing_type'] = 'tipo_cobranca deve ser prepaid|postpaid|exact_day';
        }
        if ($bt === 'exact_day') {
            $bd = (int)($inp['billing_day'] ?? 0);
            if ($bd < 1 || $bd > 31) { $required[]='billing_day|dia_cobranca (1..31)'; $errorsEn['billing_day']='billing_day is required for exact_day (1..31)'; $errorsPt['billing_day']='dia_cobranca é obrigatório para exact_day (1..31)'; }
        }

        // Build items payload mapping (opcional)
        $payloadItems = [];
        if (is_array($items)) {
            foreach ($items as $it) {
                if (!is_array($it)) continue;
                $nm = $it['name'] ?? ($it['nome'] ?? null);
                $qty = (int)($it['quantity'] ?? ($it['quantidade'] ?? 1));
                $cycles = isset($it['cycles']) ? (int)$it['cycles'] : (isset($it['ciclos']) ? (int)$it['ciclos'] : null);
                $price = $it['pricing_scheme']['price'] ?? null;
                if ($price === null) {
                    $price = $it['preco_centavos'] ?? ($it['valor_centavos'] ?? ($it['price'] ?? null));
                }
                $price = (int)$price;
                if (trim((string)$nm) === '' || $price <= 0) {
                    $errorsEn['items'][] = 'each item must have name and price (cents) > 0';
                    $errorsPt['items'][] = 'cada item deve ter nome e preco_centavos > 0';
                    continue;
                }
                $pi = [ 'name' => (string)$nm, 'quantity' => max(1, $qty), 'pricing_scheme' => ['price' => $price] ];
                if ($cycles !== null) $pi['cycles'] = $cycles;
                $payloadItems[] = $pi;
            }
        }
        // Fallback: permitir API sem itens explícitos, usando valor_centavos/amount_cents para montar item padrão
        if (empty($payloadItems)) {
            $amountTop = isset($inp['valor_centavos']) ? (int)$inp['valor_centavos'] : (int)($inp['amount_cents'] ?? 0);
            $nmTop = (string)($inp['name'] ?? '');
            if ($amountTop > 0 && $nmTop !== '') {
                $payloadItems[] = [
                    'name' => $nmTop,
                    'quantity' => 1,
                    'pricing_scheme' => ['price' => $amountTop],
                ];
            }
        }
        // Itens não são obrigatórios: ok sem items
        $ok = empty($errorsEn) && empty($errorsPt) && $interval !== '' && $count > 0 && trim((string)($inp['name'] ?? '')) !== '';

        $payload = null;
        if ($ok) {
            $payload = [
                'name' => (string)$inp['name'],
                'description' => isset($inp['description']) ? (string)$inp['description'] : null,
                'currency' => $curr,
                'interval' => $interval,
                'interval_count' => $count,
            ];
            if (!empty($payloadItems)) $payload['items'] = $payloadItems;
            if (!empty($pm)) $payload['payment_methods'] = $pm;
            if (!empty($inp['installments'])) $payload['installments'] = $inp['installments'];
            if (isset($inp['minimum_price'])) $payload['minimum_price'] = max(0, (int)$inp['minimum_price']);
            if (isset($inp['statement_descriptor'])) $payload['statement_descriptor'] = (string)$inp['statement_descriptor'];
            if (isset($inp['trial_period_days'])) $payload['trial_period_days'] = max(0, (int)$inp['trial_period_days']);
            if ($bt !== '') $payload['billing_type'] = $bt;
            if ($bt === 'exact_day') $payload['billing_day'] = (int)$inp['billing_day'];
        }
        return [
            'ok' => $ok,
            'errors' => $errorsEn,
            'errors_en' => $errorsEn,
            'errors_pt' => $errorsPt,
            'required' => array_values(array_unique($required)),
            'payload' => $payload,
        ];
    }

    public static function cardQuote(array $q): array
    {
        $errorsEn = [];
        $errorsPt = [];
        $required = [];
        $amount = (int)($q['amount_cents'] ?? ($q['valor_centavos'] ?? 0));
        $bin = (string)($q['bin'] ?? '');
        if ($bin === '' && !empty($q['numero_cartao'])) {
            $digits = preg_replace('/\D/', '', (string)$q['numero_cartao']);
            if (strlen($digits) >= 6) $bin = substr($digits, 0, 6);
        }
        if ($amount <= 0) { $required[]='amount_cents|valor_centavos'; $errorsEn['amount_cents']='amount_cents (valor_centavos) must be > 0'; $errorsPt['amount_cents']='amount_cents (valor_centavos) deve ser maior que 0'; }
        if ($bin === '') { $required[]='bin|numero_cartao'; $errorsEn['bin']='bin (ou numero_cartao) is required'; $errorsPt['bin']='bin (ou numero_cartao) é obrigatório'; }
        elseif (strlen(preg_replace('/\D/','',$bin)) < 6) { $errorsEn['bin']='bin must have at least 6 digits'; $errorsPt['bin']='bin deve ter pelo menos 6 dígitos'; }
        $ok = empty($errorsEn) && empty($errorsPt);
        return ['ok'=>$ok, 'errors'=>$errorsEn, 'errors_en'=>$errorsEn, 'errors_pt'=>$errorsPt, 'required'=>array_values(array_unique($required))];
    }

    public static function cardBrand(array $q): array
    {
        $errorsEn = [];
        $errorsPt = [];
        $required = [];
        $bin = (string)($q['bin'] ?? '');
        if ($bin === '' && !empty($q['numero_cartao'])) {
            $digits = preg_replace('/\D/', '', (string)$q['numero_cartao']);
            if (strlen($digits) >= 6) $bin = substr($digits, 0, 6);
        }
        if ($bin === '') { $required[]='bin|numero_cartao'; $errorsEn['bin']='bin (ou numero_cartao) is required'; $errorsPt['bin']='bin (ou numero_cartao) é obrigatório'; }
        elseif (strlen(preg_replace('/\D/','',$bin)) < 6) { $errorsEn['bin']='bin must have at least 6 digits'; $errorsPt['bin']='bin deve ter pelo menos 6 dígitos'; }
        $ok = empty($errorsEn) && empty($errorsPt);
        return ['ok'=>$ok, 'errors'=>$errorsEn, 'errors_en'=>$errorsEn, 'errors_pt'=>$errorsPt, 'required'=>array_values(array_unique($required))];
    }

    /** Validate customer create (used by /customers/upsert when creating) */
    public static function customerCreate(array $in): array
    {
        $errorsEn = [];
        $errorsPt = [];
        $required = [];
        if (trim((string)($in['name'] ?? '')) === '') { $required[]='name'; $errorsEn['name']='name is required'; $errorsPt['name']='name é obrigatório'; }
        if (trim((string)($in['email'] ?? '')) === '') { $required[]='email'; $errorsEn['email']='email is required'; $errorsPt['email']='email é obrigatório'; }
        if (trim((string)($in['document'] ?? ($in['cpf'] ?? ''))) === '') { $required[]='document|cpf'; $errorsEn['document']='document (cpf) is required'; $errorsPt['document']='document (cpf) é obrigatório'; }
        else {
            $doc = (string)($in['document'] ?? ($in['cpf'] ?? ''));
            $digits = self::digitsOnly($doc);
            if (strlen($digits) === 11 && !self::isValidCpf($digits)) {
                $errorsEn['document'] = 'document (cpf) is invalid';
                $errorsPt['document'] = 'document (cpf) é inválido';
            }
        }
        $type = (string)($in['type'] ?? '');
        if ($type === '') { $required[]='type'; $errorsEn['type'] = 'type is required'; $errorsPt['type'] = 'type é obrigatório'; }
        $phones = $in['phones'] ?? null;
        $hasHome = is_array($phones) && !empty($phones['home_phone']);
        $hasMobile = is_array($phones) && !empty($phones['mobile_phone']);
        $aliased = isset($in['phone']) || isset($in['telefone']) || isset($in['celular']);
        if (!$hasHome && !$hasMobile && !$aliased) {
            $required[] = 'phones.mobile_phone|phones.home_phone';
            $errorsEn['phones'] = 'At least one phone is required (mobile_phone or home_phone)';
            $errorsPt['phones'] = 'Pelo menos um telefone é obrigatório (mobile_phone ou home_phone)';
        }
        if (isset($in['address']) && is_array($in['address'])) {
            [$reqA, $errEnA, $errPtA] = self::validateAddress($in['address'], 'address');
            $required = array_merge($required, $reqA);
            $errorsEn = array_merge($errorsEn, $errEnA);
            $errorsPt = array_merge($errorsPt, $errPtA);
        }
        $ok = empty($errorsEn) && empty($errorsPt);
        return ['ok'=>$ok, 'errors'=>$errorsEn, 'errors_en'=>$errorsEn, 'errors_pt'=>$errorsPt, 'required'=>array_values(array_unique($required))];
    }

    private static function validateAddress(array $addr, string $prefix): array
    {
        $req = [];
        $en = [];
        $pt = [];
        $hasAny = false;
        foreach (['line_1','zip_code','city','state','country','street','number'] as $k) {
            if (array_key_exists($k, $addr)) { $hasAny = true; break; }
        }
        // zip_code: 8 digits
        if (isset($addr['zip_code'])) {
            $zip = preg_replace('/\D+/', '', (string)$addr['zip_code']);
            if ($zip === '' || strlen($zip) !== 8) {
                $en["$prefix.zip_code"] = ['zip_code must be 8 digits'];
                $pt["$prefix.zip_code"] = ['zip_code deve ter 8 dígitos'];
            }
        } elseif ($hasAny) {
            $req[] = "$prefix.zip_code";
        }
        // country: 2-letter code
        if (isset($addr['country'])) {
            $country = strtoupper((string)$addr['country']);
            if ($country === '' || strlen($country) !== 2 || !preg_match('/^[A-Z]{2}$/', $country)) {
                $en["$prefix.country"] = ['country must be exactly 2 letters (ISO)'];
                $pt["$prefix.country"] = ['country deve possuir exatamente 2 letras (ISO)'];
            }
        } elseif ($hasAny) {
            $req[] = "$prefix.country";
        }
        // state: 2-letter code (e.g., SP, MG)
        if (isset($addr['state'])) {
            $st = strtoupper((string)$addr['state']);
            if ($st === '' || strlen($st) !== 2 || !preg_match('/^[A-Z]{2}$/', $st)) {
                $en["$prefix.state"] = ['state must have exactly 2 letters'];
                $pt["$prefix.state"] = ['state deve possuir exatamente 2 letras'];
            }
        } elseif ($hasAny) {
            $req[] = "$prefix.state";
        }
        // city, street, number are recommended if address present
        if (isset($addr['city']) && trim((string)$addr['city']) === '') {
            $en["$prefix.city"] = ['city cannot be empty'];
            $pt["$prefix.city"] = ['city não pode ser vazio'];
        } elseif ($hasAny && !isset($addr['city'])) {
            $req[] = "$prefix.city";
        }
        if (isset($addr['street']) && trim((string)$addr['street']) === '') {
            $en["$prefix.street"] = ['street cannot be empty'];
            $pt["$prefix.street"] = ['street não pode ser vazio'];
        }
        if (isset($addr['number']) && trim((string)$addr['number']) === '') {
            $en["$prefix.number"] = ['number cannot be empty'];
            $pt["$prefix.number"] = ['number não pode ser vazio'];
        }
        // Require line_1 OR (street and number) when an address payload was provided
        $hasLine1 = trim((string)($addr['line_1'] ?? '')) !== '';
        $hasStreetNum = trim((string)($addr['street'] ?? '')) !== '' && trim((string)($addr['number'] ?? '')) !== '';
        if ($hasAny && !$hasLine1 && !$hasStreetNum) {
            $req[] = "$prefix.line_1|$prefix.street+$prefix.number";
            $en["$prefix.line_1"] = ['Provide line_1 or street+number'];
            $pt["$prefix.line_1"] = ['Informe line_1 ou street+number'];
        }
        return [$req, $en, $pt];
    }
}
