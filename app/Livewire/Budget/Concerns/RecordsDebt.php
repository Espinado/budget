<?php

namespace App\Livewire\Budget\Concerns;

use App\Enums\AccountRole;
use App\Enums\DebtDirection;
use App\Enums\DebtKind;
use App\Models\Account;
use App\Models\Debt;
use App\Services\Budget\DebtBook;
use App\Services\Budget\FamilyActivity;
use App\Services\Budget\PeriodGenerator;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

trait RecordsDebt
{
    public bool $showNew = false;

    public string $newKind = 'personal';

    public string $newDirection = 'lend';

    public string $newOrigin = 'new';

    public string $newProduct = 'loan';

    public string $newMode = 'lump';

    public string $newAmount = '';

    public string $newMonths = '12';

    public string $newDate = '';

    public string $newEnd = '';

    public string $newInterest = 'none';

    public string $newFixed = '';

    public string $newEuribor = '';

    public string $newTenor = '3';

    public string $newReset = '';

    public string $newResidual = '';

    public string $newCounterparty = '';

    public string $newContract = '';

    public string $newReference = '';

    public string $newMonthlyFee = '';

    public string $newIssuanceFee = '';

    public ?int $newAccountId = null;

    public function openNew(): void
    {
        $this->showNew = true;
        $this->newKind = 'personal';
        $this->newDirection = 'lend';
        $this->newMode = 'lump';
    }

    public function choosePersonal(): void
    {
        $this->newKind = 'personal';
        $this->newDirection = 'lend';
        $this->newMode = 'lump';
        $this->newInterest = 'none';
        $this->newOrigin = 'new';
        $this->newProduct = 'loan';
    }

    public function chooseBank(): void
    {
        $this->newKind = 'bank';
        $this->newDirection = 'borrow';
        $this->newMode = 'parts';
        $this->newAccountId = null;
    }

    public function chooseBorrow(): void
    {
        $this->newDirection = 'borrow';
        $this->newMode = $this->newKind === 'bank' ? 'parts' : 'lump';
        $this->newAccountId = null;
    }

    public function chooseLend(): void
    {
        $this->newKind = 'personal';
        $this->newDirection = 'lend';
        $this->newMode = 'lump';
    }

    public function closeNew(): void
    {
        $this->showNew = false;
        $this->reset('newKind', 'newDirection', 'newOrigin', 'newProduct', 'newMode', 'newAmount', 'newMonths', 'newDate', 'newEnd', 'newInterest', 'newFixed', 'newEuribor', 'newTenor', 'newReset', 'newResidual', 'newCounterparty', 'newContract', 'newReference', 'newMonthlyFee', 'newIssuanceFee', 'newAccountId');
        $this->newKind = 'personal';
        $this->newDirection = 'lend';
        $this->newMode = 'lump';
        $this->resetErrorBag();
    }

