<?php

namespace App\Enums;

enum DebtDirection: string
{
    case Borrow = 'borrow';
    case Lend = 'lend';

    public function label(): string
    {
        return match ($this) {
            self::Borrow => __('Кредитные обязательства'),
            self::Lend => __('Дебитовые обязательства'),
        };
    }

    public function personalLabel(): string
    {
        return match ($this) {
            self::Borrow => __('Кредиторские долги'),
            self::Lend => __('Дебиторские долги'),
        };
    }
}
