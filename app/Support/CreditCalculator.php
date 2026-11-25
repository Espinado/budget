<?php

namespace App\Support;

final class CreditCalculator
{
    /**
     * Годовая ставка в тысячных долях процента: 12,9% = 12900, EURIBOR 2,153% = 2153.
     *
     * @return array{
     *     payment_cents: int,
     *     current_payment_cents: int,
     *     next_payment_cents: ?int,
     *     last_payment_cents: int,
     *     total_cents: int,
     *     overpayment_cents: int,
     *     rate_changes: bool,
     *     finished: bool
     * }|null
     */
    public function quote(
        int $principalCents,
        int $months,
        int $fixedMilli,
        int $euriborMilli = 0,
        int $paidMonths = 0,
        ?int $newFixedMilli = null,
        ?int $newEuriborMilli = null,
    ): ?array {
        if ($principalCents <= 0 || $months < 1 || $months > 600) {
            return null;
        }

        $fixedMilli = max(0, $fixedMilli);
        $euriborMilli = max(0, $euriborMilli);
        $paidMonths = min($months, max(0, $paidMonths));
        $annual = $fixedMilli + $euriborMilli;
        $rateChanges = $newFixedMilli !== null || $newEuriborMilli !== null;
        $newAnnual = $annual;

        if ($rateChanges) {
            $newAnnual = max(0, $newFixedMilli ?? $fixedMilli) + max(0, $newEuriborMilli ?? $euriborMilli);
            $rateChanges = $newAnnual !== $annual && $paidMonths < $months;
        }

        $regular = $this->annuity($principalCents, $months, $annual);
        $balance = $principalCents;
        $payment = $regular;
        $recast = false;
        $current = $regular;
        $next = null;
        $total = 0;
        $last = 0;

        for ($month = 1; $month <= $months && $balance > 0; $month++) {
            $applyNew = $rateChanges && $month > $paidMonths;

            if ($applyNew && ! $recast) {
                $payment = $this->annuity($balance, $months - $month + 1, $newAnnual);
                $next = $payment;
                $recast = true;
            }

            $interest = $this->interest($balance, $applyNew ? $newAnnual : $annual);
            $closes = $month === $months || $payment >= $balance + $interest;

            if ($closes) {
                $due = $balance + $interest;
                $balance = 0;
            } elseif ($payment <= $interest) {
                $due = $interest;
            } else {
                $due = $payment;
                $balance -= $payment - $interest;
            }

            if ($month === $paidMonths + 1) {
                $current = $due;
            }

            $total += $due;
            $last = $due;
        }

        if ($paidMonths >= $months) {
            $current = 0;
            $next = null;
        } elseif (! $rateChanges || $paidMonths + 1 >= $months) {
            $next = null;
        }

        return [
            'payment_cents' => $regular,
            'current_payment_cents' => $current,
            'next_payment_cents' => $next,
            'last_payment_cents' => $last,
            'total_cents' => $total,
            'overpayment_cents' => $total - $principalCents,
            'rate_changes' => $next !== null,
            'finished' => $paidMonths >= $months,
        ];
    }

    public function annuity(int $balance, int $months, int $annualMilli): int
    {
        if ($balance <= 0 || $months <= 0) {
            return 0;
        }

        if ($annualMilli <= 0) {
            return max(1, (int) round($balance / $months));
        }

        $monthly = $annualMilli / 1_200_000;
        $payment = (int) round($balance * $monthly / (1 - (1 + $monthly) ** (-$months)));

        return max(1, $payment);
    }

    public function interest(int $balance, int $annualMilli): int
    {
        if ($balance <= 0 || $annualMilli <= 0) {
            return 0;
        }

        return (int) round($balance * $annualMilli / 1_200_000);
    }
}