    public function saveNew(DebtBook $book): void
    {
        $principal = Money::toCents($this->newAmount);
        $fixed = $this->newInterest === 'none' ? 0 : Debt::milliFromInput($this->newFixed);
        $euribor = $this->newInterest === 'mixed' ? Debt::milliFromInput($this->newEuribor) : 0;
        $due = $this->dueDate();
        $counterparty = trim($this->newCounterparty);
        $kind = $this->newKind === 'personal' ? DebtKind::Personal : DebtKind::Bank;

        if ($kind === DebtKind::Bank) {
            $this->newDirection = 'borrow';
        }

        $running = $this->newDirection === 'borrow' && $kind === DebtKind::Bank && $this->newOrigin === 'running';
        $months = $running ? ($this->paymentCount($due, $this->parseDate($this->newEnd)) ?? 0) : (int) $this->newMonths;

        if ($principal === null || $principal <= 0) {
            throw ValidationException::withMessages(['newAmount' => __('Укажите сумму долга.')]);
        }

        if ($counterparty === '') {
            throw ValidationException::withMessages(['newCounterparty' => __('Укажите контрагента.')]);
        }

        if (! in_array($this->newDirection, ['borrow', 'lend'], true) || ! in_array($this->newMode, ['lump', 'parts'], true)) {
            throw ValidationException::withMessages(['newDirection' => __('Выберите вид долга.')]);
        }

        if (! in_array($this->newKind, ['personal', 'bank'], true)) {
            throw ValidationException::withMessages(['newKind' => __('Выберите личный долг или кредит.')]);
        }

        if ($running) {
            $this->newMode = 'parts';
        }

        if ($this->newMode === 'parts' && ($months < 1 || $months > 600)) {
            $field = $running ? 'newEnd' : 'newMonths';
            $message = $running
                ? __('Укажите дату окончания из банка. От неё считается число платежей, не больше 600.')
                : __('Укажите срок от 1 до 600 месяцев.');

            throw ValidationException::withMessages([$field => $message]);
        }

        if ($kind === DebtKind::Bank && $this->newInterest !== 'none' && $fixed === null) {
            throw ValidationException::withMessages(['newFixed' => __('Укажите ставку, например 2,38.')]);
        }

        if ($kind === DebtKind::Bank && $this->newInterest === 'mixed' && $euribor === null) {
            throw ValidationException::withMessages(['newEuribor' => __('Укажите EURIBOR, например 2,477.')]);
        }

        if ($kind === DebtKind::Bank && $this->newInterest === 'mixed' && ! in_array((int) $this->newTenor, [3, 6, 12], true)) {
            throw ValidationException::withMessages(['newTenor' => __('Выберите срок EURIBOR: 3, 6 или 12 месяцев.')]);
        }

        if (trim($this->newDate) !== '' && $due === null) {
            throw ValidationException::withMessages(['newDate' => __('Дата выглядит неверно.')]);
        }

        if ($due !== null && $due->lt($this->currentStart())) {
            throw ValidationException::withMessages(['newDate' => __('Дата в прошлом месяце недоступна.')]);
        }

        if ($this->newMode === 'lump' && $this->newInterest !== 'none' && $due === null) {
            throw ValidationException::withMessages(['newDate' => __('Для процентов укажите дату полного возврата.')]);
        }

        $monthlyFee = $kind === DebtKind::Bank ? $this->optionalCents($this->newMonthlyFee, 'newMonthlyFee') : 0;
        $issuanceFee = $kind === DebtKind::Bank ? $this->optionalCents($this->newIssuanceFee, 'newIssuanceFee') : 0;
        $residual = $kind === DebtKind::Bank && $this->newProduct === 'leasing'
            ? $this->optionalCents($this->newResidual, 'newResidual')
            : 0;

        if ($residual >= $principal) {
            throw ValidationException::withMessages(['newResidual' => __('Выкуп должен быть меньше суммы долга.')]);
        }

        $fromAccount = null;

        if ($this->newDirection === 'lend') {
            $fromAccount = $this->requireOperatingAccount($this->newAccountId, 'lend');
        }

        if ($kind === DebtKind::Personal && $this->newDirection === 'borrow') {
            $fromAccount = $this->requireOperatingAccount($this->newAccountId, 'borrow');
        }

        $tenor = $kind === DebtKind::Bank && $this->newInterest === 'mixed' ? (int) $this->newTenor : null;
        $reset = $tenor === null ? null : ($this->parseDate($this->newReset) ?? $this->defaultReset($due, $tenor, $running));
        $direction = $this->newDirection === 'lend' ? DebtDirection::Lend : DebtDirection::Borrow;
        $from = CarbonImmutable::now('Europe/Riga')->startOfDay();
        $debt = $book->open(
            $this->household(),
            $direction,
            $counterparty,
            $this->newContract,
            $this->newReference,
            $principal,
            $this->newMode,
            $this->newMode === 'parts' ? $months : 1,
            $due,
            (int) $fixed,
            (int) $euribor,
            $from,
            $monthlyFee,
            $issuanceFee,
            $residual,
            $tenor,
            $reset,
            $kind,
            $fromAccount,
            auth()->id(),
        );

        $this->afterDebtRecorded($debt);

        FamilyActivity::tell($debt->household, auth()->user()->name.__(': новый долг ').$debt->counterparty.', '.$debt->kind->label());
        $this->closeNew();
        session()->flash('status', match (true) {
            $direction === DebtDirection::Lend => __('Долг записан. Сумма списана с расчётного счёта.'),
            $kind === DebtKind::Personal && $direction === DebtDirection::Borrow => __('Долг записан. Сумма зачислена на расчётный счёт.'),
            $due === null => __('Долг записан. Даты нет, поэтому в план он не попал.'),
            default => __('Долг записан. Возврат стоит в плане.'),
        });
    }

