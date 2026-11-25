<?php

namespace Tests\Unit;

use App\Support\CreditCalculator;
use PHPUnit\Framework\TestCase;

class CreditCalculatorTest extends TestCase
{
    public function test_a_zero_rate_repays_the_principal_without_overpayment(): void
    {
        $quote = (new CreditCalculator)->quote(10_000, 3, 0);

        $this->assertNotNull($quote);
        $this->assertSame(3_333, $quote['payment_cents']);
        $this->assertSame(10_000, $quote['total_cents']);
        $this->assertSame(0, $quote['overpayment_cents']);
        $this->assertSame(3_334, $quote['last_payment_cents']);
        $this->assertFalse($quote['rate_changes']);
    }

    public function test_fixed_rate_and_euribor_add_up_to_one_monthly_payment(): void
    {
        $calculator = new CreditCalculator;
        $together = $calculator->quote(10_000, 2, 12_000);
        $split = $calculator->quote(10_000, 2, 10_000, 2_000);

        $this->assertSame($together, $split);
        $this->assertSame(5_075, $together['payment_cents']);
        $this->assertSame(10_150, $together['total_cents']);
        $this->assertSame(150, $together['overpayment_cents']);
        $this->assertSame(5_075, $together['last_payment_cents']);
    }

    public function test_a_rate_change_starts_on_the_unpaid_month(): void
    {
        $quote = (new CreditCalculator)->quote(10_000, 2, 12_000, 0, 0, 0, 0);

        $this->assertTrue($quote['rate_changes']);
        $this->assertSame(5_000, $quote['current_payment_cents']);
        $this->assertSame(5_000, $quote['next_payment_cents']);
        $this->assertSame(10_000, $quote['total_cents']);
        $this->assertSame(0, $quote['overpayment_cents']);
    }

    public function test_a_new_rate_reaches_the_last_remaining_month(): void
    {
        $quote = (new CreditCalculator)->quote(10_000, 2, 12_000, 0, 1, 0, 0);

        $this->assertFalse($quote['rate_changes']);
        $this->assertNull($quote['next_payment_cents']);
        $this->assertSame(5_025, $quote['current_payment_cents']);
        $this->assertSame(10_100, $quote['total_cents']);
        $this->assertSame(100, $quote['overpayment_cents']);
        $this->assertFalse($quote['finished']);
    }

    public function test_luminor_annuity_rounds_half_up_to_the_bank_payment(): void
    {
        $calculator = new CreditCalculator;

        $this->assertSame(28_463, $calculator->annuity(3_711_560, 192, 5_174));
        $this->assertSame(16_003, $calculator->interest(3_711_560, 5_174));
        $this->assertSame(12_460, 28_463 - 16_003);
        $this->assertSame(28_461, $calculator->annuity(3_698_740, 191, 5_174));
        $this->assertSame(1, $calculator->interest(1, 600_000));
    }
}
