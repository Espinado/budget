<?php

namespace App\Enums;

enum DebtKind: string
{
    case Personal = 'personal';
    case Bank = 'bank';

    public function label(): string
    {
        return match ($this) {
            self::Personal => __('Личные долги'),
            self::Bank => __('Кредиты'),
        };
    }
}
