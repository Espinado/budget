<?php

namespace Tests\Feature;

use App\Enums\CashflowType;
use App\Livewire\Budget\Dashboard;
use App\Models\Category;
use App\Models\PlanLine;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Budget\PeriodGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoBudgetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanLineResolveTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_absorbs_parent_title_line_instead_of_zero_shell(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $periods = app(PeriodGenerator::class);
        $period = $periods->periodFor($household, CarbonImmutable::parse('2026-11-05'));

        $food = Category::query()->where('household_id', $household->id)->where('name', 'Питание')->firstOrFail();
        $groceries = Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $food->id,
            'type' => CashflowType::Expense,
            'name' => 'Продукты домой',
            'sort' => 70,
            'icon' => 'bag',
        ]);

        $period->planLines()->where('category_id', $food->id)->where('title', 'Продукты домой')->delete();
        $budgeted = $periods->addPlanLine($period, $food, 'Продукты домой', 14_000);
        $shell = $periods->addPlanLine($period, $groceries, 'Продукты домой', 0);

        $resolved = $periods->resolvePlanLine($period, $groceries);

        $this->assertSame($budgeted->id, $resolved->id);
        $this->assertSame($groceries->id, (int) $resolved->fresh()->category_id);
        $this->assertSame(14_000, (int) $resolved->fresh()->amount_cents);
        $this->assertFalse(PlanLine::query()->whereKey($shell->id)->exists());
        $this->assertSame(
            1,
            PlanLine::query()
                ->where('period_id', $period->id)
                ->where('title', 'Продукты домой')
                ->count(),
        );
    }

    public function test_quick_fact_on_child_reuses_parent_named_plan(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $periods = app(PeriodGenerator::class);
        $today = CarbonImmutable::now($household->timezone);
        $period = $periods->periodFor($household, $today);

        $food = Category::query()->where('household_id', $household->id)->where('name', 'Питание')->firstOrFail();
        $groceries = Category::query()->firstOrCreate(
            ['household_id' => $household->id, 'name' => 'Продукты домой', 'type' => CashflowType::Expense],
            ['parent_id' => $food->id, 'sort' => 70, 'icon' => 'bag'],
        );
        $groceries->update(['parent_id' => $food->id]);

        PlanLine::query()
            ->where('period_id', $period->id)
            ->where(function ($query) use ($food, $groceries) {
                $query->where('category_id', $food->id)->where('title', 'Продукты домой')
                    ->orWhere('category_id', $groceries->id);
            })
            ->delete();

        $budgeted = $periods->addPlanLine($period, $food, 'Продукты домой', 14_000);
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('openQuick', 'expense')
            ->set('quickAmount', '0,67')
            ->set('quickCategoryId', $groceries->id)
            ->set('quickAccountId', $cash->id)
            ->set('quickDate', $today->toDateString())
            ->set('quickNote', 'Maxima')
            ->call('saveQuick')
            ->assertHasNoErrors();

        $tx = Transaction::query()->where('note', 'Maxima')->where('amount_cents', 67)->firstOrFail();

        $this->assertSame($budgeted->id, (int) $tx->plan_line_id);
        $this->assertSame($groceries->id, (int) $tx->category_id);
        $this->assertSame($groceries->id, (int) $budgeted->fresh()->category_id);
        $this->assertSame(
            1,
            PlanLine::query()
                ->where('period_id', $period->id)
                ->where('title', 'Продукты домой')
                ->count(),
        );
    }
}
