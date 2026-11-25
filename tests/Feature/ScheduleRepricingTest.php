<?php

namespace Tests\Feature;

use App\Enums\CashflowType;
use App\Enums\DebtDirection;
use App\Enums\InterestMode;
use App\Models\Debt;
use App\Models\User;
use App\Services\Budget\DebtBook;
use App\Services\Budget\PeriodGenerator;
use App\Support\CreditCalculator;
use App\Support\DailyInterest;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoBudgetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleRepricingTest extends TestCase
{
    use RefreshDatabase;

    private CreditCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00', 'Europe/Riga'));
        $this->calculator = new CreditCalculator;
    }

    public function test_fixed_rate_credit_reprices_the_unpaid_month_and_keeps_the_fee(): void
    {
        [$household, $credit, $book] = $this->book();
        $from = CarbonImmutable::parse('2026-10-25');
        $rows = $book->draft(100_000, 'parts', 4, CarbonImmutable::parse('2026-11-10'), 6_000, $from, 490);
        $debt = $book->place($household, $credit, 'Фикс', 'FIX-1', null, 6_000, $rows, DebtDirection::Borrow, 6_000, 0, 'fixed');

        $book->changeRate($debt, 12_000, 12_000, 0);
        $debt->refresh()->load('installments');
        $first = $debt->installments->firstWhere('sequence', 1);

        $this->assertSame($this->calculator->interest(100_000, 12_000), (int) $first->interest_cents);
        $this->assertNotSame($this->calculator->interest(100_000, 6_000), (int) $first->interest_cents);
        $this->assertSame($this->calculator->annuity(100_000, 4, 12_000), (int) $first->principal_cents + (int) $first->interest_cents);
        $this->assertSame(490, (int) $first->fee_cents);
        $this->assertSame(4, $debt->installments->count());
        $this->assertCents($debt, 100_000);
    }

    public function test_euribor_tenors_reprice_before_the_reset_date(): void
    {
        [$household, $credit, $book] = $this->book();
        $from = CarbonImmutable::parse('2026-10-25');

        foreach ([3, 6, 12] as $tenor) {
            $rows = $book->draft(50_000, 'parts', 3, CarbonImmutable::parse('2026-11-10'), 4_857, $from);
            $debt = $book->place(
                $household,
                $credit,
                'Euribor '.$tenor,
                'EUR-'.$tenor,
                null,
                4_857,
                $rows,
                DebtDirection::Borrow,
                2_380,
                2_477,
                'fixed',
                null,
                0,
                $tenor,
                CarbonImmutable::parse('2027-06-10'),
            );

            $book->changeRate($debt, 5_174, 2_380, 2_794);
            $debt->refresh()->load('installments');
            $first = $debt->installments->firstWhere('sequence', 1);

            $this->assertSame('2027-06-10', $debt->rate_reset_on->toDateString());
            $this->assertSame($tenor, (int) $debt->euribor_tenor_months);
            $this->assertSame('2026-11-10', $first->due_on->toDateString());
            $this->assertSame($this->calculator->interest(50_000, 5_174), (int) $first->interest_cents);
            $this->assertSame($this->calculator->annuity(50_000, 3, 5_174), (int) $first->total_cents);
            $this->assertCents($debt, 50_000);
        }
    }

    public function test_a_sharp_lease_rate_keeps_every_month_and_the_buyout(): void
    {
        [$household, $credit, $book] = $this->book();
        $from = CarbonImmutable::parse('2026-10-25');
        $rows = $book->draft(100_000, 'parts', 4, CarbonImmutable::parse('2026-11-10'), 6_000, $from, 0, 0, 20_000);
        $debt = $book->place($household, $credit, 'Лизинг', 'LEASE-SHARP', null, 6_000, $rows, DebtDirection::Borrow, 6_000, 0, 'fixed', null, 20_000);
        $dates = $debt->installments->sortBy('sequence')->map(fn ($row) => $row->due_on->toDateString())->all();

        $book->changeRate($debt, 12_000_000, 12_000_000, 0);
        $debt->refresh()->load('installments');
        $after = $debt->installments->sortBy('sequence')->values();

        $this->assertSame($dates, $after->map(fn ($row) => $row->due_on->toDateString())->all());
        $this->assertSame($this->calculator->interest(100_000, 12_000_000), (int) $after[0]->interest_cents);
        $this->assertGreaterThan(0, (int) $after[0]->principal_cents);
        $this->assertLessThan(80_000, (int) $after[0]->principal_cents);
        $this->assertSame(20_000, (int) $after->last()->principal_cents);
        $this->assertSame(0, (int) $after->last()->interest_cents);
        $this->assertCents($debt, 100_000);
    }

    public function test_a_revolving_rate_change_starts_on_the_unpaid_row(): void
    {
        [$household, $credit, $book] = $this->book();
        $from = CarbonImmutable::parse('2026-10-25');
        $rows = $book->draft(88_062, 'parts', 6, CarbonImmutable::parse('2026-11-12'), 38_400, $from);
        $debt = $book->place($household, $credit, 'Creditea', 'REV-1', null, 38_400, $rows, DebtDirection::Borrow, 38_400, 0, 'revolving');
        $oldInterest = (int) $debt->installments->firstWhere('sequence', 1)->interest_cents;

        $book->changeRate($debt, 20_000, 20_000, 0);
        $debt->refresh()->load('installments');
        $first = $debt->installments->firstWhere('sequence', 1);

        $this->assertSame($this->calculator->interest(88_062, 20_000), (int) $first->interest_cents);
        $this->assertNotSame($oldInterest, (int) $first->interest_cents);
        $this->assertSame(6, $debt->installments->count());
        $this->assertCents($debt, 88_062);
    }

    public function test_a_minimum_payment_uses_the_new_daily_rate_from_the_unpaid_invoice(): void
    {
        [$household, $credit, $book] = $this->book();
        $rows = $book->minimumInstallments(54_750, 43_800, CarbonImmutable::parse('2026-10-23'), CarbonImmutable::parse('2026-11-27'));
        $debt = $book->place($household, $credit, 'SMScredit', 'MIN-1', null, 43_800, $rows, DebtDirection::Borrow, 43_800, 0, 'minimum', CarbonImmutable::parse('2026-10-23'));

        $book->changeRate($debt, 20_000, 20_000, 0);
        $debt->refresh()->load('installments');
        $first = $debt->installments->firstWhere('sequence', 1);

        $this->assertSame('2026-11-27', $first->due_on->toDateString());
        $this->assertSame(1_050, (int) $first->interest_cents);
        $this->assertSame(1_643, (int) $first->principal_cents);
        $this->assertSame(2_693, (int) $first->total_cents);
        $this->assertSame(count($rows), $debt->installments->count());
        $this->assertCents($debt, 54_750);
    }

    public function test_a_lump_sum_keeps_day_count_interest_on_the_only_unpaid_row(): void
    {
        [$household, $credit, $book] = $this->book();
        $opened = CarbonImmutable::parse('2026-11-01');
        $due = CarbonImmutable::parse('2026-11-11');
        $rows = $book->draft(10_000, 'lump', 1, $due, 6_000, $opened);
        $debt = $book->place($household, $credit, 'Сосед', 'LUMP-1', null, 6_000, $rows, DebtDirection::Borrow, 6_000, 0, 'fixed', $opened);

        $this->assertSame(17, (int) $debt->installments->first()->interest_cents);

        $book->changeRate($debt, 12_000, 12_000, 0);
        $debt->refresh()->load('installments');
        $row = $debt->installments->first();

        $this->assertSame(10_000, (int) $row->principal_cents);
        $this->assertSame(33, (int) $row->interest_cents);
        $this->assertSame(10_033, (int) $row->total_cents);
        $this->assertCents($debt, 10_000);
    }

    public function test_a_one_month_annuity_does_not_switch_to_day_count(): void
    {
        [$household, $credit, $book] = $this->book();
        $rows = $book->draft(10_000, 'parts', 1, CarbonImmutable::parse('2026-11-11'), 6_000, CarbonImmutable::parse('2026-11-01'));
        $debt = $book->place($household, $credit, 'Месяц', 'MONTH-1', null, 6_000, $rows);

        $book->changeRate($debt, 12_000, 12_000, 0);
        $debt->refresh()->load('installments');
        $row = $debt->installments->first();

        $this->assertSame($this->calculator->interest(10_000, 12_000), (int) $row->interest_cents);
        $this->assertSame(100, (int) $row->interest_cents);
        $this->assertSame(10_000, (int) $row->principal_cents);
        $this->assertCents($debt, 10_000);
    }

    public function test_a_paid_installment_stays_and_the_next_unpaid_takes_the_new_rate(): void
    {
        [$household, $credit, $book] = $this->book();
        $rows = $book->draft(90_000, 'parts', 3, CarbonImmutable::parse('2026-11-10'), 0, CarbonImmutable::parse('2026-10-25'));
        $debt = $book->place($household, $credit, 'Части', 'PAID-1', null, 0, $rows);
        $line = $debt->installments->firstWhere('sequence', 1)->planLine;
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        app(PeriodGenerator::class)->record($household, $credit, CashflowType::Expense, (int) $line->amount_cents, CarbonImmutable::parse('2026-11-10'), null, $line, User::query()->where('email', 'demo@budget.test')->value('id'), $cash);

        $book->changeRate($debt->fresh(), 12_000, 12_000, 0);
        $debt->refresh()->load('installments');
        $first = $debt->installments->firstWhere('sequence', 1);
        $second = $debt->installments->firstWhere('sequence', 2);

        $this->assertNotNull($first->paid_transaction_id);
        $this->assertSame(0, (int) $first->interest_cents);
        $this->assertSame(30_000, (int) $first->principal_cents);
        $this->assertSame(60_000, (int) $second->principal_before_cents);
        $this->assertSame($this->calculator->interest(60_000, 12_000), (int) $second->interest_cents);
        $this->assertCents($debt, 90_000);
    }

    public function test_daily_interest_uses_the_new_rate_up_to_the_nearest_due_date(): void
    {
        [$household, $credit, $book] = $this->book();
        $rows = $book->draft(10_000, 'parts', 3, CarbonImmutable::parse('2026-11-20'), 12_000, CarbonImmutable::parse('2026-10-25'));
        $debt = $book->place($household, $credit, 'Дни', 'DAY-1', null, 12_000, $rows, DebtDirection::Borrow, 12_000, 0, 'revolving');
        $debt->update([
            'interest_mode' => InterestMode::Daily,
            'principal_cents' => 10_000,
            'accrued_cents' => 0,
            'interest_through' => '2026-11-15',
        ]);
        $stored = (int) $debt->installments()->where('sequence', 1)->value('total_cents');

        $this->assertSame('daily', $book->changeRate($debt->fresh(), 24_000, 24_000, 0));

        $debt->refresh()->load('installments');
        $this->assertSame($stored, (int) $debt->installments->firstWhere('sequence', 1)->total_cents);
        $this->assertSame(24_000, (int) $debt->annual_rate_bps);

        $forecast = app(DailyInterest::class)->forecast($debt, CarbonImmutable::parse('2026-11-15'));

        $this->assertSame('2026-11-20', $forecast[0]['due_on']);
        $this->assertSame(35, $forecast[0]['interest_cents']);
        $this->assertSame(
            $forecast[0]['principal_cents'] + $forecast[0]['interest_cents'] + $forecast[0]['fee_cents'] + $forecast[0]['other_cents'],
            $forecast[0]['total_cents'],
        );
    }

    /**
     * @return array{0: \App\Models\Household, 1: \App\Models\Category, 2: DebtBook}
     */
    private function book(): array
    {
        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();

        return [$household, $household->categories()->where('name', 'Кредит')->firstOrFail(), app(DebtBook::class)];
    }

    private function assertCents(Debt $debt, int $principal): void
    {
        $rows = $debt->installments()->orderBy('sequence')->get();
        $expectedBefore = null;
        $sum = 0;

        foreach ($rows as $row) {
            $this->assertSame(
                (int) $row->principal_cents + (int) $row->interest_cents + (int) $row->fee_cents + (int) $row->other_cents,
                (int) $row->total_cents,
                'row '.$row->sequence,
            );
            $this->assertGreaterThanOrEqual(0, (int) $row->principal_cents, 'row '.$row->sequence);

            if ((int) $row->total_cents === 0) {
                continue;
            }

            if ($expectedBefore !== null) {
                $this->assertSame($expectedBefore, (int) $row->principal_before_cents, 'balance '.$row->sequence);
            }

            $expectedBefore = (int) $row->principal_before_cents - (int) $row->principal_cents;
            $sum += (int) $row->principal_cents;
        }

        $this->assertSame($principal, $sum);
        $this->assertSame(0, $expectedBefore);
    }
}
