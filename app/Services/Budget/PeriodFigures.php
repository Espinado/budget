<?php

namespace App\Services\Budget;

final readonly class PeriodFigures
{
    public function __construct(
        public int $opening,
        public int $plannedIncome,
        public int $actualIncome,
        public int $plannedExpense,
        public int $actualExpense,
    ) {}

    public function incomeVariance(): int
    {
        return $this->actualIncome - $this->plannedIncome;
    }

    public function expenseVariance(): int
    {
        return $this->actualExpense - $this->plannedExpense;
    }

    public function plannedClosing(): int
    {
        return $this->opening + $this->plannedIncome - $this->plannedExpense;
    }

    public function actualClosing(): int
    {
        return $this->opening + $this->actualIncome - $this->actualExpense;
    }

    public function balanceVariance(): int
    {
        return $this->actualClosing() - $this->plannedClosing();
    }
}
