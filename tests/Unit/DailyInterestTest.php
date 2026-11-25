<?php

namespace Tests\Unit;

use App\Support\DailyInterest;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DailyInterestTest extends TestCase
{
    public function test_daily_cents_use_the_month_length_and_the_debts_own_rate(): void
    {
        $interest = new DailyInterest;

        $this->assertSame(91, $interest->dailyCents(85_286, 38_400, 30));
        $this->assertSame(3, $interest->dailyCents(10_000, 12_000, 30));
        $this->assertSame(0, $interest->dailyCents(0, 38_400, 30));

        $sms = $interest->accruedBetween(
            54_750,
            43_800,
            CarbonImmutable::parse('2026-10-23'),
            CarbonImmutable::parse('2026-11-11'),
        );
        $copiedRate = $interest->accruedBetween(
            54_750,
            38_400,
            CarbonImmutable::parse('2026-10-23'),
            CarbonImmutable::parse('2026-11-11'),
        );

        $this->assertSame(1_249, $sms);
        $this->assertNotSame($copiedRate, $sms);

        $across = $interest->accruedBetween(
            10_000,
            38_400,
            CarbonImmutable::parse('2026-10-30'),
            CarbonImmutable::parse('2026-11-01'),
        );
        $october = $interest->dailyCents(10_000, 38_400, 31);
        $november = $interest->dailyCents(10_000, 38_400, 30);

        $this->assertSame($october + $november, $across);
        $this->assertNotSame($october, $november);
    }

    public function test_payment_takes_interest_before_principal(): void
    {
        $interest = new DailyInterest;

        $covered = $interest->applyPayment(10_000, 300, 500);
        $this->assertSame(300, $covered['interest_paid']);
        $this->assertSame(200, $covered['principal_paid']);
        $this->assertSame(9_800, $covered['principal']);
        $this->assertSame(0, $covered['accrued']);

        $short = $interest->applyPayment(10_000, 300, 200);
        $this->assertSame(200, $short['interest_paid']);
        $this->assertSame(0, $short['principal_paid']);
        $this->assertSame(10_000, $short['principal']);
        $this->assertSame(100, $short['accrued']);
    }

    public function test_creditea_total_drops_by_the_payment_when_interest_is_covered(): void
    {
        $split = (new DailyInterest)->applyPayment(88_000, 2_598, 5_312);

        $this->assertSame(90_598, 88_000 + 2_598);
        $this->assertSame(85_286, $split['principal']);
        $this->assertSame(0, $split['accrued']);
        $this->assertSame(85_286, 90_598 - 5_312);
    }
}
