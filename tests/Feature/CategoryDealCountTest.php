<?php

namespace Tests\Feature;

use App\Enums\AccountRole;
use App\Enums\CashflowType;
use App\Livewire\Budget\Dashboard;
use App\Models\Household;
use App\Models\User;
use App\Services\Budget\PeriodGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryDealCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_percent_shows_only_its_own_deals(): void
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
        $carsharing = $household->categories()->create([
            'type' => CashflowType::Expense,
            'name' => 'Каршеринг',
            'sort' => 10,
        ]);
        $bolt = $household->categories()->create([
            'type' => CashflowType::Expense,
            'name' => 'Болт Драйв',
            'sort' => 20,
        ]);
        $transport = $household->categories()->create([
            'type' => CashflowType::Expense,
            'name' => 'Транспорт',
            'sort' => 30,
        ]);
        $taxi = $household->categories()->create([
            'parent_id' => $transport->id,
            'type' => CashflowType::Expense,
            'name' => 'Такси',
            'sort' => 31,
        ]);
        $bus = $household->categories()->create([
            'parent_id' => $transport->id,
            'type' => CashflowType::Expense,
            'name' => 'Автобус',
            'sort' => 32,
        ]);

        $periods = app(PeriodGenerator::class);
        $today = CarbonImmutable::now('Europe/Riga')->startOfDay();
        $period = $periods->periodFor($household, $today);
        $carLine = $periods->addPlanLine($period, $carsharing, 'Каршеринг', 10_000, $today);
        $periods->addPlanLine($period, $bolt, 'Болт Драйв', 10_000, $today);
        $taxiLine = $periods->addPlanLine($period, $taxi, 'Такси', 8_000, $today);
        $busLine = $periods->addPlanLine($period, $bus, 'Автобус', 8_000, $today);

        foreach ([2_500, 2_500, 2_500] as $cents) {
            $periods->record($household, $carsharing, CashflowType::Expense, $cents, $today, null, $carLine, $user->id, $cash);
        }
        foreach ([2_000, 2_000] as $cents) {
            $periods->record($household, $taxi, CashflowType::Expense, $cents, $today, null, $taxiLine, $user->id, $cash);
        }
        $periods->record($household, $bus, CashflowType::Expense, 1_600, $today, null, $busLine, $user->id, $cash);

        $closed = Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->html();

        $this->assertMeter($closed, 'Каршеринг', '75% (3)');
        $this->assertMeter($closed, 'Болт Драйв', '0%');
        $this->assertMeter($closed, 'Транспорт', '35%');
        $this->assertStringNotContainsString('% (0)', $closed);

        $open = Livewire::actingAs($user)
            ->withQueryParams(['period' => $period->id])
            ->test(Dashboard::class)
            ->call('toggleCategory', $transport->id)
            ->html();

        $this->assertMeter($open, 'Такси', '50% (2)');
        $this->assertMeter($open, 'Автобус', '20% (1)');
        $this->assertMeter($open, 'Каршеринг', '75% (3)');
        $this->assertMeter($open, 'Болт Драйв', '0%');
        $this->assertMeter($open, 'Транспорт', '35%');
        $this->assertStringNotContainsString('% (0)', $open);
    }

    private function assertMeter(string $html, string $name, string $label): void
    {
        $sheet = strstr($html, 'budget-sheet');
        $this->assertNotFalse($sheet);
        $found = preg_match(
            '/'.preg_quote($name, '/').'[\s\S]{0,4000}?class="budget-meter-percent">([\s\S]*?)<\/span>/',
            $sheet,
            $match,
        );
        $this->assertSame(1, $found, $name);

        $text = preg_replace('/<!--\[if (?:BLOCK|ENDBLOCK)\]><!\[endif\]-->/', '', $match[1]);
        $text = trim(preg_replace('/\s+/', ' ', $text));

        $this->assertSame($label, $text, $name);
    }
}
