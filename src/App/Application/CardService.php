<?php
declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\Stone\StoneClient;
use App\Support\Env;

final class CardService
{
    public function quote(string $bin, int $amountCents, ?int $maxInstallments = null, ?int $freeInstallments = null, ?float $interestRate = null, ?int $minInstallmentCents = null, ?\App\Infrastructure\Stone\StoneClient $stoneClient = null, bool $forceInterestFree = false): array
    {
        $brand = null;
        $installments = null;
        $stone = $stoneClient; // Only call provider if client provided (multi-account)

        // Prefer unified endpoint if set (returns both)
        if ($stone) {
            try {
                $unified = $stone->cardInfoAndInstallments($bin, $amountCents);
                if (is_array($unified)) {
                    $brand = $unified['brand'] ?? $unified['card_brand'] ?? $brand;
                    if (isset($unified['installments'])) {
                        $installments = $unified['installments'];
                    }
                }
            } catch (\Throwable $e) {
                // ignore, fallback to separate endpoints
            }
        }

        // Try Stone BIN lookup first if brand still unknown
        if (!$brand && $stone) {
            try {
                $brandInfo = $stone->lookupCardBin($bin);
                $brand = $brandInfo['brand'] ?? $brandInfo['card_brand'] ?? null;
            } catch (\Throwable $e) {
                // fallback below
            }
        }
        if (!$brand) {
            $brand = self::detectBrandLocally($bin);
        }

        // Try Stone installments if still unknown
        if (!is_array($installments) && $stone) {
            try {
                $installments = $stone->getInstallments($amountCents, $maxInstallments, $freeInstallments, $interestRate, $minInstallmentCents, $brand, $bin);
            } catch (\Throwable $e) {
                // fallback compute locally
            }
        }
        if (!is_array($installments)) {
            $installments = self::computeInstallmentsLocally(
                $amountCents,
                $maxInstallments,
                $freeInstallments,
                $interestRate,
                $minInstallmentCents
            );
        }

        if ($forceInterestFree) {
            $installments = self::normalizeInterestFreeInstallments($installments, $amountCents, $minInstallmentCents);
        }

        // Build formatted entries
        $list = [];
        foreach ($installments as $row) {
            $per = (int)($row['amount_cents'] ?? 0);
            $tot = (int)($row['total_cents'] ?? 0);
            $n = (int)($row['number'] ?? 1);
            $rate = (float)($row['interest_rate'] ?? 0.0);
            $isFree = (bool)($row['is_interest_free'] ?? ($rate == 0.0));
            $list[] = array_merge($row, [
                'amount_formatted' => self::formatCents($per),
                'total_formatted' => self::formatCents($tot),
                'interest_rate_formatted' => self::formatPercent($rate),
                'label' => sprintf('%dx de %s', $n, self::formatCents($per)),
                'label_with_interest' => sprintf(
                    '%dx de %s%s',
                    $n,
                    self::formatCents($per),
                    $isFree ? ' (sem juros)' : (' (' . self::formatPercent($rate) . ' a.m.)')
                ),
            ]);
        }

        return [
            'brand' => $brand,
            'brand_icon' => APP_URL_STONE_HUB . self::brandIcon($brand),
            'installments' => $list,
        ];
    }

    public static function detectBrandLocally(string $bin): ?string
    {
        $bin = preg_replace('/\D/', '', $bin);
        if ($bin === '' || strlen($bin) < 4) return null;

        $patterns = [
            'visa' => '/^4\d{3}/',
            'mastercard' => '/^(5[1-5]\d{2}|2[2-7]\d{2})/',
            'amex' => '/^3[47]\d{2}/',
            'diners' => '/^3(0[0-5]|[68]\d)\d{1}/',
            'discover' => '/^(6011|65\d{2}|64[4-9]\d)/',
            'jcb' => '/^(352[89]|35[3-8]\d)/',
            'hipercard' => '/^(606282|3841(0|4|6))/',
            'elo' => '/^(4011(78|79)|431274|438935|451416|457393|504175|627780|636297|636368|650\d{2}|65165\d|65500\d)/',
            'aura' => '/^50\d{2}/',
        ];
        foreach ($patterns as $brand => $regex) {
            if (preg_match($regex, $bin)) return $brand;
        }
        return null;
    }

    public static function computeInstallmentsLocally(int $amountCents, ?int $maxInstallments, ?int $freeInstallments, ?float $interestRate, ?int $minInstallmentCents): array
    {
        $max = $maxInstallments ?? (int) Env::get('INSTALLMENTS_MAX', '12');
        $free = $freeInstallments ?? (int) Env::get('INSTALLMENTS_FREE', '1');
        $rate = $interestRate ?? (float) Env::get('INSTALLMENTS_INTEREST_RATE', '1.99'); // monthly %
        $min = $minInstallmentCents ?? (int) Env::get('INSTALLMENTS_MIN_AMOUNT_PER_INSTALLMENT_CENTS', '500');

        $rateDecimal = max(0.0, $rate) / 100.0;
        $result = [];
        for ($n = 1; $n <= $max; $n++) {
            if ($n <= $free || $rateDecimal == 0.0) {
                $per = intdiv($amountCents, $n);
                $total = $amountCents;
                if ($per < $min) continue;
                $result[] = [
                    'number' => $n,
                    'amount_cents' => $per,
                    'total_cents' => $total,
                    'interest_rate' => 0.0,
                    'is_interest_free' => true,
                ];
            } else {
                // Price table PMT formula: A = P * i * (1+i)^n / ((1+i)^n - 1)
                $i = $rateDecimal;
                $pow = (1 + $i) ** $n;
                $per = (int) round($amountCents * ($i * $pow) / ($pow - 1));
                $total = $per * $n;
                if ($per < $min) continue;
                $result[] = [
                    'number' => $n,
                    'amount_cents' => $per,
                    'total_cents' => $total,
                    'interest_rate' => $rateDecimal * 100.0,
                    'is_interest_free' => false,
                ];
            }
        }
        return $result;
    }

    private static function normalizeInterestFreeInstallments(array $installments, int $amountCents, ?int $minInstallmentCents): array
    {
        $min = $minInstallmentCents ?? (int) Env::get('INSTALLMENTS_MIN_AMOUNT_PER_INSTALLMENT_CENTS', '500');
        $out = [];
        foreach ($installments as $row) {
            $n = (int)($row['number'] ?? 1);
            if ($n <= 0) $n = 1;
            $per = intdiv($amountCents, $n);
            if ($per < $min) continue;
            $row['amount_cents'] = $per;
            $row['total_cents'] = $amountCents;
            $row['interest_rate'] = 0.0;
            $row['is_interest_free'] = true;
            $out[] = $row;
        }
        return $out;
    }

    private static function formatCents(int $cents): string
    {
        $v = number_format($cents / 100, 2, ',', '.');
        return 'R$ ' . $v;
    }

    private static function formatPercent(float $rate): string
    {
        return number_format($rate, 2, ',', '.') . '%';
    }

    public static function brandIcon(?string $brand): string
    {
        $b = strtolower((string)$brand);
        $map = [
            'visa' => '/assets/card-generic.svg',
            'mastercard' => '/assets/card-generic.svg',
            'amex' => '/assets/card-generic.svg',
            'elo' => '/assets/card-generic.svg',
            'hipercard' => '/assets/card-generic.svg',
            'diners' => '/assets/card-generic.svg',
            'discover' => '/assets/card-generic.svg',
            'jcb' => '/assets/card-generic.svg',
        ];
        return $map[$b] ?? '/assets/card-generic.svg';
    }
}
