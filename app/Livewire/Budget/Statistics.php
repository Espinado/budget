<?php

namespace App\Livewire\Budget;

use App\Enums\CashflowType;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Support\CategoryTree;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Statistics extends Component
{
    #[Url(as: 'range')]
    public string $range = 'month';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public ?int $category = null;

    #[Url]
    public bool $own = false;

    /** @var array<int, int> */
    public array $openCategoryIds = [];

    /** @var array<int, string> */
    public array $openTransferKeys = [];

    public function mount(): void
    {
        if (! in_array($this->range, ['month', 'week', 'custom'], true)) {
            $this->range = 'month';
        }

        if ($this->range === 'custom' && $this->datesAreValid()) {
            return;
        }

        if ($this->range === 'custom') {
            $this->range = 'month';
        }

        [$start, $end] = $this->preset($this->range);
        $this->from = $start->toDateString();
        $this->to = $end->toDateString();

        if ($this->category === null) {
            return;
        }

        $found = Category::query()
            ->where('household_id', $this->household()->id)
            ->find($this->category);

        if ($found === null) {
            $this->category = null;
            $this->own = false;

            return;
        }

        $this->openCategoryIds = $this->categoryPath($found);
    }

    public function setRange(string $range): void
    {
        if (! in_array($range, ['month', 'week', 'custom'], true)) {
            return;
        }

        $this->range = $range;
        $this->closeAccordions();

        if ($range === 'custom') {
            return;
        }

        [$start, $end] = $this->preset($range);
        $this->from = $start->toDateString();
        $this->to = $end->toDateString();
    }

    public function updatedFrom(): void
    {
        $this->range = 'custom';
        $this->closeAccordions();
    }

    public function updatedTo(): void
    {
        $this->range = 'custom';
        $this->closeAccordions();
    }

    public function toggleCategory(int $categoryId): void
    {
        $this->category = null;
        $this->own = false;
        $category = Category::query()
            ->where('household_id', $this->household()->id)
            ->find($categoryId);

        if ($category === null) {
            return;
        }

        $this->openTransferKeys = [];
        $open = array_map('intval', $this->openCategoryIds);

        if (in_array((int) $categoryId, $open, true)) {
            $hide = array_merge(
                [(int) $categoryId],
                CategoryTree::descendantIds($this->household()->categories()->get(), (int) $categoryId),
            );
            $this->openCategoryIds = array_values(array_filter(
                $open,
                fn (int $id): bool => ! in_array($id, $hide, true),
            ));

            return;
        }

        $this->openCategoryIds = $this->categoryPath($category);
    }

    public function closeAccordions(): void
    {
        $this->openCategoryIds = [];
        $this->openTransferKeys = [];
        $this->category = null;
        $this->own = false;
    }

    public function toggleTransfer(int $fromId, int $toId): void
    {
        $ownsBoth = $this->household()->accounts()->whereIn('id', [$fromId, $toId])->count() === 2;

        if (! $ownsBoth || $fromId === $toId) {
            return;
        }

        $key = $fromId.'-'.$toId;
        $this->openCategoryIds = [];
        $this->category = null;
        $this->own = false;

        if (in_array($key, $this->openTransferKeys, true)) {
            $this->openTransferKeys = array_values(array_filter(
                $this->openTransferKeys,
                fn (string $item): bool => $item !== $key,
            ));

            return;
        }

        $this->openTransferKeys = [$key];
    }

    public function transferOpen(int $fromId, int $toId): bool
    {
        return in_array($fromId.'-'.$toId, $this->openTransferKeys, true);
    }

    public function categoryOpen(int $categoryId): bool
    {
        return in_array($categoryId, array_map('intval', $this->openCategoryIds), true);
    }

    public function selectCategory(?int $categoryId = null, bool $own = false): void
    {
        $this->openTransferKeys = [];

        if ($categoryId === null) {
            $this->category = null;
            $this->own = false;
            $this->openCategoryIds = [];

            return;
        }

        $found = Category::query()
            ->where('household_id', $this->household()->id)
            ->find($categoryId);

        if ($found === null) {
            return;
        }

        if ((int) $this->category === (int) $found->id && $this->own === $own) {
            $this->category = null;
            $this->own = false;

            return;
        }

        $this->category = $found->id;
        $this->own = $own;
        $this->openCategoryIds = $this->categoryPath($found);
    }

    #[On('family-pulse')]
    public function refreshFromFamily(): void {}

    public function deleteTransfer(int $transferId): void
    {
        Transfer::query()
            ->where('household_id', $this->household()->id)
            ->findOrFail($transferId);
    }

    public function render()
    {
        $bounds = $this->bounds();
        $transactions = $bounds === null
            ? collect()
            : Transaction::query()
                ->where('household_id', $this->household()->id)
                ->whereDate('occurred_on', '>=', $bounds[0]->toDateString())
                ->whereDate('occurred_on', '<=', $bounds[1]->toDateString())
                ->with('category.parent')
                ->orderBy('occurred_on')
                ->orderBy('id')
                ->get();

        $selected = $this->category === null
            ? null
            : Category::query()
                ->where('household_id', $this->household()->id)
                ->with('parent')
                ->find($this->category);

        if ($this->category !== null && $selected === null) {
            $this->category = null;
            $this->own = false;
        }

        $operations = $selected === null
            ? collect()
            : $transactions
                ->where('category_id', $selected->id)
                ->sortByDesc(fn (Transaction $transaction) => $transaction->occurred_on->format('Y-m-d').sprintf('%010d', $transaction->id))
                ->values();

        $transfers = $selected !== null || $bounds === null
            ? collect()
            : Transfer::query()
                ->where('household_id', $this->household()->id)
                ->whereDate('occurred_on', '>=', $bounds[0]->toDateString())
                ->whereDate('occurred_on', '<=', $bounds[1]->toDateString())
                ->with(['fromAccount', 'toAccount'])
                ->orderByDesc('occurred_on')
                ->orderByDesc('id')
                ->get();

        $income = (int) $transactions->where('type', CashflowType::Income)->sum('amount_cents');
        $expense = (int) $transactions->where('type', CashflowType::Expense)->sum('amount_cents');
        $sections = $this->sections($transactions);

        return view('livewire.budget.statistics', [
            'bounds' => $bounds,
            'rangeError' => $this->rangeError(),
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'count' => $transactions->count(),
            'transfers' => $transfers,
            'transferGroups' => $this->transferGroups($transfers),
            'sections' => $sections,
            'operations' => $operations,
        ])->title(__('Статистика — Family Money'));
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function bounds(): ?array
    {
        if (! $this->datesAreValid() || $this->rangeError() !== null) {
            return null;
        }

        return [
            CarbonImmutable::createFromFormat('Y-m-d', $this->from)->startOfDay(),
            CarbonImmutable::createFromFormat('Y-m-d', $this->to)->startOfDay(),
        ];
    }

    private function rangeError(): ?string
    {
        if (! $this->datesAreValid()) {
            return __('Укажите обе даты.');
        }

        $start = CarbonImmutable::createFromFormat('Y-m-d', $this->from)->startOfDay();
        $end = CarbonImmutable::createFromFormat('Y-m-d', $this->to)->startOfDay();

        if ($end->lt($start)) {
            return __('Дата «по» не может быть раньше даты «с».');
        }

        return null;
    }

    private function datesAreValid(): bool
    {
        return $this->isDate($this->from) && $this->isDate($this->to);
    }

    private function isDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $parsed = CarbonImmutable::createFromFormat('Y-m-d', $value);

        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function preset(string $range): array
    {
        $today = CarbonImmutable::now($this->household()->timezone)->startOfDay();

        if ($range === 'week') {
            $start = $today->startOfWeek(CarbonImmutable::MONDAY);

            return [$start, $start->addDays(6)];
        }

        return [$today->startOfMonth(), $today->endOfMonth()->startOfDay()];
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<string, array{total: int, rows: list<array{category: Category, amount: int, own: int, children: list<array<string, mixed>>}>}>
     */
    private function sections(Collection $transactions): array
    {
        $sections = [];

        foreach ([CashflowType::Income, CashflowType::Expense] as $type) {
            $sections[$type->value] = [
                'total' => (int) $transactions->where('type', $type)->sum('amount_cents'),
                'rows' => $this->moneyTree($transactions, $type),
            ];
        }

        return $sections;
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return list<array{category: Category, amount: int, own: int, children: list<array<string, mixed>>}>
     */
    private function moneyTree(Collection $transactions, CashflowType $type): array
    {
        $catalog = Category::query()
            ->where('household_id', $this->household()->id)
            ->where('type', $type)
            ->get();
        $amounts = [];

        foreach ($transactions->where('type', $type) as $transaction) {
            if ($transaction->category_id === null) {
                continue;
            }

            $id = (int) $transaction->category_id;
            $amounts[$id] = ($amounts[$id] ?? 0) + (int) $transaction->amount_cents;
        }

        $known = $catalog->map(fn (Category $category) => (int) $category->id)->all();
        $byParent = $catalog->groupBy(function (Category $category) use ($known) {
            $parentId = (int) ($category->parent_id ?? 0);

            return in_array($parentId, $known, true) ? $parentId : 0;
        });
        $build = function (Category $category) use (&$build, $byParent, $amounts): ?array {
            $children = [];

            foreach ($byParent->get((int) $category->id, collect()) as $child) {
                $node = $build($child);

                if ($node !== null) {
                    $children[] = $node;
                }
            }

            usort($children, fn (array $left, array $right) => $right['amount'] <=> $left['amount']);
            $own = (int) ($amounts[(int) $category->id] ?? 0);
            $amount = $own + (int) array_sum(array_column($children, 'amount'));

            if ($amount <= 0) {
                return null;
            }

            return [
                'category' => $category,
                'amount' => $amount,
                'own' => $own,
                'children' => $children,
            ];
        };
        $roots = [];

        foreach ($byParent->get(0, collect()) as $category) {
            $node = $build($category);

            if ($node !== null) {
                $roots[] = $node;
            }
        }

        usort($roots, fn (array $left, array $right) => $right['amount'] <=> $left['amount']);

        return $roots;
    }

    /**
     * @return list<int>
     */
    private function categoryPath(Category $category): array
    {
        $ids = [(int) $category->id];
        $cursor = $category;
        $guard = 0;

        while ($cursor->parent_id !== null && $guard < 30) {
            $cursor = Category::query()
                ->where('household_id', $this->household()->id)
                ->find($cursor->parent_id);

            if ($cursor === null) {
                break;
            }

            array_unshift($ids, (int) $cursor->id);
            $guard++;
        }

        return $ids;
    }

    /**
     * @param  Collection<int, Transfer>  $transfers
     * @return list<array{from_id: int, to_id: int, from: string, to: string, amount: int, rows: Collection<int, Transfer>}>
     */
    private function transferGroups(Collection $transfers): array
    {
        return $transfers
            ->groupBy(fn (Transfer $transfer): string => $transfer->from_account_id.'-'.$transfer->to_account_id)
            ->map(function (Collection $rows): array {
                /** @var Transfer $first */
                $first = $rows->first();

                return [
                    'from_id' => (int) $first->from_account_id,
                    'to_id' => (int) $first->to_account_id,
                    'from' => $first->fromAccount?->name ?? __('Счёт'),
                    'to' => $first->toAccount?->name ?? __('Счёт'),
                    'amount' => (int) $rows->sum('amount_cents'),
                    'rows' => $rows->values(),
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    private function household(): Household
    {
        return auth()->user()->currentHousehold();
    }
}
