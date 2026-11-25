<?php

namespace App\Livewire\Concerns;

use App\Support\Money;

trait NormalizesMoneyInput
{
    public function updated(string $name, mixed $value): void
    {
        if (! is_string($value) || (! str_contains($value, '.') && ! str_starts_with(trim($value), ','))) {
            return;
        }

        if ($name !== 'openingAmount' && ! str_ends_with($name, 'Amount') && ! str_starts_with($name, 'planAmounts.') && ! str_starts_with($name, 'accountAmounts.') && ! str_starts_with($name, 'templateAmounts.')) {
            return;
        }

        data_set($this, $name, Money::normalize($value));
    }
}
