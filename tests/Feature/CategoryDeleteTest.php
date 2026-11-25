<?php

namespace Tests\Feature;

use App\Enums\CashflowType;
use App\Livewire\Budget\Settings;
use App\Models\Category;
use App\Models\User;
use App\Services\Budget\PeriodGenerator;
use App\Support\CategoryIcons;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoBudgetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_parent_with_children_and_a_category_with_records_cannot_be_deleted(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $telecom = $household->categories()->where('name', 'Telekomunikacija')->firstOrFail();
        $cigarettes = $household->categories()->where('name', 'Сигареты')->firstOrFail();
        $food = $household->categories()->where('name', 'Питание')->firstOrFail();

        $empty = $household->categories()->create([
            'parent_id' => $food->id,
            'type' => CashflowType::Expense,
            'name' => 'Пустая',
            'icon' => CategoryIcons::guess('Пустая', 'expense'),
            'sort' => 90,
            'color' => '#9a3412',
        ]);

        $component = Livewire::actingAs($user)->test(Settings::class);
        $html = $component->call('toggleCategory', $food->id)->html();
        $this->assertStringNotContainsString('deleteCategory('.$telecom->id.')', $html);
        $this->assertStringNotContainsString('deleteCategory('.$cigarettes->id.')', $html);
        $this->assertStringNotContainsString('deleteCategory('.$food->id.')', $html);
        $this->assertStringContainsString('deleteCategory('.$empty->id.')', $html);

        $component->call('deleteCategory', $telecom->id)->assertHasErrors('categoryDelete');
        $this->assertModelExists($telecom);

        $component->call('deleteCategory', $cigarettes->id)->assertHasErrors('categoryDelete');
        $this->assertModelExists($cigarettes);

        $component->call('deleteCategory', $empty->id)->assertHasNoErrors();
        $this->assertModelMissing($empty);

        $repeating = $household->categories()->create([
            'type' => CashflowType::Expense,
            'name' => 'Повтор',
            'icon' => 'out',
            'sort' => 91,
            'color' => '#9a3412',
        ]);
        $household->templates()->create([
            'category_id' => $repeating->id,
            'title' => 'Повтор',
            'amount_cents' => 1000,
            'active' => true,
        ]);

        $component->call('deleteCategory', $repeating->id)->assertHasErrors('categoryDelete');
        $this->assertModelExists($repeating);
    }

    public function test_a_subcategory_with_an_operation_cannot_be_deleted(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $parent = $household->categories()->create([
            'type' => CashflowType::Expense,
            'name' => 'Связь',
            'icon' => 'signal',
            'sort' => 80,
            'color' => '#9a3412',
        ]);
        $child = $household->categories()->create([
            'parent_id' => $parent->id,
            'type' => CashflowType::Expense,
            'name' => 'Домашний интернет',
            'icon' => 'wifi',
            'sort' => 81,
            'color' => '#9a3412',
        ]);

        $component = Livewire::actingAs($user)->test(Settings::class);
        $open = $component->call('toggleCategory', $parent->id)->html();
        $this->assertStringNotContainsString('deleteCategory('.$parent->id.')', $open);
        $this->assertStringContainsString('deleteCategory('.$child->id.')', $open);
        $component->call('deleteCategory', $parent->id)->assertHasErrors('categoryDelete');
        $this->assertModelExists($parent);
        $this->assertModelExists($child);

        app(PeriodGenerator::class)->record(
            $household,
            $child,
            CashflowType::Expense,
            500,
            CarbonImmutable::now($household->timezone),
            null,
            null,
            $user->id,
        );

        $this->assertStringNotContainsString(
            'deleteCategory('.$child->id.')',
            Livewire::actingAs($user)->test(Settings::class)->call('toggleCategory', $parent->id)->html(),
        );
        $component->call('deleteCategory', $child->id)->assertHasErrors('categoryDelete');
        $this->assertSame(1, $child->transactions()->count());
        $this->assertModelExists($child);
    }
}
