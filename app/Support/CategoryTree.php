<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class CategoryTree
{
    /**
     * @param  Collection<int, object>  $categories
     * @return list<int>
     */
    public static function descendantIds(Collection $categories, int $id): array
    {
        $byParent = $categories->groupBy(fn (object $category) => (int) ($category->parent_id ?? 0));
        $ids = [];
        $walk = function (int $parentId) use (&$walk, &$ids, $byParent): void {
            foreach ($byParent->get($parentId, collect()) as $child) {
                $childId = (int) $child->id;
                $ids[] = $childId;
                $walk($childId);
            }
        };
        $walk($id);

        return $ids;
    }

    /**
     * @param  Collection<int, object>  $categories
     * @return list<array{0: object, 1: string}>
     */
    public static function branches(Collection $categories): array
    {
        $rows = [];

        foreach (self::choices($categories) as $choice) {
            if (! $choice['selectable']) {
                continue;
            }

            $rows[] = [$choice['category'], self::label($categories, $choice['category'])];
        }

        return $rows;
    }

    /**
     * Parents A–Z, then each parent's children A–Z, for the current app locale.
     * A parent missing from the list stays as a bold heading when its name is known.
     *
     * @param  Collection<int, object>  $categories
     * @return list<array{category: object, depth: int, selectable: bool}>
     */
    public static function choices(Collection $categories): array
    {
        /** @var Collection<int, object> $items */
        $items = $categories->keyBy(fn (object $category): int => (int) $category->id);
        $selectable = [];

        foreach ($items as $id => $category) {
            $selectable[(int) $id] = true;
        }

        foreach ($categories as $category) {
            $parentId = (int) ($category->parent_id ?? 0);

            if ($parentId === 0 || $items->has($parentId)) {
                continue;
            }

            $parent = (isset($category->parent) && $category->parent !== null) ? $category->parent : null;

            if ($parent !== null && ! $items->has((int) $parent->id)) {
                $items->put((int) $parent->id, $parent);
            }
        }

        /** @var array<int, list<object>> $byParent */
        $byParent = [];

        foreach ($items as $category) {
            $parentId = (int) ($category->parent_id ?? 0);

            if ($parentId !== 0 && ! $items->has($parentId)) {
                $parentId = 0;
            }

            $byParent[$parentId][] = $category;
        }

        foreach ($byParent as &$siblings) {
            usort($siblings, function (object $left, object $right): int {
                $byName = self::compareNames((string) $left->name, (string) $right->name);

                return $byName !== 0 ? $byName : ((int) $left->id <=> (int) $right->id);
            });
        }
        unset($siblings);

        $rows = [];
        $seen = [];
        $visit = function (int $parentId, int $depth) use (&$visit, &$rows, &$seen, $byParent, $selectable): void {
            foreach ($byParent[$parentId] ?? [] as $category) {
                $id = (int) $category->id;

                if (isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $rows[] = [
                    'category' => $category,
                    'depth' => $depth,
                    'selectable' => isset($selectable[$id]),
                ];
                $visit($id, $depth + 1);
            }
        };
        $visit(0, 0);

        return $rows;
    }

    private static function compareNames(string $left, string $right): int
    {
        $result = self::collator()->compare($left, $right);

        return $result === false ? strcasecmp($left, $right) : $result;
    }

    private static function collator(): \Collator
    {
        $locale = app()->getLocale() === 'lv' ? 'lv' : 'ru';

        static $collators = [];

        if (! isset($collators[$locale])) {
            $collator = new \Collator($locale);
            $collator->setStrength(\Collator::SECONDARY);
            $collators[$locale] = $collator;
        }

        return $collators[$locale];
    }

    /**
     * @param  Collection<int, object>  $categories
     */
    public static function label(Collection $categories, object $category): string
    {
        $byId = $categories->keyBy(fn (object $item) => (int) $item->id);
        $names = [$category->name];
        $cursor = (int) ($category->parent_id ?? 0);
        $seen = [(int) $category->id => true];
        $guard = 0;

        while ($cursor !== 0 && $guard < 30 && ! isset($seen[$cursor])) {
            $parent = $byId->get($cursor);

            if ($parent === null) {
                break;
            }

            array_unshift($names, $parent->name);
            $seen[$cursor] = true;
            $cursor = (int) ($parent->parent_id ?? 0);
            $guard++;
        }

        return implode(' → ', $names);
    }

    /**
     * @param  Collection<int, object>  $categories
     */
    public static function pathWithin(Collection $categories, object $category, int $rootId): string
    {
        $byId = $categories->keyBy(fn (object $item) => (int) $item->id);
        $names = [$category->name];
        $cursor = (int) ($category->parent_id ?? 0);
        $seen = [(int) $category->id => true];
        $guard = 0;

        while ($cursor !== 0 && $cursor !== $rootId && $guard < 30 && ! isset($seen[$cursor])) {
            $parent = $byId->get($cursor);

            if ($parent === null) {
                break;
            }

            array_unshift($names, $parent->name);
            $seen[$cursor] = true;
            $cursor = (int) ($parent->parent_id ?? 0);
            $guard++;
        }

        return implode(' → ', $names);
    }
}
