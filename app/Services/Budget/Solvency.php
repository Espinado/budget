<?php

namespace App\Services\Budget;

use App\Enums\CashflowType;
use App\Enums\DebtDirection;
use App\Enums\DebtKind;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtInstallment;
use App\Models\Household;
use App\Models\Transaction;
use App\Support\CreditCalculator;
use Carbon\CarbonImmutable;

final class Solvency
{
    public const MONTHS = 6;

    public const RATIO_LIMIT = 40;

    public const DTI_LIMIT = 6;

    /** Удар по уже сохранённому EURIBOR: две процентные точки, в тысячных долях процента. */
    private const RATE_SHOCK_MILLI = 2000;

    /**
     * Плановые строки этих категорий — уже занятые платежи, если они не входят в график долга.
     *
     * @var list<string>
     */
    private const PLAN_CATEGORIES = ['Ипотека', 'Кредит', 'Кредитные обязательства'];

    /** @var list<string> */
    private const PAYABLE_PLAN_CATEGORIES = ['Ипотека', 'Кредит', 'Кредитные обязательства', 'Личные долги'];

    public static function tracksCreditPlan(Category $category): bool
    {
        if (in_array($category->name, self::PAYABLE_PLAN_CATEGORIES, true)) {
            return true;
        }

        $parent = $category->parent;

        return $parent !== null && in_array($parent->name, self::PAYABLE_PLAN_CATEGORIES, true);
    }

    /**
     * @return array{
     *     preliminary: bool,
     *     months: int,
     *     income: ?int,
     *     income_low: ?int,
     *     income_high: ?int,
     *     living: ?int,
     *     obligations: int,
     *     items: list<array{name: string, cents: int}>,
     *     free: ?int,
     *     ratio_tenths: ?int,
     *     current_income: int,
     *     income_months: int,
     *     debt_cents: int,
     *     dti_tenths: ?int,
     *     room_cents: ?int,
     *     stressed_obligations: ?int,
     *     stressed_room_cents: ?int,
     *     replaceable: list<array{id: int, name: string, payment_cents: int, principal_cents: int}>,
     *     current_living: int,
     *     has_current: bool,
     *     extra: ?int,
     *     fits: ?bool,
     *     passed: bool
     * }
     */
    public function assess(Household $household, ?int $extraCents = null): array
    {
        $today = CarbonImmutable::now($household->timezone ?: 'Europe/Riga')->startOfDay();
        $closed = $household->periods()
            ->whereDate('ends_on', '<', $today->toDateString())
            ->orderBy('starts_on')
            ->get();
        $current = $household->periods()
            ->whereDate('starts_on', '<=', $today->toDateString())
            ->whereDate('ends_on', '>=', $today->toDateString())
            ->first();

        $incomeByPeriod = $this->sums($household, CashflowType::Income, $closed->pluck('id')->push($current?->id)->filter()->all());
        $qualifying = $closed->filter(fn ($period): bool => ($incomeByPeriod[$period->id] ?? 0) > 0)->values();
        $samples = array_map(fn ($period): int => (int) $incomeByPeriod[$period->id], $qualifying->all());
        $receivedIncome = $current ? (int) ($incomeByPeriod[$current->id] ?? 0) : 0;

        if ($receivedIncome > 0) {
            $samples[] = $receivedIncome;
        }

        $samples = array_slice($samples, -self::MONTHS);
        $incomeMonths = count($samples);
        // Знаменатель — среднее уже полученного дохода, не больше чем за шесть месяцев. План, который ещё не пришёл, среднее не поднимает.
        $income = $incomeMonths === 0 ? null : (int) round(array_sum($samples) / $incomeMonths);
        $living = $income === null || $current === null ? null : $this->plannedLiving($household, $current);
        $livingByPeriod = $this->livingSums($household, array_values(array_filter([...$qualifying->pluck('id')->all(), $current?->id])));

        $load = $this->obligations($household, $current, $today);
        $items = $load['items'];
        $obligations = (int) array_sum(array_column($items, 'cents'));
        $stressedObligations = $load['stressed'];
        $debt = $load['debt'];
        $free = $income === null || $living === null ? null : $income - $obligations - $living;
        $annual = $income === null ? 0 : $income * 12;
        $dtiTenths = $annual > 0 ? (int) round($debt * 10 / $annual) : null;
        $room = $this->room($income, $living, $obligations);
        $stressedRoom = $stressedObligations === null ? null : $this->room($income, $living, $stressedObligations);
        $extra = $extraCents !== null && $extraCents > 0 ? $extraCents : null;
        $withinRatio = $income !== null && $income > 0 && $obligations * 100 <= $income * self::RATIO_LIMIT;
        $withinDebt = $dtiTenths !== null && $dtiTenths <= self::DTI_LIMIT * 10;

        return [
            'preliminary' => $incomeMonths < self::MONTHS,
            'months' => $incomeMonths,
            'income' => $income,
            'income_low' => $samples === [] ? null : min($samples),
            'income_high' => $samples === [] ? null : max($samples),
            'living' => $living,
            'obligations' => $obligations,
            'items' => $items,
            'free' => $free,
            'ratio_tenths' => $income ? (int) round($obligations * 1000 / $income) : null,
            'current_income' => $receivedIncome,
            'income_months' => $incomeMonths,
            'debt_cents' => $debt,
            'dti_tenths' => $dtiTenths,
            'room_cents' => $room,
            'stressed_obligations' => $stressedObligations,
            'stressed_room_cents' => $stressedRoom,
            'replaceable' => $load['replaceable'],
            'current_living' => $current ? (int) ($livingByPeriod[$current->id] ?? 0) : 0,
            'has_current' => $current !== null,
            'extra' => $extra,
            'fits' => $this->fits($income, $free, $obligations, $extra),
            'passed' => $withinRatio && $withinDebt && $free !== null && $free >= 0,
        ];
    }

