<?php

namespace App\Enums;

enum AccountRole: string
{
    case Operating = 'operating';
    case Savings = 'savings';

    public function label(): string
    {
        return match ($this) {
            self::Operating => __('Расчётный счёт'),
            self::Savings => __('Накопительный счёт'),
        };
    }
}
