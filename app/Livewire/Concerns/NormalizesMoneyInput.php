<?php

namespace App\Livewire\Concerns;

use App\Support\Money;

trait NormalizesMoneyInput
{
    public function updated(string $name, mixed $value): void
    {
        if (is_string($value)) {
            if ($this->isMoneyField($name) && $this->moneyNeedsSeparator($value)) {
                data_set($this, $name, Money::normalize($value));
            }

            return;
        }

        if ($name !== 'contractParts' || ! is_array($value)) {
            return;
        }

        foreach ($value as $index => $part) {
            if (! is_array($part) || ! isset($part['amount']) || ! is_string($part['amount'])) {
                continue;
            }

            if ($this->moneyNeedsSeparator($part['amount'])) {
                $part['amount'] = Money::normalize($part['amount']);
                $value[$index] = $part;
            }
        }

        $this->contractParts = $value;
    }

    private function isMoneyField(string $name): bool
    {
        return $name === 'openingAmount'
            || str_ends_with($name, 'Amount')
            || str_ends_with($name, '.amount')
            || str_starts_with($name, 'planAmounts.')
            || str_starts_with($name, 'accountAmounts.')
            || str_starts_with($name, 'templateAmounts.')
            || in_array($name, [
                'balanceTotal',
                'feeMonthly',
                'feeIssuance',
                'newResidual',
                'newMonthlyFee',
                'newIssuanceFee',
            ], true);
    }

    private function moneyNeedsSeparator(string $value): bool
    {
        return str_contains($value, '.') || str_starts_with(trim($value), ',');
    }
}