    private function room(?int $income, ?int $living, int $obligations): ?int
    {
        if ($income === null || $income <= 0 || $living === null) {
            return null;
        }

        $ratioRoom = intdiv($income * self::RATIO_LIMIT - $obligations * 100, 100);

        return min($ratioRoom, $income - $obligations - $living);
    }

    private function fits(?int $income, ?int $free, int $obligations, ?int $extra): ?bool
    {
        if ($extra === null || $income === null || $free === null || $income <= 0) {
            return null;
        }

        return $free - $extra >= 0 && ($obligations + $extra) * 100 <= $income * self::RATIO_LIMIT;
    }

    /**
     * @param  list<int>  $periodIds
     * @return array<int, int>
     */
    private function sums(Household $household, CashflowType $type, array $periodIds): array
    {
        if ($periodIds === []) {
            return [];
        }

        $totals = [];

        foreach (Transaction::query()
            ->where('household_id', $household->id)
            ->where('type', $type)
            ->whereIn('period_id', $periodIds)
            ->selectRaw('period_id, sum(amount_cents) as total')
            ->groupBy('period_id')
            ->get() as $row) {
            $totals[(int) $row->period_id] = (int) $row->total;
        }

        return $totals;
    }

    private function plannedLiving(Household $household, BudgetPeriod $period): int
    {
        $debtLineIds = $this->debtPlanLineIds($household);

        return (int) $period->planLines()
            ->whereNotIn('id', $debtLineIds === [] ? [0] : $debtLineIds)
            ->whereHas('category', fn ($query) => $query
                ->where('type', CashflowType::Expense)
                ->whereNotIn('name', self::PLAN_CATEGORIES))
            ->sum('amount_cents');
    }

    /**
     * @param  list<int>  $periodIds
     * @return array<int, int>
     */
    private function livingSums(Household $household, array $periodIds): array
    {
        if ($periodIds === []) {
            return [];
        }

        $debtLineIds = $this->debtPlanLineIds($household);
        $paidIds = $this->paidTransactionIds($household);
        $totals = [];

        Transaction::query()
            ->where('household_id', $household->id)
            ->where('type', CashflowType::Expense)
            ->whereIn('period_id', $periodIds)
            ->with('category:id,name')
            ->get()
            ->each(function (Transaction $transaction) use (&$totals, $debtLineIds, $paidIds): void {
                if ($this->isCreditExpense($transaction, $debtLineIds, $paidIds)) {
                    return;
                }

                $totals[$transaction->period_id] = ($totals[$transaction->period_id] ?? 0) + (int) $transaction->amount_cents;
            });

        return $totals;
    }

