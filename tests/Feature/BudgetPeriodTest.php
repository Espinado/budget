<?php

namespace Tests\Feature;

use App\Enums\AccountRole;
use App\Enums\CashflowType;
use App\Livewire\Budget\Dashboard;
use App\Livewire\Budget\Settings;
use App\Livewire\Budget\Statistics;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Budget\DailyPace;
use App\Services\Budget\PeriodCalendar;
use App\Services\Budget\PeriodGenerator;
use App\Services\Budget\PeriodSummary;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoBudgetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class BudgetPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_current_period_compares_plan_with_actual(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $period = app(PeriodGenerator::class)->periodFor(
            $household,
            CarbonImmutable::now($household->timezone),
        );
        $figures = app(PeriodSummary::class)->figures($period);

        $this->assertSame(17_274, $figures->opening);
        $this->assertSame(20_000, $figures->plannedIncome);
        $this->assertSame(0, $figures->actualIncome);
        $this->assertSame(1_249, $figures->plannedExpense);
        $this->assertSame(0, $figures->actualExpense);
        $this->assertSame(36_025, $figures->plannedClosing());
        $this->assertSame(17_274, $figures->actualClosing());

        $this->actingAs($user)
            ->get('/?period='.$period->id)
            ->assertOk()
            ->assertSee('172,74')
            ->assertSee('360,25')
            ->assertSee('Баланс Октябрь 2026')
            ->assertSee('Всего денег на счетах')
            ->assertSee('Сводка')
            ->assertDontSee('Баланс отчётного периода')
            ->assertDontSee('Останется')
            ->assertDontSee('Общий баланс')
            ->assertSee('Доход')
            ->assertSee('Расход')
            ->assertDontSee('ещё придёт')
            ->assertDontSee('План месяца')
            ->assertDontSee('Итог бюджета')
            ->assertDontSee('Прогноз остатка')
            ->assertSee('Доход')
            ->assertSee('Telekomunikacija')
            ->assertSee('Факт')
            ->assertSee('Luminor Debet')
            ->assertSee('Аванс')
            ->assertSee('Tet')
            ->assertDontSee('Зарплата')
            ->assertDontSee('Платеж по кредиту')
            ->assertDontSee('Даты')
            ->assertSee('aria-label="Наверх"', false);

        $next = app(PeriodGenerator::class)->periodAfter($period);

        $this->actingAs($user)
            ->get('/?period='.$next->id)
            ->assertOk()
            ->assertSee('Ноябрь 2026')
            ->assertSee('Кредит')
            ->assertSee('Ипотека')
            ->assertSee('Баланс Ноябрь 2026')
            ->assertSee('1 394,21')
            ->assertSee('+1 033,96')
            ->assertSee('1 500,00')
            ->assertSee('466,04')
            ->assertSee('Октябрь 2026')
            ->assertSee('360,25')
            ->assertDontSee('Сводка и баланс этого месяца не считаются.')
            ->assertDontSee('+187,51')
            ->assertDontSee('Tet')
            ->assertDontSee('Аванс');
    }

    public function test_savings_stay_out_of_the_spendable_total(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $user->currentHousehold()->accounts()->create([
            'name' => 'Копилка',
            'kind' => 'bank',
            'role' => AccountRole::Savings,
            'balance_cents' => 5_000,
            'sort' => 40,
        ]);

        $this->actingAs($user)
            ->get('/budget')
            ->assertOk()
            ->assertSee('Всего денег на счетах')
            ->assertSee('Накопления')
            ->assertSee('Копилка')
            ->assertSee('172,74')
            ->assertSee('50,00')
            ->assertDontSee('222,74');
    }

    public function test_a_transfer_moves_money_between_accounts_without_changing_the_budget(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $debit = $household->accounts()->where('name', 'Luminor Debet')->firstOrFail();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $credit = $household->accounts()->where('name', 'Luminor Credit')->firstOrFail();
        $period = app(PeriodGenerator::class)->periodFor($household, CarbonImmutable::now($household->timezone));
        $figures = app(PeriodSummary::class)->figures($period);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('openTransfer')
            ->assertSet('showTransfer', true)
            ->assertSet('transferFromId', $debit->id)
            ->assertSet('transferToId', $credit->id)
            ->set('transferFromId', $debit->id)
            ->set('transferToId', $cash->id)
            ->set('transferAmount', '10,00')
            ->call('transferFunds')
            ->assertHasNoErrors()
            ->assertSet('showTransfer', false)
            ->call('openTransfer')
            ->set('transferFromId', $debit->id)
            ->set('transferToId', $debit->id)
            ->set('transferAmount', '10,00')
            ->call('transferFunds')
            ->assertHasErrors('transferToId')
            ->assertSet('showTransfer', true);

        $this->assertSame(15_829, $debit->fresh()->balance_cents);
        $this->assertSame(1_200, $cash->fresh()->balance_cents);
        $this->assertSame(245, $credit->fresh()->balance_cents);
        $this->assertSame(0, $period->transactions()->count());
        $this->assertSame($figures->actualIncome, app(PeriodSummary::class)->figures($period)->actualIncome);
        $this->assertSame($figures->actualExpense, app(PeriodSummary::class)->figures($period)->actualExpense);
        $this->assertSame(1, $household->transfers()->count());

        $this->actingAs($user)
            ->get('/budget')
            ->assertOk()
            ->assertSee('Перевод денег на другой счёт')
            ->assertDontSee('Luminor Debet → Наличные');

        $this->actingAs($user)
            ->get('/statistics')
            ->assertOk()
            ->assertSee('Переводы')
            ->assertSee('Luminor Debet → Наличные');

        $statistics = Livewire::actingAs($user)
            ->test(Statistics::class)
            ->call('deleteTransfer', $household->transfers()->value('id'));

        $statistics->assertDontSee('Отменить этот перевод');
        $this->assertSame(15_829, $debit->fresh()->balance_cents);
        $this->assertSame(1_200, $cash->fresh()->balance_cents);
        $this->assertSame(1, $household->transfers()->count());
    }

    public function test_the_summary_stays_on_the_plan_when_an_expense_is_recorded(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $generator = app(PeriodGenerator::class);
        $period = $generator->periodFor($household, CarbonImmutable::now($household->timezone));
        $telecom = $household->categories()->where('name', 'Telekomunikacija')->firstOrFail();
        $debit = $household->accounts()->where('name', 'Luminor Debet')->firstOrFail();
        $line = $period->planLines()->where('title', 'Tet')->firstOrFail();

        $generator->record(
            $household,
            $telecom,
            CashflowType::Expense,
            400,
            CarbonImmutable::parse($period->starts_on)->addDays(2),
            'часть',
            $line,
            $user->id,
            $debit,
        );

        $this->actingAs($user)
            ->get('/?period='.$period->id)
            ->assertOk()
            ->assertSee('+187,51')
            ->assertSee('200,00')
            ->assertSee('12,49')
            ->assertSee('360,25')
            ->assertDontSee('+191,51');
    }

    public function test_a_future_month_assumes_the_open_plan_will_be_spent(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $generator = app(PeriodGenerator::class);
        $current = $generator->periodFor($household, CarbonImmutable::now($household->timezone));
        $next = $generator->periodAfter($current);
        $telecom = $household->categories()->where('name', 'Telekomunikacija')->firstOrFail();
        $debit = $household->accounts()->where('name', 'Luminor Debet')->firstOrFail();
        $line = $current->planLines()->where('title', 'Tet')->firstOrFail();

        $generator->addPlanLine($current, $telecom, 'Дополнительно', 50_000);
        $generator->record(
            $household,
            $telecom,
            CashflowType::Expense,
            400,
            CarbonImmutable::parse($current->starts_on)->addDays(2),
            'часть',
            $line,
            $user->id,
            $debit,
        );

        $this->actingAs($user)
            ->get('/?period='.$next->id)
            ->assertOk()
            ->assertDontSee('Всего денег на счетах')
            ->assertDontSee('Накопления')
            ->assertSee('Баланс Ноябрь 2026')
            ->assertSee('894,21')
            ->assertSee('Баланс Октябрь 2026')
            ->assertSee('−139,75')
            ->assertDontSee('−308,49')
            ->assertDontSee('−312,49')
            ->assertDontSee('1 402,70');

        $later = $generator->periodAfter($next);

        $this->actingAs($user)
            ->get('/?period='.$later->id)
            ->assertOk()
            ->assertDontSee('Баланс '.ru_month($next->starts_on))
            ->assertDontSee('Баланс '.ru_month($current->starts_on))
            ->assertSee('Баланс '.ru_month($later->starts_on))
            ->assertSee('Сводка')
            ->assertDontSee('Сводка и баланс этого месяца не считаются.');
    }

    public function test_on_the_first_unspent_money_is_credited_to_the_new_month(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $generator = app(PeriodGenerator::class);
        $current = $generator->periodFor($household, CarbonImmutable::now($household->timezone));
        $next = $generator->periodAfter($current);
        $telecom = $household->categories()->where('name', 'Telekomunikacija')->firstOrFail();

        $generator->addPlanLine($current, $telecom, 'Дополнительно', 50_000);

        $this->travelTo(CarbonImmutable::parse($current->ends_on)->addDay()->startOfDay());

        $this->actingAs($user)
            ->get('/?period='.$next->id)
            ->assertOk()
            ->assertSee('172,74')
            ->assertSee('1 206,70')
            ->assertDontSee('Октябрь 2026')
            ->assertDontSee('−312,49');
    }

    public function test_copying_a_budget_adds_lines_without_removing_the_next_month(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $generator = app(PeriodGenerator::class);
        $current = $generator->periodFor($household, CarbonImmutable::now($household->timezone));
        $next = $generator->periodAfter($current);
        $salary = $next->planLines()->where('title', 'Зарплата')->firstOrFail();
        $octoberLines = $current->planLines()->count();
        $novemberLines = $next->planLines()->count();

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $current->id])
            ->test(Dashboard::class)
            ->call('copyToNextMonth')
            ->assertSet('periodId', $next->id)
            ->assertSee(ru_month($next->starts_on))
            ->assertSee('Уже скопированные строки не повторялись.');

        $this->assertSame($octoberLines, $current->planLines()->count());
        $this->assertSame($novemberLines + $octoberLines - 2, $next->planLines()->count());
        $copiedCigarettes = $next->planLines()->where('title', 'Сигареты')->firstOrFail();
        $this->assertSame(
            (int) $current->planLines()->where('title', 'Сигареты')->value('id'),
            (int) $copiedCigarettes->source_line_id,
        );
        $this->assertSame(150_000, $salary->fresh()->amount_cents);
        $this->assertSame(2, $next->planLines()->where('title', 'Платеж по кредиту')->count());

        $advance = $next->planLines()->where('title', 'Аванс')->firstOrFail();
        $this->assertSame(20_000, $advance->amount_cents);
        $this->assertSame(
            CarbonImmutable::parse($current->planLines()->where('title', 'Аванс')->value('expected_on'))->addMonthNoOverflow()->toDateString(),
            $advance->expected_on->toDateString(),
        );
        $this->assertSame(0, $next->transactions()->count());
        $this->assertSame(
            (int) $current->planLines()->where('title', 'Аванс')->value('id'),
            (int) $advance->fresh()->source_line_id,
        );

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $current->id])
            ->test(Dashboard::class)
            ->call('copyToNextMonth')
            ->assertSet('periodId', $next->id)
            ->assertSee('Бюджет уже скопирован')
            ->assertSee('Сводка');

        $this->assertSame($novemberLines + $octoberLines - 2, $next->planLines()->count());
    }

    public function test_copying_onto_zero_template_shells_does_not_duplicate_salary(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $generator = app(PeriodGenerator::class);
        $october = $generator->periodFor($household, CarbonImmutable::now($household->timezone));
        $november = $generator->periodAfter($october);
        $income = $household->categories()->where('name', 'Доход')->firstOrFail();
        $salary = $november->planLines()->where('title', 'Зарплата')->firstOrFail();

        $template = $household->templates()->create([
            'category_id' => $income->id,
            'title' => 'Зарплата',
            'amount_cents' => 0,
            'expected_day' => null,
            'active' => true,
        ]);

        $december = $generator->periodAfter($november);
        $shell = $december->planLines()->where('title', 'Зарплата')->firstOrFail();
        $this->assertSame(0, (int) $shell->amount_cents);
        $this->assertSame((int) $template->id, (int) $shell->template_id);
        $this->assertNull($shell->source_line_id);

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $november->id])
            ->test(Dashboard::class)
            ->call('copyToNextMonth')
            ->assertSet('periodId', $december->id)
            ->assertSee('Сводка')
            ->assertSee('План добавлен');

        $decemberSalaries = $december->planLines()->where('title', 'Зарплата')->orderBy('id')->get();
        $this->assertCount(1, $decemberSalaries);
        $this->assertSame(150_000, (int) $decemberSalaries->first()->amount_cents);
        $this->assertSame((int) $salary->id, (int) $decemberSalaries->first()->source_line_id);
        $this->assertSame(
            CarbonImmutable::parse($salary->expected_on)->addMonthNoOverflow()->toDateString(),
            $decemberSalaries->first()->expected_on->toDateString(),
        );

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $november->id])
            ->test(Dashboard::class)
            ->call('copyToNextMonth')
            ->assertSet('periodId', $december->id)
            ->assertSee('Бюджет уже скопирован');

        $this->assertSame(1, $december->planLines()->where('title', 'Зарплата')->count());
    }

    public function test_recopy_removes_leftover_zero_template_salary_duplicate(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $generator = app(PeriodGenerator::class);
        $october = $generator->periodFor($household, CarbonImmutable::now($household->timezone));
        $november = $generator->periodAfter($october);
        $income = $household->categories()->where('name', 'Доход')->firstOrFail();
        $salary = $november->planLines()->where('title', 'Зарплата')->firstOrFail();
        $debit = $household->accounts()->where('name', 'Luminor Debet')->firstOrFail();

        $template = $household->templates()->create([
            'category_id' => $income->id,
            'title' => 'Зарплата',
            'amount_cents' => 0,
            'expected_day' => null,
            'active' => true,
        ]);

        $december = $generator->periodAfter($november);
        $shell = $december->planLines()->where('title', 'Зарплата')->firstOrFail();
        $this->assertSame((int) $template->id, (int) $shell->template_id);

        // Simulate the old buggy copy: linked line beside the empty template shell.
        $copied = $generator->addPlanLine(
            $december,
            $income,
            'Зарплата',
            150_000,
            CarbonImmutable::parse($salary->expected_on)->addMonthNoOverflow(),
            account: $debit,
        );
        $copied->update(['source_line_id' => $salary->id]);
        $this->assertSame(2, $december->planLines()->where('title', 'Зарплата')->count());

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $november->id])
            ->test(Dashboard::class)
            ->call('copyToNextMonth')
            ->assertSet('periodId', $december->id)
            ->assertSee('Сводка');

        $remaining = $december->planLines()->where('title', 'Зарплата')->get();
        $this->assertCount(1, $remaining);
        $this->assertSame((int) $copied->id, (int) $remaining->first()->id);
        $this->assertSame(150_000, (int) $remaining->first()->amount_cents);
        $this->assertNull($shell->fresh());

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $november->id])
            ->test(Dashboard::class)
            ->call('copyToNextMonth')
            ->assertSet('periodId', $december->id)
            ->assertSee('Бюджет уже скопирован');

        $this->assertSame(1, $december->planLines()->where('title', 'Зарплата')->count());
    }

    public function test_a_far_future_month_always_shows_summary_even_without_income(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $generator = app(PeriodGenerator::class);
        $current = $generator->periodFor($household, CarbonImmutable::now($household->timezone));
        $later = $generator->periodAfter($generator->periodAfter($current));

        $this->actingAs($user)
            ->get('/?period='.$later->id)
            ->assertOk()
            ->assertSee('Сводка')
            ->assertSee('Баланс '.ru_month($later->starts_on))
            ->assertSee('0,00')
            ->assertDontSee('Сводка и баланс этого месяца не считаются.');
    }

    public function test_the_reporting_period_is_a_calendar_month(): void
    {
        $household = Household::query()->create([
            'name' => 'Наша семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);

        $generator = app(PeriodGenerator::class);
        $current = $generator->periodFor($household, CarbonImmutable::parse('2026-10-18'));
        $next = $generator->periodAfter($current);

        $this->assertSame('2026-10-01', $current->starts_on->toDateString());
        $this->assertSame('2026-10-31', $current->ends_on->toDateString());
        $this->assertSame('2026-11-01', $next->starts_on->toDateString());
        $this->assertSame('2026-11-30', $next->ends_on->toDateString());
    }

    public function test_existing_payday_periods_become_calendar_months(): void
    {
        $household = Household::query()->create([
            'name' => 'Наша семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 11,
        ]);
        $income = $household->categories()->create([
            'type' => CashflowType::Income,
            'name' => 'Доход',
            'sort' => 1,
            'color' => '#0f6e6b',
        ]);

        $generator = app(PeriodGenerator::class);
        $october = $generator->periodFor($household, CarbonImmutable::parse('2026-10-18'));
        $november = $generator->periodAfter($october);
        $advance = $generator->addPlanLine($october, $income, 'Аванс', 20_000, CarbonImmutable::parse('2026-10-26'));
        $salary = $generator->addPlanLine($november, $income, 'Зарплата', 150_000, CarbonImmutable::parse('2026-11-11'));

        $generator->useCalendarMonths($household->fresh());

        $october->refresh();
        $november->refresh();
        $advance->refresh();
        $salary->refresh();

        $this->assertSame(1, $household->fresh()->anchor_day);
        $this->assertSame('2026-10-01', $october->starts_on->toDateString());
        $this->assertSame('2026-10-31', $october->ends_on->toDateString());
        $this->assertSame('2026-11-01', $november->starts_on->toDateString());
        $this->assertSame('2026-11-30', $november->ends_on->toDateString());
        $this->assertSame($october->id, $advance->period_id);
        $this->assertSame($november->id, $salary->period_id);
    }

    public function test_custom_dates_cannot_overlap_another_period(): void
    {
        $household = Household::query()->create([
            'name' => 'Наша семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);

        $generator = app(PeriodGenerator::class);
        $current = $generator->periodFor($household, CarbonImmutable::parse('2026-10-18'));
        $generator->periodAfter($current);

        $this->expectException(InvalidArgumentException::class);

        $generator->updateBounds(
            $current->fresh(),
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-11-20'),
        );
    }

    public function test_a_recorded_expense_can_be_changed_and_removed(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $debit = $household->accounts()->where('name', 'Luminor Debet')->firstOrFail();
        $cigarettes = $household->categories()->where('name', 'Сигареты')->firstOrFail();
        $food = $household->categories()->where('name', 'Питание')->firstOrFail();
        $generator = app(PeriodGenerator::class);
        $period = $generator->periodFor($household, CarbonImmutable::now($household->timezone));
        $line = $period->planLines()->where('title', 'Сигареты')->firstOrFail();
        $date = CarbonImmutable::parse($period->starts_on)->addDays(2);

        $transaction = $generator->record(
            $household,
            $cigarettes,
            CashflowType::Expense,
            5_000,
            $date,
            null,
            $line,
            $user->id,
            $cash,
        );

        $this->assertSame(-4_800, $cash->fresh()->balance_cents);

        $component = Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->call('editTransaction', $transaction->id)
            ->set('editAmount', '40')
            ->set('editCategoryId', $food->id)
            ->set('editAccountId', $debit->id)
            ->set('editDate', $date->toDateString())
            ->set('editNote', 'магазин')
            ->call('saveEditedTransaction')
            ->assertHasNoErrors()
            ->assertSet('editingTransactionId', null);

        $transaction->refresh();
        $this->assertSame(4_000, $transaction->amount_cents);
        $this->assertSame($food->id, $transaction->category_id);
        $this->assertNull($transaction->plan_line_id);
        $this->assertSame($debit->id, $transaction->account_id);
        $this->assertSame('магазин', $transaction->note);
        $this->assertSame(200, $cash->fresh()->balance_cents);
        $this->assertSame(12_829, $debit->fresh()->balance_cents);

        $tet = $period->planLines()->where('title', 'Tet')->firstOrFail();
        $component
            ->call('editLine', $tet->id)
            ->set('lineCategoryId', $food->id)
            ->set('lineDate', $period->starts_on->toDateString())
            ->set('lineAccountId', $debit->id)
            ->call('saveLine')
            ->assertHasNoErrors();

        $tet->refresh();
        $this->assertSame('Tet', $tet->title);
        $this->assertSame($food->id, $tet->category_id);
        $this->assertSame($debit->id, $tet->account_id);

        $component->call('deleteTransaction', $transaction->id);

        $this->assertModelMissing($transaction);
        $this->assertSame(16_829, $debit->fresh()->balance_cents);
    }

    public function test_a_future_dated_expense_leaves_todays_wallet_immediately(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $cigarettes = $household->categories()->where('name', 'Сигареты')->firstOrFail();
        $income = $household->categories()->where('name', 'Доход')->firstOrFail();
        $generator = app(PeriodGenerator::class);
        $today = CarbonImmutable::now($household->timezone);
        $period = $generator->periodFor($household, $today);
        $line = $period->planLines()->where('title', 'Сигареты')->firstOrFail();

        $generator->record(
            $household,
            $cigarettes,
            CashflowType::Expense,
            5_000,
            $today->addDay(),
            null,
            $line,
            $user->id,
            $cash,
        );

        $this->assertSame(-4_800, (int) $cash->fresh()->balance_cents);

        $this->actingAs($user)
            ->get('/?period='.$period->id)
            ->assertOk()
            ->assertSee('−48,00')
            ->assertSee('122,74')
            ->assertDontSee('172,74')
            ->assertSee('Аванс');

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->call('toggleCategory', $income->id)
            ->assertSee('Аванс');
    }

    public function test_a_new_category_can_belong_to_a_parent(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $food = $household->categories()->where('name', 'Питание')->firstOrFail();
        $period = app(PeriodGenerator::class)->periodFor($household, CarbonImmutable::now($household->timezone));
        $cafe = $household->categories()->create([
            'parent_id' => $food->id,
            'type' => CashflowType::Expense,
            'name' => 'Кафе',
            'icon' => 'cup',
            'sort' => 61,
        ]);

        $plan = Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->call('togglePlan');
        $picker = $plan->html();
        $foodAt = strpos($picker, 'data-category-choice="'.$food->id.'"');
        $cafeAt = strpos($picker, 'data-category-choice="'.$cafe->id.'"');
        $this->assertNotFalse($foodAt);
        $this->assertNotFalse($cafeAt);
        $this->assertLessThan($cafeAt, $foodAt);
        $this->assertMatchesRegularExpression('/data-category-choice="'.$food->id.'"[^>]*font-weight:\s*700/', $picker);
        $this->assertMatchesRegularExpression('/data-category-choice="'.$cafe->id.'"[^>]*data-depth="1"/', $picker);

        $plan->assertDontSee('Материнская')
            ->set('newPlanType', 'expense')
            ->set('newPlanCategoryId', $cafe->id)
            ->assertSee('Питание → Кафе')
            ->set('newPlanAmount', '18,00')
            ->call('addPlanLine')
            ->assertHasNoErrors()
            ->assertSee('Кафе');

        $this->assertTrue(
            $period->planLines()->where('category_id', $cafe->id)->where('amount_cents', 1_800)->exists()
        );

        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->set('expenseCategoryId', $credit->id)
            ->call('addExpenseCategory')
            ->assertHasNoErrors();

        $this->assertTrue(
            $period->planLines()->where('category_id', $credit->id)->where('title', 'Кредит')->exists()
        );
    }

    public function test_a_budget_entry_stays_in_the_open_month(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $period = app(PeriodGenerator::class)->periodFor($household, CarbonImmutable::now($household->timezone));

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->call('togglePlan')
            ->assertSee('Добавить запись в бюджет')
            ->set('newPlanCategoryId', $household->categories()->where('name', 'Сигареты')->value('id'))
            ->set('newPlanAmount', '15,50')
            ->set('newPlanDate', $period->ends_on->toDateString())
            ->set('newPlanTitle', 'витамины')
            ->call('addPlanLine')
            ->assertHasNoErrors()
            ->assertSet('showPlan', false)
            ->set('newPlanCategoryId', $household->categories()->where('name', 'Сигареты')->value('id'))
            ->set('newPlanAmount', '1,00')
            ->set('newPlanDate', CarbonImmutable::parse($period->ends_on)->addDay()->toDateString())
            ->call('addPlanLine')
            ->assertHasErrors('newPlanDate');

        $line = $period->planLines()->where('title', 'витамины')->firstOrFail();
        $this->assertSame(1_550, $line->amount_cents);
        $this->assertSame($period->ends_on->toDateString(), $line->expected_on->toDateString());
        $this->assertSame($period->id, $line->period_id);
    }

    public function test_one_category_can_hold_several_planned_payments_with_or_without_a_date(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $cigarettes = $household->categories()->where('name', 'Сигареты')->firstOrFail();
        $period = app(PeriodGenerator::class)->periodFor($household, CarbonImmutable::now($household->timezone));
        $first = $period->planLines()->where('category_id', $cigarettes->id)->firstOrFail();

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->set('expenseCategoryId', $cigarettes->id)
            ->call('addExpenseCategory')
            ->assertHasNoErrors()
            ->assertSee('Дата, можно пусто')
            ->set('lineAmount', '12,00')
            ->set('lineDate', '')
            ->call('saveLine')
            ->assertHasNoErrors()
            ->call('editLine', $first->id)
            ->set('lineDate', $period->starts_on->toDateString())
            ->set('lineAmount', '50,00')
            ->call('saveLine')
            ->assertHasNoErrors()
            ->assertSee('12,00')
            ->assertSee('50,00');

        $second = $period->planLines()->where('category_id', $cigarettes->id)->whereKeyNot($first->id)->firstOrFail();
        $this->assertSame(1_200, $second->fresh()->amount_cents);
        $this->assertNull($second->fresh()->expected_on);
        $this->assertSame($period->starts_on->toDateString(), $first->fresh()->expected_on->toDateString());
        $this->assertSame(2, $period->planLines()->where('category_id', $cigarettes->id)->count());
    }

    public function test_several_operations_can_be_recorded_against_one_monthly_plan(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $period = app(PeriodGenerator::class)->periodFor($household, CarbonImmutable::now($household->timezone));
        $line = $period->planLines()->where('title', 'Сигареты')->firstOrFail();
        $plan = $line->amount_cents;
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->call('toggleDetail', $line->id)
            ->set('operationAmount', '4,50')
            ->set('operationNote', 'первая')
            ->call('addOperation')
            ->assertHasErrors(['operationAccountId'])
            ->assertSee('field-invalid')
            ->set('operationAccountId', $cash->id)
            ->call('addOperation')
            ->assertHasNoErrors()
            ->set('operationAmount', '3,20')
            ->set('operationNote', 'вторая')
            ->set('operationAccountId', $cash->id)
            ->call('addOperation')
            ->assertHasNoErrors();

        $this->assertSame($plan, $line->fresh()->amount_cents);
        $this->assertSame([450, 320], $line->transactions()->orderBy('id')->pluck('amount_cents')->all());
    }

    public function test_the_record_form_placeholders_the_unpaid_remainder_without_filling_it(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00:00', 'Europe/Riga'));
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $generator = app(PeriodGenerator::class);
        $period = $generator->periodFor($household, CarbonImmutable::parse('2026-11-11', 'Europe/Riga'));
        $line = $period->planLines()->where('title', 'Tet')->firstOrFail();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('toggleDetail', $line->id)
            ->assertSet('operationAmount', '')
            ->assertSeeHtml('placeholder="12,49"');

        $generator->record($household, $line->category, CashflowType::Expense, 450, CarbonImmutable::parse('2026-11-11'), null, $line, $user->id, $cash);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('toggleDetail', $line->id)
            ->assertSet('operationAmount', '')
            ->assertSeeHtml('placeholder="7,99"')
            ->call('toggleDeals', $line->id)
            ->assertSee('11.11.2026')
            ->assertDontSee('11 ноября');
    }

    public function test_the_record_form_opens_under_the_clicked_line_without_a_line_picker(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00:00', 'Europe/Riga'));
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $generator = app(PeriodGenerator::class);
        $period = $generator->periodFor($household, CarbonImmutable::parse('2026-11-11', 'Europe/Riga'));
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $inbank = $generator->addPlanLine($period, $credit, 'Inbank', 15_403, CarbonImmutable::parse('2026-11-15', 'Europe/Riga'));
        $sms = $generator->addPlanLine($period, $credit, 'SMScredit', 3_943, CarbonImmutable::parse('2026-11-27', 'Europe/Riga'));
        $creditea = $generator->addPlanLine($period, $credit, 'Creditea', 5_312, CarbonImmutable::parse('2026-11-28', 'Europe/Riga'));
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('toggleCategory', $credit->id)
            ->call('toggleDetail', $inbank->id)
            ->assertSet('detailLineId', $inbank->id)
            ->assertSet('operationLineId', $inbank->id)
            ->assertSet('operationAmount', '')
            ->assertSeeHtml('placeholder="154,03"')
            ->assertDontSeeHtml('placeholder="39,43"')
            ->assertDontSeeHtml('wire:model.live="operationLineId"')
            ->assertSeeHtmlInOrder([
                'wire:key="line-'.$inbank->id.'"',
                'placeholder="154,03"',
                'wire:key="line-'.$sms->id.'"',
            ])
            ->call('toggleDetail', $sms->id)
            ->assertSet('operationLineId', $sms->id)
            ->assertSeeHtml('placeholder="39,43"')
            ->assertDontSeeHtml('placeholder="154,03"')
            ->assertSeeHtmlInOrder([
                'wire:key="line-'.$inbank->id.'"',
                'wire:key="line-'.$sms->id.'"',
                'placeholder="39,43"',
                'wire:key="line-'.$creditea->id.'"',
            ])
            ->call('toggleDetail', $sms->id)
            ->assertSet('detailLineId', null)
            ->assertDontSeeHtml('wire:submit="addOperation"');

        $generator->record($household, $credit, CashflowType::Expense, 100, CarbonImmutable::parse('2026-11-11'), null, $sms, $user->id, $cash);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('toggleCategory', $credit->id)
            ->call('toggleDetail', $sms->id)
            ->assertSet('operationAmount', '')
            ->assertSeeHtml('placeholder="38,43"');
    }

    public function test_recording_on_the_second_child_does_not_attach_to_the_first(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00:00', 'Europe/Riga'));
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $generator = app(PeriodGenerator::class);
        $period = $generator->periodFor($household, CarbonImmutable::parse('2026-11-11', 'Europe/Riga'));
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $luminor = $generator->addPlanLine($period, $credit, 'Luminor Bank', 27_843, CarbonImmutable::parse('2026-11-10', 'Europe/Riga'));
        $creditea = $generator->addPlanLine($period, $credit, 'Creditea', 5_312, CarbonImmutable::parse('2026-11-12', 'Europe/Riga'));
        $food = $household->categories()->where('name', 'Питание')->firstOrFail();
        $groceries = $period->planLines()->where('title', 'Продукты домой')->firstOrFail();
        $groceries->update(['amount_cents' => 8_000]);
        $coffee = $generator->addPlanLine($period, $food, 'Кофе', 1_200, CarbonImmutable::parse('2026-11-12', 'Europe/Riga'));
        $internet = Category::query()->create([
            'household_id' => $household->id,
            'type' => CashflowType::Expense,
            'name' => 'Интернет услуги',
            'sort' => 70,
        ]);
        $tet = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $internet->id,
            'type' => CashflowType::Expense,
            'name' => 'Tet',
            'sort' => 71,
        ]);
        $homeNet = $generator->addPlanLine($period, $tet, 'Домашний', 4_000, CarbonImmutable::parse('2026-11-05', 'Europe/Riga'));
        $mobile = $generator->addPlanLine($period, $tet, 'Мобильный', 900, CarbonImmutable::parse('2026-11-20', 'Europe/Riga'));
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('toggleCategory', $credit->id)
            ->assertSeeHtml('wire:click="toggleDetail('.$creditea->id.')"')
            ->assertSeeHtml('wire:click="toggleDetail('.$luminor->id.')"')
            ->call('toggleDetail', $creditea->id)
            ->assertSet('detailLineId', $creditea->id)
            ->assertSet('operationLineId', $creditea->id)
            ->assertDontSeeHtml('wire:model.live="operationLineId"')
            ->assertSeeHtmlInOrder([
                'wire:key="line-'.$luminor->id.'"',
                'wire:key="line-'.$creditea->id.'"',
                'placeholder="53,12"',
            ])
            ->set('operationAmount', '10,00')
            ->set('operationAccountId', $cash->id)
            ->call('addOperation')
            ->assertHasNoErrors()
            ->call('toggleCategory', $food->id)
            ->call('toggleDetail', $coffee->id)
            ->assertSet('operationLineId', $coffee->id)
            ->assertSeeHtmlInOrder([
                'wire:key="line-'.$groceries->id.'"',
                'wire:key="line-'.$coffee->id.'"',
                'placeholder="12,00"',
            ])
            ->set('operationAmount', '3,50')
            ->set('operationAccountId', $cash->id)
            ->call('addOperation')
            ->assertHasNoErrors()
            ->call('toggleCategory', $internet->id)
            ->call('toggleDetail', $mobile->id)
            ->assertSet('operationLineId', $mobile->id)
            ->assertSeeHtmlInOrder([
                'wire:key="line-'.$homeNet->id.'"',
                'wire:key="line-'.$mobile->id.'"',
                'placeholder="9,00"',
            ])
            ->set('operationAmount', '4,00')
            ->set('operationAccountId', $cash->id)
            ->call('addOperation')
            ->assertHasNoErrors();

        $this->assertSame($creditea->id, Transaction::query()->where('amount_cents', 1_000)->value('plan_line_id'));
        $this->assertSame($credit->id, Transaction::query()->where('amount_cents', 1_000)->value('category_id'));
        $this->assertSame(0, Transaction::query()->where('plan_line_id', $luminor->id)->count());
        $this->assertSame($coffee->id, Transaction::query()->where('amount_cents', 350)->value('plan_line_id'));
        $this->assertSame(0, Transaction::query()->where('plan_line_id', $groceries->id)->count());
        $this->assertSame($mobile->id, Transaction::query()->where('amount_cents', 400)->value('plan_line_id'));
        $this->assertSame($tet->id, Transaction::query()->where('amount_cents', 400)->value('category_id'));
        $this->assertSame(0, Transaction::query()->where('plan_line_id', $homeNet->id)->count());
    }

    public function test_a_plan_line_cannot_be_deleted_after_an_operation_is_recorded(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-18 12:00:00', 'Europe/Riga'));
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $generator = app(PeriodGenerator::class);
        $period = $generator->periodFor($household, CarbonImmutable::now($household->timezone));
        $cigarettes = $period->planLines()->where('title', 'Сигареты')->firstOrFail();
        $groceries = $period->planLines()->where('title', 'Продукты домой')->firstOrFail();
        $advance = $period->planLines()->where('title', 'Аванс')->firstOrFail();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();

        $generator->record(
            $household,
            $cigarettes->category,
            CashflowType::Expense,
            111,
            CarbonImmutable::parse('2026-10-18'),
            'пачка',
            $cigarettes,
            $user->id,
            $cash,
        );
        $generator->record(
            $household,
            $groceries->category,
            CashflowType::Expense,
            50,
            CarbonImmutable::parse('2026-10-18'),
            'хлеб',
            null,
            $user->id,
            $cash,
        );

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->assertDontSee('deletePlanLine('.$cigarettes->id.')', false)
            ->assertDontSee('deletePlanLine('.$groceries->id.')', false)
            ->assertSee('deletePlanLine('.$advance->id.')', false)
            ->call('editLine', $cigarettes->id)
            ->assertDontSee('deletePlanLine('.$cigarettes->id.')', false)
            ->call('deletePlanLine', $cigarettes->id)
            ->call('deletePlanLine', $groceries->id)
            ->call('deletePlanLine', $advance->id);

        $this->assertNotNull($cigarettes->fresh());
        $this->assertNotNull($groceries->fresh());
        $this->assertNull($advance->fresh());
        $this->assertSame(1, $cigarettes->transactions()->count());
        $this->assertSame(1, $groceries->category->transactions()->where('period_id', $period->id)->count());
    }

    public function test_repeating_categories_stay_checked_and_leave_on_the_next_month(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $periods = app(PeriodGenerator::class);
        $period = $periods->periodFor($household, CarbonImmutable::now($household->timezone));
        $next = $periods->periodAfter($period);
        $food = $household->categories()->where('name', 'Питание')->firstOrFail();
        $cigarettes = $household->categories()->where('name', 'Сигареты')->firstOrFail();
        $household->templates()->create([
            'category_id' => $food->id,
            'title' => 'обед',
            'amount_cents' => 1_250,
            'expected_day' => 5,
            'active' => true,
        ]);

        $margo = $household->categories()->create([
            'type' => CashflowType::Expense,
            'name' => 'Марго',
            'sort' => 90,
        ]);
        $foodLines = $period->planLines()->where('category_id', $food->id)->count();
        $nextFoodLines = $next->planLines()->where('category_id', $food->id)->count();

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Settings::class)
            ->assertSet('templateCategoryIds', [$food->id])
            ->assertSet('openRepeatingIds', [])
            ->set('templateCategoryIds', [$food->id, $cigarettes->id, $margo->id])
            ->assertSet('openRepeatingIds', [$cigarettes->id, $margo->id])
            ->set('templateAmounts.'.$margo->id, '50,00')
            ->call('syncTemplates')
            ->assertHasNoErrors()
            ->assertSet('templateCategoryIds', [$food->id, $cigarettes->id, $margo->id]);

        $foodTemplate = $household->templates()->where('category_id', $food->id)->where('active', true)->firstOrFail();
        $cigaretteTemplate = $household->templates()->where('category_id', $cigarettes->id)->where('active', true)->firstOrFail();
        $margoTemplate = $household->templates()->where('category_id', $margo->id)->where('active', true)->firstOrFail();
        $this->assertSame('обед', $foodTemplate->title);
        $this->assertSame(1_250, $foodTemplate->amount_cents);
        $this->assertSame('Сигареты', $cigaretteTemplate->title);
        $this->assertSame(0, $cigaretteTemplate->amount_cents);
        $this->assertNull($cigaretteTemplate->expected_day);
        $this->assertSame($foodLines, $period->planLines()->where('category_id', $food->id)->count());
        $this->assertSame($nextFoodLines, $next->planLines()->where('category_id', $food->id)->count());
        $this->assertSame(1, $period->planLines()->where('category_id', $margo->id)->count());
        $this->assertSame(1, $next->planLines()->where('category_id', $margo->id)->count());
        $this->assertSame(5_000, $period->planLines()->where('template_id', $margoTemplate->id)->value('amount_cents'));

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Settings::class)
            ->set('templateCategoryIds', [$food->id, $cigarettes->id, $margo->id])
            ->set('templateAmounts.'.$margo->id, '50,00')
            ->call('syncTemplates')
            ->assertHasNoErrors();

        $this->assertSame(1, $period->planLines()->where('category_id', $margo->id)->count());
        $this->assertSame(1, $next->planLines()->where('category_id', $margo->id)->count());

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Settings::class)
            ->set('templateCategoryIds', [$cigarettes->id])
            ->call('syncTemplates')
            ->assertHasNoErrors();

        $this->assertFalse($foodTemplate->fresh()->active);
        $this->assertFalse($margoTemplate->fresh()->active);
        $this->assertSame(1, $period->planLines()->where('category_id', $margo->id)->count());
        $this->assertSame(0, $next->planLines()->where('category_id', $margo->id)->count());
        $this->assertSame($foodLines, $period->planLines()->where('category_id', $food->id)->count());
        $this->assertSame($nextFoodLines, $next->planLines()->where('category_id', $food->id)->count());
    }

    public function test_a_category_parent_can_be_reassigned(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $food = $household->categories()->where('name', 'Питание')->firstOrFail();
        $cigarettes = $household->categories()->where('name', 'Сигареты')->firstOrFail();

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('editCategory', $cigarettes->id)
            ->set('editCategoryParentId', $food->id)
            ->call('saveCategory')
            ->assertHasNoErrors();

        $this->assertSame($food->id, $cigarettes->fresh()->parent_id);

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('editCategory', $cigarettes->id)
            ->set('editCategoryParentId', null)
            ->call('saveCategory')
            ->assertHasNoErrors();

        $this->assertNull($cigarettes->fresh()->parent_id);
    }

    public function test_a_parent_category_moves_under_another_and_keeps_its_children(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $food = $household->categories()->where('name', 'Питание')->firstOrFail();
        $telecom = $household->categories()->where('name', 'Telekomunikacija')->firstOrFail();
        $cafe = $household->categories()->create([
            'parent_id' => $food->id,
            'type' => CashflowType::Expense,
            'name' => 'Кафе',
            'icon' => 'out',
            'sort' => 61,
        ]);
        $generator = app(PeriodGenerator::class);
        $period = $generator->periodFor($household, CarbonImmutable::now($household->timezone));
        $generator->addPlanLine($period, $cafe, 'Кафе', 1_000);
        $before = app(PeriodSummary::class)->figures($period)->plannedExpense;

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('editCategory', $food->id)
            ->assertDontSee('Есть подкатегории, сама остаётся сверху')
            ->set('editCategoryParentId', $telecom->id)
            ->call('saveCategory')
            ->assertHasNoErrors()
            ->call('toggleCategory', $telecom->id)
            ->assertSee('Кафе');

        $this->assertSame($telecom->id, $food->fresh()->parent_id);
        $this->assertSame($food->id, $cafe->fresh()->parent_id);
        $this->assertSame($before, app(PeriodSummary::class)->figures($period)->plannedExpense);

        $cafeLineId = $period->planLines()->where('category_id', $cafe->id)->value('id');
        $table = Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->call('toggleCategory', $telecom->id);

        $this->assertStringContainsString('Открыть Питание', $table->html());
        $this->assertStringNotContainsString('wire:key="line-'.$cafeLineId.'"', $table->html());

        $table->call('toggleCategory', $food->id);

        $this->assertStringContainsString('Закрыть Питание', $table->html());
        $this->assertStringContainsString('wire:key="line-'.$cafeLineId.'" class="budget-subcategory budget-on-plan"', $table->html());
        $this->assertStringNotContainsString('budget-object', $table->html());
        $this->assertSame(1_000, $period->planLines()->where('category_id', $cafe->id)->value('amount_cents'));

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('editCategory', $telecom->id)
            ->set('editCategoryParentId', $cafe->id)
            ->call('saveCategory')
            ->assertHasErrors('editCategoryParentId');

        $this->assertNull($telecom->fresh()->parent_id);
    }

    public function test_moving_a_category_keeps_its_lines_and_operations(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $mortgage = $household->categories()->where('name', 'Ипотека')->firstOrFail();
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $generator = app(PeriodGenerator::class);
        $line = $mortgage->planLines()->where('title', 'Ипотечный кредит')->firstOrFail();
        $home = $line->period;

        $transaction = $generator->record(
            $household,
            $mortgage,
            CashflowType::Expense,
            2_830,
            CarbonImmutable::parse($home->starts_on)->addDays(2),
            'банк',
            $line,
            $user->id,
        );

        $lineIds = $home->planLines()->orderBy('id')->pluck('id')->all();
        $transactionIds = $home->transactions()->orderBy('id')->pluck('id')->all();

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('editCategory', $mortgage->id)
            ->set('editCategoryParentId', $credit->id)
            ->call('saveCategory')
            ->assertHasNoErrors();

        $this->assertSame($credit->id, $mortgage->fresh()->parent_id);
        $this->assertSame($lineIds, $home->planLines()->orderBy('id')->pluck('id')->all());
        $this->assertSame($transactionIds, $home->transactions()->orderBy('id')->pluck('id')->all());
        $this->assertSame($mortgage->id, $line->fresh()->category_id);
        $this->assertSame(28_300, $line->fresh()->amount_cents);
        $this->assertSame('Ипотечный кредит', $line->fresh()->title);
        $this->assertSame($mortgage->id, $transaction->fresh()->category_id);
        $this->assertSame(2_830, $transaction->fresh()->amount_cents);

        $html = Livewire::actingAs($user)
            ->withQueryParams(['period' => $home->id])
            ->test(Dashboard::class)
            ->call('toggleCategory', $credit->id)
            ->html();

        $this->assertStringContainsString('wire:key="line-'.$line->id.'" class="budget-subcategory budget-on-plan"', $html);
        $this->assertSame(1, substr_count($html, 'Операции Ипотека'));
        $this->assertStringNotContainsString('Сделок пока нет', $html);
    }

    public function test_expense_rows_turn_red_only_after_the_plan_is_exceeded(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $cigarettes = $household->categories()->where('name', 'Сигареты')->firstOrFail();
        $generator = app(PeriodGenerator::class);
        $period = $generator->periodFor($household, CarbonImmutable::now($household->timezone));
        $line = $period->planLines()->where('category_id', $cigarettes->id)->firstOrFail();

        $within = Livewire::actingAs($user)->test(Dashboard::class)->html();
        $this->assertMatchesRegularExpression('/budget-parent budget-on-plan[\s\S]{0,900}Сигареты/', $within);
        $this->assertDoesNotMatchRegularExpression('/budget-parent budget-over-plan[\s\S]{0,900}Сигареты/', $within);

        $generator->record(
            $household,
            $cigarettes,
            CashflowType::Expense,
            $line->amount_cents + 100,
            CarbonImmutable::parse($period->starts_on)->addDay(),
            'сверх плана',
            $line,
            $user->id,
        );

        $over = Livewire::actingAs($user)->test(Dashboard::class)->html();
        $this->assertMatchesRegularExpression('/budget-parent budget-over-plan[\s\S]{0,900}Сигареты/', $over);
    }

    public function test_a_fulfilled_plan_line_shows_done(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $telecom = $household->categories()->where('name', 'Telekomunikacija')->firstOrFail();
        $generator = app(PeriodGenerator::class);
        $period = $generator->periodFor($household, CarbonImmutable::now($household->timezone));
        $line = $period->planLines()->where('title', 'Tet')->firstOrFail();

        $generator->record(
            $household,
            $telecom,
            CashflowType::Expense,
            $line->amount_cents,
            CarbonImmutable::parse($period->starts_on)->addDay(),
            'ровно по плану',
            $line,
            $user->id,
        );

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('/ '.money($line->amount_cents))
            ->assertSee(money($line->amount_cents))
            ->assertSee('100%')
            ->assertDontSee('готово · ')
            ->assertSeeHtml('budget-figure-done')
            ->assertSeeHtml('budget-meter-done');
    }

    public function test_a_renamed_category_keeps_its_name_and_opens_its_deals(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $cigarettes = $household->categories()->where('name', 'Сигареты')->firstOrFail();
        $generator = app(PeriodGenerator::class);
        $period = $generator->periodFor($household, CarbonImmutable::now($household->timezone));
        $date = CarbonImmutable::parse($period->starts_on)->addDays(3);

        $generator->record(
            $household,
            $cigarettes,
            CashflowType::Expense,
            730,
            $date,
            'киоск',
            null,
            $user->id,
        );

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('editCategory', $cigarettes->id)
            ->set('editCategoryName', 'Табак')
            ->call('saveCategory')
            ->assertHasNoErrors()
            ->assertSet('editingCategoryId', null);

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('openNewCategory')
            ->assertSet('showNewCategory', true)
            ->set('categoryName', 'Без значка')
            ->set('categoryIcon', '')
            ->call('addCategory')
            ->assertHasNoErrors()
            ->assertSet('showNewCategory', false);

        $this->assertSame('out', $household->categories()->where('name', 'Без значка')->value('icon'));

        $line = $period->planLines()->where('category_id', $cigarettes->id)->firstOrFail();
        $this->assertSame('Табак', $line->title);

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->assertSee('Табак')
            ->assertDontSee('>изменить<', false)
            ->assertDontSee('Сделок пока нет')
            ->call('toggleDeals', $line->id)
            ->assertSee('киоск')
            ->assertSee('7,30 €')
            ->assertSee('Чек')
            ->assertSee(ru_date($date));
    }

    public function test_a_mistaken_fact_can_be_moved_into_another_months_plan(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $before = (int) $cash->balance_cents;
        $bank = Category::query()->create([
            'household_id' => $household->id,
            'type' => CashflowType::Expense,
            'name' => 'Банковские услуги',
            'sort' => 90,
        ]);
        $fee = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $bank->id,
            'type' => CashflowType::Expense,
            'name' => 'Комиссия',
            'sort' => 91,
        ]);
        $generator = app(PeriodGenerator::class);
        $october = $generator->periodFor($household, CarbonImmutable::parse('2026-10-23'));
        $transaction = $generator->record(
            $household,
            $fee,
            CashflowType::Expense,
            560,
            CarbonImmutable::parse('2026-10-23'),
            null,
            null,
            $user->id,
            $cash,
        );

        $this->assertSame($before - 560, (int) $cash->fresh()->balance_cents);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('editTransaction', $transaction->id)
            ->assertSet('planMonth', '2026-11')
            ->call('moveTransactionToPlan')
            ->assertHasNoErrors()
            ->assertSee('Факт убран');

        $this->assertSame($before, (int) $cash->fresh()->balance_cents);
        $this->assertNull(Transaction::query()->find($transaction->id));

        $november = $generator->periodFor($household, CarbonImmutable::parse('2026-11-01'));
        $line = $november->planLines()->where('category_id', $fee->id)->first();

        $this->assertNotNull($line);
        $this->assertSame('Комиссия', $line->title);
        $this->assertSame(560, (int) $line->amount_cents);
        $this->assertSame(0, $october->transactions()->where('category_id', $fee->id)->count());
    }

    public function test_a_closed_month_cannot_be_changed(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $food = $household->categories()->where('name', 'Питание')->firstOrFail();
        $generator = app(PeriodGenerator::class);
        $october = $generator->periodFor($household, CarbonImmutable::parse('2026-10-15'));
        $transaction = $generator->record(
            $household,
            $food,
            CashflowType::Expense,
            400,
            CarbonImmutable::parse('2026-10-15'),
            null,
            null,
            $user->id,
            $cash,
        );
        $line = $generator->addPlanLine($october, $food, 'Хлеб', 1000, null, null, $cash);
        $balance = (int) $cash->fresh()->balance_cents;

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00', 'Europe/Riga'));

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->set('periodId', $october->id)
            ->assertDontSee('Добавить запись в бюджет')
            ->assertDontSee('Изменить')
            ->call('editTransaction', $transaction->id)
            ->assertSet('editingTransactionId', null)
            ->assertSee('Прошлый месяц уже закрыт')
            ->call('deleteTransaction', $transaction->id)
            ->call('editLine', $line->id)
            ->assertSet('editingLineId', null)
            ->call('deletePlanLine', $line->id)
            ->call('togglePlan')
            ->assertSet('showPlan', false)
            ->set('quickKind', 'expense')
            ->set('quickAmount', '3')
            ->set('quickCategoryId', $food->id)
            ->set('quickAccountId', $cash->id)
            ->set('quickDate', '2026-10-15')
            ->call('saveQuick')
            ->assertHasErrors('quickDate')
            ->assertSee('Операцию можно записать только в текущем месяце.');

        $this->assertNotNull(Transaction::query()->find($transaction->id));
        $this->assertSame(1000, (int) $line->fresh()->amount_cents);
        $this->assertSame($balance, (int) $cash->fresh()->balance_cents);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('openQuick', 'expense')
            ->set('quickAmount', '2')
            ->set('quickCategoryId', $food->id)
            ->set('quickAccountId', $cash->id)
            ->set('quickDate', '2026-11-02')
            ->call('saveQuick')
            ->assertHasNoErrors();

        $this->assertSame($balance - 200, (int) $cash->fresh()->balance_cents);
        $this->assertSame(400, (int) Transaction::query()->find($transaction->id)->amount_cents);
    }

    public function test_note_fields_offer_previous_explanations_of_the_same_kind(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $generator = app(PeriodGenerator::class);
        $period = $generator->periodFor($household, CarbonImmutable::now($household->timezone));
        $cigarettes = $household->categories()->where('name', 'Сигареты')->firstOrFail();
        $income = $household->categories()->where('name', 'Доход')->firstOrFail();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $day = CarbonImmutable::parse($period->starts_on)->addDays(2);

        $generator->record($household, $cigarettes, CashflowType::Expense, 450, $day, 'магазин', null, $user->id, $cash);
        $generator->record($household, $cigarettes, CashflowType::Expense, 450, $day, 'Магазин', null, $user->id, $cash);
        $generator->record($household, $income, CashflowType::Income, 2000, $day, 'аванс', null, $user->id, $cash);

        $html = Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('openQuick', 'expense')
            ->assertSeeHtml('list="note-suggestions-expense"')
            ->html();

        $this->assertMatchesRegularExpression(
            '/<datalist id="note-suggestions-expense">.*<option value="Магазин"><\/option>.*<\/datalist>/s',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<datalist id="note-suggestions-expense">.*<option value="магазин"><\/option>.*<\/datalist>/s',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<datalist id="note-suggestions-expense">.*<option value="аванс"><\/option>.*<\/datalist>/s',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/<datalist id="note-suggestions-income">.*<option value="аванс"><\/option>.*<\/datalist>/s',
            $html,
        );
    }

    public function test_income_and_expense_can_be_added_from_the_buttons_above_the_table(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $cigarettes = $household->categories()->where('name', 'Сигареты')->firstOrFail();
        $income = $household->categories()->where('name', 'Доход')->firstOrFail();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $today = CarbonImmutable::now($household->timezone)->format('d.m.Y');

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('Добавить приход')
            ->assertSee('Добавить расход')
            ->call('openQuick', 'expense')
            ->assertSet('quickKind', 'expense')
            ->assertSet('quickDate', $today)
            ->set('quickCategoryId', $cigarettes->id)
            ->set('quickAmount', '4,50')
            ->call('saveQuick')
            ->assertHasErrors(['quickAccountId'])
            ->set('quickAccountId', $cash->id)
            ->set('quickNote', 'киоск')
            ->set('quickReceipt', \Illuminate\Http\UploadedFile::fake()->image('check.jpg'))
            ->call('saveQuick')
            ->assertHasNoErrors()
            ->assertSet('quickKind', null)
            ->call('openQuick', 'income')
            ->set('quickCategoryId', $income->id)
            ->set('quickAccountId', $cash->id)
            ->set('quickAmount', '20,00')
            ->set('quickNote', 'аванс')
            ->call('saveQuick')
            ->assertHasNoErrors()
            ->assertSet('quickKind', null)
            ->call('openQuick', 'expense')
            ->set('quickCategoryId', $cigarettes->id)
            ->set('quickAccountId', $cash->id)
            ->set('quickAmount', '.75')
            ->assertSet('quickAmount', '0,75')
            ->set('quickNote', 'мелочь')
            ->call('saveQuick')
            ->assertHasNoErrors()
            ->assertSet('quickKind', null);

        $expense = \App\Models\Transaction::query()->where('household_id', $household->id)->where('note', 'киоск')->firstOrFail();
        $this->assertSame(450, $expense->amount_cents);
        $this->assertSame($cigarettes->id, $expense->category_id);
        $this->assertNotNull($expense->receipt_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($expense->receipt_path);

        $debit = $household->accounts()->where('name', 'Luminor Debet')->firstOrFail();
        $balance = $debit->fresh()->balance_cents;

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('deleteTransaction', $expense->id);

        $this->assertNotNull($expense->fresh());
        $this->assertSame($balance, $debit->fresh()->balance_cents);

        $this->assertSame(2000, \App\Models\Transaction::query()->where('household_id', $household->id)->where('note', 'аванс')->value('amount_cents'));
        $this->assertSame(75, \App\Models\Transaction::query()->where('household_id', $household->id)->where('note', 'мелочь')->value('amount_cents'));
    }

    public function test_a_dot_in_an_amount_is_rewritten_to_a_comma(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('openQuick', 'expense')
            ->set('quickAmount', '12.50')
            ->assertSet('quickAmount', '12,50')
            ->set('transferAmount', '3.5')
            ->assertSet('transferAmount', '3,5');
    }

    public function test_saving_a_plan_amount_refreshes_income_expense_and_balance(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $generator = app(PeriodGenerator::class);
        $current = $generator->periodFor($household, CarbonImmutable::now($household->timezone));
        $next = $generator->periodAfter($current);
        $line = $next->planLines()->whereHas('category', fn ($query) => $query->where('type', CashflowType::Expense))->firstOrFail();
        $before = (int) $next->planLines()->whereHas('category', fn ($query) => $query->where('type', CashflowType::Expense))->sum('amount_cents');

        $component = Livewire::actingAs($user)
            ->withQueryParams(['period' => $next->id])
            ->test(Dashboard::class)
            ->call('editLine', $line->id)
            ->set('lineAmount', '9999,99')
            ->call('saveLine')
            ->assertHasNoErrors();

        $after = $before - $line->amount_cents + 999_999;
        $html = $component->html();

        $this->assertStringContainsString(money($after), $html);
        $this->assertStringNotContainsString(money($before), $html);
        $this->assertSame(999_999, $line->fresh()->amount_cents);
    }

    public function test_accordions_close_when_the_month_or_operation_changes(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $food = $household->categories()->where('name', 'Питание')->firstOrFail();
        $period = app(PeriodGenerator::class)->periodFor($household, CarbonImmutable::now($household->timezone));

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->call('toggleCategory', $food->id)
            ->assertSet('openCategoryIds', [$food->id])
            ->call('openQuick', 'expense')
            ->assertSet('openCategoryIds', [])
            ->assertSet('quickKind', 'expense')
            ->call('toggleCategory', $food->id)
            ->assertSet('quickKind', null)
            ->assertSet('openCategoryIds', [$food->id])
            ->call('previous')
            ->assertSet('openCategoryIds', [])
            ->call('closeAccordions')
            ->assertSet('dealsLineId', null);

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Settings::class)
            ->set('templateCategoryIds', [$food->id])
            ->set('openRepeatingIds', [$food->id])
            ->call('toggleCategory', $food->id)
            ->assertSet('openCategoryIds', [$food->id])
            ->assertSet('openRepeatingIds', [])
            ->call('closeAccordions')
            ->assertSet('openCategoryIds', [])
            ->assertSet('editingCategoryId', null);
    }

    public function test_empty_required_fields_block_plan_and_fact_saves(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $beforeTx = Transaction::query()->where('household_id', $household->id)->count();
        $beforeLines = $household->periods()->withCount('planLines')->get()->sum('plan_lines_count');

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('openQuick', 'expense')
            ->set('quickAmount', '')
            ->set('quickCategoryId', null)
            ->set('quickAccountId', null)
            ->call('saveQuick')
            ->assertHasErrors(['quickAmount', 'quickCategoryId', 'quickAccountId'])
            ->call('togglePlan')
            ->set('newPlanAmount', '')
            ->set('newPlanCategoryId', null)
            ->call('addPlanLine')
            ->assertHasErrors(['newPlanAmount', 'newPlanCategoryId']);

        $this->assertSame($beforeTx, Transaction::query()->where('household_id', $household->id)->count());
        $this->assertSame(
            $beforeLines,
            (int) $household->periods()->withCount('planLines')->get()->sum('plan_lines_count'),
        );
    }

    public function test_fact_without_plan_line_is_visible_editable_and_tied_to_a_line(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $generator = app(PeriodGenerator::class);
        $period = $generator->periodFor($household, CarbonImmutable::now($household->timezone));
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $category = Category::query()->create([
            'household_id' => $household->id,
            'type' => CashflowType::Expense,
            'name' => 'Непредвиденные расходы',
            'icon' => 'fuel',
            'sort' => 99,
        ]);
        $orphan = $generator->record(
            $household,
            $category,
            CashflowType::Expense,
            6000,
            CarbonImmutable::parse($period->starts_on)->addDays(2),
            'ошибка',
            null,
            $user->id,
            $cash,
        );
        $balance = (int) $cash->fresh()->balance_cents;

        $this->assertSame(0, $period->planLines()->where('category_id', $category->id)->count());
        $this->assertNull($orphan->plan_line_id);

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->call('toggleCategory', $category->id)
            ->assertSee('60,00')
            ->assertSee('ошибка')
            ->assertSee('Изменить')
            ->assertSee('Удалить')
            ->call('editTransaction', $orphan->id)
            ->assertSet('editingTransactionId', $orphan->id)
            ->call('deleteTransaction', $orphan->id);

        $this->assertNull(Transaction::query()->find($orphan->id));
        $this->assertSame($balance + 6000, (int) $cash->fresh()->balance_cents);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('openQuick', 'expense')
            ->set('quickCategoryId', $category->id)
            ->set('quickAccountId', $cash->id)
            ->set('quickAmount', '12,00')
            ->set('quickNote', 'видимый')
            ->call('saveQuick')
            ->assertHasNoErrors();

        $created = Transaction::query()->where('household_id', $household->id)->where('note', 'видимый')->firstOrFail();
        $this->assertNotNull($created->plan_line_id);
        $this->assertSame(0, (int) $created->planLine->amount_cents);
        $this->assertSame(1200, (int) $created->amount_cents);

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->call('toggleDeals', $created->plan_line_id)
            ->assertSee('видимый')
            ->assertSee('12,00')
            ->call('deleteTransaction', $created->id);

        $this->assertNull(Transaction::query()->find($created->id));
    }

    public function test_daily_spend_allowance_recalculates_when_the_calendar_day_rolls_over(): void
    {
        // Each render uses household-local today. The page stamps the next local midnight
        // so the browser can $refresh once; there is no wire:poll.
        $this->travelTo(CarbonImmutable::parse('2026-10-17 23:30:00', 'Europe/Riga'));
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $period = app(PeriodGenerator::class)->periodFor($household, CarbonImmutable::now($household->timezone));
        $planned = (int) $period->planLines()->where('title', 'Tet')->value('amount_cents');
        $calendar = app(PeriodCalendar::class);
        $pace = app(DailyPace::class);

        $dayN = CarbonImmutable::parse('2026-10-17', 'Europe/Riga');
        $dayNext = CarbonImmutable::parse('2026-10-18', 'Europe/Riga');
        $daysN = $calendar->spendableDays($period->starts_on, $period->ends_on, $dayN);
        $daysNext = $calendar->spendableDays($period->starts_on, $period->ends_on, $dayNext);
        $centsN = $pace->centsPerDay($planned, 0, $daysN);
        $centsNext = $pace->centsPerDay($planned, 0, $daysNext);

        $this->assertSame(15, $daysN);
        $this->assertSame(14, $daysNext);
        $this->assertNotSame($centsN, $centsNext);

        $labelN = __('в день ~:amount', ['amount' => money($centsN)]);
        $labelNext = __('в день ~:amount', ['amount' => money($centsNext)]);
        $midnight = CarbonImmutable::parse('2026-10-18 00:00:00', 'Europe/Riga')->getTimestamp();
        $serverNow = CarbonImmutable::parse('2026-10-17 23:30:00', 'Europe/Riga')->getTimestamp();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee($labelN, false)
            ->assertDontSee($labelNext, false)
            ->assertSee('data-budget-day="2026-10-17"', false)
            ->assertSee('data-budget-midnight="'.$midnight.'"', false)
            ->assertSee('data-budget-now="'.$serverNow.'"', false)
            ->assertDontSee('wire:poll', false);

        $this->travelTo(CarbonImmutable::parse('2026-10-18 00:05:00', 'Europe/Riga'));

        $midnightNext = CarbonImmutable::parse('2026-10-19 00:00:00', 'Europe/Riga')->getTimestamp();
        $serverNowNext = CarbonImmutable::parse('2026-10-18 00:05:00', 'Europe/Riga')->getTimestamp();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee($labelNext, false)
            ->assertDontSee($labelN, false)
            ->assertSee('data-budget-day="2026-10-18"', false)
            ->assertSee('data-budget-midnight="'.$midnightNext.'"', false)
            ->assertSee('data-budget-now="'.$serverNowNext.'"', false);
    }
}
