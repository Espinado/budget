<?php

namespace App\Support;

class CategoryIcons
{
    /** @return array<string, string> */
    public static function all(): array
    {
        return [
            'in' => __('Поступление'),
            'salary' => __('Зарплата'),
            'coins' => __('Деньги'),
            'wallet' => __('Кошелёк'),
            'card' => __('Кредит'),
            'bank' => __('Банк'),
            'home' => __('Дом'),
            'building' => __('Коммунальные'),
            'bolt' => __('Электричество'),
            'flame' => __('Газ'),
            'drop' => __('Вода'),
            'signal' => __('Связь'),
            'phone' => __('Телефон'),
            'wifi' => __('Интернет'),
            'bag' => __('Продукты'),
            'utensils' => __('Еда'),
            'cup' => __('Кафе'),
            'car' => __('Транспорт'),
            'fuel' => __('Топливо'),
            'smoke' => __('Сигареты'),
            'piggy' => __('Пенсия'),
            'shield' => __('Страховка'),
            'heart' => __('Здоровье'),
            'pill' => __('Аптека'),
            'shirt' => __('Одежда'),
            'baby' => __('Дети'),
            'paw' => __('Животные'),
            'book' => __('Учёба'),
            'sport' => __('Спорт'),
            'plane' => __('Поездки'),
            'fun' => __('Развлечения'),
            'gift' => __('Подарки'),
            'cart' => __('Покупки'),
            'out' => __('Прочий расход'),
        ];
    }

    public static function fallback(string $type = 'expense'): string
    {
        return $type === 'income' ? 'in' : 'out';
    }

    public static function resolve(?string $icon, string $type = 'expense'): string
    {
        if (is_string($icon) && array_key_exists($icon, self::all())) {
            return $icon;
        }

        return self::fallback($type);
    }

    public static function guess(string $name, string $type = 'expense'): string
    {
        $haystack = mb_strtolower($name);
        // Stems match stored category names (usually Russian); include Latvian for new LV households.
        $rules = [
            'аванс' => 'coins',
            'зарплат' => 'salary',
            'доход' => 'in',
            'ienākum' => 'in',
            'ипотек' => 'home',
            'hipotek' => 'home',
            'квартплат' => 'home',
            'коммунал' => 'building',
            'электри' => 'bolt',
            'газ' => 'flame',
            'страхов' => 'shield',
            'apen' => 'shield',
            'пенсион' => 'piggy',
            'pensij' => 'piggy',
            'indexo' => 'piggy',
            'luminor' => 'bank',
            'сигарет' => 'smoke',
            'cigaret' => 'smoke',
            'кафе' => 'cup',
            'kafejn' => 'cup',
            'продукт' => 'bag',
            'produkt' => 'bag',
            'питан' => 'utensils',
            'ēdinā' => 'utensils',
            'здоров' => 'heart',
            'veselīb' => 'heart',
            'развлеч' => 'fun',
            'izkla' => 'fun',
            'дом' => 'home',
            'māj' => 'home',
            'болт' => 'car',
            'транспорт' => 'car',
            'transport' => 'car',
            'lmt' => 'phone',
            'tet' => 'wifi',
            'telekom' => 'signal',
            'кредит' => 'card',
            'kredit' => 'card',
            'credit' => 'card',
        ];

        foreach ($rules as $needle => $icon) {
            if (str_contains($haystack, $needle)) {
                return $icon;
            }
        }

        return $type === 'income' ? 'in' : 'out';
    }

    public static function tone(string $icon, string $kind = 'expense'): string
    {
        return match ($icon) {
            'in', 'salary', 'bank', 'signal', 'phone', 'wifi' => 'bg-tide-soft text-tide',
            'coins', 'piggy', 'shield' => 'bg-emerald-100 text-emerald-800',
            'home', 'flame' => 'bg-rose-100 text-rose-800',
            'card', 'fuel', 'utensils' => 'bg-amber-100 text-amber-800',
            'bag', 'drop' => 'bg-lime-100 text-lime-800',
            'bolt', 'cup', 'plane', 'book' => 'bg-sky-100 text-sky-800',
            'car', 'building', 'cart' => 'bg-stone-100 text-stone-700',
            'smoke', 'wallet' => 'bg-stone-200 text-stone-700',
            'heart', 'gift', 'baby', 'fun' => 'bg-pink-100 text-pink-800',
            'pill', 'shirt', 'sport', 'paw' => 'bg-violet-100 text-violet-800',
            default => $kind === 'income' ? 'bg-tide-soft text-tide' : 'bg-amber-100 text-amber-800',
        };
    }
}
