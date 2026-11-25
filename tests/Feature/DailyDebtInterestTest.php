<?php

namespace Tests\Feature;

use App\Enums\CashflowType;
use App\Enums\DebtDirection;
use App\Enums\InterestMode;
use App\Livewire\Budget\Dashboard;
use App\Livewire\Budget\DebtShow;
use App\Models\Debt;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Budget\DebtBook;
use App\Services\Budget\PeriodGenerator;
use App\Support\DailyInterest;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoBudgetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DailyDebtInterestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_daily_payment_pays_interest_then_principal_and_matches_creditea(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $book = app(DebtBook::class);
        $rows = $book->draft(90_598, 'parts', 6, CarbonImmutable::parse('2026-11-11'), 38_400, CarbonImmutable::parse('2026-11-11'));
        $debt = $book->place($household, $credit, 'Creditea', null, null, 38_400, $rows, DebtDirection::Borrow, 38_400, 0, 'revolving');
        $debt->update([
            'interest_mode' => InterestMode::Daily,
            'principal_cents' => 88_000,
            'accrued_cents' => 2_598,
            'interest_through' => '2026-11-11',
        ]);

        $this->assertSame(90_598, $debt->fresh()->remainingCents());

        $line = $debt->installments()->where('sequence', 1)->firstOrFail()->planLine;
        $planned = (int) $line->amount_cents;
        $before = (int) $cash->balance_cents;
        $payment = app(PeriodGenerator::class)->record(
            $household,
            $credit,
            CashflowType::Expense,
            5_312,
            CarbonImmutable::parse('2026-11-11'),
            null,
            $line,
            $user->id,
            $cash,
        );

        $debt->refresh();
        $paid = $debt->installments()->where('sequence', 1)->firstOrFail();
        $next = $debt->installments()->where('sequence', 2)->firstOrFail();

        $this->assertSame($before - 5_312, (int) $cash->fresh()->balance_cents);
        $this->assertSame(1, Transaction::query()->where('account_id', $cash->id)->where('amount_cents', 5_312)->count());
        $this->assertSame(85_286, (int) $debt->principal_cents);
        $this->assertSame(0, (int) $debt->accrued_cents);
        $this->assertSame('2026-11-11', $debt->interest_through->toDateString());
        $this->assertSame(85_286, $debt->remainingCents());
        $this->assertSame($payment->id, (int) $paid->paid_transaction_id);
        $this->assertSame(2_598, (int) $paid->interest_cents);
        $this->assertSame(2_714, (int) $paid->principal_cents);
        $this->assertSame($planned, (int) $next->planLine->amount_cents);
        $this->assertSame($rows[1]['total_cents'], (int) $next->total_cents);

        $forecast = app(DailyInterest::class)->forecast($debt, CarbonImmutable::parse('2026-11-11'));
        $this->assertSame(85_286, $forecast[0]['principal_before_cents']);
        $this->assertSame($rows[1]['total_cents'], $forecast[0]['total_cents']);
        $this->assertSame($forecast[0]['interest_cents'] + $forecast[0]['principal_cents'], $forecast[0]['total_cents']);

        $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00:00', 'Europe/Riga'));
        $day = app(DailyInterest::class)->dailyCents(85_286, 38_400, 30);
        $this->assertSame(85_286 + $day, $debt->fresh()->remainingCents());

        Livewire::actingAs($user)->test(Dashboard::class)->call('deleteTransaction', $payment->id);
        $debt->refresh();
        $this->assertSame(88_000, (int) $debt->principal_cents);
        $this->assertSame(2_598, (int) $debt->accrued_cents);
        $this->assertSame($before, (int) $cash->fresh()->balance_cents);
    }

    public function test_daily_balance_can_be_set_from_the_creditor_total(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $book = app(DebtBook::class);
        $rows = $book->draft(54_750, 'parts', 4, CarbonImmutable::parse('2026-11-27'), 43_800, CarbonImmutable::parse('2026-10-23'));
        $debt = $book->place($household, $credit, 'SMScredit', null, null, 43_800, $rows, DebtDirection::Borrow, 43_800, 0, 'minimum', CarbonImmutable::parse('2026-10-23'));
        $debt->update([
            'interest_mode' => InterestMode::Daily,
            'principal_cents' => 54_750,
            'accrued_cents' => 0,
            'interest_through' => '2026-10-23',
        ]);

        $this->assertSame(54_750 + 1_249, $debt->fresh()->remainingCents());

        Livewire::actingAs($user)
            ->test(DebtShow::class, ['debt' => $debt])
            ->assertSee('Тело')
            ->assertSee('Прогноз')
            ->call('toggleBalance')
            ->set('balanceTotal', '500,00')
            ->call('saveBalance')
            ->assertHasNoErrors();

        $debt->refresh();
        $this->assertSame(50_000, (int) $debt->principal_cents);
        $this->assertSame(0, (int) $debt->accrued_cents);
        $this->assertSame('2026-11-11', $debt->interest_through->toDateString());
        $this->assertSame(50_000, $debt->remainingCents());
    }

    public function test_a_schedule_debt_payment_keeps_the_amortization_row(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $book = app(DebtBook::class);
        $rows = $book->draft(70_517, 'parts', 3, CarbonImmutable::parse('2026-11-15'), 12_900, CarbonImmutable::parse('2026-11-15'));
        $debt = $book->place($household, $credit, 'Inbank', 'inbank-test', null, 12_900, $rows, DebtDirection::Borrow, 12_900, 0, 'fixed');
        $line = $debt->installments()->where('sequence', 1)->firstOrFail()->planLine;

        app(PeriodGenerator::class)->record(
            $household,
            $credit,
            CashflowType::Expense,
            (int) $line->amount_cents,
            CarbonImmutable::parse('2026-11-15'),
            null,
            $line,
            $user->id,
            $cash,
        );

        $debt->refresh();
        $second = $debt->installments()->where('sequence', 2)->firstOrFail();

        $this->assertSame(InterestMode::Schedule, $debt->interest_mode);
        $this->assertNull($debt->interest_through);
        $this->assertNull($debt->getRawOriginal('principal_cents'));
        $this->assertSame($rows[1]['principal_before_cents'], (int) $second->principal_before_cents);
        $this->assertSame($rows[1]['interest_cents'], (int) $second->interest_cents);
        $this->assertSame($rows[1]['principal_before_cents'], $debt->remainingCents());
    }

    public function test_a_second_daily_payment_cuts_principal_and_drops_the_last_month(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $rows = [
            ['sequence' => 1, 'due_on' => '2026-11-11', 'principal_before_cents' => 20_000, 'principal_cents' => 5_000, 'interest_cents' => 0, 'other_cents' => 0, 'fee_cents' => 0, 'total_cents' => 5_000],
            ['sequence' => 2, 'due_on' => '2026-12-11', 'principal_before_cents' => 15_000, 'principal_cents' => 2_000, 'interest_cents' => 0, 'other_cents' => 0, 'fee_cents' => 0, 'total_cents' => 5_000],
            ['sequence' => 3, 'due_on' => '2027-01-11', 'principal_before_cents' => 13_000, 'principal_cents' => 3_000, 'interest_cents' => 0, 'other_cents' => 0, 'fee_cents' => 0, 'total_cents' => 5_000],
            ['sequence' => 4, 'due_on' => '2027-02-11', 'principal_before_cents' => 10_000, 'principal_cents' => 4_000, 'interest_cents' => 0, 'other_cents' => 0, 'fee_cents' => 0, 'total_cents' => 5_000],
            ['sequence' => 5, 'due_on' => '2027-03-11', 'principal_before_cents' => 6_000, 'principal_cents' => 6_000, 'interest_cents' => 0, 'other_cents' => 0, 'fee_cents' => 0, 'total_cents' => 6_000],
        ];
        $debt = app(DebtBook::class)->place($household, $credit, 'Creditea', null, null, 38_400, $rows, DebtDirection::Borrow, 38_400, 0, 'revolving');
        $debt->update([
            'interest_mode' => InterestMode::Daily,
            'principal_cents' => 20_000,
            'accrued_cents' => 0,
            'interest_through' => '2026-11-11',
        ]);
        $line = $debt->installments()->where('sequence', 1)->firstOrFail()->planLine;
        $beforeCash = (int) $cash->fresh()->balance_cents;
        $periods = app(PeriodGenerator::class);
        $periods->record($household, $credit, CashflowType::Expense, 5_000, CarbonImmutable::parse('2026-11-11'), null, $line, $user->id, $cash);
        $extra = $periods->record($household, $credit, CashflowType::Expense, 6_000, CarbonImmutable::parse('2026-11-11'), null, $line, $user->id, $cash);

        $debt->refresh();
        $next = $debt->installments()->where('sequence', 2)->firstOrFail();
        $last = $debt->installments()->where('sequence', 5)->firstOrFail();
        $forecast = app(DailyInterest::class)->forecast($debt, CarbonImmutable::parse('2026-11-11'));

        $this->assertSame($beforeCash - 11_000, (int) $cash->fresh()->balance_cents);
        $this->assertSame(1, Transaction::query()->where('account_id', $cash->id)->where('amount_cents', 5_000)->count());
        $this->assertSame(1, Transaction::query()->where('account_id', $cash->id)->where('amount_cents', 6_000)->count());
        $this->assertSame(9_000, (int) $debt->principal_cents);
        $this->assertSame(0, (int) $debt->accrued_cents);
        $this->assertSame(9_000, $debt->remainingCents());
        $this->assertSame(5_000, (int) $next->total_cents);
        $this->assertSame(5_000, (int) $next->planLine->amount_cents);
        $this->assertSame(0, (int) $last->total_cents);
        $this->assertSame(4, $debt->installments()->where('total_cents', '>', 0)->count());
        $this->assertCount(2, $forecast);
        $this->assertSame(5_000, $forecast[0]['total_cents']);
        $this->assertSame(9_000, $forecast[0]['principal_before_cents']);

        Livewire::actingAs($user)->test(Dashboard::class)->call('deleteTransaction', $extra->id);
        $debt->refresh();
        $last->refresh();
        $this->assertSame(15_000, (int) $debt->principal_cents);
        $this->assertSame(6_000, (int) $last->total_cents);
        $this->assertSame($beforeCash - 5_000, (int) $cash->fresh()->balance_cents);
    }
}
