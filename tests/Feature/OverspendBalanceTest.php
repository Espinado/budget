<?php

namespace Tests\Feature;

use App\Enums\AccountRole;
use App\Enums\CashflowType;
use App\Models\Household;
use App\Models\User;
use App\Services\Budget\PeriodGenerator;
use App\Services\Budget\PeriodSummary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverspendBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_overspend_and_an_extra_credit_payment_reduce_summary_and_balance(): void
    {
        $user = User::factory()->create();
        $household = Household::query()->create([
            'name' => 'Семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $user->households()->attach($household->id, ['role' => 'owner']);
        $cash = $household->accounts()->create([
            'name' => 'Карта',
            'kind' => 'card',
            'role' => AccountRole::Operating,
            'balance_cents' => 100_000,
            'sort' => 10,
        ]);
        $food = $household->categories()->create([
            'type' => CashflowType::Expense,
            'name' => 'Еда',
            'sort' => 10,
        ]);
        $credit = $household->categories()->create([
            'type' => CashflowType::Expense,
            'name' => 'Кредит',
            'sort' => 20,
        ]);
        $income = $household->categories()->create([
            'type' => CashflowType::Income,
            'name' => 'Зарплата',
            'sort' => 30,
        ]);
        $periods = app(PeriodGenerator::class);
        $today = CarbonImmutable::now('Europe/Riga')->startOfDay();
        $period = $periods->periodFor($household, $today);
        $periods->addPlanLine($period, $income, 'Зарплата', 50_000, $today);
        $groceries = $periods->addPlanLine($period, $food, 'Продукты', 10_000, $today);
        $periods->addPlanLine($period, $food, 'Кафе', 10_000, $today);
        $periods->addPlanLine($period, $credit, 'Ипотека', 20_000, $today);
        $consumer = $periods->addPlanLine($period, $credit, 'Потребительский', 10_000, $today);

        $before = $this->actingAs($user)->get('/?period='.$period->id);
        $before->assertOk()
            ->assertSee('0,00 €')
            ->assertSee('1 000,00 €')
            ->assertDontSee('−90,00 €');

        $periods->record($household, $food, CashflowType::Expense, 15_000, $today, 'магазин', $groceries, $user->id, $cash);
        $periods->record($household, $credit, CashflowType::Expense, 14_000, $today, 'доплата', $consumer, $user->id, $cash);

        $this->assertSame(71_000, $cash->fresh()->balance_cents);
        $this->assertSame(20_000, app(PeriodSummary::class)->unbookedNet($period->fresh()));

        $this->actingAs($user)
            ->get('/?period='.$period->id)
            ->assertOk()
            ->assertSee('590,00 €')
            ->assertSee('−90,00 €')
            ->assertSee('910,00 €')
            ->assertSee('710,00 €');
    }

    public function test_a_payment_inside_the_plan_does_not_reduce_the_balance_twice(): void
    {
        $user = User::factory()->create();
        $household = Household::query()->create([
            'name' => 'Семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $user->households()->attach($household->id, ['role' => 'owner']);
        $cash = $household->accounts()->create([
            'name' => 'Карта',
            'kind' => 'card',
            'role' => AccountRole::Operating,
            'balance_cents' => 20_000,
            'sort' => 10,
        ]);
        $food = $household->categories()->create([
            'type' => CashflowType::Expense,
            'name' => 'Еда',
            'sort' => 10,
        ]);
        $periods = app(PeriodGenerator::class);
        $today = CarbonImmutable::now('Europe/Riga')->startOfDay();
        $period = $periods->periodFor($household, $today);
        $line = $periods->addPlanLine($period, $food, 'Продукты', 5_000, $today);

        $periods->record($household, $food, CashflowType::Expense, 2_000, $today, 'хлеб', $line, $user->id, $cash);

        $this->assertSame(18_000, $cash->fresh()->balance_cents);

        $this->actingAs($user)
            ->get('/?period='.$period->id)
            ->assertOk()
            ->assertSee('−50,00 €')
            ->assertSee('150,00 €')
            ->assertDontSee('130,00 €');
    }
}
