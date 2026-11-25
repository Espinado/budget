<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneySeparatorTest extends TestCase
{
    public function test_dot_is_replaced_with_the_comma_separator(): void
    {
        $this->assertSame('12,50', Money::normalize('12.50'));
        $this->assertSame('12,5', Money::normalize('12.5'));
        $this->assertSame('12,50', Money::normalize('12,50'));
        $this->assertSame(1250, Money::toCents('12.50'));
        $this->assertSame(1250, Money::toCents('12,50'));
        $this->assertSame('0,00', Money::normalize('.00'));
        $this->assertSame('0,00', Money::normalize(',00'));
        $this->assertSame('0,50', Money::normalize('.50'));
        $this->assertSame(50, Money::toCents('.50'));
        $this->assertSame(50, Money::toCents(',50'));
    }
}