    /**
     * @return array{text: string, plan: string}|null
     */
    public function preview(): ?array
    {
        $principal = Money::toCents($this->newAmount);

        if ($principal === null || $principal <= 0) {
            return null;
        }

        $fixed = $this->newInterest === 'none' ? 0 : Debt::milliFromInput($this->newFixed);
        $euribor = $this->newInterest === 'mixed' ? (Debt::milliFromInput($this->newEuribor) ?? 0) : 0;

        if ($this->newKind === 'bank' && $this->newInterest !== 'none' && $fixed === null) {
            return null;
        }

        $due = $this->dueDate();
        $running = $this->newKind === 'bank' && $this->newDirection === 'borrow' && $this->newOrigin === 'running';
        $months = $running ? $this->paymentCount($due, $this->parseDate($this->newEnd)) : max(1, (int) $this->newMonths);

        if ($running && $months === null) {
            return [
                'text' => __('Укажите дату следующего платежа и дату окончания из банка.'),
                'plan' => '',
            ];
        }

        if ($this->newMode === 'lump' && $this->newInterest !== 'none' && $due === null) {
            return [
                'text' => __('Укажите дату полного возврата, чтобы посчитать проценты.'),
                'plan' => '',
            ];
        }

        $residual = $this->newKind === 'bank' && $this->newProduct === 'leasing'
            ? ($this->optionalCents($this->newResidual, 'newResidual', false) ?? 0)
            : 0;
        $rows = app(DebtBook::class)->draft(
            $principal,
            ($running || $this->newMode === 'parts') ? 'parts' : 'lump',
            (int) $months,
            $due,
            (int) $fixed + (int) $euribor,
            CarbonImmutable::now('Europe/Riga')->startOfDay(),
            $this->newKind === 'bank' ? ($this->optionalCents($this->newMonthlyFee, 'newMonthlyFee', false) ?? 0) : 0,
            $this->newKind === 'bank' ? ($this->optionalCents($this->newIssuanceFee, 'newIssuanceFee', false) ?? 0) : 0,
            $residual,
        );

        if ($rows === []) {
            return null;
        }

        $first = $rows[0];
        $last = $rows[array_key_last($rows)];
        $total = array_sum(array_column($rows, 'total_cents'));

        $parts = $running || $this->newMode === 'parts';

        if ($this->newDirection === 'lend') {
            $text = __('Выдаём ').money($principal).__(' с расчётного счёта.');
            $text .= ' '.__('К возврату ').money($first['total_cents']);
            $text .= $first['interest_cents'] > 0 ? __(', из них проценты ').money($first['interest_cents']).'.' : '.';
        } elseif ($this->newKind === 'personal' && $this->newDirection === 'borrow' && ! $parts) {
            $text = __('Зачисляем ').money($principal).__(' на расчётный счёт.');
            $text .= ' '.__('К возврату ').money($first['total_cents']);
            $text .= $first['interest_cents'] > 0 ? __(', из них проценты ').money($first['interest_cents']).'.' : '.';
        } elseif ($this->newKind === 'personal' && $this->newDirection === 'borrow' && $parts) {
            $text = __('Зачисляем ').money($principal).__(' на расчётный счёт.');
            $text .= ' '.__('Платёж ').money($first['total_cents']);
            $text .= $last['total_cents'] === $first['total_cents'] ? '.' : __(', последний ').money($last['total_cents']).'.';
            $text .= __(' Всего ').money($total).'.';
        } elseif (! $parts) {
            $text = __('К возврату ').money($first['total_cents']);
            $text .= $first['interest_cents'] > 0 ? __(', из них проценты ').money($first['interest_cents']).'.' : '.';
        } else {
            $text = __('Платёж ').money($first['total_cents']);
            $text .= $last['total_cents'] === $first['total_cents'] ? '.' : __(', последний ').money($last['total_cents']).'.';
            $text .= __(' Всего ').money($total).'.';

            if ($running) {
                $text .= __(' Сверьте платёж с банком.');
            }
        }

        $notes = [];

        if ((int) $first['other_cents'] > 0) {
            $notes[] = __('плата за выдачу ').money((int) $first['other_cents']);
        }

        if ((int) $first['fee_cents'] > 0) {
            $notes[] = __('ежемесячная плата ').money((int) $first['fee_cents']);
        }

        if ($notes !== []) {
            $text .= __(' В том числе ').implode(', ', $notes).'.';
        }

        $mode = $running || $this->newMode === 'parts' ? 'parts' : 'lump';
        $plan = $due === null
            ? __('В план не попадёт, пока нет даты.')
            : ($mode === 'lump'
                ? __('В план ').ru_pay_date($due).'.'
                : ($running
                    ? count($rows).' '.$this->paymentWord(count($rows)).__(', последний ').ru_pay_date($last['due_on']).'.'
                    : __('Первый платёж ').ru_pay_date($due).__(', дальше в тот же день каждого месяца. Последний ').ru_pay_date($last['due_on']).'.'));

        return ['text' => $text, 'plan' => $plan];
    }

