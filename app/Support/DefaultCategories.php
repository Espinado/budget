<?php

namespace App\Support;

use App\Enums\CashflowType;
use App\Models\Category;
use App\Models\Household;

class DefaultCategories
{
    public static function install(Household $household): void
    {
        foreach ([
            [__('Здоровье'), 'heart', 10],
            [__('Развлечения'), 'fun', 20],
            [__('Транспорт'), 'car', 30],
            [__('Дом'), 'home', 40],
            [__('Питание'), 'utensils', 50],
        ] as [$name, $icon, $sort]) {
            $household->categories()->firstOrCreate(
                ['name' => $name, 'type' => CashflowType::Expense, 'parent_id' => null],
                ['icon' => $icon, 'sort' => $sort],
            );
        }

        $household->categories()->firstOrCreate(
            ['name' => __('Зарплата'), 'type' => CashflowType::Income, 'parent_id' => null],
            ['icon' => 'salary', 'sort' => 5],
        );

        self::ensureCar($household);
        self::ensureMedicine($household);
        self::ensureHobby($household);
    }

    public static function ensureHobby(Household $household): void
    {
        $hobby = $household->categories()
            ->where('type', CashflowType::Expense)
            ->where('name', __('Хобби'))
            ->whereNull('archived_at')
            ->get()
            ->sortBy(fn (Category $category) => $category->parent_id === null ? 0 : 1)
            ->first();

        if ($hobby === null) {
            $hobby = $household->categories()->create([
                'name' => __('Хобби'),
                'type' => CashflowType::Expense,
                'icon' => 'sport',
                'sort' => 25,
            ]);
        }

        foreach ([
            [__('Абонемент'), 'card'],
            [__('Экипировка'), 'shirt'],
        ] as [$name, $icon]) {
            self::child($household, $hobby, $name, $icon);
        }
    }

    public static function ensureMedicine(Household $household): void
    {
        $medicine = $household->categories()
            ->where('type', CashflowType::Expense)
            ->where('name', __('Медицина'))
            ->whereNull('archived_at')
            ->get()
            ->sortBy(fn (Category $category) => $category->parent_id === null ? 0 : 1)
            ->first();

        if ($medicine === null) {
            $medicine = $household->categories()->create([
                'name' => __('Медицина'),
                'type' => CashflowType::Expense,
                'icon' => 'pill',
                'sort' => 15,
            ]);
        }

        foreach ([
            [__('Семейный врач'), 'heart'],
            [__('Приём у специалиста'), 'pill'],
            [__('Лекарства'), 'pill'],
        ] as [$name, $icon]) {
            self::child($household, $medicine, $name, $icon);
        }
    }

    public static function ensureCar(Household $household): void
    {
        $transport = $household->categories()
            ->where('type', CashflowType::Expense)
            ->where('name', __('Транспорт'))
            ->whereNull('archived_at')
            ->get()
            ->sortBy(fn (Category $category) => $category->parent_id === null ? 0 : 1)
            ->first();

        if ($transport === null) {
            $transport = $household->categories()->create([
                'name' => __('Транспорт'),
                'type' => CashflowType::Expense,
                'icon' => 'car',
                'sort' => 30,
            ]);
        }

        $car = self::child($household, $transport, __('Автомобиль'), 'car');

        foreach ([
            [__('Топливо'), 'fuel'],
            [__('Обслуживание'), 'cart'],
            [__('Покупка расходных материалов'), 'bag'],
            [__('Парковка'), 'car'],
            [__('Пропуск'), 'card'],
        ] as [$name, $icon]) {
            self::child($household, $car, $name, $icon);
        }
    }

    private static function child(Household $household, Category $parent, string $name, string $icon): Category
    {
        $existing = $household->categories()
            ->where('parent_id', $parent->id)
            ->where('name', $name)
            ->whereNull('archived_at')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $sort = (int) $household->categories()->where('parent_id', $parent->id)->max('sort');

        return $household->categories()->create([
            'parent_id' => $parent->id,
            'type' => CashflowType::Expense,
            'name' => $name,
            'icon' => $icon,
            'sort' => $sort + 1,
        ]);
    }
}