    /**
     * @param  list<int>  $debtLineIds
     * @param  list<int>  $paidIds
     */
    private function isCreditExpense(Transaction $transaction, array $debtLineIds, array $paidIds): bool
    {
        $linked = $transaction->plan_line_id !== null && in_array((int) $transaction->plan_line_id, $debtLineIds, true);
        $settled = in_array((int) $transaction->id, $paidIds, true);
        $category = in_array($transaction->category?->name, self::PLAN_CATEGORIES, true);

        return $linked || $settled || $category;
    }

    /**
     * @return list<int>
     */
    private function debtPlanLineIds(Household $household): array
    {
        return DebtInstallment::query()
            ->whereNotNull('plan_line_id')
            ->whereHas('debt', fn ($query) => $query->where('household_id', $household->id))
            ->pluck('plan_line_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function paidTransactionIds(Household $household): array
    {
        return DebtInstallment::query()
            ->whereNotNull('paid_transaction_id')
            ->whereHas('debt', fn ($query) => $query->where('household_id', $household->id))
            ->pluck('paid_transaction_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Ближайший регулярный взнос каждого банковского кредита. Личные долги в долю не входят,
     * их остаток основного долга учитывается отдельно. Один и тот же взнос не считается дважды.
     *
     * @return array{
     *     items: list<array{name: string, cents: int}>,
     *     stressed: ?int,
     *     debt: int,
     *     replaceable: list<array{id: int, name: string, payment_cents: int, principal_cents: int}>
     * }
     */
    private function obligations(Household $household, ?BudgetPeriod $period, CarbonImmutable $today): array
    {
        $items = [];
        $replaceable = [];
        $counted = [];
        $seenTx = [];
        $seenPlan = [];
        $stressed = 0;
        $repriced = false;
        $debtPrincipal = 0;

        $debts = Debt::query()
            ->where('household_id', $household->id)
            ->where('direction', DebtDirection::Borrow)
            ->with('installments')
            ->orderBy('counterparty')
            ->get();

        foreach ($debts as $debt) {
            $principal = $this->outstandingPrincipal($debt);

            if ($debt->kind !== DebtKind::Bank) {
                $debtPrincipal += $principal;

                continue;
            }

            $debtPrincipal += $principal;
            $installment = $this->monthlyInstallment($debt, $today);

            if ($installment === null) {
                continue;
            }

            $items[] = ['name' => $debt->counterparty, 'cents' => $installment['cents']];
            $counted[$debt->counterparty] = true;
            $replaceable[] = [
                'id' => (int) $debt->id,
                'name' => $debt->counterparty,
                'payment_cents' => $installment['cents'],
                'principal_cents' => $principal,
            ];
            $stressed += $installment['stressed'] ?? $installment['cents'];
            $repriced = $repriced || $installment['stressed'] !== null;

            if ($installment['plan_line_id'] !== null) {
                $seenPlan[(int) $installment['plan_line_id']] = true;
            }
        }

        foreach ($debts as $debt) {
            foreach ($debt->installments as $row) {
                if ($row->plan_line_id !== null) {
                    $seenPlan[(int) $row->plan_line_id] = true;
                }

                if ($row->paid_transaction_id !== null) {
                    $seenTx[(int) $row->paid_transaction_id] = true;
                }
            }
        }

        if ($period === null) {
            return [
                'items' => $items,
                'stressed' => $repriced ? $stressed : null,
                'debt' => $debtPrincipal,
                'replaceable' => $replaceable,
            ];
        }

        $debtLineIds = $this->debtPlanLineIds($household);
        $paidOnLine = $this->paidOnPlanLines($household, $period);

        $lines = $period->planLines()
            ->whereNotIn('id', $debtLineIds === [] ? [0] : $debtLineIds)
            ->whereHas('category', fn ($query) => $query->whereIn('name', self::PLAN_CATEGORIES))
            ->get();

        foreach ($lines as $line) {
            if (isset($seenPlan[(int) $line->id]) || isset($counted[$line->title])) {
                continue;
            }

            $paid = (int) ($paidOnLine[(int) $line->id] ?? 0);
            $cents = max((int) $line->amount_cents, $paid);

            if ($cents <= 0) {
                continue;
            }

            $items[] = ['name' => $line->title, 'cents' => $cents];
            $stressed += $cents;
            $seenPlan[(int) $line->id] = true;
        }

        $planTitles = $period->planLines()->pluck('title', 'id');

        Transaction::query()
            ->where('household_id', $household->id)
            ->where('type', CashflowType::Expense)
            ->where('period_id', $period->id)
            ->with('category:id,name')
            ->get()
            ->each(function (Transaction $transaction) use (&$items, &$stressed, &$seenTx, $seenPlan, $counted, $planTitles): void {
                if (isset($seenTx[(int) $transaction->id])) {
                    return;
                }

                if ($transaction->plan_line_id !== null && isset($seenPlan[(int) $transaction->plan_line_id])) {
                    return;
                }

                $planTitle = $transaction->plan_line_id === null ? null : ($planTitles[(int) $transaction->plan_line_id] ?? null);

                if ($planTitle !== null && isset($counted[$planTitle])) {
                    return;
                }

                if (! in_array($transaction->category?->name, self::PLAN_CATEGORIES, true)) {
                    return;
                }

                $cents = (int) $transaction->amount_cents;
                $items[] = ['name' => $transaction->category->name, 'cents' => $cents];
                $stressed += $cents;
            });

        return [
            'items' => $items,
            'stressed' => $repriced ? $stressed : null,
            'debt' => $debtPrincipal,
            'replaceable' => $replaceable,
        ];
    }

    private function outstandingPrincipal(Debt $debt): int
    {
        if ($debt->usesDailyInterest()) {
            return max(0, (int) $debt->principal_cents);
        }

        $next = $debt->installments->first(fn (DebtInstallment $row): bool => $row->paid_transaction_id === null && (int) $row->principal_before_cents > 0);

        return $next === null ? 0 : (int) $next->principal_before_cents;
    }

    /**
     * @return array{cents: int, plan_line_id: ?int, stressed: ?int}|null
     */
    private function monthlyInstallment(Debt $debt, CarbonImmutable $today): ?array
    {
        $rows = $debt->installments
            ->filter(fn (DebtInstallment $row): bool => $row->paid_transaction_id === null && (int) $row->total_cents > 0)
            ->values();

        if ($rows->isEmpty()) {
            return null;
        }

        $next = $rows->first();
        $cents = (int) $next->total_cents;

        if ($rows->count() === 1 && $next->due_on !== null) {
            $span = ($next->due_on->year - $today->year) * 12 + ($next->due_on->month - $today->month);
            $cents = (int) round($cents / max(1, $span));
        }

        return [
            'cents' => max(0, $cents),
            'plan_line_id' => $next->plan_line_id === null ? null : (int) $next->plan_line_id,
            'stressed' => $this->stressedInstallment($debt, $rows),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, DebtInstallment>  $rows
     */
    private function stressedInstallment(Debt $debt, $rows): ?int
    {
        $schedule = $debt->schedule ?: 'fixed';

        if ((int) $debt->euribor_rate_bps <= 0 || $debt->usesDailyInterest() || $rows->count() < 2 || $schedule !== 'fixed') {
            return null;
        }

        $residual = max(0, (int) $debt->residual_cents);
        $last = $rows->last();
        $separate = $residual > 0
            && (int) $last->interest_cents === 0
            && (int) $last->principal_cents === min($residual, (int) $last->principal_before_cents);
        $amort = $separate ? $rows->slice(0, $rows->count() - 1)->values() : $rows;
        $months = $amort->count();

        if ($months < 1) {
            return null;
        }

        $balance = (int) $rows->first()->principal_before_cents;
        $residualLeft = $separate ? min($residual, $balance) : 0;
        $calculator = app(CreditCalculator::class);
        $annual = (int) $debt->annual_rate_bps + self::RATE_SHOCK_MILLI;
        $payment = $calculator->annuity($balance - $residualLeft, $months, $annual) + $calculator->interest($residualLeft, $annual);

        return $payment + max(0, (int) $rows->first()->fee_cents);
    }

    /**
     * @return array<int, int>
     */
    private function paidOnPlanLines(Household $household, BudgetPeriod $period): array
    {
        $totals = [];

        foreach (Transaction::query()
            ->where('household_id', $household->id)
            ->where('type', CashflowType::Expense)
            ->where('period_id', $period->id)
            ->whereNotNull('plan_line_id')
            ->selectRaw('plan_line_id, sum(amount_cents) as total')
            ->groupBy('plan_line_id')
            ->get() as $row) {
            $totals[(int) $row->plan_line_id] = (int) $row->total;
        }

        return $totals;
    }
}
