<?php

namespace App\Services\Budget;

use App\Enums\CashflowType;
use App\Models\BudgetPeriod;
use App\Models\Category;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class PeriodSummary
{
    public function figures(BudgetPeriod $period): PeriodFigures
    {
        return new PeriodFigures(
            opening: $this->openingCents($period),
            plannedIncome: $this->planned($period, CashflowType::Income),
            actualIncome: $this->actual($period, CashflowType::Income),
            plannedExpense: $this->planned($period, CashflowType::Expense),
            actualExpense: $this->actual($period, CashflowType::Expense),
        );
    }

    /**
     * @return Collection<int, CategorySnapshot>
     */
    public function groups(BudgetPeriod $period): Collection
    {
        $categories = Category::query()
            ->where('household_id', $period->household_id)
            ->whereNull('archived_at')
            ->with('parent')
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        $lines = $period->planLines()->with(['account', 'installment'])->orderBy('expected_on')->orderBy('id')->get()->groupBy('category_id');
        $transactions = $period->transactions()->with('account')->orderBy('occurred_on')->orderBy('id')->get()->groupBy('category_id');

        return $categories->map(function (Category $category) use ($lines, $transactions) {
            $categoryLines = $lines->get($category->id, collect());
            $categoryTransactions = $transactions->get($category->id, collect());

            return new CategorySnapshot(
                category: $category,
                planned: (int) $categoryLines->sum('amount_cents'),
                actual: (int) $categoryTransactions->sum('amount_cents'),
                lines: $categoryLines,
                transactions: $categoryTransactions,
            );
        })->values();
    }

    /**
     * A mortgage plan line under a property stays in that property total.
     * It is not added into the «Кредит» header. Lines already inside «Кредит» stay in that category's own sum.
     */
    public function propertyMortgagePlanOutsideCredit(BudgetPeriod $period): int
    {
        return 0;
    }

    public function unbookedNet(BudgetPeriod $period): int
    {
        return $this->unbooked($period, CashflowType::Income)
            - $this->unbooked($period, CashflowType::Expense);
    }

    /**
     * What an earlier month still does to a later forecast.
     * Until that month ends, its remaining plan is treated as spent and received.
     * A posted payment counts even when its date is still ahead: the money has already moved.
     * Overspend on one line does not reduce what another line still has to pay.
     * On the first day the month is over, so this is no longer applied: real money
     * left unspent is already in the wallets and becomes the next month's start.
     */
    public function carryNet(BudgetPeriod $period): int
    {
        return $this->unbookedNet($period);
    }

    public function openingCents(BudgetPeriod $period): int
    {
        $origin = $period;
        $guard = 0;

        while (! $origin->opening_manual && $guard < 240) {
            $previous = BudgetPeriod::query()
                ->where('household_id', $origin->household_id)
                ->whereDate('ends_on', CarbonImmutable::parse($origin->starts_on)->subDay()->toDateString())
                ->first();

            if ($previous === null) {
                break;
            }

            $origin = $previous;
            $guard++;
        }

        $balance = (int) $origin->opening_balance_cents;

        if ($origin->is($period)) {
            return $balance;
        }

        $chain = BudgetPeriod::query()
            ->where('household_id', $period->household_id)
            ->whereDate('starts_on', '>=', $origin->starts_on->toDateString())
            ->whereDate('starts_on', '<=', $period->starts_on->toDateString())
            ->orderBy('starts_on')
            ->get();

        foreach ($chain as $item) {
            if ($item->is($period)) {
                return $balance;
            }

            $balance += $this->actual($item, CashflowType::Income);
            $balance -= $this->actual($item, CashflowType::Expense);
        }

        return $balance;
    }

    private function planned(BudgetPeriod $period, CashflowType $type): int
    {
        return (int) $period->planLines()
            ->whereHas('category', fn ($query) => $query->where('type', $type))
            ->sum('amount_cents');
    }

    private function actual(BudgetPeriod $period, CashflowType $type): int
    {
        return (int) $period->transactions()->where('type', $type)->sum('amount_cents');
    }

    private function unbooked(BudgetPeriod $period, CashflowType $type): int
    {
        $lines = $period->planLines()
            ->whereHas('category', fn ($query) => $query->where('type', $type))
            ->get(['id', 'category_id', 'amount_cents']);

        $transactions = $period->transactions()
            ->where('type', $type)
            ->get(['id', 'category_id', 'plan_line_id', 'amount_cents']);

        $linesByCategory = $lines->groupBy('category_id');
        $transactionsByCategory = $transactions->groupBy('category_id');
        $outstanding = 0;

        foreach ($linesByCategory->keys()->merge($transactionsByCategory->keys())->unique() as $categoryId) {
            $outstanding += LineRemainder::split(
                $linesByCategory->get($categoryId, collect()),
                $transactionsByCategory->get($categoryId, collect()),
            )['outstanding'];
        }

        return $outstanding;
    }
}
