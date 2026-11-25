<?php

namespace Tests\Feature;

use App\Enums\CashflowType;
use App\Enums\DebtDirection;
use App\Enums\DebtKind;
use App\Enums\PropertyObjectKind;
use App\Livewire\Budget\Dashboard;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtInstallment;
use App\Models\Household;
use App\Models\PlanLine;
use App\Models\PropertyObject;
use App\Models\User;
use App\Services\Budget\PeriodSummary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PropertyMortgageCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_mortgage_line_sits_under_the_property_and_credit_header_leaves_it_out(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'Europe/Riga'));

        $household = Household::query()->create([
            'name' => 'Наша семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $user = User::factory()->create();
        $household->users()->attach($user->id, ['role' => 'owner']);

        $credit = $this->category($household, 'Кредит', 30);
        $home = $this->category($household, 'Дом', 40);
        $prusu = $this->category($household, 'Прушу', 50, $home->id);
        $mortgageCategory = $this->category($household, 'Luminor Bank', 51, $prusu->id);
        $jurmala = $this->category($household, 'Юрмала', 52, $home->id);
        $electricity = $this->category($household, 'Электричество Юрмала', 53, $jurmala->id);

        $november = $this->period($household, '2026-11-01', '2026-11-30');
        $december = $this->period($household, '2026-12-01', '2026-12-31');

        $novemberMortgage = $this->line($november, $credit, 'Luminor Bank', 27_843);
        $decemberMortgage = $this->line($december, $mortgageCategory, 'Luminor Bank', 28_463);
        $this->line($december, $credit, 'Creditea', 5_312);
        $this->line($december, $credit, 'Inbank', 15_403);
        $this->line($december, $electricity, 'Электричество Юрмала', 2_400);

        $debt = Debt::query()->create([
            'household_id' => $household->id,
            'category_id' => $credit->id,
            'direction' => DebtDirection::Borrow,
            'kind' => DebtKind::Bank,
            'counterparty' => 'Luminor Bank',
        ]);
        $novemberInstallment = $this->installment($debt, 1, '2026-11-10', 27_843, $novemberMortgage);
        $decemberInstallment = $this->installment($debt, 2, '2026-12-10', 28_463, $decemberMortgage);

        $property = PropertyObject::query()->create([
            'household_id' => $household->id,
            'name' => 'Прушу',
            'kind' => PropertyObjectKind::Apartment,
            'label' => 'Прушу',
        ]);
        $property->debts()->attach($debt->id);
        $property->planLines()->attach($decemberMortgage->id);

        $summary = app(PeriodSummary::class);
        $creditHeader = (int) $december->planLines()->where('category_id', $credit->id)->sum('amount_cents');
        $propertyTotal = (int) $december->planLines()->whereIn('category_id', [$prusu->id, $mortgageCategory->id])->sum('amount_cents');
        $novemberCredit = (int) $november->planLines()->where('category_id', $credit->id)->sum('amount_cents');

        $this->assertSame($prusu->id, (int) $decemberMortgage->fresh()->category->parent_id);
        $this->assertSame(0, $summary->propertyMortgagePlanOutsideCredit($december));
        $this->assertSame(5_312 + 15_403, $creditHeader);
        $this->assertSame(28_463, $propertyTotal);
        $this->assertSame(
            5_312 + 15_403 + 28_463 + 2_400,
            $summary->figures($december)->plannedExpense,
        );
        $this->assertSame(0, $summary->propertyMortgagePlanOutsideCredit($november));
        $this->assertSame(27_843, $novemberCredit);
        $this->assertSame($credit->id, (int) $novemberMortgage->fresh()->category_id);
        $this->assertSame($decemberMortgage->id, (int) $decemberInstallment->fresh()->plan_line_id);
        $this->assertSame(28_463, (int) $decemberInstallment->fresh()->total_cents);
        $this->assertSame($novemberMortgage->id, (int) $novemberInstallment->fresh()->plan_line_id);
        $this->assertNull($decemberInstallment->fresh()->paid_transaction_id);

        $decemberLines = $summary->groups($december)->first(fn ($group) => (int) $group->category->id === (int) $credit->id);
        $this->assertSame(['Creditea', 'Inbank'], $decemberLines->lines->pluck('title')->sort()->values()->all());

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $december->id])
            ->test(Dashboard::class)
            ->set('openCategoryIds', [$home->id, $prusu->id, $jurmala->id, $credit->id])
            ->assertSee('Прушу')
            ->assertSee('Luminor Bank')
            ->assertSee('Creditea')
            ->assertSee('Inbank')
            ->assertSee(money($creditHeader))
            ->assertSee(money($propertyTotal))
            ->assertDontSee(money($creditHeader + $propertyTotal))
            ->assertSee(money(2_400));

        $this->assertSame(1, PlanLine::query()->where('period_id', $december->id)->where('title', 'Luminor Bank')->count());
        $this->assertSame($credit->id, (int) $debt->fresh()->category_id);
    }

    private function category(Household $household, string $name, int $sort, ?int $parentId = null): Category
    {
        return Category::query()->create([
            'household_id' => $household->id,
            'parent_id' => $parentId,
            'type' => CashflowType::Expense,
            'name' => $name,
            'sort' => $sort,
        ]);
    }

    private function period(Household $household, string $starts, string $ends): BudgetPeriod
    {
        return BudgetPeriod::query()->create([
            'household_id' => $household->id,
            'starts_on' => $starts,
            'ends_on' => $ends,
            'opening_balance_cents' => 0,
            'status' => 'open',
        ]);
    }

    private function line(BudgetPeriod $period, Category $category, string $title, int $cents): PlanLine
    {
        return PlanLine::query()->create([
            'period_id' => $period->id,
            'category_id' => $category->id,
            'title' => $title,
            'amount_cents' => $cents,
        ]);
    }

    private function installment(Debt $debt, int $sequence, string $due, int $cents, PlanLine $line): DebtInstallment
    {
        return $debt->installments()->create([
            'sequence' => $sequence,
            'due_on' => $due,
            'principal_before_cents' => 1_000_000,
            'principal_cents' => $cents,
            'interest_cents' => 0,
            'other_cents' => 0,
            'fee_cents' => 0,
            'total_cents' => $cents,
            'plan_line_id' => $line->id,
        ]);
    }
}
