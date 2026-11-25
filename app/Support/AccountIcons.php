<?php

namespace App\Support;

use App\Enums\AccountRole;
use App\Models\Account;

class AccountIcons
{
    /**
     * @return array{icon: string, tone: string}
     */
    public static function mark(Account $account): array
    {
        $role = $account->role instanceof AccountRole ? $account->role->value : (string) $account->role;
        $name = mb_strtolower($account->name);

        if ($role === AccountRole::Savings->value || str_contains($name, 'копил') || str_contains($name, 'krājk') || str_contains($name, 'krajk')) {
            return ['icon' => 'piggy', 'tone' => 'bg-emerald-100 text-emerald-800'];
        }

        if ($account->kind === 'cash' || str_contains($name, 'налич') || str_contains($name, 'skaidr')) {
            return ['icon' => 'coins', 'tone' => 'bg-emerald-100 text-emerald-800'];
        }

        if (str_contains($name, 'кредит') || str_contains($name, 'kredit') || str_contains($name, 'credit')) {
            return ['icon' => 'card', 'tone' => 'bg-amber-100 text-amber-800'];
        }

        if ($account->kind === 'card' || str_contains($name, 'дебет') || str_contains($name, 'debet') || str_contains($name, 'debit') || str_contains($name, 'карт') || str_contains($name, 'kart')) {
            return ['icon' => 'card', 'tone' => 'bg-sky-100 text-sky-800'];
        }

        return ['icon' => 'bank', 'tone' => 'bg-stone-100 text-stone-700'];
    }
}
