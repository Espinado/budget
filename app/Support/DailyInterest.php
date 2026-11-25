<?php

namespace App\Support;

use App\Models\Debt;
use App\Models\DebtInstallment;
use Carbon\CarbonImmutable;

/**
 * Revolving credit: interest each day is principal × (annual nominal / 12) / days in that month.
 * Annual nominal is stored in thousandths of a percent (38.4% = 38400). Unpaid interest is not capitalized.
 */
final class DailyInterest
{
    public function dailyCents(int $principalCents, int $annualMilli, int $daysInMonth): int
    {
        if ($principalCents <= 0 || $annualMilli <= 0 || $daysInMonth <= 0) {
            return 0;
        }

        return (int) round($principalCents * $annualMilli / (1_200_000 * $daysInMonth));
    }

    public function accruedBetween(int $principalCents, int $annualMilli, CarbonImmutable $through, CarbonImmutable $asOf): int
    {
        $through = $this->day($through);
        $asOf = $this->day($asOf);

        if ($asOf->lessThanOrEqualTo($through)) {
            return 0;
        }

        $total = 0;

        for ($day = $through->addDay(); $day->lessThanOrEqualTo($asOf); $day = $day->addDay()) {
            $total += $this->dailyCents($principalCents, $annualMilli, $day->daysInMonth);
        }

        return $total;
    }

    /**
     * @return array{principal: int, accrued: int, total: int}
     */
    public function position(Debt $debt, ?CarbonImmutable $asOf = null): array
    {
        $asOf = $this->asOf($debt, $asOf);
        $principal = max(0, (int) $debt->principal_cents);
        $accrued = max(0, (int) $debt->accrued_cents);
        $accrued += $this->accruedBetween($principal, (int) $debt->annual_rate_bps, $this->through($debt, $asOf), $asOf);

        return [
            'principal' => $principal,
            'accrued' => $accrued,
            'total' => $principal + $accrued,
        ];
    }

    /**
     * Interest is paid first. Whatever is left reduces principal. Accrued stays only when the payment does not cover it.
     *
     * @return array{principal: int, accrued: int, interest_paid: int, principal_paid: int}
     */
    public function applyPayment(int $principalCents, int $accruedCents, int $amountCents): array
    {
        $principalCents = max(0, $principalCents);
        $accruedCents = max(0, $accruedCents);
        $amountCents = max(0, $amountCents);
        $interestPaid = min($accruedCents, $amountCents);
        $principalPaid = min($principalCents, $amountCents - $interestPaid);

        return [
            'principal' => $principalCents - $principalPaid,
            'accrued' => $accruedCents - $interestPaid,
            'interest_paid' => $interestPaid,
            'principal_paid' => $principalPaid,
        ];
    }

    /**
     * Forecast at the usual payment on each due date. The split changes if the payment happens on another day.
     *
     * @return list<array{id: int, due_on: ?string, principal_before_cents: int, principal_cents: int, interest_cents: int, fee_cents: int, other_cents: int, total_cents: int}>
     */
    public function forecast(Debt $debt, ?CarbonImmutable $asOf = null): array
    {
        $debt->loadMissing('installments');
        $asOf = $this->asOf($debt, $asOf);
        $principal = max(0, (int) $debt->principal_cents);
        $accrued = max(0, (int) $debt->accrued_cents);
        $through = $this->through($debt, $asOf);
        $rate = (int) $debt->annual_rate_bps;
        $rows = [];

        $unpaid = $debt->installments
            ->filter(fn (DebtInstallment $row): bool => $row->paid_transaction_id === null && (int) $row->total_cents > 0)
            ->values();

        foreach ($unpaid as $row) {
            if ($principal <= 0 && $accrued <= 0) {
                break;
            }

            $due = $row->due_on !== null
                ? CarbonImmutable::parse($row->due_on->toDateString())->startOfDay()
                : $asOf;
            $payOn = $due->lessThan($asOf) ? $asOf : $due;

            if ($payOn->lessThan($through)) {
                $payOn = $through;
            }

            $interest = $accrued + $this->accruedBetween($principal, $rate, $through, $payOn);
            $charges = (int) $row->fee_cents + (int) $row->other_cents;
            $usual = (int) $row->total_cents;
            $room = max(0, $usual - $charges);
            $closing = $principal + $interest + $charges > 0 && $principal + $interest + $charges <= $usual;
            $interestPaid = $closing ? $interest : min($interest, $room);
            $principalPaid = $closing ? $principal : min($principal, max(0, $room - $interestPaid));

            $rows[] = [
                'id' => (int) $row->id,
                'due_on' => $row->due_on?->toDateString(),
                'principal_before_cents' => $principal,
                'principal_cents' => $principalPaid,
                'interest_cents' => $interestPaid,
                'fee_cents' => (int) $row->fee_cents,
                'other_cents' => (int) $row->other_cents,
                'total_cents' => $closing ? $principal + $interest + $charges : $usual,
            ];

            $principal -= $principalPaid;
            $accrued = $interest - $interestPaid;
            $through = $payOn;
        }

        return $rows;
    }

    private function asOf(Debt $debt, ?CarbonImmutable $asOf): CarbonImmutable
    {
        if ($asOf !== null) {
            return $this->day($asOf);
        }

        $debt->loadMissing('household');
        $tz = $debt->household?->timezone ?: 'Europe/Riga';

        return CarbonImmutable::parse(CarbonImmutable::now($tz)->toDateString())->startOfDay();
    }

    private function through(Debt $debt, CarbonImmutable $asOf): CarbonImmutable
    {
        if ($debt->interest_through === null) {
            return $asOf;
        }

        return CarbonImmutable::parse($debt->interest_through->toDateString())->startOfDay();
    }

    private function day(CarbonImmutable $day): CarbonImmutable
    {
        return CarbonImmutable::parse($day->toDateString())->startOfDay();
    }
}
