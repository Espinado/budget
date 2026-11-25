<?php

namespace App\Livewire\Budget;

use App\Models\Debt;
use App\Models\DebtInstallment;
use App\Models\Household;
use App\Services\Budget\DebtBook;
use App\Services\Budget\FamilyActivity;
use App\Livewire\Concerns\NormalizesMoneyInput;
use App\Support\DailyInterest;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class DebtShow extends Component
{
    use NormalizesMoneyInput;

    public int $debtId;

    public string $rateFixed = '';

    public string $rateEuribor = '';

    public string $feeMonthly = '';

    public string $feeIssuance = '';

    public bool $editingConditions = false;

    public bool $editingBalance = false;

    public string $balanceTotal = '';

    public function mount(Debt $debt): void
    {
        abort_unless((int) $debt->household_id === (int) $this->household()->id, 404);
        $this->debtId = $debt->id;
        $this->fillRates($debt);
        $this->fillFees($debt);
    }

    public function toggleConditions(): void
    {
        if ($this->editingConditions) {
            $this->closeConditions();

            return;
        }

        $debt = $this->debt();
        $this->fillRates($debt);
        $this->fillFees($debt);
        $this->resetErrorBag();
        $this->editingConditions = true;
    }

    public function cancelConditions(): void
    {
        $this->closeConditions();
    }

    public function saveConditions(DebtBook $book): void
    {
        $debt = $this->debt();
        $fees = $this->feeChange($debt);
        $rates = $this->rateChange($debt);

        $status = [];

        if ($fees['changed']) {
            $feeResult = $book->changeFees($debt, $fees['monthly'], $fees['issuance']);
            $debt->refresh();
            FamilyActivity::tell($debt->household, auth()->user()->name.__(': платы ').$debt->counterparty.__(' обновлены'));
            $status[] = $feeResult === 'last'
                ? __('Платы сохранены. Это последний платёж, его сумма не меняется.')
                : __('Платы сохранены. Ближайший платёж остаётся, со следующего месяца плата новая.');
        }

        $result = $book->changeRate($debt->fresh(), $rates['fixed'] + $rates['euribor'], $rates['fixed'], $rates['euribor']);
        $debt->refresh();
        $rateStatus = $this->rateStatus($debt, $result);

        if ($rateStatus !== null && $result !== 'same') {
            $status[] = $rateStatus;
        }

        if ($status !== []) {
            session()->flash('status', implode(' ', $status));
        }

        $this->closeConditions();
    }

    public function toggleBalance(): void
    {
        $debt = $this->debt();

        if (! $debt->usesDailyInterest()) {
            return;
        }

        if ($this->editingBalance) {
            $this->editingBalance = false;
            $this->balanceTotal = '';
            $this->resetErrorBag('balanceTotal');

            return;
        }

        $this->balanceTotal = Money::toInput(app(DailyInterest::class)->position($debt)['total']);
        $this->resetErrorBag('balanceTotal');
        $this->editingBalance = true;
    }

    public function saveBalance(DebtBook $book): void
    {
        $debt = $this->debt();

        if (! $debt->usesDailyInterest()) {
            return;
        }

        $cents = Money::toCents($this->balanceTotal);

        if ($cents === null || $cents < 0) {
            throw ValidationException::withMessages([
                'balanceTotal' => __('Сумма в евро, до двух знаков после запятой.'),
            ]);
        }

        $today = CarbonImmutable::now($debt->household->timezone ?: 'Europe/Riga')->startOfDay();
        $book->adjustDailyTotal($debt, $cents, $today);
        $debt->refresh();
        FamilyActivity::tell($debt->household, auth()->user()->name.__(': остаток ').$debt->counterparty.__(' теперь ').money($cents));
        session()->flash('status', __('Остаток обновлён: ').money($cents).'.');
        $this->editingBalance = false;
        $this->balanceTotal = '';
    }

    public function saveRate(DebtBook $book): void
    {
        $debt = $this->debt();
        $rates = $this->rateChange($debt);

        if (! $rates['changed']) {
            $this->fillRates($debt);

            return;
        }

        $result = $book->changeRate($debt, $rates['fixed'] + $rates['euribor'], $rates['fixed'], $rates['euribor']);
        $debt->refresh();
        $this->fillRates($debt);
        $status = $this->rateStatus($debt, $result);

        if ($status !== null) {
            session()->flash('status', $status);
        }
    }

    public function saveFees(DebtBook $book): void
    {
        $debt = $this->debt();
        $fees = $this->feeChange($debt);

        if (! $fees['changed']) {
            $this->fillFees($debt);

            return;
        }

        $feeResult = $book->changeFees($debt, $fees['monthly'], $fees['issuance']);
        $debt->refresh();
        $this->fillFees($debt);
        FamilyActivity::tell($debt->household, auth()->user()->name.__(': платы ').$debt->counterparty.__(' обновлены'));
        session()->flash('status', $feeResult === 'last'
            ? __('Платы сохранены. Это последний платёж, его сумма не меняется.')
            : __('Платы сохранены. Ближайший платёж остаётся, со следующего месяца плата новая.'));
    }

    #[On('family-pulse')]
    public function refreshFromFamily(): void {}

    public function render()
    {
        $debt = $this->debt()->load(['installments', 'household']);
        $next = $debt->installments->first(fn (DebtInstallment $row): bool => $row->paid_transaction_id === null && (int) $row->total_cents > 0);
        $daily = $debt->usesDailyInterest() ? app(DailyInterest::class) : null;

        return view('livewire.budget.debt-show', [
            'debt' => $debt,
            'next' => $next,
            'canChangeRate' => $this->canChangeRate($debt),
            'canEditFees' => $this->canEditFees($debt),
            'dailyPosition' => $daily?->position($debt),
            'forecast' => $daily?->forecast($debt) ?? [],
        ])->title($debt->counterparty.' — Family Money');
    }

    public function rateHint(): ?string
    {
        if (trim($this->rateFixed) === '') {
            return null;
        }

        $debt = $this->debt();

        if ($debt->usesDailyInterest()) {
            $fixed = Debt::milliFromInput($this->rateFixed);
            $euribor = trim($this->rateEuribor) === '' ? 0 : Debt::milliFromInput($this->rateEuribor);

            if ($fixed === null || $euribor === null || ($fixed === (int) $debt->fixed_rate_bps && $euribor === (int) $debt->euribor_rate_bps)) {
                return null;
            }

            return __('Дальше проценты капают по этой ставке. Сумма платежа в плане не меняется.');
        }

        $fixed = Debt::milliFromInput($this->rateFixed);
        $euribor = trim($this->rateEuribor) === '' ? 0 : Debt::milliFromInput($this->rateEuribor);

        if ($fixed === null || $euribor === null) {
            return null;
        }

        if ($fixed + $euribor === (int) $debt->annual_rate_bps) {
            return null;
        }

        $first = app(DebtBook::class)->pricedTail($debt, $fixed + $euribor)[0] ?? null;

        if ($first === null) {
            return null;
        }

        $payment = money($first['total_cents']);

        $ending = __(' Дата окончания остаётся.');

        return $first['due_on']
            ? __('С ближайшего платежа, ').ru_pay_date($first['due_on']).__(', платёж ').$payment.'.'.$ending
            : __('С ближайшего платежа ').$payment.'.'.$ending;
    }

    private function closeConditions(): void
    {
        $debt = $this->debt();
        $this->fillRates($debt);
        $this->fillFees($debt);
        $this->resetErrorBag();
        $this->editingConditions = false;
    }

    /**
     * @return array{changed: bool, monthly: int, issuance: int}
     */
    private function feeChange(Debt $debt): array
    {
        if (! $this->canEditFees($debt)) {
            return [
                'changed' => false,
                'monthly' => (int) $debt->monthly_fee_cents,
                'issuance' => (int) $debt->issuance_fee_cents,
            ];
        }

        $monthly = $this->optionalCents($this->feeMonthly, 'feeMonthly');
        $issuance = $this->optionalCents($this->feeIssuance, 'feeIssuance');

        return [
            'changed' => $monthly !== (int) $debt->monthly_fee_cents || $issuance !== (int) $debt->issuance_fee_cents,
            'monthly' => $monthly,
            'issuance' => $issuance,
        ];
    }

    /**
     * @return array{changed: bool, fixed: int, euribor: int}
     */
    private function rateChange(Debt $debt): array
    {
        if (! $this->canChangeRate($debt)) {
            return [
                'changed' => false,
                'fixed' => (int) $debt->fixed_rate_bps,
                'euribor' => (int) $debt->euribor_rate_bps,
            ];
        }

        $fixed = Debt::milliFromInput($this->rateFixed);
        $euribor = trim($this->rateEuribor) === '' ? 0 : Debt::milliFromInput($this->rateEuribor);

        if ($fixed === null) {
            throw ValidationException::withMessages([
                'rateFixed' => __('Укажите ставку, например 6,50.'),
            ]);
        }

        if ($euribor === null) {
            throw ValidationException::withMessages([
                'rateEuribor' => __('EURIBOR выглядит неверно.'),
            ]);
        }

        return [
            'changed' => $fixed !== (int) $debt->fixed_rate_bps || $euribor !== (int) $debt->euribor_rate_bps,
            'fixed' => $fixed,
            'euribor' => $euribor,
        ];
    }

    private function rateStatus(Debt $debt, string $result): ?string
    {
        if ($result === 'paid') {
            return __('Этот долг уже выплачен.');
        }

        if ($result === 'same') {
            return __('Ставка сохранена.');
        }

        if ($result === 'rebuilt') {
            return __('График пересчитан с ближайшего платежа. Дата окончания та же.');
        }

        if ($result === 'daily') {
            FamilyActivity::tell($debt->household, auth()->user()->name.__(': ставка ').$debt->counterparty.__(' теперь ').$debt->rateLabel());

            return __('Ставка сохранена. Дальше проценты капают по новой ставке.');
        }

        FamilyActivity::tell($debt->household, auth()->user()->name.__(': ставка ').$debt->counterparty.__(' теперь ').$debt->rateLabel());

        return $result === 'last'
            ? __('Ставка сохранена. Это последний платёж, его сумма не меняется.')
            : __('Ставка сохранена. Ближайший платёж пересчитан по новой ставке, дата окончания та же.');
    }

    private function canEditFees(Debt $debt): bool
    {
        $debt->loadMissing('installments');

        return $debt->installments->contains(fn (DebtInstallment $row): bool => $row->paid_transaction_id === null && (int) $row->total_cents > 0);
    }

    private function canChangeRate(Debt $debt): bool
    {
        $debt->loadMissing('installments');

        return $debt->installments->filter(fn (DebtInstallment $row): bool => $row->paid_transaction_id === null && (int) $row->total_cents > 0)->count() > 1;
    }

    private function fillRates(Debt $debt): void
    {
        $this->rateFixed = Debt::formatMilli((int) $debt->fixed_rate_bps);
        $this->rateEuribor = Debt::formatMilli((int) $debt->euribor_rate_bps);
    }

    private function fillFees(Debt $debt): void
    {
        $this->feeMonthly = $this->moneyInput((int) $debt->monthly_fee_cents);
        $this->feeIssuance = $this->moneyInput((int) $debt->issuance_fee_cents);
    }

    private function moneyInput(int $cents): string
    {
        if ($cents <= 0) {
            return '';
        }

        return number_format($cents / 100, 2, ',', '');
    }

    private function optionalCents(string $raw, string $field): int
    {
        if (trim($raw) === '') {
            return 0;
        }

        $cents = Money::toCents($raw);

        if ($cents === null || $cents < 0) {
            throw ValidationException::withMessages([
                $field => __('Сумма в евро, до двух знаков после запятой.'),
            ]);
        }

        return $cents;
    }

    private function debt(): Debt
    {
        return Debt::query()
            ->where('household_id', $this->household()->id)
            ->findOrFail($this->debtId);
    }

    private function household(): Household
    {
        return auth()->user()->currentHousehold();
    }

}
