<?php

namespace Tests\Unit;

use App\Services\Budget\PeriodCalendar;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class PeriodCalendarTest extends TestCase
{
    private PeriodCalendar $calendar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calendar = new PeriodCalendar;
    }

    public function test_payday_anchor_runs_from_the_11th_through_the_day_before_the_next_11th(): void
    {
        [$start, $end] = $this->calendar->boundsFor(CarbonImmutable::parse('2026-10-17'), 11);

        $this->assertSame('2026-10-11', $start->toDateString());
        $this->assertSame('2026-11-10', $end->toDateString());
    }

    public function test_anchor_day_itself_opens_the_new_period(): void
    {
        [$start, $end] = $this->calendar->boundsFor(CarbonImmutable::parse('2026-10-11'), 11);

        $this->assertSame('2026-10-11', $start->toDateString());
        $this->assertSame('2026-11-10', $end->toDateString());
    }

    public function test_day_before_anchor_stays_in_the_previous_period(): void
    {
        [$start, $end] = $this->calendar->boundsFor(CarbonImmutable::parse('2026-10-10'), 11);

        $this->assertSame('2026-09-11', $start->toDateString());
        $this->assertSame('2026-10-10', $end->toDateString());
    }

    public function test_anchor_on_the_first_is_a_calendar_month(): void
    {
        [$start, $end] = $this->calendar->boundsFor(CarbonImmutable::parse('2026-10-17'), 1);

        $this->assertSame('2026-10-01', $start->toDateString());
        $this->assertSame('2026-10-31', $end->toDateString());
    }

    public function test_anchor_31_clamps_to_the_last_day_of_february(): void
    {
        [$start, $end] = $this->calendar->boundsFor(CarbonImmutable::parse('2026-02-15'), 31);

        $this->assertSame('2026-01-31', $start->toDateString());
        $this->assertSame('2026-02-27', $end->toDateString());

        [$nextStart, $nextEnd] = $this->calendar->boundsFor(CarbonImmutable::parse('2026-02-28'), 31);

        $this->assertSame('2026-02-28', $nextStart->toDateString());
        $this->assertSame('2026-03-30', $nextEnd->toDateString());
    }

    public function test_day_count_ignores_the_october_dst_change(): void
    {
        $start = CarbonImmutable::parse('2026-10-11', 'Europe/Riga');
        $end = CarbonImmutable::parse('2026-11-10', 'Europe/Riga');

        $this->assertSame(31, $this->calendar->inclusiveDays($start, $end));
    }

    public function test_spendable_days_include_today_through_end(): void
    {
        $start = CarbonImmutable::parse('2026-10-01', 'Europe/Riga');
        $end = CarbonImmutable::parse('2026-10-31', 'Europe/Riga');

        $this->assertSame(15, $this->calendar->spendableDays($start, $end, CarbonImmutable::parse('2026-10-17', 'Europe/Riga')));
        $this->assertSame(1, $this->calendar->spendableDays($start, $end, CarbonImmutable::parse('2026-10-31', 'Europe/Riga')));
        $this->assertSame(0, $this->calendar->spendableDays($start, $end, CarbonImmutable::parse('2026-11-01', 'Europe/Riga')));
        $this->assertSame(31, $this->calendar->spendableDays($start, $end, CarbonImmutable::parse('2026-09-20', 'Europe/Riga')));
    }
}
