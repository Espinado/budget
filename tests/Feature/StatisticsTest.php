<?php

namespace Tests\Feature;

use App\Enums\CashflowType;
use App\Livewire\Budget\Statistics;
use App\Models\Transfer;
use App\Models\User;
use App\Services\Budget\PeriodGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoBudgetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_statistics_follow_the_chosen_period(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-18 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $generator = app(PeriodGenerator::class);
        $income = $household->categories()->where('name', 'Доход')->firstOrFail();
        $food = $household->categories()->where('name', 'Питание')->firstOrFail();
        $cigarettes = $household->categories()->where('name', 'Сигареты')->firstOrFail();
        $telecom = $household->categories()->where('name', 'Telekomunikacija')->firstOrFail();

        $generator->record($household, $cigarettes, CashflowType::Expense, 111, CarbonImmutable::parse('2026-10-18'), null, null, $user->id);
        $generator->record($household, $food, CashflowType::Expense, 440, CarbonImmutable::parse('2026-10-01'), null, null, $user->id);
        $generator->record($household, $telecom, CashflowType::Expense, 990, CarbonImmutable::parse('2026-09-30'), null, null, $user->id);
        $generator->record($household, $income, CashflowType::Income, 770, CarbonImmutable::parse('2026-10-18'), null, null, $user->id);

        $home = $this->actingAs($user)->get('/budget');
        $home->assertOk()->assertSee('Статистика');
        $this->assertLessThan(
            mb_strpos($home->getContent(), 'Статистика'),
            mb_strpos($home->getContent(), 'Настройки'),
        );

        $this->actingAs($user)
            ->get('/statistics')
            ->assertOk()
            ->assertSee('Этот месяц')
            ->assertSee('1 октября 2026 — 31 октября 2026')
            ->assertSee('5,51')
            ->assertSee('7,70')
            ->assertDontSee('/ 200,00 €')
            ->assertDontSee('/ 0,00 €')
            ->assertSee('2,19')
            ->assertSee('Сигареты')
            ->assertSee('Питание')
            ->assertDontSee('9,90');

        $this->actingAs($user)
            ->get('/statistics?range=week')
            ->assertOk()
            ->assertSee('12 октября 2026 — 18 октября 2026')
            ->assertSee('1,11')
            ->assertSee('7,70')
            ->assertDontSee('4,40')
            ->assertDontSee('9,90');

        $this->actingAs($user)
            ->get('/statistics?range=custom&from=2026-10-01&to=2026-10-01')
            ->assertOk()
            ->assertSee('1 октября 2026 — 1 октября 2026')
            ->assertSee('4,40')
            ->assertDontSee('1,11')
            ->assertDontSee('7,70');

        $this->actingAs($user)
            ->get('/statistics?range=custom&from=2026-10-18&to=2026-10-01')
            ->assertOk()
            ->assertSee('Дата «по» не может быть раньше даты «с».')
            ->assertDontSee('Разница');
    }

    public function test_a_category_opens_subcategories_and_then_purchases(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-18 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $food = $household->categories()->where('name', 'Питание')->firstOrFail();
        $cafe = $household->categories()->create([
            'parent_id' => $food->id,
            'type' => CashflowType::Expense,
            'name' => 'Кафе',
            'icon' => 'out',
            'sort' => 61,
        ]);
        $latte = $household->categories()->create([
            'parent_id' => $cafe->id,
            'type' => CashflowType::Expense,
            'name' => 'Латте',
            'icon' => 'out',
            'sort' => 62,
        ]);
        $generator = app(PeriodGenerator::class);
        $generator->record($household, $cafe, CashflowType::Expense, 300, CarbonImmutable::parse('2026-10-18'), 'кофе', null, $user->id);
        $generator->record($household, $latte, CashflowType::Expense, 80, CarbonImmutable::parse('2026-10-18'), 'латте', null, $user->id);
        $generator->record($household, $food, CashflowType::Expense, 150, CarbonImmutable::parse('2026-10-02'), 'рынок', null, $user->id);
        $accounts = $household->accounts()->orderBy('sort')->get();
        Transfer::query()->create([
            'household_id' => $household->id,
            'from_account_id' => $accounts[0]->id,
            'to_account_id' => $accounts[1]->id,
            'amount_cents' => 500,
            'occurred_on' => '2026-10-18',
            'note' => 'между счетами',
            'created_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(Statistics::class)
            ->assertSee('Питание')
            ->assertSee('5,30')
            ->assertSee('5,00')
            ->assertDontSee('Кафе')
            ->assertDontSee('кофе')
            ->assertDontSee('между счетами')
            ->call('toggleTransfer', $accounts[0]->id, $accounts[1]->id)
            ->assertSee('между счетами')
            ->assertDontSee('Кафе')
            ->call('toggleCategory', $food->id)
            ->assertSee('Кафе')
            ->assertDontSee('между счетами')
            ->assertSee('Без подкатегории')
            ->assertSee('3,80')
            ->assertDontSee('Латте')
            ->assertDontSee('кофе')
            ->call('toggleCategory', $cafe->id)
            ->assertSee('Латте')
            ->assertSee('0,80')
            ->assertSee('Питание')
            ->call('selectCategory', $latte->id)
            ->assertSee('латте')
            ->assertDontSee('кофе')
            ->assertDontSee('5,00')
            ->call('selectCategory', $food->id, true)
            ->assertSee('рынок')
            ->assertDontSee('кофе')
            ->call('selectCategory')
            ->assertSee('5,00')
            ->assertDontSee('Кафе')
            ->assertDontSee('кофе')
            ->assertDontSee('между счетами');
    }
}
