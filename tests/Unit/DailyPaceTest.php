<?php

namespace Tests\Unit;

use App\Services\Budget\DailyPace;
use App\Services\Budget\PeriodCalendar;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DailyPaceTest extends TestCase
{
    private DailyPace $pace;

    private PeriodCalendar $calendar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pace = new DailyPace;
        $this->calendar = new PeriodCalendar;
    }

    public function test_divides_remaining_plan_by_inclusive_days_through_period_end(): void
    {
        // 17 Oct–10 Nov: 25 days inclusive; remaining plan 250,00 € → 10,00 €/day
        $days = $this->calendar->spendableDays(
            CarbonImmutable::parse('2026-10-11', 'Europe/Riga'),
            CarbonImmutable::parse('2026-11-10', 'Europe/Riga'),
            CarbonImmutable::parse('2026-10-17', 'Europe/Riga'),
        );

        $this->assertSame(25, $days);
        $this->assertSame(1000, $this->pace->centsPerDay(30_000, 5_000, $days));
    }

    public function test_last_day_of_period_is_one_spendable_day(): void
    {
        $days = $this->calendar->spendableDays(
            CarbonImmutable::parse('2026-10-01', 'Europe/Riga'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Riga'),
            CarbonImmutable::parse('2026-10-31', 'Europe/Riga'),
        );

        $this->assertSame(1, $days);
        $this->assertSame(17274, $this->pace->centsPerDay(17_274, 0, $days));
    }

    public function test_after_period_ends_returns_null(): void
    {
        $days = $this->calendar->spendableDays(
            CarbonImmutable::parse('2026-09-01', 'Europe/Riga'),
            CarbonImmutable::parse('2026-09-30', 'Europe/Riga'),
            CarbonImmutable::parse('2026-10-01', 'Europe/Riga'),
        );

        $this->assertSame(0, $days);
        $this->assertNull($this->pace->centsPerDay(10_000, 0, $days));
    }

    public function test_overspent_or_fully_spent_is_zero(): void
    {
        $this->assertSame(0, $this->pace->centsPerDay(10_000, 10_000, 10));
        $this->assertSame(0, $this->pace->centsPerDay(10_000, 12_500, 10));
    }

    public function test_zero_remaining_plan_is_zero(): void
    {
        $this->assertSame(0, $this->pace->centsPerDay(0, 0, 5));
    }

    public function test_rounds_to_nearest_cent(): void
    {
        // 100 cents / 3 days → 33.333… → 33
        $this->assertSame(33, $this->pace->centsPerDay(100, 0, 3));
        // 200 cents / 3 days → 66.666… → 67
        $this->assertSame(67, $this->pace->centsPerDay(200, 0, 3));
    }

    public function test_before_period_uses_full_length(): void
    {
        $days = $this->calendar->spendableDays(
            CarbonImmutable::parse('2026-11-01', 'Europe/Riga'),
            CarbonImmutable::parse('2026-11-30', 'Europe/Riga'),
            CarbonImmutable::parse('2026-10-15', 'Europe/Riga'),
        );

        $this->assertSame(30, $days);
        $this->assertSame(1000, $this->pace->centsPerDay(30_000, 0, $days));
    }

    public function test_dst_boundary_still_counts_calendar_days(): void
    {
        $days = $this->calendar->spendableDays(
            CarbonImmutable::parse('2026-10-11', 'Europe/Riga'),
            CarbonImmutable::parse('2026-11-10', 'Europe/Riga'),
            CarbonImmutable::parse('2026-10-25', 'Europe/Riga'),
        );

        $this->assertSame(17, $days);
    }

    public function test_same_remaining_plan_yields_higher_daily_pace_on_the_next_day(): void
    {
        $start = CarbonImmutable::parse('2026-10-01', 'Europe/Riga');
        $end = CarbonImmutable::parse('2026-10-31', 'Europe/Riga');
        $dayN = CarbonImmutable::parse('2026-10-17', 'Europe/Riga');
        $dayNext = CarbonImmutable::parse('2026-10-18', 'Europe/Riga');
        $planned = 12_490;
        $actual = 0;

        $daysN = $this->calendar->spendableDays($start, $end, $dayN);
        $daysNext = $this->calendar->spendableDays($start, $end, $dayNext);

        $this->assertSame(15, $daysN);
        $this->assertSame(14, $daysNext);
        $this->assertSame(833, $this->pace->centsPerDay($planned, $actual, $daysN));
        $this->assertSame(892, $this->pace->centsPerDay($planned, $actual, $daysNext));
    }
}
