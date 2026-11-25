<?php

namespace App\Support;

final class Money
{
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '−' : '';
        $cents = abs($cents);
        $whole = intdiv($cents, 100);
        $fraction = str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

        return $sign.number_format($whole, 0, ',', ' ').','.$fraction.' €';
    }

    public static function toInput(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);
        $whole = intdiv($cents, 100);
        $fraction = str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

        return $sign.$whole.','.$fraction;
    }

    public static function normalize(string $amount): string
    {
        $amount = str_replace('.', ',', trim($amount));

        return preg_replace('/^,/', '0,', $amount) ?? $amount;
    }

    public static function toCents(string $amount): ?int
    {
        $normalized = str_replace([' ', "\u{00A0}"], '', trim(self::normalize($amount)));
        $normalized = str_replace(',', '.', $normalized);

        if ($normalized === '' || ! preg_match('/^-?\d+(\.\d{1,2})?$/', $normalized)) {
            return null;
        }

        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }
}
