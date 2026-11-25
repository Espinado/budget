<?php

namespace App\Services\Budget;

/**
 * How much of the remaining expense plan can still be spent per day.
 *
 * Remaining plan cents: max(0, planned − actual).
 * Spendable days come from {@see PeriodCalendar::spendableDays()} (today through
 * period end inclusive in the household calendar timezone).
 *
 * Rounding: nearest cent (PHP round half away from zero). When there are no
 * spendable days, returns null (UI shows «—»). When the line is spent or
 * overspent, returns 0.
 */
final class DailyPace
{
    public function centsPerDay(int $plannedCents, int $actualCents, int $spendableDays): ?int
    {
        if ($spendableDays <= 0) {
            return null;
        }

        $remaining = $plannedCents - $actualCents;

        if ($remaining <= 0) {
            return 0;
        }

        return (int) round($remaining / $spendableDays);
    }
}
