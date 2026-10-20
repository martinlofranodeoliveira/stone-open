<?php
declare(strict_types=1);

namespace App\Support;

final class I18n
{
    public static function ptHttpMessage(int $status, ?string $fallback = null): string
    {
        return match (true) {
            $status === 400 => 'Requisição inválida',
            $status === 401 => 'Não autorizado',
            $status === 403 => 'Acesso negado',
            $status === 404 => 'Recurso não encontrado',
            $status === 409 => 'Conflito',
            $status === 422 => 'Erro de validação',
            $status === 429 => 'Muitas requisições',
            $status >= 500 => 'Erro interno do servidor',
            default => ($fallback ?? 'Erro ao processar a requisição'),
        };
    }

    public static function translateProviderMessagePt(string $message): string
    {
        $raw = trim($message);
        if ($raw === '') return $message;
        $m = strtolower($raw);
        $map = [
            // Card verification
            'could not create credit card. the card verification failed.' => 'Não foi possível validar o cartão. O número é inválido ou falhou na verificação. Em homologação, use cartão de teste válido (Luhn).',
            'card verification failed' => 'Não foi possível validar o cartão. O número é inválido ou falhou na verificação. Em homologação, use cartão de teste válido (Luhn).',
            'verification failed' => 'Não foi possível validar o cartão. O número é inválido ou falhou na verificação. Em homologação, use cartão de teste válido (Luhn).',
            'could not create credit card' => 'Não foi possível validar o cartão. Confira os dados informados e tente novamente.',

            // Card data validation
            'invalid_cvv' => 'CVV inválido. Verifique os 3 ou 4 dígitos e tente novamente.',
            'invalid cvc' => 'CVV inválido. Verifique os 3 ou 4 dígitos e tente novamente.',
            'invalid_number' => 'Número do cartão inválido. Confira os dígitos e tente novamente.',
            'not a valid card number' => 'Número do cartão inválido. Confira os dígitos e tente novamente.',
            'invalid_card' => 'Cartão inválido. Verifique os dados do cartão e tente novamente.',
            'invalid expiration' => 'Validade inválida. Use o formato MM/AA e tente novamente.',
            'invalid_expiration' => 'Validade inválida. Use o formato MM/AA e tente novamente.',
            'card_expired' => 'Cartão vencido. Use outro cartão ou atualize a validade.',
            'expired card' => 'Cartão vencido. Use outro cartão ou atualize a validade.',
            'invalid_holder' => 'Nome do titular inválido. Use o nome exatamente como está no cartão.',
            'holder name' => 'Nome do titular inválido. Use o nome exatamente como está no cartão.',
            'invalid_document' => 'Documento do titular inválido. Confira CPF/CNPJ e tente novamente.',
            'holder_document' => 'Documento do titular inválido. Confira CPF/CNPJ e tente novamente.',
            'incorrect_zip' => 'CEP inválido no endereço de cobrança. Confira e tente novamente.',
            'invalid billing' => 'Endereço de cobrança inválido. Verifique rua, número, CEP, cidade, estado e país.',

            // Business / risk / issuer
            'insufficient_funds' => 'Saldo insuficiente. Use outro cartão ou limite disponível.',
            'insufficient funds' => 'Saldo insuficiente. Use outro cartão ou limite disponível.',
            'do_not_honor' => 'Transação não autorizada pelo banco emissor. Contate o banco ou use outro cartão.',
            'do not honor' => 'Transação não autorizada pelo banco emissor. Contate o banco ou use outro cartão.',
            'rejected_by_antifraud' => 'Transação recusada pelo antifraude. Confira os dados ou use outro cartão.',
            'antifraud' => 'Transação recusada por regra antifraude. Confira os dados ou use outro cartão.',
            'suspected_fraud' => 'Transação recusada por suspeita de fraude. Contate o banco ou use outro cartão.',
            'stolen' => 'Cartão reportado como roubado. Contate o banco emissor.',
            'lost' => 'Cartão reportado como perdido. Contate o banco emissor.',
            'blocked' => 'Cartão bloqueado. Contate o banco emissor.',
            'restricted' => 'Cartão com restrição para este tipo de compra. Contate o banco emissor.',
            'pickup_card' => 'Cartão retido pelo emissor. Contate o banco emissor.',
            'call_issuer' => 'Transação exige contato com o banco emissor. Peça para o titular contatar o banco.',
            'issuer_unavailable' => 'Banco emissor indisponível no momento. Tente novamente em alguns minutos.',
            'acquirer_timeout' => 'Tempo de resposta da adquirente excedido. Tente novamente.',
            'acquirer' => 'Transação recusada pela adquirente. Tente novamente ou use outro cartão.',
            'processing_error' => 'Erro no processamento pela adquirente. Tente novamente em alguns minutos.',
            'internal_error' => 'Erro interno do provedor. Tente novamente em alguns minutos.',
            'no_acquirer' => 'Nenhuma adquirente disponível para processar. Tente novamente mais tarde.',

            // Generic validation / request errors
            'invalid_parameter' => 'Parâmetro inválido ou ausente na requisição.',
            'validation_error' => 'Erro de validação. Confira os dados e tente novamente.',
            'request is invalid' => 'Requisição inválida. Confira os dados enviados.',
            'the request is invalid' => 'Requisição inválida. Confira os dados enviados.',
            'action_forbidden' => 'Ação não permitida para esta credencial/conta.',
            'not_found' => 'Recurso não encontrado.',
        ];
        foreach ($map as $k => $pt) {
            if (str_contains($m, $k)) return $pt;
        }
        if (str_contains($m, 'validation_error') && str_contains($m, 'billing') && str_contains($m, 'value')) {
            return 'Endereço de cobrança é obrigatório (billing_address). Informe rua (line_1), CEP, cidade, estado e país.';
        }
        if (str_contains($m, 'invalid') && str_contains($m, 'billing')) {
            return 'Endereço de cobrança inválido. Verifique rua, número, CEP, cidade, estado e país.';
        }
        if (str_contains($m, 'authentication') && str_contains($m, '3d')) {
            return 'Autenticação 3DS não concluída. Finalize a confirmação no app do banco e tente novamente.';
        }
        if (str_contains($m, 'authentication') && str_contains($m, 'failed')) {
            return 'Falha na autenticação do cartão. Tente novamente ou use outro cartão.';
        }
        return $message;
    }
}
