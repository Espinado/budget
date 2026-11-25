<?php

namespace App\Support;

use App\Enums\CashflowType;
use App\Models\Category;
use App\Models\Household;
use Illuminate\Support\Collection;

final class FoodCategories
{
    public static function root(Household $household): ?Category
    {
        return $household->categories()
            ->where('type', CashflowType::Expense)
            ->where('name', __('Питание'))
            ->whereNull('parent_id')
            ->whereNull('archived_at')
            ->orderBy('sort')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return Collection<int, Category>
     */
    public static function tree(Household $household): Collection
    {
        $root = self::root($household);

        if ($root === null) {
            return collect();
        }

        $all = $household->categories()
            ->where('type', CashflowType::Expense)
            ->whereNull('archived_at')
            ->with('parent')
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        $ids = array_merge([$root->id], CategoryTree::descendantIds($all, $root->id));

        return $all->whereIn('id', $ids)->values();
    }

    public static function contains(Household $household, int $categoryId): bool
    {
        return self::tree($household)->contains(fn (Category $category): bool => $category->id === $categoryId);
    }
}
