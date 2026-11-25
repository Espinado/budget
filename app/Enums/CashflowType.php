<?php

namespace App\Enums;

enum CashflowType: string
{
    case Income = 'income';
    case Expense = 'expense';
    /** Personal borrow receipt: credits account, excluded from month income. */
    case LiabilityIn = 'liability_in';

    public function label(): string
    {
        return match ($this) {
            self::Income => __('Поступление'),
            self::Expense => __('Расход'),
            self::LiabilityIn => __('Взяли в долг'),
        };
    }

    public function increasesBalance(): bool
    {
        return match ($this) {
            self::Income, self::LiabilityIn => true,
            self::Expense => false,
        };
    }
}
