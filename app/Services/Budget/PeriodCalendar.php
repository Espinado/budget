<?php

namespace App\Services\Budget;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeImmutable;

final class PeriodCalendar
{
    /**
     * Inclusive payday cycle that contains the date.
     * The anchor day belongs to the new period: anchor 11 means 11 Oct–10 Nov.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function boundsFor(CarbonInterface $date, int $anchorDay): array
    {
        $anchorDay = max(1, min(31, $anchorDay));
        $day = CarbonImmutable::parse($date->toDateString())->startOfDay();
        $startThisMonth = $this->onDay($day->startOfMonth(), $anchorDay);
        $start = $day->lessThan($startThisMonth)
            ? $this->onDay($day->subMonthNoOverflow()->startOfMonth(), $anchorDay)
            : $startThisMonth;
        $nextStart = $this->onDay($start->addMonthNoOverflow()->startOfMonth(), $anchorDay);

        return [$start, $nextStart->subDay()];
    }

    public function inclusiveDays(CarbonInterface $start, CarbonInterface $end): int
    {
        $from = new DateTimeImmutable($start->toDateString());
        $to = new DateTimeImmutable($end->toDateString());

        return (int) $from->diff($to)->days + 1;
    }

    /**
     * @return array{length: int, elapsed: int, remaining: int, underway: bool}
     */
    public function progress(CarbonInterface $start, CarbonInterface $end, CarbonInterface $today): array
    {
        $startDay = $start->toDateString();
        $endDay = $end->toDateString();
        $todayDay = $today->toDateString();
        $length = $this->inclusiveDays($start, $end);

        if ($todayDay < $startDay) {
            return ['length' => $length, 'elapsed' => 0, 'remaining' => $length, 'underway' => false];
        }

        if ($todayDay > $endDay) {
            return ['length' => $length, 'elapsed' => $length, 'remaining' => 0, 'underway' => false];
        }

        return [
            'length' => $length,
            'elapsed' => $this->inclusiveDays($start, $today),
            'remaining' => $this->inclusiveDays($today, $end) - 1,
            'underway' => true,
        ];
    }

    /**
     * Calendar days left to spend the remaining expense plan.
     *
     * Uses date-only comparison in the caller's timezone (household: Europe/Riga).
     * Rule: from today through period end inclusive. Before the period starts,
     * the whole period length counts. After the period ends, 0.
     *
     * Differs from progress()['remaining'], which excludes today («ещё N дней»).
     */
    public function spendableDays(CarbonInterface $start, CarbonInterface $end, CarbonInterface $today): int
    {
        $startDay = $start->toDateString();
        $endDay = $end->toDateString();
        $todayDay = $today->toDateString();

        if ($todayDay > $endDay) {
            return 0;
        }

        if ($todayDay < $startDay) {
            return $this->inclusiveDays($start, $end);
        }

        return $this->inclusiveDays($today, $end);
    }

    public function expectedOn(CarbonInterface $start, CarbonInterface $end, ?int $day): ?CarbonImmutable
    {
        if ($day === null || $day < 1 || $day > 31) {
            return null;
        }

        $cursor = CarbonImmutable::parse($start->toDateString())->startOfMonth();
        $last = CarbonImmutable::parse($end->toDateString())->startOfMonth();
        $startDay = $start->toDateString();
        $endDay = $end->toDateString();

        while ($cursor->lessThanOrEqualTo($last)) {
            if ($day <= $cursor->daysInMonth) {
                $candidate = $cursor->day($day)->toDateString();

                if ($candidate >= $startDay && $candidate <= $endDay) {
                    return CarbonImmutable::parse($candidate);
                }
            }

            $cursor = $cursor->addMonthNoOverflow()->startOfMonth();
        }

        return null;
    }

    private function onDay(CarbonImmutable $monthStart, int $anchorDay): CarbonImmutable
    {
        return $monthStart->day(min($anchorDay, $monthStart->daysInMonth))->startOfDay();
    }
}
