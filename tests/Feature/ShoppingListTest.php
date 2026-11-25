<?php

namespace Tests\Feature;

use App\Enums\CashflowType;
use App\Livewire\Budget\Shopping;
use App\Models\Household;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\Transaction;
use App\Models\User;
use App\Support\DefaultCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShoppingListTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_add_check_and_archive_food_only(): void
    {
        [$owner] = $this->family();
        $household = $owner->currentHousehold();
        DefaultCategories::install($household);

        $food = $household->categories()->where('name', 'Питание')->whereNull('parent_id')->firstOrFail();
        $cafe = $household->categories()->create([
            'parent_id' => $food->id,
            'type' => CashflowType::Expense,
            'name' => 'Кафе',
            'icon' => 'cup',
            'sort' => 1,
        ]);
        $home = $household->categories()->where('name', 'Дом')->whereNull('parent_id')->firstOrFail();

        $this->actingAs($owner)
            ->get('/budget')
            ->assertOk()
            ->assertSee('aria-label="Список покупок"', false)
            ->assertSee('Список покупок');

        $this->actingAs($owner)
            ->get('/shopping')
            ->assertOk()
            ->assertSee('Новый список')
            ->assertSee('Питание');

        Livewire::actingAs($owner)
            ->test(Shopping::class)
            ->assertSet('creating', true)
            ->set('createCategoryId', $home->id)
            ->call('createList')
            ->assertHasErrors(['createCategoryId'])
            ->assertSee('Выберите категорию из Питания.')
            ->set('createCategoryId', $food->id)
            ->call('createList')
            ->assertHasNoErrors()
            ->assertSet('creating', false)
            ->assertSee('Питание')
            ->assertSee('Добавьте продукты.');

        $list = ShoppingList::query()->where('household_id', $household->id)->firstOrFail();
        $this->assertSame('Список покупок', $list->name);
        $this->assertTrue($list->categories()->where('categories.id', $food->id)->exists());

        Livewire::actingAs($owner)
            ->test(Shopping::class)
            ->call('openAddItem', $food->id)
            ->set('itemName', 'Молоко')
            ->set('itemQuantity', '2')
            ->set('itemPriceAmount', '1,49')
            ->call('saveItem')
            ->assertSee('Молоко')
            ->assertSee('2')
            ->assertSee('1,49')
            ->assertSee('2,98')
            ->call('openAddCategory')
            ->set('addCategoryId', $home->id)
            ->call('addCategory')
            ->assertHasErrors(['addCategoryId'])
            ->set('addCategoryId', $cafe->id)
            ->call('addCategory')
            ->assertHasNoErrors()
            ->assertSee('Питание → Кафе')
            ->call('openAddItem', $cafe->id)
            ->set('itemName', 'Латте')
            ->call('saveItem')
            ->assertSee('Латте');

        $milk = ShoppingListItem::query()->where('name', 'Молоко')->firstOrFail();
        $this->assertSame(149, $milk->price_cents);
        $this->assertSame('2', $milk->quantity);
        $this->assertFalse($milk->is_checked);
        $this->assertNull($milk->archived_at);

        Livewire::actingAs($owner)
            ->test(Shopping::class)
            ->assertDontSee('Куплено')
            ->assertDontSee('Nopirkts')
            ->call('toggleChecked', $milk->id)
            ->assertSee('Молоко')
            ->assertSeeHtml('item-'.$milk->id.'-1')
            ->assertSeeHtml('line-through')
            ->call('archiveChecked')
            ->assertDontSee('>Молоко<', false)
            ->call('toggleArchive')
            ->assertSee('Архив')
            ->assertSee('Молоко');

        $this->assertNotNull($milk->fresh()->archived_at);
        $this->assertTrue($milk->fresh()->is_checked);
        $this->assertSame(0, ShoppingListItem::query()->where('shopping_list_id', $list->id)->whereNull('archived_at')->where('name', 'Молоко')->count());
        $this->assertSame(1, ShoppingListItem::query()->where('shopping_list_id', $list->id)->whereNull('archived_at')->where('name', 'Латте')->count());
        $this->assertSame(0, Transaction::query()->where('household_id', $household->id)->count());
    }

    public function test_edit_and_delete_item(): void
    {
        [$owner] = $this->family();
        $household = $owner->currentHousehold();
        DefaultCategories::install($household);
        $food = $household->categories()->where('name', 'Питание')->whereNull('parent_id')->firstOrFail();

        $list = ShoppingList::query()->create([
            'household_id' => $household->id,
            'name' => 'Список покупок',
        ]);
        $list->categories()->attach($food->id, ['sort' => 1]);
        $item = ShoppingListItem::query()->create([
            'shopping_list_id' => $list->id,
            'category_id' => $food->id,
            'name' => 'Хлеб',
            'sort' => 1,
        ]);

        Livewire::actingAs($owner)
            ->test(Shopping::class)
            ->call('openEdit', $item->id)
            ->set('editName', 'Батон')
            ->call('saveEdit')
            ->assertSee('Батон')
            ->assertSeeHtml('aria-label="Изменить"')
            ->assertSeeHtml('aria-label="Удалить"')
            ->assertDontSee('>Изменить<', false)
            ->assertDontSee('>Удалить<', false)
            ->call('deleteItem', $item->id)
            ->assertDontSee('Батон');

        $this->assertNull(ShoppingListItem::query()->find($item->id));
    }

    public function test_inline_qty_and_price_recalculate_sum(): void
    {
        [$owner] = $this->family();
        $household = $owner->currentHousehold();
        DefaultCategories::install($household);
        $food = $household->categories()->where('name', 'Питание')->whereNull('parent_id')->firstOrFail();

        $list = ShoppingList::query()->create([
            'household_id' => $household->id,
            'name' => 'Список покупок',
        ]);
        $list->categories()->attach($food->id, ['sort' => 1]);
        $item = ShoppingListItem::query()->create([
            'shopping_list_id' => $list->id,
            'category_id' => $food->id,
            'name' => 'Молоко',
            'quantity' => '2',
            'price_cents' => 149,
            'sort' => 1,
        ]);

        Livewire::actingAs($owner)
            ->test(Shopping::class)
            ->assertSee('2,98')
            ->call('startInlineEdit', $item->id, 'quantity')
            ->assertSet('inlineField', 'quantity')
            ->set('inlineQuantity', '3')
            ->call('saveInlineEdit')
            ->assertSet('inlineItemId', null)
            ->assertSee('4,47')
            ->call('startInlineEdit', $item->id, 'price')
            ->assertSet('inlineField', 'price')
            ->set('inlinePriceAmount', '2,00')
            ->call('saveInlineEdit')
            ->assertSee('6,00')
            ->call('startInlineEdit', $item->id, 'price')
            ->set('inlinePriceAmount', '')
            ->call('saveInlineEdit')
            ->assertSeeHtml('>—<');

        $item->refresh();
        $this->assertSame('3', $item->quantity);
        $this->assertNull($item->price_cents);
        $this->assertNull($item->lineTotalCents());
    }

    /**
     * @return array{0: User}
     */
    private function family(): array
    {
        $household = Household::query()->create([
            'name' => 'Наша семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $owner = User::factory()->create([
            'email' => 'shopper@example.com',
            'password' => 'secret-pass',
        ]);
        $owner->households()->attach($household->id, ['role' => 'owner']);

        return [$owner];
    }
}
