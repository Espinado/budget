<?php

namespace Tests\Feature;

use App\Enums\CashflowType;
use App\Enums\DebtDirection;
use App\Support\CreditCalculator;
use App\Livewire\Budget\Debts;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Budget\DebtBook;
use App\Services\Budget\PeriodGenerator;
use App\Services\Budget\Solvency;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoBudgetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SolvencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_month_uses_actual_income_and_paid_plus_planned_credits(): void
    {
        [$user, $household, $periods, $income, $food, $credit, $cash] = $this->octoberHousehold();
        $october = $periods->periodFor($household, CarbonImmutable::parse('2026-10-15'));

        $report = app(Solvency::class)->assess($household);

        $this->assertTrue($report['preliminary']);
        $this->assertSame(2, $report['months']);
        $this->assertSame(150_000, $report['income']);
        $this->assertSame(1_249, $report['living']);
        $this->assertSame(10_000, $report['obligations']);
        $this->assertSame(150_000 - 10_000 - 1_249, $report['free']);
        $this->assertSame((int) round(10_000 * 1000 / 150_000), $report['ratio_tenths']);
        $this->assertSame(99_999, $report['current_income']);
        $this->assertSame(10_000, $report['debt_cents']);
        $this->assertSame(50_000, $report['room_cents']);
        $this->assertFalse(collect($report['items'])->contains(fn (array $item): bool => $item['name'] === 'Друг'));
        $this->assertTrue($report['passed']);

        $periods->addPlanLine($october, $income, 'Зарплата', 250_000, CarbonImmutable::parse('2026-10-28'));
        $plannedOnly = app(Solvency::class)->assess($household);

        $this->assertSame(150_000, $plannedOnly['income']);
        $this->assertSame(1_249, $plannedOnly['living']);
        $this->assertSame(10_000, $plannedOnly['obligations']);
        $this->assertSame(150_000 - 10_000 - 1_249, $plannedOnly['free']);
        $this->assertSame((int) round(10_000 * 1000 / 150_000), $plannedOnly['ratio_tenths']);
        $this->assertTrue($plannedOnly['passed']);

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->assertSee('Регулярные взносы по банковским кредитам составляют 6,7 % от среднего дохода за 2 мес.')
            ->assertSee('Под новый платёж остаётся 500,00 €.');

        $fits = app(Solvency::class)->assess($household, 20_000);
        $this->assertTrue($fits['fits']);

        $tight = app(Solvency::class)->assess($household, 120_000);
        $this->assertFalse($tight['fits']);

        $creditLine = $periods->addPlanLine($october, $credit, 'Платеж октября', 12_000, CarbonImmutable::parse('2026-10-20'));
        $withCreditPlan = app(Solvency::class)->assess($household);

        $this->assertSame(1_249, $withCreditPlan['living']);
        $this->assertSame(22_000, $withCreditPlan['obligations']);
        $this->assertSame(150_000, $withCreditPlan['income']);

        $periods->record($household, $credit, CashflowType::Expense, 12_000, CarbonImmutable::parse('2026-10-20'), null, $creditLine, $user->id, $cash);
        $paid = app(Solvency::class)->assess($household);

        $this->assertSame(22_000, $paid['obligations']);
        $this->assertSame(150_000, $paid['income']);

        $periods->record($household, $credit, CashflowType::Expense, 40_000, CarbonImmutable::parse('2026-10-21'), null, null, $user->id, $cash);
        $overspent = app(Solvency::class)->assess($household);

        $this->assertSame(62_000, $overspent['obligations']);
        $this->assertSame(150_000, $overspent['income']);
        $this->assertSame(1_249, $overspent['living']);
        $this->assertSame(150_000 - 62_000 - 1_249, $overspent['free']);
        $this->assertSame((int) round(62_000 * 1000 / 150_000), $overspent['ratio_tenths']);
        $this->assertFalse($overspent['passed']);

        $december = $periods->periodFor($household, CarbonImmutable::parse('2026-12-15'));
        $periods->addPlanLine($december, $food, 'Повтор продуктов', 500_000, CarbonImmutable::parse('2026-12-05'));
        $periods->addPlanLine($december, $income, 'Будущая зарплата', 900_000, CarbonImmutable::parse('2026-12-11'));
        $withFuturePlans = app(Solvency::class)->assess($household);

        $this->assertSame($overspent['income'], $withFuturePlans['income']);
        $this->assertSame($overspent['living'], $withFuturePlans['living']);
        $this->assertSame($overspent['obligations'], $withFuturePlans['obligations']);
        $this->assertSame($overspent['free'], $withFuturePlans['free']);
        $this->assertSame($overspent['passed'], $withFuturePlans['passed']);

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->assertSee('Платежеспособность')
            ->assertSee('нет')
            ->assertSee('Предварительно')
            ->assertDontSee('>Да<')
            ->assertDontSee('>Нет<')
            ->assertSee('Регулярные взносы по банковским кредитам составляют 41,3 % от среднего дохода за 2 мес.')
            ->assertSee('Остаток долга — 0,0 годовых дохода из 6.')
            ->assertDontSee('Новый платёж')
            ->assertDontSee('Доля платежей')
            ->assertDontSee('План в доход не входит');
    }

    public function test_closed_average_is_used_when_the_open_month_has_no_planned_income(): void
    {
        [$user, $household, $periods] = $this->octoberHousehold();
        $october = $periods->periodFor($household, CarbonImmutable::parse('2026-10-15'));
        $income = $household->categories()->where('name', 'Доход')->firstOrFail();

        $october->planLines()->where('category_id', $income->id)->delete();

        $report = app(Solvency::class)->assess($household);

        $this->assertSame(150_000, $report['income']);
        $this->assertSame(1_249, $report['living']);
        $this->assertSame(10_000, $report['obligations']);
        $this->assertTrue($report['preliminary']);
        $this->assertTrue($report['passed']);
        $this->assertSame(99_999, $report['current_income']);

        Transaction::query()->where('household_id', $household->id)->where('type', CashflowType::Income)->delete();

        $empty = app(Solvency::class)->assess($household);

        $this->assertNull($empty['income']);
        $this->assertNull($empty['living']);
        $this->assertFalse($empty['passed']);
        $this->assertNull($empty['ratio_tenths']);

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->assertSee('Пока нет месяцев с полученным доходом, поэтому долю посчитать нельзя.');
    }

    public function test_only_received_income_counts_and_unreceived_plan_does_not(): void
    {
        [$user, $household, $periods, $income] = $this->octoberHousehold();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();

        Transaction::query()->where('household_id', $household->id)->where('type', CashflowType::Income)->delete();
        $periods->record($household, $income, CashflowType::Income, 20_000, CarbonImmutable::parse('2026-09-10'), null, null, $user->id, $cash);

        $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00:00', 'Europe/Riga'));
        $november = $periods->periodFor($household, CarbonImmutable::parse('2026-11-11'));
        $november->planLines()->whereHas('category', fn ($query) => $query->where('type', CashflowType::Income))->delete();
        $periods->addPlanLine($november, $income, 'Зарплата', 176_654, CarbonImmutable::parse('2026-11-11'));
        $periods->addPlanLine($november, $income, 'Аванс', 20_000, CarbonImmutable::parse('2026-11-05'));

        $before = app(Solvency::class)->assess($household);

        $this->assertSame(20_000, $before['income']);
        $this->assertSame(0, $before['current_income']);
        $this->assertSame(10_000 + 28_300, $before['obligations']);
        $this->assertSame((int) round($before['obligations'] * 1000 / 20_000), $before['ratio_tenths']);

        $periods->record($household, $income, CashflowType::Income, 176_656, CarbonImmutable::parse('2026-11-11'), null, null, $user->id, $cash);
        $after = app(Solvency::class)->assess($household);

        $this->assertSame((int) round((20_000 + 176_656) / 2), $after['income']);
        $this->assertSame(176_656, $after['current_income']);
        $this->assertSame($before['obligations'], $after['obligations']);
        $this->assertSame((int) round($after['obligations'] * 1000 / $after['income']), $after['ratio_tenths']);

        $periods->record($household, $income, CashflowType::Income, 100_000, CarbonImmutable::parse('2026-11-12'), null, null, $user->id, $cash);
        $abovePlan = app(Solvency::class)->assess($household);

        $this->assertSame((int) round((20_000 + 276_656) / 2), $abovePlan['income']);
        $this->assertSame(276_656, $abovePlan['current_income']);
        $this->assertSame((int) round($abovePlan['obligations'] * 1000 / $abovePlan['income']), $abovePlan['ratio_tenths']);
    }

    public function test_a_floating_rate_leaves_less_room_and_a_closed_debt_comes_out_of_the_load(): void
    {
        [$user, $household, $periods, , , $credit, $cash] = $this->octoberHousehold();

        app(DebtBook::class)->open(
            $household,
            DebtDirection::Borrow,
            'Плавающий',
            null,
            null,
            120_000,
            'annuity',
            12,
            CarbonImmutable::parse('2026-11-25'),
            2_000,
            3_000,
            CarbonImmutable::parse('2026-10-25'),
        );

        $report = app(Solvency::class)->assess($household);
        $contract = app(CreditCalculator::class)->annuity(120_000, 12, 5_000);
        $shocked = app(CreditCalculator::class)->annuity(120_000, 12, 7_000);
        $floating = collect($report['items'])->firstWhere('name', 'Плавающий');

        $this->assertNotNull($floating);
        $this->assertSame($contract, $floating['cents']);
        $this->assertNotNull($report['stressed_obligations']);
        $this->assertSame($report['obligations'] - $contract + $shocked, $report['stressed_obligations']);
        $this->assertLessThan($report['room_cents'], $report['stressed_room_cents']);
        $this->assertTrue(collect($report['replaceable'])->contains(
            fn (array $row): bool => $row['name'] === 'Плавающий' && $row['principal_cents'] === 120_000,
        ));

        $october = $household->periods()->whereDate('starts_on', '2026-10-01')->firstOrFail();
        $sameName = $periods->addPlanLine($october, $credit, 'Плавающий', 50_000, CarbonImmutable::parse('2026-10-28'));
        $periods->record($household, $credit, CashflowType::Expense, 50_000, CarbonImmutable::parse('2026-10-28'), null, $sameName, $user->id, $cash);
        $again = app(Solvency::class)->assess($household);

        $this->assertSame($report['obligations'], $again['obligations']);

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->assertSee('Запас под новый кредит')
            ->assertSee('Плавающий')
            ->assertSee('Если ставка с EURIBOR вырастет на 2 пункта');
    }

    /**
     * @return array{0: User, 1: Household, 2: PeriodGenerator, 3: \App\Models\Category, 4: \App\Models\Category, 5: \App\Models\Category, 6: \App\Models\Account}
     */
    private function octoberHousehold(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00', 'Europe/Riga'));
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $periods = app(PeriodGenerator::class);
        $income = $household->categories()->where('name', 'Доход')->firstOrFail();
        $food = $household->categories()->where('name', 'Питание')->firstOrFail();
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();

        $household->periods()->whereDate('starts_on', '2026-11-01')->firstOrFail()
            ->planLines()->where('title', 'Платеж по кредиту')->delete();

        $periods->record($household, $income, CashflowType::Income, 200_000, CarbonImmutable::parse('2026-09-10'), null, null, $user->id, $cash);
        $periods->record($household, $food, CashflowType::Expense, 30_000, CarbonImmutable::parse('2026-09-12'), null, null, $user->id, $cash);
        $periods->record($household, $credit, CashflowType::Expense, 10_000, CarbonImmutable::parse('2026-09-20'), null, null, $user->id, $cash);
        $periods->record($household, $income, CashflowType::Income, 99_999, CarbonImmutable::parse('2026-10-05'), null, null, $user->id, $cash);

        $rows = app(DebtBook::class)->draft(10_000, 'lump', 1, CarbonImmutable::parse('2026-11-15'), 0, CarbonImmutable::parse('2026-10-25'));
        app(DebtBook::class)->place($household, $credit, 'Касса', null, null, 0, $rows, DebtDirection::Borrow);
        $rows = app(DebtBook::class)->draft(50_000, 'lump', 1, CarbonImmutable::parse('2026-11-15'), 0, CarbonImmutable::parse('2026-10-25'));
        app(DebtBook::class)->place($household, $household->categories()->where('name', 'Дебитовые обязательства')->first() ?? $income, 'Друг', null, null, 0, $rows, DebtDirection::Lend);

        return [$user, $household, $periods, $income, $food, $credit, $cash];
    }
}
