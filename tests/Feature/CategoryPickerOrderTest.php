<?php

namespace Tests\Feature;

use App\Enums\CashflowType;
use App\Livewire\Budget\Dashboard;
use App\Livewire\Budget\Settings;
use App\Models\Category;
use App\Models\Household;
use App\Models\User;
use App\Support\CategoryTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryPickerOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_picker_groups_each_parent_with_its_children_alphabetically(): void
    {
        $user = $this->owner();
        $household = $user->currentHousehold();
        $home = $this->category($household, 'Быт', 90);
        $water = $this->category($household, 'Вода', 40, $home->id);
        $chores = $this->category($household, 'Хозяйство', 2, $home->id);
        $transport = $this->category($household, 'Транспорт', 1);
        $fuel = $this->category($household, 'Бензин', 3, $transport->id);
        $diesel = $this->category($household, 'Дизель', 1, $fuel->id);
        $petrol = $this->category($household, 'АИ-95', 8, $fuel->id);
        $taxi = $this->category($household, 'Такси', 4, $transport->id);

        $storedSort = $household->categories()->orderBy('id')->pluck('sort', 'id')->all();

        $choices = CategoryTree::choices($household->categories()->with('parent')->orderBy('sort')->get());
        $this->assertSame(
            ['Быт', 'Вода', 'Хозяйство', 'Транспорт', 'Бензин', 'АИ-95', 'Дизель', 'Такси'],
            array_map(fn (array $choice): string => $choice['category']->name, $choices),
        );
        $this->assertSame([0, 1, 1, 0, 1, 2, 2, 1], array_map(fn (array $choice): int => $choice['depth'], $choices));
        $this->assertTrue($choices[0]['selectable']);
        $this->assertSame($storedSort, $household->categories()->orderBy('id')->pluck('sort', 'id')->all());

        $withoutHome = $household->categories()->with('parent')->whereKeyNot($home->id)->orderBy('sort')->get();
        $headed = CategoryTree::choices($withoutHome);
        $this->assertSame(
            ['Быт', 'Вода', 'Хозяйство', 'Транспорт', 'Бензин', 'АИ-95', 'Дизель', 'Такси'],
            array_map(fn (array $choice): string => $choice['category']->name, $headed),
        );
        $this->assertFalse($headed[0]['selectable']);
        $this->assertTrue($headed[1]['selectable']);
        $this->assertSame(1, $headed[1]['depth']);

        View::share('errors', new ViewErrorBag);
        $picker = view('livewire.budget.partials.category-picker', [
            'categories' => $withoutHome,
            'property' => 'newPlanCategoryId',
            'value' => null,
            'placeholder' => 'Выберите',
        ])->render();
        $rows = $this->choiceRows($picker);
        $this->assertSame($home->id, $rows[0]['id']);
        $this->assertSame(0, $rows[0]['selectable']);
        $this->assertSame(700, $rows[0]['weight']);
        $this->assertSame($water->id, $rows[1]['id']);
        $this->assertSame(1, $rows[1]['selectable']);
        $this->assertSame(1, $rows[1]['depth']);
        $this->assertSame(400, $rows[1]['weight']);
        $this->assertGreaterThan($rows[0]['pad'], $rows[1]['pad']);

        $plan = Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('togglePlan')
            ->html();
        $this->assertSame(
            [$home->id, $water->id, $chores->id, $transport->id, $fuel->id, $petrol->id, $diesel->id, $taxi->id],
            array_column($this->choiceRows($plan), 'id'),
        );

        $settings = Livewire::actingAs($user)->test(Settings::class)->html();
        $catalogEnds = strpos($settings, 'Повторяется каждый период');
        $catalog = substr($settings, (int) strpos($settings, '>Категории<'), $catalogEnds - (int) strpos($settings, '>Категории<'));
        $this->assertMatchesRegularExpression('/Транспорт[\s\S]+Быт/', $catalog);
        $this->assertDoesNotMatchRegularExpression('/Быт[\s\S]+Транспорт/', $catalog);
        $this->assertSame(
            [$home->id, $water->id, $chores->id, $transport->id, $fuel->id, $petrol->id, $diesel->id, $taxi->id],
            array_column($this->choiceRows($settings), 'id'),
        );
    }

    public function test_latvian_locale_sorts_picker_choices_with_latvian_letters(): void
    {
        $user = $this->owner();
        $household = $user->currentHousehold();
        $apples = $this->category($household, 'Āboli', 50);
        $this->category($household, 'Sula', 2, $apples->id);
        $this->category($household, 'Miza', 1, $apples->id);
        $this->category($household, 'Čaula', 1);
        $land = $this->category($household, 'Zeme', 2);
        $this->category($household, 'Burkāns', 1, $land->id);
        $this->category($household, 'ābols', 9, $land->id);

        app()->setLocale('lv');

        $choices = CategoryTree::choices($household->categories()->with('parent')->orderBy('sort')->get());

        $this->assertSame(
            ['Āboli', 'Miza', 'Sula', 'Čaula', 'Zeme', 'ābols', 'Burkāns'],
            array_map(fn (array $choice): string => $choice['category']->name, $choices),
        );
        $this->assertSame([0, 1, 1, 0, 0, 1, 1], array_map(fn (array $choice): int => $choice['depth'], $choices));
    }

    /**
     * @return list<array{id: int, depth: int, selectable: int, pad: float, weight: int}>
     */
    private function choiceRows(string $html): array
    {
        preg_match_all(
            '/data-category-choice="(\d+)" data-depth="(\d+)" data-selectable="(\d)" style="padding-left: ([0-9.]+)rem;[^"]*font-weight:\s*(\d+)/',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        return array_map(fn (array $row): array => [
            'id' => (int) $row[1],
            'depth' => (int) $row[2],
            'selectable' => (int) $row[3],
            'pad' => (float) $row[4],
            'weight' => (int) $row[5],
        ], $matches);
    }

    private function owner(): User
    {
        $household = Household::query()->create([
            'name' => 'Семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $user = User::factory()->create();
        $user->households()->attach($household->id, ['role' => 'owner']);

        return $user;
    }

    private function category(Household $household, string $name, int $sort, ?int $parentId = null): Category
    {
        return $household->categories()->create([
            'parent_id' => $parentId,
            'type' => CashflowType::Expense,
            'name' => $name,
            'icon' => 'out',
            'sort' => $sort,
        ]);
    }
}