    protected function afterDebtRecorded(Debt $debt): void {}

    private function requireOperatingAccount(mixed $accountId, string $purpose = 'lend'): Account
    {
        $account = Account::query()
            ->where('household_id', $this->household()->id)
            ->where('role', AccountRole::Operating)
            ->find($accountId);

        if (! $account instanceof Account) {
            throw ValidationException::withMessages([
                'newAccountId' => $purpose === 'borrow'
                    ? __('Выберите расчётный счёт, на который зачисляете долг.')
                    : __('Выберите расчётный счёт, с которого выдаёте долг.'),
            ]);
        }

        return $account;
    }

    private function dueDate(): ?CarbonImmutable
    {
        return $this->parseDate($this->newDate);
    }

    private function currentStart(): CarbonImmutable
    {
        return app(PeriodGenerator::class)
            ->periodFor($this->household(), CarbonImmutable::now('Europe/Riga'))
            ->starts_on
            ->toImmutable()
            ->startOfDay();
    }

    private function parseDate(string $raw): ?CarbonImmutable
    {
        $raw = trim($raw);

        if ($raw === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', $raw, 'Europe/Riga');

        return $date === false ? null : $date->startOfDay();
    }

    private function paymentCount(?CarbonImmutable $first, ?CarbonImmutable $end): ?int
    {
        if ($first === null || $end === null || $end->lt($first)) {
            return null;
        }

        $cursor = $first;
        $count = 0;

        while ($cursor->lte($end)) {
            $count++;

            if ($count > 600) {
                return null;
            }

            $cursor = $cursor->addMonthsNoOverflow(1);
        }

        return $count;
    }

    private function defaultReset(?CarbonImmutable $due, int $tenor, bool $running): ?CarbonImmutable
    {
        if ($due === null) {
            return null;
        }

        return $running ? $due : $due->addMonthsNoOverflow($tenor);
    }

    private function paymentWord(int $count): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return __('платёж');
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return __('платежа');
        }

        return __('платежей');
    }

    private function optionalCents(string $raw, string $field, bool $strict = true): ?int
    {
        if (trim($raw) === '') {
            return 0;
        }

        $cents = Money::toCents($raw);

        if ($cents === null || $cents < 0) {
            if (! $strict) {
                return null;
            }

            throw ValidationException::withMessages([
                $field => __('Сумма в евро, до двух знаков после запятой.'),
            ]);
        }

        return $cents;
    }
}
