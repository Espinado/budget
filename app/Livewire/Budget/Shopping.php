<?php

namespace App\Livewire\Budget;

use App\Livewire\Concerns\NormalizesMoneyInput;
use App\Models\Category;
use App\Models\Household;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Services\Budget\PeriodGenerator;
use App\Support\CategoryTree;
use App\Support\FoodCategories;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class Shopping extends Component
{
    use NormalizesMoneyInput;

    public bool $creating = false;

    public ?int $createCategoryId = null;

    public bool $addingCategory = false;

    public ?int $addCategoryId = null;

    public ?int $addingItemCategoryId = null;

    public string $itemName = '';

    public string $itemQuantity = '';

    public string $itemPriceAmount = '';

    public ?int $editingItemId = null;

    public string $editName = '';

    public ?int $inlineItemId = null;

    public ?string $inlineField = null;

    public string $inlineQuantity = '';

    public string $inlinePriceAmount = '';

    public bool $showArchive = false;

    public function mount(): void
    {
        $list = $this->list();

        if ($list === null) {
            $this->creating = true;
        }
    }

    public function startCreate(): void
    {
        $this->creating = true;
        $this->createCategoryId = null;
        $this->resetErrorBag();
    }

    public function createList(): void
    {
        $household = $this->household();
        $categoryId = (int) $this->createCategoryId;

        if ($categoryId < 1 || ! FoodCategories::contains($household, $categoryId)) {
            throw ValidationException::withMessages([
                'createCategoryId' => __('Выберите категорию из Питания.'),
            ]);
        }

        $this->resetErrorBag();

        if ($this->list() !== null) {
            $this->creating = false;

            return;
        }

        $list = ShoppingList::query()->create([
            'household_id' => $household->id,
            'name' => __('Список покупок'),
        ]);

        $list->categories()->attach($categoryId, ['sort' => 1]);
        $this->creating = false;
        $this->createCategoryId = null;
        session()->flash('status', __('Список создан. Добавьте продукты.'));
    }

    public function openAddCategory(): void
    {
        $this->addingCategory = true;
        $this->addCategoryId = null;
        $this->resetErrorBag();
    }

    public function closeAddCategory(): void
    {
        $this->addingCategory = false;
        $this->addCategoryId = null;
        $this->resetErrorBag();
    }

    public function addCategory(): void
    {
        $list = $this->requireList();
        $household = $this->household();
        $categoryId = (int) $this->addCategoryId;

        if ($categoryId < 1 || ! FoodCategories::contains($household, $categoryId)) {
            throw ValidationException::withMessages([
                'addCategoryId' => __('Выберите категорию из Питания.'),
            ]);
        }

        if ($list->categories()->where('categories.id', $categoryId)->exists()) {
            throw ValidationException::withMessages([
                'addCategoryId' => __('Эта категория уже в списке.'),
            ]);
        }

        $this->resetErrorBag();
        $sort = (int) $list->categories()->max('shopping_list_categories.sort') + 1;
        $list->categories()->attach($categoryId, ['sort' => $sort]);
        $this->closeAddCategory();
        session()->flash('status', __('Категория добавлена.'));
    }

    public function openAddItem(int $categoryId): void
    {
        $this->assertListCategory($categoryId);
        $this->addingItemCategoryId = $categoryId;
        $this->itemName = '';
        $this->itemQuantity = '';
        $this->itemPriceAmount = '';
        $this->editingItemId = null;
        $this->closeInlineEdit();
        $this->resetErrorBag();
    }

    public function closeAddItem(): void
    {
        $this->addingItemCategoryId = null;
        $this->itemName = '';
        $this->itemQuantity = '';
        $this->itemPriceAmount = '';
        $this->resetErrorBag();
    }

    public function saveItem(): void
    {
        $list = $this->requireList();
        $categoryId = (int) $this->addingItemCategoryId;
        $this->assertListCategory($categoryId);

        $name = trim($this->itemName);

        if ($name === '') {
            throw ValidationException::withMessages([
                'itemName' => __('Укажите название.'),
            ]);
        }

        $price = $this->optionalPrice($this->itemPriceAmount, 'itemPriceAmount');
        $this->resetErrorBag();
        $sort = (int) $list->items()->where('category_id', $categoryId)->max('sort') + 1;

        ShoppingListItem::query()->create([
            'shopping_list_id' => $list->id,
            'category_id' => $categoryId,
            'name' => $name,
            'quantity' => $this->nullableText($this->itemQuantity),
            'price_cents' => $price,
            'is_checked' => false,
            'sort' => $sort,
        ]);

        $this->closeAddItem();
    }

    public function toggleChecked(int $itemId): void
    {
        $item = $this->ownedItem($itemId);

        if ($item->archived_at !== null) {
            return;
        }

        $item->is_checked = ! $item->is_checked;
        $item->save();
    }

    public function archiveChecked(): void
    {
        $list = $this->requireList();
        $now = CarbonImmutable::now('Europe/Riga');

        $count = $list->items()
            ->whereNull('archived_at')
            ->where('is_checked', true)
            ->update(['archived_at' => $now]);

        if ($count === 0) {
            session()->flash('status', __('Отмеченных позиций нет.'));

            return;
        }

        session()->flash('status', __('Купленное убрано в архив.'));
    }

    public function openEdit(int $itemId): void
    {
        $item = $this->ownedItem($itemId);

        if ($item->archived_at !== null) {
            return;
        }

        $this->closeInlineEdit();
        $this->editingItemId = $item->id;
        $this->editName = $item->name;
        $this->addingItemCategoryId = null;
        $this->resetErrorBag();
    }

    public function closeEdit(): void
    {
        $this->editingItemId = null;
        $this->editName = '';
        $this->resetErrorBag();
    }

    public function saveEdit(): void
    {
        $item = $this->ownedItem((int) $this->editingItemId);

        if ($item->archived_at !== null) {
            $this->closeEdit();

            return;
        }

        $name = trim($this->editName);

        if ($name === '') {
            throw ValidationException::withMessages([
                'editName' => __('Укажите название.'),
            ]);
        }

        $this->resetErrorBag();
        $item->name = $name;
        $item->save();
        $this->closeEdit();
    }

    public function startInlineEdit(int $itemId, string $field): void
    {
        if (! in_array($field, ['quantity', 'price'], true)) {
            return;
        }

        $item = $this->ownedItem($itemId);

        if ($item->archived_at !== null) {
            return;
        }

        $this->closeEdit();
        $this->addingItemCategoryId = null;
        $this->inlineItemId = $item->id;
        $this->inlineField = $field;
        $this->inlineQuantity = (string) ($item->quantity ?? '');
        $this->inlinePriceAmount = $item->price_cents === null ? '' : Money::toInput($item->price_cents);
        $this->resetErrorBag();
    }

    public function closeInlineEdit(): void
    {
        $this->inlineItemId = null;
        $this->inlineField = null;
        $this->inlineQuantity = '';
        $this->inlinePriceAmount = '';
        $this->resetErrorBag('inlineQuantity', 'inlinePriceAmount');
    }

    public function saveInlineEdit(): void
    {
        if ($this->inlineItemId === null || $this->inlineField === null) {
            return;
        }

        $item = $this->ownedItem($this->inlineItemId);

        if ($item->archived_at !== null) {
            $this->closeInlineEdit();

            return;
        }

        if ($this->inlineField === 'quantity') {
            $this->resetErrorBag();
            $item->quantity = $this->nullableText($this->inlineQuantity);
            $item->save();
            $this->closeInlineEdit();

            return;
        }

        $price = $this->optionalPrice($this->inlinePriceAmount, 'inlinePriceAmount');
        $this->resetErrorBag();
        $item->price_cents = $price;
        $item->save();
        $this->closeInlineEdit();
    }

    public function deleteItem(int $itemId): void
    {
        $this->ownedItem($itemId)->delete();

        if ($this->editingItemId === $itemId) {
            $this->closeEdit();
        }

        if ($this->inlineItemId === $itemId) {
            $this->closeInlineEdit();
        }
    }

    public function toggleArchive(): void
    {
        $this->showArchive = ! $this->showArchive;
    }

    #[On('family-pulse')]
    public function refreshFromFamily(): void {}

    public function render()
    {
        $household = $this->household();
        $list = $this->list();
        $food = FoodCategories::tree($household);
        $sections = $list ? $this->sections($list) : collect();

        return view('livewire.budget.shopping', [
            'list' => $list,
            'foodCategories' => $food,
            'sections' => $sections,
            'availableFood' => $list
                ? $food->reject(fn (Category $category): bool => $list->categories->contains('id', $category->id))->values()
                : $food,
            'archivedItems' => $list && $this->showArchive
                ? $list->items()->whereNotNull('archived_at')->with('category')->orderByDesc('archived_at')->orderBy('id')->get()
                : collect(),
        ])->title(__('Список покупок — Family Money'));
    }

    /**
     * @return Collection<int, array{category: Category, label: string, items: Collection<int, ShoppingListItem>, priceSum: int, remaining: int, overPlan: bool}>
     */
    private function sections(ShoppingList $list): Collection
    {
        $list->loadMissing(['categories', 'activeItems']);
        $food = FoodCategories::tree($this->household());
        $itemsByCategory = $list->activeItems
            ->sortBy([['sort', 'asc'], ['id', 'asc']])
            ->groupBy('category_id');
        $remainingByCategory = $this->remainingByCategory($list);

        return $list->categories->map(function (Category $category) use ($food, $itemsByCategory, $remainingByCategory): array {
            $items = $itemsByCategory->get($category->id, collect())->values();
            $priceSum = (int) $items->sum(fn (ShoppingListItem $item): int => (int) ($item->lineTotalCents() ?? 0));
            $remaining = (int) ($remainingByCategory[$category->id] ?? 0);

            return [
                'category' => $category,
                'label' => CategoryTree::label($food, $category),
                'items' => $items,
                'priceSum' => $priceSum,
                'remaining' => $remaining,
                'overPlan' => $priceSum > 0 && $priceSum > $remaining,
            ];
        })->values();
    }

    /**
     * @return array<int, int>
     */
    private function remainingByCategory(ShoppingList $list): array
    {
        $household = $this->household();
        $period = app(PeriodGenerator::class)->periodFor(
            $household,
            CarbonImmutable::now('Europe/Riga'),
        );
        $categoryIds = $list->categories->pluck('id')->all();

        if ($categoryIds === []) {
            return [];
        }

        $planned = $period->planLines()
            ->whereIn('category_id', $categoryIds)
            ->get(['category_id', 'amount_cents'])
            ->groupBy('category_id')
            ->map(fn (Collection $rows): int => (int) $rows->sum('amount_cents'));

        $actual = $period->transactions()
            ->whereIn('category_id', $categoryIds)
            ->get(['category_id', 'amount_cents'])
            ->groupBy('category_id')
            ->map(fn (Collection $rows): int => (int) $rows->sum('amount_cents'));

        $out = [];

        foreach ($categoryIds as $categoryId) {
            $out[$categoryId] = max(0, (int) ($planned[$categoryId] ?? 0) - (int) ($actual[$categoryId] ?? 0));
        }

        return $out;
    }

    private function list(): ?ShoppingList
    {
        return ShoppingList::query()
            ->where('household_id', $this->household()->id)
            ->orderBy('id')
            ->first();
    }

    private function requireList(): ShoppingList
    {
        $list = $this->list();

        if ($list === null) {
            abort(404);
        }

        return $list;
    }

    private function assertListCategory(int $categoryId): void
    {
        $list = $this->requireList();

        if (! $list->categories()->where('categories.id', $categoryId)->exists()) {
            abort(404);
        }

        if (! FoodCategories::contains($this->household(), $categoryId)) {
            throw ValidationException::withMessages([
                'itemName' => __('Выберите категорию из Питания.'),
            ]);
        }
    }

    private function ownedItem(int $itemId): ShoppingListItem
    {
        return ShoppingListItem::query()
            ->whereKey($itemId)
            ->whereHas('list', fn ($query) => $query->where('household_id', $this->household()->id))
            ->firstOrFail();
    }

    private function optionalPrice(string $raw, string $field): ?int
    {
        if (trim($raw) === '') {
            return null;
        }

        $cents = Money::toCents($raw);

        if ($cents === null || $cents < 0) {
            throw ValidationException::withMessages([
                $field => __('Сумма в евро, до двух знаков после запятой.'),
            ]);
        }

        return $cents;
    }

    private function nullableText(string $raw): ?string
    {
        $text = trim($raw);

        return $text === '' ? null : $text;
    }

    private function household(): Household
    {
        return auth()->user()->currentHousehold();
    }
}
