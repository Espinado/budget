<?php

namespace App\Services\Budget;

use App\Enums\AccountRole;
use App\Enums\CashflowType;
use App\Enums\DebtDirection;
use App\Enums\DebtKind;
use App\Models\Account;
use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtInstallment;
use App\Models\Household;
use App\Models\PlanLine;
use App\Models\Transaction;
use App\Support\CreditCalculator;
use App\Support\DailyInterest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DebtBook
{
    public function __construct(private PeriodGenerator $periods) {}

    /**
     * @param  list<array{sequence: int, due_on: string, principal_before_cents: int, principal_cents: int, interest_cents: int, other_cents: int, fee_cents: int, total_cents: int}>  $installments
     */
    public function place(
        Household $household,
        Category $category,
        string $counterparty,
        ?string $contractNumber,
        ?string $reference,
        int $annualRateBps,
        array $installments,
        ?DebtDirection $direction = null,
        ?int $fixedBps = null,
        ?int $euriborBps = null,
        string $schedule = 'fixed',
        ?CarbonImmutable $openedOn = null,
        int $residualCents = 0,
        ?int $euriborTenorMonths = null,
        ?CarbonImmutable $rateResetOn = null,
        ?DebtKind $kind = null,
    ): Debt {
        if ($category->type !== CashflowType::Expense && $category->type !== CashflowType::Income) {
            throw new InvalidArgumentException(__('У долга должна быть категория плана.'));
        }

        $contractNumber = trim((string) $contractNumber);
        $contractNumber = $contractNumber === '' ? null : $contractNumber;
        $reference = trim((string) $reference);
        $reference = $reference === '' ? null : $reference;
        $direction ??= DebtDirection::Borrow;
        $kind ??= $direction === DebtDirection::Lend ? DebtKind::Personal : DebtKind::Bank;
        $fixedBps ??= $annualRateBps;
        $euriborBps ??= 0;

        if ($contractNumber !== null) {
            $existing = Debt::query()
                ->where('household_id', $household->id)
                ->where('contract_number', $contractNumber)
                ->first();

            if ($existing !== null) {
                return $existing->load('installments');
            }
        }

        $debt = Debt::query()->create([
            'household_id' => $household->id,
            'category_id' => $category->id,
            'direction' => $direction,
            'kind' => $kind,
            'counterparty' => trim($counterparty),
            'contract_number' => $contractNumber,
            'reference' => $reference,
            'annual_rate_bps' => $annualRateBps,
            'fixed_rate_bps' => $fixedBps,
            'euribor_rate_bps' => $euriborBps,
            'monthly_fee_cents' => (int) ($installments[0]['fee_cents'] ?? 0),
            'issuance_fee_cents' => (int) ($installments[0]['other_cents'] ?? 0),
            'residual_cents' => max(0, $residualCents),
            'euribor_tenor_months' => $euriborTenorMonths,
            'rate_reset_on' => $rateResetOn?->toDateString(),
            'schedule' => in_array($schedule, ['revolving', 'minimum'], true) ? $schedule : 'fixed',
            'opened_on' => $openedOn?->toDateString(),
        ]);

        foreach ($installments as $row) {
            $due = isset($row['due_on']) && $row['due_on'] !== null && $row['due_on'] !== ''
                ? CarbonImmutable::parse($row['due_on'])->startOfDay()
                : null;
            $installment = $debt->installments()->create([
                'sequence' => $row['sequence'],
                'due_on' => $due?->toDateString(),
                'principal_before_cents' => $row['principal_before_cents'],
                'principal_cents' => $row['principal_cents'],
                'interest_cents' => $row['interest_cents'],
                'other_cents' => $row['other_cents'],
                'fee_cents' => $row['fee_cents'],
                'total_cents' => $row['total_cents'],
            ]);

            if ($due === null || ! $this->showsOnPlan($household, $due)) {
                continue;
            }

            $period = $this->periods->periodFor($household, $due);
            $line = $this->periods->addPlanLine($period, $category, $debt->counterparty, $row['total_cents'], $due);
            $installment->update(['plan_line_id' => $line->id]);
        }

        return $debt->load('installments');
    }

    public function removeUnpaidPlanLine(Household $household, string $monthStart, string $title, int $cents): string
    {
        $line = PlanLine::query()
            ->where('title', $title)
            ->where('amount_cents', $cents)
            ->whereHas('period', function ($query) use ($household, $monthStart): void {
                $query->where('household_id', $household->id)->whereDate('starts_on', $monthStart);
            })
            ->first();

        if ($line === null) {
            return 'missing';
        }

        if ($line->transactions()->exists()) {
            return 'has-payments';
        }

        $line->delete();

        return 'removed';
    }

    public function settle(PlanLine $planLine, Transaction $transaction): void
    {
        $installment = DebtInstallment::query()->where('plan_line_id', $planLine->id)->first();

        if ($installment === null) {
            return;
        }

        $debt = $installment->debt;

        if ($this->prepayCovers($debt, $transaction)) {
            return;
        }

        if ($installment->paid_transaction_id !== null) {
            if ((int) $installment->paid_transaction_id === (int) $transaction->id) {
                return;
            }

            if ($debt->usesDailyInterest()) {
                $this->applyAnotherDaily($installment, $transaction);

                return;
            }

            $this->absorbExtraPrincipal($debt, (int) $installment->sequence, (int) $transaction->amount_cents, $transaction, false);

            return;
        }

        if ($debt->usesDailyInterest()) {
            $this->settleDaily($installment, $transaction);

            return;
        }

        $amount = (int) $transaction->amount_cents;
        $planned = (int) $installment->total_cents;

        if ($amount > $planned) {
            $installment->update(['paid_transaction_id' => $transaction->id]);
            $this->absorbExtraPrincipal($debt, (int) $installment->sequence, $amount - $planned, $transaction, false);

            return;
        }

        if ($amount !== $planned) {
            return;
        }

        $installment->update(['paid_transaction_id' => $transaction->id]);
    }

    public function reopen(Transaction $transaction): void
    {
        $restored = $this->undoPrepay($transaction);
        $installment = DebtInstallment::query()->where('paid_transaction_id', $transaction->id)->first();

        if ($installment === null) {
            return;
        }

        $debt = $installment->debt;

        if ($debt->usesDailyInterest()) {
            $this->reopenDaily($debt, $installment, $transaction);

            return;
        }

        $installment->update(['paid_transaction_id' => null]);

        if (! $restored && in_array($debt->schedule, ['revolving', 'minimum'], true)) {
            $this->rewriteTail($debt, (int) $installment->sequence - 1, (int) $installment->principal_before_cents);
        }
    }

    public function materialize(Household $household): void
    {
        $limit = $this->forwardLimit($household);

        $rows = DebtInstallment::query()
            ->whereNull('plan_line_id')
            ->whereNull('paid_transaction_id')
            ->whereNotNull('due_on')
            ->where('total_cents', '>', 0)
            ->whereDate('due_on', '>=', $limit['from']->toDateString())
            ->whereDate('due_on', '<=', $limit['end']->toDateString())
            ->whereHas('debt', fn ($query) => $query->where('household_id', $household->id))
            ->with('debt.category')
            ->get();

        foreach ($rows as $row) {
            $due = CarbonImmutable::parse($row->due_on)->startOfDay();
            $period = $this->periods->periodFor($household, $due);
            $line = $this->periods->addPlanLine($period, $row->debt->category, $row->debt->counterparty, (int) $row->total_cents, $due);
            $row->update(['plan_line_id' => $line->id]);
        }
    }

    public function canOpen(Household $household, CarbonImmutable $nextStart): bool
    {
        return $nextStart->toDateString() <= $this->forwardLimit($household)['start']->toDateString();
    }

    /**
     * Новая ставка входит с ближайшего неоплаченного платежа.
     * Оплаченные строки не трогаем. Дата окончания и число платежей не меняются.
     * Месячная сумма считается заново и может вырасти или уменьшиться.
     *
     * @return 'updated'|'rebuilt'|'last'|'paid'|'same'|'daily'
     */
    public function changeRate(Debt $debt, int $annualBps, ?int $fixedBps = null, ?int $euriborBps = null): string
    {
        $annualBps = max(0, $annualBps);
        $fixedBps = max(0, $fixedBps ?? $annualBps);
        $euriborBps = max(0, $euriborBps ?? 0);
        $parts = [
            'annual_rate_bps' => $annualBps,
            'fixed_rate_bps' => $fixedBps,
            'euribor_rate_bps' => $euriborBps,
        ];
        $partsChanged = (int) $debt->annual_rate_bps !== $annualBps
            || (int) $debt->fixed_rate_bps !== $fixedBps
            || (int) $debt->euribor_rate_bps !== $euriborBps;

        if ($debt->usesDailyInterest()) {
            if (! $partsChanged) {
                return 'same';
            }

            $debt->update($parts);

            return 'daily';
        }

        $tail = $this->pricedTail($debt, $annualBps);

        if ($tail === null) {
            if ($partsChanged) {
                $debt->update($parts);
            }

            return $partsChanged ? 'paid' : 'same';
        }

        if ($tail === []) {
            $debt->update($parts);

            return 'last';
        }

        foreach ($tail as $row) {
            $installment = $debt->installments->firstWhere('id', $row['id']);

            if ($installment === null) {
                continue;
            }

            $fields = [
                'principal_before_cents' => $row['principal_before_cents'],
                'principal_cents' => $row['principal_cents'],
                'interest_cents' => $row['interest_cents'],
                'fee_cents' => $row['fee_cents'],
                'total_cents' => $row['total_cents'],
            ];

            if (array_key_exists('other_cents', $row)) {
                $fields['other_cents'] = $row['other_cents'];
            }

            $installment->update($fields);

            if ($installment->plan_line_id !== null) {
                PlanLine::query()
                    ->whereKey($installment->plan_line_id)
                    ->whereDoesntHave('transactions')
                    ->update(['amount_cents' => $row['total_cents']]);
            }
        }

        if ($partsChanged) {
            $debt->update($parts);
        }

        return $partsChanged ? 'updated' : 'rebuilt';
    }

    /**
     * Пустая плата остаётся нулём. Ближайший ещё не оплаченный платёж не трогаем:
     * новые условия входят со следующего месяца.
     *
     * @return 'updated'|'same'|'last'
     */
    public function changeFees(Debt $debt, int $monthlyFeeCents, int $issuanceFeeCents): string
    {
        $monthlyFeeCents = max(0, $monthlyFeeCents);
        $issuanceFeeCents = max(0, $issuanceFeeCents);

        if ((int) $debt->monthly_fee_cents === $monthlyFeeCents && (int) $debt->issuance_fee_cents === $issuanceFeeCents) {
            return 'same';
        }

        $debt->loadMissing('installments');
        $future = $debt->installments
            ->filter(fn (DebtInstallment $row): bool => $row->paid_transaction_id === null && ! ((int) $row->principal_cents === 0 && (int) $row->interest_cents === 0 && (int) $row->total_cents === 0))
            ->slice(1)
            ->values();

        foreach ($future as $row) {
            $other = (int) $row->sequence === 1 ? $issuanceFeeCents : (int) $row->other_cents;
            $total = (int) $row->principal_cents + (int) $row->interest_cents + $monthlyFeeCents + $other;
            $row->update([
                'fee_cents' => $monthlyFeeCents,
                'other_cents' => $other,
                'total_cents' => $total,
            ]);

            if ($row->plan_line_id !== null) {
                PlanLine::query()
                    ->whereKey($row->plan_line_id)
                    ->whereDoesntHave('transactions')
                    ->update(['amount_cents' => $total]);
            }
        }

        $debt->update([
            'monthly_fee_cents' => $monthlyFeeCents,
            'issuance_fee_cents' => $issuanceFeeCents,
        ]);

        return $future->isEmpty() ? 'last' : 'updated';
    }

    /**
     * @return list<array{id: int, due_on: ?string, principal_before_cents: int, principal_cents: int, interest_cents: int, fee_cents: int, total_cents: int, other_cents?: int}>|null
     */
    public function pricedTail(Debt $debt, int $annualBps): ?array
    {
        $debt->loadMissing('installments');
        $unpaid = $debt->installments
            ->filter(fn (DebtInstallment $row): bool => $row->paid_transaction_id === null && (int) $row->total_cents > 0)
            ->values();

        if ($unpaid->isEmpty()) {
            return null;
        }

        $anchor = $unpaid->first();
        $balance = (int) $anchor->principal_before_cents;

        if ($debt->schedule === 'minimum') {
            return $this->minimumPricedTail($debt, $unpaid, $balance, $annualBps);
        }

        if ($debt->schedule !== 'fixed') {
            return $this->legacyPricedTail($debt, $unpaid, $balance, $annualBps);
        }

        return $this->fixedPricedTail($debt, $unpaid, $balance, $annualBps);
    }

    /**
     * Обычный кредит и ипотека: срок до даты окончания тот же, месячный платёж новый.
     * Выкуп в конце остаётся отдельной последней строкой, как в draft().
     *
     * @param  \Illuminate\Support\Collection<int, DebtInstallment>  $future
     * @return list<array{id: int, due_on: ?string, principal_before_cents: int, principal_cents: int, interest_cents: int, fee_cents: int, other_cents: int, total_cents: int}>
     */
    private function fixedPricedTail(Debt $debt, $future, int $balance, int $annualBps): array
    {
        $calculator = app(CreditCalculator::class);
        $milli = max(0, $annualBps);
        $count = $future->count();
        $residual = min(max(0, (int) $debt->residual_cents), max(0, $balance));
        $buyout = $residual > 0 && $count > 1;
        $amortizing = $buyout ? $count - 1 : $count;
        $payment = $calculator->annuity($balance - ($buyout ? $residual : 0), $amortizing, $milli)
            + $calculator->interest($buyout ? $residual : 0, $milli);
        $rows = [];
        $lumpDays = $count === 1 ? $this->lumpDays($debt, $future->first()) : null;

        foreach ($future->values() as $index => $row) {
            $dueOn = $row->due_on?->toDateString();
            $fee = (int) $row->fee_cents;
            $issuance = (int) $row->other_cents;

            if ($balance <= 0) {
                $rows[] = [
                    'id' => $row->id,
                    'due_on' => $dueOn,
                    'principal_before_cents' => 0,
                    'principal_cents' => 0,
                    'interest_cents' => 0,
                    'fee_cents' => 0,
                    'other_cents' => 0,
                    'total_cents' => 0,
                ];

                continue;
            }

            if ($buyout && $index === $count - 1) {
                $rows[] = [
                    'id' => $row->id,
                    'due_on' => $dueOn,
                    'principal_before_cents' => $balance,
                    'principal_cents' => $balance,
                    'interest_cents' => 0,
                    'fee_cents' => $fee,
                    'other_cents' => 0,
                    'total_cents' => $balance + $fee,
                ];
                $balance = 0;

                continue;
            }

            $interest = $lumpDays !== null
                ? ($milli === 0 || $lumpDays === 0 ? 0 : (int) round($balance * $milli * $lumpDays / 36_000_000))
                : $calculator->interest($balance, $milli);
            $floor = $buyout ? $residual : 0;
            $room = max(0, $balance - $floor);

            if ($index === $amortizing - 1) {
                $principal = $room;
                $due = $principal + $interest;
            } else {
                $slice = $payment - $interest;
                $principal = $slice > 0 ? min($room, $slice) : 0;
                $due = $principal + $interest;
            }

            $rows[] = [
                'id' => $row->id,
                'due_on' => $dueOn,
                'principal_before_cents' => $balance,
                'principal_cents' => $principal,
                'interest_cents' => $interest,
                'fee_cents' => $fee,
                'other_cents' => $issuance,
                'total_cents' => $due + $fee + $issuance,
            ];
            $balance -= $principal;
        }

        return $rows;
    }

    /**
     * Возобновляемый график. Срок может сжаться, если новый платёж закрывает остаток раньше.
     *
     * @param  \Illuminate\Support\Collection<int, DebtInstallment>  $future
     * @return list<array{id: int, due_on: ?string, principal_before_cents: int, principal_cents: int, interest_cents: int, fee_cents: int, total_cents: int}>
     */
    private function legacyPricedTail(Debt $debt, $future, int $balance, int $annualBps): array
    {
        $calculator = app(CreditCalculator::class);
        $milli = max(0, $annualBps);
        $count = $future->count();
        $residual = min(max(0, (int) $debt->residual_cents), $balance);
        $keepsBuyout = $residual > 0 && $count > 1;
        $amortizing = $keepsBuyout ? $count - 1 : $count;
        $payment = $calculator->annuity($balance - ($keepsBuyout ? $residual : 0), $amortizing, $milli)
            + ($keepsBuyout ? $calculator->interest($residual, $milli) : 0);
        $rows = [];
        $future = $future->values();

        foreach ($future as $index => $row) {
            $fee = (int) $row->fee_cents;

            if ($balance <= 0) {
                $rows[] = [
                    'id' => $row->id,
                    'due_on' => $row->due_on?->toDateString(),
                    'principal_before_cents' => 0,
                    'principal_cents' => 0,
                    'interest_cents' => 0,
                    'fee_cents' => 0,
                    'other_cents' => 0,
                    'total_cents' => 0,
                ];

                continue;
            }

            if ($keepsBuyout && $index === $count - 1) {
                $other = (int) $row->other_cents;
                $rows[] = [
                    'id' => $row->id,
                    'due_on' => $row->due_on?->toDateString(),
                    'principal_before_cents' => $balance,
                    'principal_cents' => $balance,
                    'interest_cents' => 0,
                    'fee_cents' => $fee,
                    'other_cents' => $other,
                    'total_cents' => $balance + $fee + $other,
                ];

                continue;
            }

            $interest = $calculator->interest($balance, $milli);
            $floor = $keepsBuyout ? $residual : 0;
            $closes = $index === $amortizing - 1 || $payment >= $balance - $floor + $interest;

            if ($closes) {
                $principal = $balance - $floor;
                $due = $principal + $interest;
            } elseif ($payment <= $interest) {
                $principal = 0;
                $due = $interest;
            } else {
                $principal = $payment - $interest;
                $due = $payment;
            }

            $other = (int) $row->other_cents;
            $rows[] = [
                'id' => $row->id,
                'due_on' => $row->due_on?->toDateString(),
                'principal_before_cents' => $balance,
                'principal_cents' => $principal,
                'interest_cents' => $interest,
                'fee_cents' => $fee,
                'other_cents' => $other,
                'total_cents' => $due + $fee + $other,
            ];
            $balance -= $principal;
        }

        return $rows;
    }

    /**
     * Минимальный платёж: 3 % тела и проценты за дни с прошлой даты, с ближайшего неоплаченного месяца.
     *
     * @param  \Illuminate\Support\Collection<int, DebtInstallment>  $future
     * @return list<array{id: int, due_on: ?string, principal_before_cents: int, principal_cents: int, interest_cents: int, fee_cents: int, other_cents: int, total_cents: int}>
     */
    private function minimumPricedTail(Debt $debt, $future, int $balance, int $annualBps): array
    {
        $future = $future->values();
        $previous = $this->minimumCursor($debt, $future->first());
        $rows = [];
        $last = $future->count() - 1;

        foreach ($future as $index => $row) {
            $fee = (int) $row->fee_cents;
            $other = (int) $row->other_cents;

            if ($balance <= 0 || $row->due_on === null || $previous === null) {
                $rows[] = [
                    'id' => $row->id,
                    'due_on' => $row->due_on?->toDateString(),
                    'principal_before_cents' => 0,
                    'principal_cents' => 0,
                    'interest_cents' => 0,
                    'fee_cents' => 0,
                    'other_cents' => 0,
                    'total_cents' => 0,
                ];

                continue;
            }

            $due = CarbonImmutable::parse($row->due_on)->startOfDay();
            $split = $this->minimumSplit($balance, max(0, $annualBps), (int) $previous->diff($due)->days);

            if ($index === $last && $split['principal'] < $balance) {
                $split = [
                    'principal' => $balance,
                    'interest' => $split['interest'],
                    'total' => $balance + $split['interest'],
                ];
            }

            $rows[] = [
                'id' => $row->id,
                'due_on' => $due->toDateString(),
                'principal_before_cents' => $balance,
                'principal_cents' => $split['principal'],
                'interest_cents' => $split['interest'],
                'fee_cents' => $fee,
                'other_cents' => $other,
                'total_cents' => $split['total'] + $fee + $other,
            ];
            $balance -= $split['principal'];
            $previous = $due;
        }

        return $rows;
    }

    private function minimumCursor(Debt $debt, ?DebtInstallment $first): ?CarbonImmutable
    {
        if ($first === null) {
            return null;
        }

        if ((int) $first->sequence > 1) {
            $prior = $debt->installments->first(
                fn (DebtInstallment $row): bool => (int) $row->sequence === (int) $first->sequence - 1,
            );

            if ($prior?->due_on !== null) {
                return CarbonImmutable::parse($prior->due_on)->startOfDay();
            }
        }

        return $debt->opened_on === null ? null : CarbonImmutable::parse($debt->opened_on)->startOfDay();
    }

    /**
     * Один платёж «в срок» хранит проценты за фактические дни. Аннуитет на один месяц — нет.
     */
    private function lumpDays(Debt $debt, ?DebtInstallment $row): ?int
    {
        if ($row === null || $debt->opened_on === null || $row->due_on === null) {
            return null;
        }

        $days = CarbonImmutable::parse($debt->opened_on)->startOfDay()
            ->diff(CarbonImmutable::parse($row->due_on)->startOfDay())->days;
        $balance = (int) $row->principal_before_cents;
        $old = (int) $debt->annual_rate_bps;
        $dayInterest = $old === 0 || $days === 0
            ? 0
            : (int) round($balance * $old * $days / 36_000_000);

        if ((int) $row->interest_cents !== $dayInterest) {
            return null;
        }

        return (int) $days;
    }

    /**
     * @return list<array{sequence: int, due_on: ?string, principal_before_cents: int, principal_cents: int, interest_cents: int, other_cents: int, fee_cents: int, total_cents: int}>
     */
    public function draft(int $principalCents, string $mode, int $months, ?CarbonImmutable $dueOn, int $annualBps, CarbonImmutable $from, int $monthlyFeeCents = 0, int $issuanceFeeCents = 0, int $residualCents = 0): array
    {
        $annualBps = max(0, $annualBps);
        $principalCents = max(0, $principalCents);
        $monthlyFeeCents = max(0, $monthlyFeeCents);
        $issuanceFeeCents = max(0, $issuanceFeeCents);
        $residualCents = min(max(0, $residualCents), $principalCents);

        if ($mode === 'lump') {
            $days = 0;

            if ($dueOn !== null) {
                $days = $from->startOfDay()->diff($dueOn->startOfDay())->days;
            }

            $interest = $annualBps === 0 || $days === 0
                ? 0
                : (int) round($principalCents * $annualBps * $days / 36_000_000);

            return [[
                'sequence' => 1,
                'due_on' => $dueOn?->toDateString(),
                'principal_before_cents' => $principalCents,
                'principal_cents' => $principalCents,
                'interest_cents' => $interest,
                'other_cents' => $issuanceFeeCents,
                'fee_cents' => $monthlyFeeCents,
                'total_cents' => $principalCents + $interest + $monthlyFeeCents + $issuanceFeeCents,
            ]];
        }

        $calculator = app(CreditCalculator::class);
        $milli = $annualBps;
        $count = max(1, $months);
        $balance = $principalCents;
        $payment = $calculator->annuity($balance - $residualCents, $count, $milli) + $calculator->interest($residualCents, $milli);
        $cursor = $dueOn?->startOfDay();
        $rows = [];

        for ($index = 0; $index < $count && $balance > $residualCents; $index++) {
            $interest = $calculator->interest($balance, $milli);
            $floor = $residualCents > 0 ? $residualCents : 0;
            $closes = $index === $count - 1 || $payment >= $balance - $floor + $interest;

            if ($closes) {
                $principal = $balance - $floor;
                $due = $principal + $interest;
            } elseif ($payment <= $interest) {
                $principal = 0;
                $due = $interest;
            } else {
                $principal = $payment - $interest;
                $due = $payment;
            }

            $issuance = $index === 0 ? $issuanceFeeCents : 0;
            $rows[] = [
                'sequence' => $index + 1,
                'due_on' => $cursor?->toDateString(),
                'principal_before_cents' => $balance,
                'principal_cents' => $principal,
                'interest_cents' => $interest,
                'other_cents' => $issuance,
                'fee_cents' => $monthlyFeeCents,
                'total_cents' => $due + $monthlyFeeCents + $issuance,
            ];
            $balance -= $principal;
            $cursor = $cursor?->addMonthsNoOverflow(1);
        }

        if ($residualCents > 0 && $balance > 0) {
            $rows[] = [
                'sequence' => count($rows) + 1,
                'due_on' => $cursor?->toDateString(),
                'principal_before_cents' => $balance,
                'principal_cents' => $balance,
                'interest_cents' => 0,
                'other_cents' => 0,
                'fee_cents' => $monthlyFeeCents,
                'total_cents' => $balance + $monthlyFeeCents,
            ];
        }

        return $rows;
    }

    public function open(
        Household $household,
        DebtDirection $direction,
        string $counterparty,
        ?string $contractNumber,
        ?string $reference,
        int $principalCents,
        string $mode,
        int $months,
        ?CarbonImmutable $dueOn,
        int $fixedBps,
        int $euriborBps,
        CarbonImmutable $from,
        int $monthlyFeeCents = 0,
        int $issuanceFeeCents = 0,
        int $residualCents = 0,
        ?int $euriborTenorMonths = null,
        ?CarbonImmutable $rateResetOn = null,
        DebtKind $kind = DebtKind::Bank,
        ?Account $fromAccount = null,
        ?int $userId = null,
    ): Debt {
        if ($kind === DebtKind::Bank && $direction !== DebtDirection::Borrow) {
            throw new InvalidArgumentException(__('Банковский кредит только как обязательство, которое должны мы.'));
        }

        if ($direction === DebtDirection::Lend) {
            $kind = DebtKind::Personal;
            $this->assertOperatingAccount($household, $fromAccount, 'lend');
        }

        if ($kind === DebtKind::Personal && $direction === DebtDirection::Borrow) {
            $this->assertOperatingAccount($household, $fromAccount, 'borrow');
        }

        $rows = $this->draft($principalCents, $mode, $months, $dueOn, $fixedBps + $euriborBps, $from, $monthlyFeeCents, $issuanceFeeCents, $residualCents);
        $openedOn = $kind === DebtKind::Personal ? $from : null;

        $debt = $this->place(
            $household,
            $this->categoryFor($household, $direction, $kind),
            $counterparty,
            $contractNumber,
            $reference,
            $fixedBps + $euriborBps,
            $rows,
            $direction,
            $fixedBps,
            $euriborBps,
            'fixed',
            $openedOn,
            $residualCents,
            $euriborTenorMonths,
            $rateResetOn,
            $kind,
        );

        if ($direction === DebtDirection::Lend) {
            $transaction = $this->periods->record(
                $household,
                $this->issuanceExpenseCategory($household),
                CashflowType::Expense,
                $principalCents,
                $from,
                $counterparty,
                null,
                $userId,
                $fromAccount,
            );
            $debt->update([
                'account_id' => $fromAccount->id,
                'issued_transaction_id' => $transaction->id,
            ]);
        }

        if ($kind === DebtKind::Personal && $direction === DebtDirection::Borrow) {
            $transaction = $this->periods->record(
                $household,
                $this->issuanceExpenseCategory($household),
                CashflowType::LiabilityIn,
                $principalCents,
                $from,
                __('взяли в долг').': '.$counterparty,
                null,
                $userId,
                $fromAccount,
            );
            $debt->update([
                'account_id' => $fromAccount->id,
                'issued_transaction_id' => $transaction->id,
            ]);
        }

        return $debt->fresh(['installments']);
    }

    /**
     * Backfill cash-out for a personal loan that was recorded without debiting an account.
     */
    public function recordMissingIssuance(Debt $debt, Account $account, ?int $userId = null): Transaction
    {
        if ($debt->direction !== DebtDirection::Lend) {
            throw new InvalidArgumentException(__('Списание при выдаче только для выданного личного долга.'));
        }

        if ($debt->issued_transaction_id !== null) {
            throw new InvalidArgumentException(__('Выдача этого долга уже списана со счёта.'));
        }

        $this->assertOperatingAccount($debt->household, $account, 'lend');
        $principal = $debt->remainingCents();

        if ($principal <= 0) {
            $first = $debt->installments()->orderBy('sequence')->first();
            $principal = (int) ($first?->principal_before_cents ?? 0);
        }

        if ($principal <= 0) {
            throw new InvalidArgumentException(__('У долга нет суммы к списанию.'));
        }

        $on = $debt->opened_on?->toImmutable()?->startOfDay()
            ?? CarbonImmutable::parse($debt->created_at)->timezone($debt->household->timezone ?: 'Europe/Riga')->startOfDay();

        $transaction = $this->periods->record(
            $debt->household,
            $this->issuanceExpenseCategory($debt->household),
            CashflowType::Expense,
            $principal,
            $on,
            $debt->counterparty,
            null,
            $userId,
            $account,
        );

        $debt->update([
            'kind' => DebtKind::Personal,
            'account_id' => $account->id,
            'issued_transaction_id' => $transaction->id,
            'opened_on' => $debt->opened_on?->toDateString() ?? $on->toDateString(),
        ]);

        return $transaction;
    }

    private function assertOperatingAccount(Household $household, ?Account $account, string $purpose = 'lend'): void
    {
        if ($account === null
            || (int) $account->household_id !== (int) $household->id
            || $account->role !== AccountRole::Operating) {
            throw new InvalidArgumentException($purpose === 'borrow'
                ? __('Выберите расчётный счёт, на который зачисляете долг.')
                : __('Выберите расчётный счёт, с которого выдаёте долг.'));
        }
    }

    private function categoryFor(Household $household, DebtDirection $direction, DebtKind $kind = DebtKind::Bank): Category
    {
        $type = $direction === DebtDirection::Lend ? CashflowType::Income : CashflowType::Expense;
        $names = match (true) {
            $kind === DebtKind::Personal => ['Личные долги', $direction === DebtDirection::Lend ? 'Дебитовые обязательства' : 'Кредитные обязательства'],
            default => ['Кредитные обязательства'],
        };
        $category = $household->categories()
            ->whereIn('name', $names)
            ->where('type', $type)
            ->whereNull('parent_id')
            ->whereNull('archived_at')
            ->orderByRaw("case when name = ? then 0 else 1 end", [$names[0]])
            ->first();

        if ($category !== null) {
            return $category;
        }

        return $household->categories()->create([
            'parent_id' => null,
            'type' => $type,
            'name' => $names[0],
            'icon' => $direction === DebtDirection::Lend ? 'coins' : 'card',
            'sort' => ((int) $household->categories()->max('sort')) + 10,
        ]);
    }

    private function issuanceExpenseCategory(Household $household): Category
    {
        $category = $household->categories()
            ->whereIn('name', ['Личные долги', 'Дебитовые обязательства'])
            ->where('type', CashflowType::Expense)
            ->whereNull('parent_id')
            ->whereNull('archived_at')
            ->orderByRaw("case when name = 'Личные долги' then 0 else 1 end")
            ->first();

        if ($category !== null) {
            return $category;
        }

        return $household->categories()->create([
            'parent_id' => null,
            'type' => CashflowType::Expense,
            'name' => 'Личные долги',
            'icon' => 'coins',
            'sort' => ((int) $household->categories()->max('sort')) + 10,
        ]);
    }

    public function adjustDailyTotal(Debt $debt, int $totalCents, CarbonImmutable $on): void
    {
        if (! $debt->usesDailyInterest()) {
            throw new InvalidArgumentException(__('Подправить остаток можно у кредита с ежедневными процентами.'));
        }

        $debt->update([
            'principal_cents' => max(0, $totalCents),
            'accrued_cents' => 0,
            'interest_through' => $on->startOfDay()->toDateString(),
        ]);
    }

    private function settleDaily(DebtInstallment $installment, Transaction $transaction): void
    {
        $debt = $installment->debt()->firstOrFail();
        $asOf = CarbonImmutable::parse($transaction->occurred_on)->startOfDay();
        $interest = app(DailyInterest::class);
        $position = $interest->position($debt, $asOf);
        $charges = (int) $installment->fee_cents + (int) $installment->other_cents;
        $planned = (int) $installment->total_cents;
        $scheduledPrincipal = (int) $installment->principal_cents;
        $applied = max(0, (int) $transaction->amount_cents - $charges);
        $split = $interest->applyPayment($position['principal'], $position['accrued'], $applied);
        $dailyState = $this->dailyState($debt);

        DB::transaction(function () use ($debt, $installment, $transaction, $split, $asOf, $position, $planned, $scheduledPrincipal, $dailyState): void {
            $debt->update([
                'principal_cents' => $split['principal'],
                'accrued_cents' => $split['accrued'],
                'interest_through' => $asOf->toDateString(),
            ]);
            $installment->update([
                'principal_before_cents' => $position['principal'],
                'principal_cents' => $split['principal_paid'],
                'interest_cents' => $split['interest_paid'],
                'paid_transaction_id' => $transaction->id,
            ]);

            if ((int) $transaction->amount_cents <= $planned) {
                return;
            }

            $extraPrincipal = min(
                max(0, $split['principal_paid'] - $scheduledPrincipal),
                (int) $transaction->amount_cents - $planned,
            );
            $this->absorbExtraPrincipal($debt, (int) $installment->sequence, $extraPrincipal, $transaction, true, $dailyState);
        });
    }

    private function applyAnotherDaily(DebtInstallment $installment, Transaction $transaction): void
    {
        $debt = $installment->debt()->firstOrFail();
        $asOf = CarbonImmutable::parse($transaction->occurred_on)->startOfDay();
        $interest = app(DailyInterest::class);
        $position = $interest->position($debt, $asOf);
        $applied = max(0, (int) $transaction->amount_cents);
        $split = $interest->applyPayment($position['principal'], $position['accrued'], $applied);
        $dailyState = $this->dailyState($debt);

        DB::transaction(function () use ($debt, $installment, $transaction, $split, $asOf, $dailyState): void {
            $debt->update([
                'principal_cents' => $split['principal'],
                'accrued_cents' => $split['accrued'],
                'interest_through' => $asOf->toDateString(),
            ]);
            $this->absorbExtraPrincipal($debt, (int) $installment->sequence, $split['principal_paid'], $transaction, true, $dailyState);
        });
    }

    private function reopenDaily(Debt $debt, DebtInstallment $installment, Transaction $transaction): void
    {
        $asOf = CarbonImmutable::parse($transaction->occurred_on)->startOfDay();

        DB::transaction(function () use ($debt, $installment, $asOf): void {
            $debt->update([
                'principal_cents' => (int) $installment->principal_before_cents,
                'accrued_cents' => (int) $installment->interest_cents + (int) $debt->accrued_cents,
                'interest_through' => $asOf->toDateString(),
            ]);
            $installment->update(['paid_transaction_id' => null]);
        });
    }

    /**
     * @return array{principal_cents: int, accrued_cents: int, interest_through: ?string}
     */
    private function dailyState(Debt $debt): array
    {
        return [
            'principal_cents' => (int) $debt->principal_cents,
            'accrued_cents' => (int) $debt->accrued_cents,
            'interest_through' => $debt->interest_through?->toDateString(),
        ];
    }

    /**
     * Extra principal shortens the term from the end. Surviving installments keep their payment.
     * A schedule-mode row that no longer covers a full installment becomes the smaller final payment.
     *
     * @param  array{principal_cents: int, accrued_cents: int, interest_through: ?string}|null  $dailyState
     */
    private function absorbExtraPrincipal(Debt $debt, int $afterSequence, int $extra, Transaction $transaction, bool $daily, ?array $dailyState = null): void
    {
        if ($this->prepayCovers($debt, $transaction)) {
            return;
        }

        $rows = $debt->installments()->where('sequence', '>', $afterSequence)->orderBy('sequence')->get();
        $extra = max(0, $extra);

        if ($rows->isNotEmpty() && $extra > 0) {
            $extra = min($extra, (int) $rows->sum('principal_cents'));
        }

        $snapshot = [];

        foreach ($rows as $row) {
            $snapshot[] = [
                'id' => (int) $row->id,
                'plan_line_id' => $row->plan_line_id !== null ? (int) $row->plan_line_id : null,
                'principal_before_cents' => (int) $row->principal_before_cents,
                'principal_cents' => (int) $row->principal_cents,
                'interest_cents' => (int) $row->interest_cents,
                'other_cents' => (int) $row->other_cents,
                'fee_cents' => (int) $row->fee_cents,
                'total_cents' => (int) $row->total_cents,
            ];

            if ($extra <= 0) {
                continue;
            }

            $row->principal_before_cents = max(0, (int) $row->principal_before_cents - $extra);

            if ((int) $row->principal_before_cents <= 0) {
                $this->clearInstallment($row);

                continue;
            }

            if (! $daily && (int) $row->principal_before_cents < (int) $row->principal_cents) {
                $cut = (int) $row->principal_cents - (int) $row->principal_before_cents;
                $row->principal_cents = (int) $row->principal_before_cents;
                $row->total_cents = max(0, (int) $row->total_cents - $cut);
                $row->save();

                if ($row->plan_line_id !== null) {
                    PlanLine::query()
                        ->whereKey($row->plan_line_id)
                        ->whereDoesntHave('transactions')
                        ->update(['amount_cents' => (int) $row->total_cents]);
                }

                continue;
            }

            $row->save();
        }

        $log = $debt->prepay_log ?? [];
        $entry = [
            'transaction_id' => (int) $transaction->id,
            'daily' => $daily,
            'rows' => $snapshot,
        ];

        if ($daily && $dailyState !== null) {
            $entry['principal_cents'] = $dailyState['principal_cents'];
            $entry['accrued_cents'] = $dailyState['accrued_cents'];
            $entry['interest_through'] = $dailyState['interest_through'];
        }

        $log[] = $entry;
        $debt->update(['prepay_log' => $log]);
    }

    private function prepayCovers(Debt $debt, Transaction $transaction): bool
    {
        foreach ($debt->prepay_log ?? [] as $item) {
            if ((int) ($item['transaction_id'] ?? 0) === (int) $transaction->id) {
                return true;
            }
        }

        return false;
    }

    private function undoPrepay(Transaction $transaction): bool
    {
        $installment = $transaction->plan_line_id
            ? DebtInstallment::query()->where('plan_line_id', $transaction->plan_line_id)->first()
            : null;
        $debt = $installment?->debt;

        if ($debt === null) {
            $debt = DebtInstallment::query()->where('paid_transaction_id', $transaction->id)->first()?->debt;
        }

        if ($debt === null || ! $this->prepayCovers($debt, $transaction)) {
            return false;
        }

        $kept = [];
        $entry = null;

        foreach ($debt->prepay_log ?? [] as $item) {
            if ((int) ($item['transaction_id'] ?? 0) === (int) $transaction->id && $entry === null) {
                $entry = $item;

                continue;
            }

            $kept[] = $item;
        }

        if ($entry === null) {
            return false;
        }

        foreach ($entry['rows'] ?? [] as $snap) {
            $row = DebtInstallment::query()->find($snap['id'] ?? 0);

            if ($row === null) {
                continue;
            }

            $planLineId = $snap['plan_line_id'] ?? null;

            if ($planLineId && ! PlanLine::query()->whereKey($planLineId)->exists()) {
                $planLineId = $this->restorePlanLine($debt, $row, $snap);
            } elseif ($planLineId) {
                PlanLine::query()
                    ->whereKey($planLineId)
                    ->whereDoesntHave('transactions')
                    ->update(['amount_cents' => (int) $snap['total_cents']]);
            }

            $row->update([
                'principal_before_cents' => (int) $snap['principal_before_cents'],
                'principal_cents' => (int) $snap['principal_cents'],
                'interest_cents' => (int) $snap['interest_cents'],
                'other_cents' => (int) $snap['other_cents'],
                'fee_cents' => (int) $snap['fee_cents'],
                'total_cents' => (int) $snap['total_cents'],
                'plan_line_id' => $planLineId,
            ]);
        }

        $settlesThis = DebtInstallment::query()->where('paid_transaction_id', $transaction->id)->exists();

        if (($entry['daily'] ?? false) && ! $settlesThis) {
            $debt->update([
                'principal_cents' => (int) ($entry['principal_cents'] ?? 0),
                'accrued_cents' => (int) ($entry['accrued_cents'] ?? 0),
                'interest_through' => $entry['interest_through'] ?? null,
            ]);
        }

        $debt->update(['prepay_log' => $kept === [] ? null : array_values($kept)]);

        return true;
    }

    /**
     * @param  array{plan_line_id: ?int, total_cents: int}  $snap
     */
    private function restorePlanLine(Debt $debt, DebtInstallment $row, array $snap): ?int
    {
        if ($row->due_on === null) {
            return null;
        }

        $debt->loadMissing('household', 'category');
        $due = CarbonImmutable::parse($row->due_on)->startOfDay();

        if (! $this->showsOnPlan($debt->household, $due)) {
            return null;
        }

        $period = $this->periods->periodFor($debt->household, $due);
        $line = $this->periods->addPlanLine($period, $debt->category, $debt->counterparty, (int) $snap['total_cents'], $due);

        return (int) $line->id;
    }

    private function rewriteTail(Debt $debt, int $afterSequence, int $balance): void
    {
        $later = $debt->installments()->where('sequence', '>', $afterSequence)->orderBy('sequence')->get();

        if ($later->isEmpty()) {
            return;
        }

        if ($debt->schedule === 'minimum') {
            $this->rewriteMinimum($debt, $afterSequence, $balance, $later);

            return;
        }

        $draft = [];

        if ($balance > 0) {
            $firstDue = $later->first()->due_on ? CarbonImmutable::parse($later->first()->due_on) : null;
            $issuance = (int) $later->first()->sequence === 1 ? (int) $debt->issuance_fee_cents : 0;
            $residual = (int) $debt->residual_cents;
            $periods = $later->count();

            if ($residual > 0 && $periods > 1) {
                $periods--;
            }

            $draft = $this->draft($balance, 'parts', $periods, $firstDue, (int) $debt->annual_rate_bps, CarbonImmutable::now(), (int) $debt->monthly_fee_cents, $issuance, $residual);
        }

        foreach ($later->values() as $index => $row) {
            $next = $draft[$index] ?? null;

            if ($next === null || (int) $next['total_cents'] <= 0) {
                $this->clearInstallment($row);

                continue;
            }

            $row->update([
                'principal_before_cents' => $next['principal_before_cents'],
                'principal_cents' => $next['principal_cents'],
                'interest_cents' => $next['interest_cents'],
                'other_cents' => $next['other_cents'],
                'fee_cents' => $next['fee_cents'],
                'total_cents' => $next['total_cents'],
            ]);

            if ($row->plan_line_id !== null) {
                PlanLine::query()
                    ->whereKey($row->plan_line_id)
                    ->whereDoesntHave('transactions')
                    ->update(['amount_cents' => $next['total_cents']]);
            }
        }
    }

    /**
     * Минимум: 3 % непогашенной основной суммы плюс проценты за дни с прошлой даты. Пол 20 €, последний платёж закрывает остаток.
     *
     * @return list<array{sequence: int, due_on: string, principal_before_cents: int, principal_cents: int, interest_cents: int, other_cents: int, fee_cents: int, total_cents: int}>
     */
    public function minimumInstallments(int $principalCents, int $annualBps, CarbonImmutable $openedOn, CarbonImmutable $firstDue): array
    {
        $balance = max(0, $principalCents);
        $previous = $openedOn->startOfDay();
        $due = $firstDue->startOfDay();
        $rows = [];

        for ($sequence = 1; $balance > 0 && $sequence <= 360; $sequence++) {
            $days = $previous->diff($due)->days;
            $split = $this->minimumSplit($balance, $annualBps, (int) $days);
            $rows[] = [
                'sequence' => $sequence,
                'due_on' => $due->toDateString(),
                'principal_before_cents' => $balance,
                'principal_cents' => $split['principal'],
                'interest_cents' => $split['interest'],
                'other_cents' => 0,
                'fee_cents' => 0,
                'total_cents' => $split['total'],
            ];
            $balance -= $split['principal'];
            $previous = $due;
            $due = $due->addMonthsNoOverflow(1);
        }

        return $rows;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, DebtInstallment>  $later
     */
    private function rewriteMinimum(Debt $debt, int $afterSequence, int $balance, $later): void
    {
        $previous = $debt->installments()->where('sequence', $afterSequence)->first()?->due_on;
        $cursor = $previous ? CarbonImmutable::parse($previous)->startOfDay() : ($debt->opened_on?->startOfDay() ?? CarbonImmutable::now()->startOfDay());

        foreach ($later->values() as $row) {
            if ($balance <= 0 || $row->due_on === null) {
                $this->clearInstallment($row);

                continue;
            }

            $due = CarbonImmutable::parse($row->due_on)->startOfDay();
            $split = $this->minimumSplit($balance, (int) $debt->annual_rate_bps, (int) $cursor->diff($due)->days);
            $row->update([
                'principal_before_cents' => $balance,
                'principal_cents' => $split['principal'],
                'interest_cents' => $split['interest'],
                'other_cents' => 0,
                'fee_cents' => 0,
                'total_cents' => $split['total'],
            ]);

            if ($row->plan_line_id !== null) {
                PlanLine::query()
                    ->whereKey($row->plan_line_id)
                    ->whereDoesntHave('transactions')
                    ->update(['amount_cents' => $split['total']]);
            }

            $balance -= $split['principal'];
            $cursor = $due;
        }
    }

    /**
     * @return array{principal: int, interest: int, total: int}
     */
    private function minimumSplit(int $balance, int $annualBps, int $days): array
    {
        $interest = $balance > 0 && $annualBps > 0 && $days > 0
            ? (int) round($balance * $annualBps * $days / 36_500_000)
            : 0;
        $slice = (int) round($balance * 300 / 10_000);
        $obligations = $balance + $interest;
        $formula = $interest + $slice;

        if ($obligations <= $formula || $obligations <= 2000) {
            return ['principal' => $balance, 'interest' => $interest, 'total' => $obligations];
        }

        if ($formula < 2000) {
            return ['principal' => 2000 - $interest, 'interest' => $interest, 'total' => 2000];
        }

        return ['principal' => $slice, 'interest' => $interest, 'total' => $formula];
    }

    private function clearInstallment(DebtInstallment $row): void
    {
        if ($row->plan_line_id !== null) {
            $deleted = PlanLine::query()
                ->whereKey($row->plan_line_id)
                ->whereDoesntHave('transactions')
                ->delete();

            if ($deleted > 0) {
                $row->plan_line_id = null;
            }
        }

        $row->update([
            'plan_line_id' => $row->plan_line_id,
            'principal_before_cents' => 0,
            'principal_cents' => 0,
            'interest_cents' => 0,
            'other_cents' => 0,
            'fee_cents' => 0,
            'total_cents' => 0,
        ]);
    }

    /**
     * @return array{from: CarbonImmutable, start: CarbonImmutable, end: CarbonImmutable}
     */
    private function forwardLimit(Household $household): array
    {
        $today = CarbonImmutable::now($household->timezone ?: 'Europe/Riga')->startOfDay();
        $current = $this->periods->periodFor($household, $today);
        $start = CarbonImmutable::parse($current->starts_on)->startOfDay();
        $last = $start->addMonthsNoOverflow(6)->startOfDay();

        return [
            'from' => $start,
            'start' => $last,
            'end' => $last->endOfMonth()->startOfDay(),
        ];
    }

    private function showsOnPlan(Household $household, CarbonImmutable $due): bool
    {
        return $due->startOfDay()->toDateString() <= $this->forwardLimit($household)['end']->toDateString();
    }
}
