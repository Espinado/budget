<?php

namespace Database\Seeders;

use App\Enums\CashflowType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\User;
use App\Services\Budget\PeriodGenerator;
use App\Support\CategoryIcons;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DemoBudgetSeeder extends Seeder
{
    public function run(): void
    {
        $household = Household::query()->create([
            'name' => 'Наша семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);

        $user = User::factory()->create([
            'name' => 'Демо',
            'email' => 'demo@budget.test',
        ]);
        $user->households()->attach($household->id, ['role' => 'owner']);

        $cash = $this->account($household, 'Наличные', 'cash', 200, 10);
        $debit = $this->account($household, 'Luminor Debet', 'bank', 16_829, 20);
        $this->account($household, 'Luminor Credit', 'bank', 245, 30);

        $income = $this->category($household, CashflowType::Income, 'Доход', 10);
        $telecom = $this->category($household, CashflowType::Expense, 'Telekomunikacija', 20);
        $credit = $this->category($household, CashflowType::Expense, 'Кредит', 30);
        $mortgage = $this->category($household, CashflowType::Expense, 'Ипотека', 40);
        $cigarettes = $this->category($household, CashflowType::Expense, 'Сигареты', 50);
        $food = $this->category($household, CashflowType::Expense, 'Питание', 60);

        $today = CarbonImmutable::now('Europe/Riga');
        $year = (int) $today->year;
        $generator = app(PeriodGenerator::class);
        $current = $generator->periodFor($household, $today);
        $current->update([
            'opening_manual' => true,
            'opening_balance_cents' => $cash->balance_cents + $debit->balance_cents + 245,
        ]);
        $next = $generator->periodAfter($current);

        $on = fn (int $month, int $day) => CarbonImmutable::create($year, $month, $day);

        $generator->addPlanLine($current, $income, 'Аванс', 20_000, $on(10, 26), account: $debit);
        $generator->addPlanLine($current, $telecom, 'Tet', 1_249, $on(10, 22));
        $generator->addPlanLine($current, $cigarettes, 'Сигареты', 0);
        $generator->addPlanLine($current, $food, 'Продукты домой', 0);

        $generator->addPlanLine($next, $income, 'Зарплата', 150_000, $on(11, 11), account: $debit);
        $generator->addPlanLine($next, $mortgage, 'Ипотечный кредит', 28_300, $on(11, 11));
        $generator->addPlanLine($next, $credit, 'Платеж по кредиту', 3_000, $on(11, 11));
        $generator->addPlanLine($next, $credit, 'Платеж по кредиту', 15_304, $on(11, 11));
        $generator->addPlanLine($next, $cigarettes, 'Сигареты', 0);
        $generator->addPlanLine($next, $food, 'Продукты домой', 0);
    }

    private function account(Household $household, string $name, string $kind, int $cents, int $sort): Account
    {
        return $household->accounts()->create([
            'name' => $name,
            'kind' => $kind,
            'balance_cents' => $cents,
            'sort' => $sort,
        ]);
    }

    private function category(Household $household, CashflowType $type, string $name, int $sort): Category
    {
        return $household->categories()->create([
            'type' => $type,
            'name' => $name,
            'icon' => CategoryIcons::guess($name, $type->value),
            'sort' => $sort,
        ]);
    }
}
