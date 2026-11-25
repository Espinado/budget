<?php

namespace Tests\Unit;

use App\Support\AppDate;
use PHPUnit\Framework\TestCase;

class AppDateTest extends TestCase
{
    public function test_dotted_day_month_year_is_the_calendar_day(): void
    {
        $date = AppDate::parse('06.11.2027');

        $this->assertNotNull($date);
        $this->assertSame('2027-11-06', $date->toDateString());
        $this->assertSame('06.11.2027', AppDate::format($date));
        $this->assertSame('06.11.2027', AppDate::present('2027-11-06'));
        $this->assertSame('06.11.2027', AppDate::present('6.11.2027'));
    }

    public function test_an_impossible_day_is_rejected(): void
    {
        $this->assertNull(AppDate::parse('32.11.2027'));
        $this->assertNull(AppDate::parse('06/11/2027'));
        $this->assertSame('06/11/2027', AppDate::present('06/11/2027'));
    }
}
