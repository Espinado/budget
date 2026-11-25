<?php

namespace App\Livewire\Budget;

use App\Enums\AccountRole;
use App\Enums\DebtKind;
use App\Enums\CashflowType;
use App\Enums\DebtDirection;
use App\Models\Account;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Household;
use App\Models\PlanLine;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Services\Budget\DebtBook;
use App\Services\Budget\FamilyActivity;
use App\Services\Budget\PeriodCalendar;
use App\Services\Budget\PeriodGenerator;
use App\Services\Budget\PeriodSummary;
use App\Services\Budget\Solvency;
use App\Livewire\Concerns\NormalizesMoneyInput;
use App\Livewire\Concerns\PresentsDayFirstDates;
use App\Rules\AppDay;
use App\Support\AppDate;
use App\Support\CategoryIcons;
use App\Support\CategoryTree;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Dashboard extends Component
{
    use NormalizesMoneyInput;
    use PresentsDayFirstDates;
    use WithFileUploads;
    #[Url(as: 'period')]
    public ?int $periodId = null;

    public bool $showEntry = false;

    public bool $showPlan = false;

    public ?int $detailCategoryId = null;

    public ?int $detailLineId = null;

    public ?string $quickKind = null;

    public string $quickAmount = '';

    public ?int $quickCategoryId = null;

    public ?int $quickLineId = null;

    public string $quickDate = '';

    public string $quickNote = '';

    public ?int $quickAccountId = null;

    public ?TemporaryUploadedFile $quickReceipt = null;

    public ?TemporaryUploadedFile $operationReceipt = null;

    public ?int $dealsLineId = null;

    public ?int $payingLineId = null;

    public ?int $payAccountId = null;

    /** @var array<int, int> */
    public array $openCategoryIds = [];

    public function toggleCategory(int $categoryId): void
    {
        $this->closeQuick();
        $this->closeTransfer();
        $this->showPlan = false;
        $this->showEntry = false;

        $category = $this->household()->categories()->findOrFail($categoryId);
        $open = array_map('intval', $this->openCategoryIds);
        $wasOpen = in_array((int) $categoryId, $open, true);

        $this->dealsLineId = null;
        $this->cancelOperation();
        $this->cancelLine();
        $this->cancelPay();
        $this->resetEditTransaction();

        if ($wasOpen) {
            $hide = array_merge(
                [(int) $categoryId],
                CategoryTree::descendantIds($this->household()->categories()->get(), (int) $categoryId),
            );
            $this->openCategoryIds = array_values(array_filter(
                $open,
                fn (int $id): bool => ! in_array($id, $hide, true),
            ));

            return;
        }

        $this->openCategoryIds = $this->categoryPath($category);
    }

    public function closeAccordions(): void
    {
        $this->openCategoryIds = [];
        $this->dealsLineId = null;
        $this->cancelOperation();
        $this->cancelLine();
        $this->cancelPay();
        $this->resetEditTransaction();
    }

    public function toggleDeals(int $lineId): void
    {
        if ($this->dealsLineId === $lineId) {
            $this->dealsLineId = null;
            $this->resetEditTransaction();

            return;
        }

        $this->detailCategoryId = null;
        $this->detailLineId = null;
        $this->cancelLine();
        $this->closeQuick();
        $this->closeTransfer();
        $this->showPlan = false;
        $this->showEntry = false;

        $line = $this->ownedLine($lineId);

        if (! $this->planLineHasEntries($line)) {
            $this->dealsLineId = null;

            return;
        }

        $this->dealsLineId = $lineId;
        $this->resetEditTransaction();
    }

    public function categoryOpen(int $categoryId): bool
    {
        return in_array($categoryId, array_map('intval', $this->openCategoryIds), true);
    }

    public function toggleDetail(int $lineId): void
    {
        if ($this->detailLineId === $lineId) {
            $this->cancelOperation();

            return;
        }

        $line = $this->ownedLine($lineId);

        $this->dealsLineId = null;
        $this->cancelLine();
        $this->cancelPay();
        $this->resetEditTransaction();
        $this->closeQuick();
        $this->closeTransfer();
        $this->showPlan = false;
        $this->showEntry = false;
        $this->detailLineId = $line->id;
        $this->detailCategoryId = $line->category_id;
        $this->prepareOperation($line);
    }

    public function openQuick(string $kind): void
    {
        if (! in_array($kind, ['income', 'expense'], true)) {
            return;
        }

        if ($this->quickKind === $kind) {
            $this->closeQuick();

            return;
        }

        $this->closeAccordions();
        $this->closeTransfer();
        $this->showPlan = false;
        $this->showEntry = false;
        $this->detailCategoryId = null;
        $this->detailLineId = null;
        $this->quickKind = $kind;
        $this->reset('quickAmount', 'quickCategoryId', 'quickLineId', 'quickNote', 'quickAccountId', 'quickReceipt');
        $this->quickDate = $this->today()->toDateString();
        $this->resetErrorBag();
    }

    public function closeQuick(): void
    {
        $this->quickKind = null;
        $this->reset('quickAmount', 'quickCategoryId', 'quickLineId', 'quickNote', 'quickAccountId', 'quickReceipt', 'quickDate');
        $this->resetErrorBag(['quickAmount', 'quickCategoryId', 'quickLineId', 'quickDate', 'quickNote', 'quickReceipt']);
    }

    public function openTransfer(): void
    {
        if ($this->showTransfer) {
            $this->closeTransfer();

            return;
        }

        $this->closeAccordions();
        $this->closeQuick();
        $this->showPlan = false;
        $this->showEntry = false;
        $this->showTransfer = true;
        $this->reset('transferFromId', 'transferToId', 'transferAmount', 'transferNote');
        [$this->transferFromId, $this->transferToId] = Account::transferPair(
            $this->household()->accounts()->orderBy('sort')->orderBy('id')->get(),
        );
        $this->transferDate = $this->today()->toDateString();
        $this->resetErrorBag();
    }

    public function closeTransfer(): void
    {
        $this->showTransfer = false;
        $this->reset('transferFromId', 'transferToId', 'transferAmount', 'transferNote', 'transferDate');
        $this->resetErrorBag(['transferFromId', 'transferToId', 'transferAmount', 'transferDate', 'transferNote']);
    }

    public function updatedQuickCategoryId(): void
    {
        $this->quickLineId = null;
    }

    public function addOperation(PeriodGenerator $periods): void
    {
        $this->operationAmount = Money::normalize($this->operationAmount);

        $this->validate([
            'operationAmount' => ['required', 'regex:/^\d+([.,]\d{1,2})?$/'],
            'operationDate' => ['required', new AppDay],
            'operationNote' => ['nullable', 'string', 'max:200'],
            'operationLineId' => ['required', 'integer', 'min:1'],
            'operationAccountId' => ['required', 'integer', 'min:1'],
            'operationReceipt' => ['nullable', 'image', 'max:8192'],
        ], [
            'operationAmount.required' => __('Укажите сумму этой операции.'),
            'operationAmount.regex' => __('Сумма в евро, до двух знаков после запятой.'),
            'operationDate.required' => __('Укажите дату.'),
            'operationLineId.required' => __('Выберите строку плана.'),
            'operationLineId.min' => __('Выберите строку плана.'),
            'operationAccountId.required' => __('Выберите счёт.'),
            'operationAccountId.min' => __('Выберите счёт.'),
            'operationReceipt.image' => __('Чек должен быть фотографией.'),
            'operationReceipt.max' => __('Фото чека больше 8 МБ.'),
        ]);

        $cents = Money::toCents($this->operationAmount);

        if ($cents === null || $cents <= 0) {
            throw ValidationException::withMessages([
                'operationAmount' => __('Сумма должна быть больше нуля.'),
            ]);
        }

        $this->refuseFactOutsideCurrent($this->typedDay($this->operationDate, 'operationDate'), 'operationDate', $periods);

        $line = $this->ownedLine((int) $this->operationLineId)->load('category');
        $account = $this->requireAccount($this->operationAccountId, 'operationAccountId');

        if (Solvency::tracksCreditPlan($line->category) && $account->role !== AccountRole::Operating) {
            throw ValidationException::withMessages([
                'operationAccountId' => __('Выберите расчётный счёт.'),
            ]);
        }

        $transaction = $periods->record(
            $this->household(),
            $line->category,
            $line->category->type,
            $cents,
            $this->typedDay($this->operationDate, 'operationDate'),
            $this->operationNote !== '' ? $this->operationNote : null,
            $line,
            auth()->id(),
            $account,
        );
        $this->storeReceipt($transaction, $this->operationReceipt);

        $this->detailCategoryId = null;
        $this->detailLineId = null;
        $this->dealsLineId = $line->id;
        $this->reset('operationAmount', 'operationNote', 'operationReceipt');
        $this->tellFamily(__('новый ').$this->factPhrase($line->category->type, $cents, $line->category->name));
    }

    public function cancelPay(): void
    {
        $this->payingLineId = null;
        $this->payAccountId = null;
    }

    public function payLine(int $lineId, PeriodGenerator $periods): void
    {
        $this->resetErrorBag();
        $line = $this->ownedLine($lineId)->load(['category.parent', 'account', 'period']);
        $error = 'pay.'.$line->id;

        if ($line->category->type !== CashflowType::Expense || ! Solvency::tracksCreditPlan($line->category)) {
            throw ValidationException::withMessages([
                $error => __('Оплатить целиком можно строку кредитной категории.'),
            ]);
        }

        if ($line->transactions()->exists()) {
            throw ValidationException::withMessages([
                $error => __('По этой строке уже есть оплата.'),
            ]);
        }

        $cents = (int) $line->amount_cents;

        if ($cents <= 0) {
            throw ValidationException::withMessages([
                $error => __('У строки нет суммы.'),
            ]);
        }

        $date = $this->paymentDate($line, $periods);
        $this->refuseFactOutsideCurrent($date, $error, $periods);

        if ((int) $this->payingLineId !== (int) $line->id) {
            $this->detailCategoryId = null;
            $this->detailLineId = null;
            $this->payingLineId = $line->id;
            $this->payAccountId = null;

            return;
        }

        $account = Account::query()
            ->where('household_id', $this->household()->id)
            ->find($this->payAccountId);

        if (! $account instanceof Account || $account->role !== AccountRole::Operating) {
            throw ValidationException::withMessages([
                $error => __('Выберите расчётный счёт'),
            ]);
        }

        $periods->record(
            $this->household(),
            $line->category,
            CashflowType::Expense,
            $cents,
            $date,
            null,
            $line,
            auth()->id(),
            $account,
        );

        $this->detailCategoryId = null;
        $this->detailLineId = null;
        $this->cancelPay();
        $this->dealsLineId = $line->id;
        session()->flash('status', __('Оплачено полностью: ').money($cents).'.');
        $this->tellFamily(__('оплата ').money($cents).', '.$line->title);
    }

    public function saveQuick(PeriodGenerator $periods): void
    {
        $this->quickAmount = Money::normalize($this->quickAmount);

        $this->validate([
            'quickKind' => ['required', 'in:income,expense'],
            'quickAmount' => ['required', 'regex:/^\d+([.,]\d{1,2})?$/'],
            'quickCategoryId' => ['required', 'integer', 'min:1'],
            'quickAccountId' => ['required', 'integer', 'min:1'],
            'quickDate' => ['required', new AppDay],
            'quickNote' => ['nullable', 'string', 'max:200'],
            'quickReceipt' => ['nullable', 'image', 'max:8192'],
        ], [
            'quickAmount.required' => __('Укажите сумму.'),
            'quickAmount.regex' => __('Сумма в евро, до двух знаков после запятой.'),
            'quickCategoryId.required' => __('Выберите категорию.'),
            'quickCategoryId.min' => __('Выберите категорию.'),
            'quickAccountId.required' => __('Выберите счёт.'),
            'quickAccountId.min' => __('Выберите счёт.'),
            'quickDate.required' => __('Укажите дату.'),
            'quickReceipt.image' => __('Чек должен быть фотографией.'),
            'quickReceipt.max' => __('Фото чека больше 8 МБ.'),
        ]);

        $cents = Money::toCents($this->quickAmount);

        if ($cents === null || $cents <= 0) {
            throw ValidationException::withMessages([
                'quickAmount' => __('Сумма должна быть больше нуля.'),
            ]);
        }

        $category = Category::query()
            ->where('household_id', $this->household()->id)
            ->where('type', $this->quickKind)
            ->whereNull('archived_at')
            ->find($this->quickCategoryId);

        if ($category === null) {
            throw ValidationException::withMessages([
                'quickCategoryId' => __('Выберите категорию.'),
            ]);
        }

        $date = $this->typedDay($this->quickDate, 'quickDate');
        $this->refuseFactOutsideCurrent($date, 'quickDate', $periods);
        $period = $periods->periodFor($this->household(), $date);
        $account = $this->requireAccount($this->quickAccountId, 'quickAccountId');
        $lines = $period->planLines()
            ->where('category_id', $category->id)
            ->orderByDesc('amount_cents')
            ->orderBy('id')
            ->get();
        $line = null;

        if ($lines->count() > 1) {
            $line = $lines->firstWhere('id', (int) $this->quickLineId);

            if ($line === null) {
                throw ValidationException::withMessages([
                    'quickLineId' => __('Выберите пункт плана.'),
                ]);
            }
        } elseif ($lines->count() === 1) {
            $line = $lines->first();
        } else {
            $line = $periods->resolvePlanLine($period, $category, $account);
        }

        $transaction = $periods->record(
            $this->household(),
            $category,
            $category->type,
            $cents,
            $date,
            $this->quickNote !== '' ? $this->quickNote : null,
            $line,
            auth()->id(),
            $account,
        );
        $this->storeReceipt($transaction, $this->quickReceipt);

        $this->revealParent($category);
        $this->dealsLineId = $line?->id;
        $this->closeQuick();
        session()->flash('status', __('Операция записана.'));
        $this->tellFamily(__('новый ').$this->factPhrase($category->type, $cents, $category->name));
    }

    public function cancelOperation(): void
    {
        $this->detailCategoryId = null;
        $this->detailLineId = null;
        $this->reset('operationAmount', 'operationNote', 'operationReceipt');
    }

    private function quickLines(): Collection
    {
        if ($this->quickCategoryId === null || $this->quickDate === '') {
            return collect();
        }

        $quickDay = AppDate::parse($this->quickDate);

        if ($quickDay === null) {
            return collect();
        }

        $period = app(PeriodGenerator::class)->periodFor($this->household(), $quickDay);

        return $period->planLines()
            ->with('installment')
            ->where('category_id', $this->quickCategoryId)
            ->orderBy('id')
            ->get();
    }

    private function storeReceipt(Transaction $transaction, ?TemporaryUploadedFile $receipt): void
    {
        if ($receipt === null) {
            return;
        }

        $transaction->update([
            'receipt_path' => $receipt->store('receipts', 'public'),
        ]);
    }

    public string $entryType = 'expense';

    public string $entryAmount = '';

    public ?int $entryCategoryId = null;

    public string $entryDate = '';

    public string $entryNote = '';

    public ?int $entryPlanLineId = null;

    public ?int $entryAccountId = null;

    public string $operationAmount = '';

    public string $operationDate = '';

    public ?int $transferFromId = null;

    public ?int $transferToId = null;

    public string $transferAmount = '';

    public string $transferDate = '';

    public string $transferNote = '';

    public bool $showTransfer = false;

    public function transferFunds(): void
    {
        $this->transferAmount = Money::normalize($this->transferAmount);

        $this->validate([
            'transferFromId' => ['required', 'integer'],
            'transferToId' => ['required', 'integer'],
            'transferAmount' => ['required', 'regex:/^\d+([.,]\d{1,2})?$/'],
            'transferDate' => ['required', new AppDay],
            'transferNote' => ['nullable', 'string', 'max:200'],
        ], [
            'transferFromId.required' => __('Выберите счёт, откуда перевести.'),
            'transferToId.required' => __('Выберите счёт, куда перевести.'),
            'transferAmount.required' => __('Укажите сумму перевода.'),
            'transferAmount.regex' => __('Сумма в евро, до двух знаков после запятой.'),
            'transferDate.required' => __('Укажите дату.'),
        ]);

        if ((int) $this->transferFromId === (int) $this->transferToId) {
            throw ValidationException::withMessages([
                'transferToId' => __('Выберите разные счета.'),
            ]);
        }

        $cents = Money::toCents($this->transferAmount);

        if ($cents === null || $cents <= 0) {
            throw ValidationException::withMessages([
                'transferAmount' => __('Сумма должна быть больше нуля.'),
            ]);
        }

        $transferDay = $this->typedDay($this->transferDate, 'transferDate');
        $this->refuseFactOutsideCurrent($transferDay, 'transferDate', app(PeriodGenerator::class));

        $from = $this->ownedAccount((int) $this->transferFromId);
        $to = $this->ownedAccount((int) $this->transferToId);

        Transfer::query()->create([
            'household_id' => $this->household()->id,
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount_cents' => $cents,
            'occurred_on' => $transferDay->toDateString(),
            'note' => $this->transferNote !== '' ? $this->transferNote : null,
            'created_by' => auth()->id(),
        ]);

        $from->decrement('balance_cents', $cents);
        $to->increment('balance_cents', $cents);

        $this->closeTransfer();
        session()->flash('status', __('Перевод записан.'));
        $this->tellFamily(__('перевод ').money($cents).__(' с ').$from->name.__(' на ').$to->name);
    }

    public string $operationNote = '';

    public ?int $operationAccountId = null;

    public ?int $operationLineId = null;

    public string $newPlanCategoryName = '';

    public ?int $newPlanCategoryId = null;

    public string $newPlanType = 'expense';

    public string $newPlanTitle = '';

    public string $newPlanAmount = '';

    public string $newPlanDate = '';

    public string $newPlanIcon = 'out';

    public ?int $incomeCategoryId = null;

    public ?int $expenseCategoryId = null;

    public string $quickCategoryName = '';

    public string $quickCategoryIcon = 'out';

    public ?int $quickParentId = null;

    public ?int $newPlanParentId = null;

    /** @var array<int|string, string> */
    public array $planAmounts = [];

    public ?int $editingTransactionId = null;

    public string $editType = 'expense';

    public string $editAmount = '';

    public string $editDate = '';

    public string $editNote = '';

    public string $planMonth = '';

    public ?int $editCategoryId = null;

    public ?int $editAccountId = null;

    public ?TemporaryUploadedFile $editReceipt = null;

    public ?TemporaryUploadedFile $editReceiptCamera = null;

    public ?int $editingLineId = null;

    public ?int $lineCategoryId = null;

    public string $lineDate = '';

    public string $lineAmount = '';

    public ?int $lineAccountId = null;

    private bool $savingPlan = false;

    public function mount(PeriodGenerator $periods): void
    {
        $household = $this->household();
        $period = $this->ownedPeriod($this->periodId);
        $period ??= $periods->periodFor($household, $this->today());
        $this->syncPeriod($period);
        $this->entryDate = $this->today()->toDateString();
        $this->operationDate = $this->entryDate;
        $this->transferDate = $this->entryDate;
    }

    #[On('family-pulse')]
    public function refreshFromFamily(): void {}

    public function previous(PeriodGenerator $periods): void
    {
        $this->syncPeriod($periods->periodBefore($this->period()));
    }

    public function next(PeriodGenerator $periods): void
    {
        $period = $this->period();
        $nextStart = CarbonImmutable::parse($period->ends_on)->addDay()->startOfDay();

        if (! app(DebtBook::class)->canOpen($period->household, $nextStart)) {
            return;
        }

        $this->syncPeriod($periods->periodAfter($period));
    }

    public function copyToNextMonth(PeriodGenerator $periods): void
    {
        $period = $this->period();
        $nextStart = CarbonImmutable::parse($period->ends_on)->addDay()->startOfDay();

        if (! app(DebtBook::class)->canOpen($period->household, $nextStart)) {
            session()->flash('status', __('Дальше шести месяцев план не показываем.'));

            return;
        }

        if ($period->planLines()->doesntExist()) {
            session()->flash('status', __('В этом месяце нет строк плана.'));

            return;
        }

        $next = $periods->copyPlanOntoNext($period);
        $month = ru_month($next->starts_on);
        $this->syncPeriod($next);

        if ($periods->lastCopiedCount === 0) {
            session()->flash('status', __('Бюджет уже скопирован в ').$month.__('. Повторно строки не добавлялись.'));

            return;
        }

        session()->flash('status', __('План добавлен в ').$month.__('. Уже скопированные строки не повторялись.'));
        $this->tellFamily(__('план скопирован в ').$month);
    }

    public function updatedEntryType(): void
    {
        $this->entryCategoryId = null;
        $this->entryPlanLineId = null;
    }

    public function updatedEntryCategoryId(): void
    {
        $this->entryPlanLineId = null;
    }

    public function togglePlan(): void
    {
        if (! $this->showPlan && $this->periodHasEnded($this->period())) {
            session()->flash('status', __('Прошлый месяц уже закрыт. Менять его нельзя.'));

            return;
        }

        $this->closeAccordions();
        $this->closeQuick();
        $this->closeTransfer();
        $this->showEntry = false;
        $this->showPlan = ! $this->showPlan;

        if ($this->showPlan) {
            $this->js(<<<'JS'
                const reveal = () => {
                    const form = document.getElementById('plan-entry');

                    if (!form) {
                        return;
                    }

                    const rect = form.getBoundingClientRect();
                    const offscreen = rect.top < 8 || rect.bottom > window.innerHeight - 8;

                    if (offscreen) {
                        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }

                    form.querySelector('select')?.focus({ preventScroll: true });
                };

                requestAnimationFrame(() => requestAnimationFrame(reveal));
            JS);
        }
    }

    public function toggleFact(): void
    {
        $this->showPlan = false;
        $this->showEntry = ! $this->showEntry;

        if ($this->showEntry) {
            $this->reset('entryAmount', 'entryNote', 'entryPlanLineId', 'entryAccountId', 'entryCategoryId');
        }
    }

    public function openFact(int $lineId): void
    {
        $line = $this->ownedLine($lineId)->load('category');
        $this->showPlan = false;
        $this->showEntry = true;
        $this->entryType = $line->category->type->value;
        $this->entryCategoryId = $line->category_id;
        $this->entryPlanLineId = $line->id;
        $this->entryAccountId = $line->account_id;
        $this->entryAmount = '';
        $this->entryNote = '';
    }

    public function updatedPlanAmounts(mixed $value, string $key): void
    {
        if ($this->savingPlan) {
            return;
        }

        $this->savePlanAmount((int) $key);
    }

    public function saveTransaction(PeriodGenerator $periods): void
    {
        $this->validate([
            'entryType' => ['required', 'in:income,expense'],
            'entryAmount' => ['required', 'regex:/^\d+([.,]\d{1,2})?$/'],
            'entryCategoryId' => ['required', 'integer', 'min:1'],
            'entryAccountId' => ['required', 'integer', 'min:1'],
            'entryDate' => ['required', new AppDay],
            'entryNote' => ['nullable', 'string', 'max:200'],
        ], [
            'entryAmount.required' => __('Укажите сумму.'),
            'entryAmount.regex' => __('Сумма в евро, до двух знаков после запятой.'),
            'entryCategoryId.required' => __('Выберите категорию.'),
            'entryCategoryId.min' => __('Выберите категорию.'),
            'entryAccountId.required' => __('Выберите счёт.'),
            'entryAccountId.min' => __('Выберите счёт.'),
            'entryDate.required' => __('Укажите дату.'),
        ]);

        $cents = Money::toCents($this->entryAmount);

        if ($cents === null || $cents <= 0) {
            throw ValidationException::withMessages([
                'entryAmount' => __('Сумма должна быть больше нуля.'),
            ]);
        }

        $household = $this->household();
        $category = Category::query()
            ->where('household_id', $household->id)
            ->where('type', $this->entryType)
            ->find($this->entryCategoryId);

        if ($category === null) {
            throw ValidationException::withMessages([
                'entryCategoryId' => __('Выберите категорию.'),
            ]);
        }

        $date = $this->typedDay($this->entryDate, 'entryDate');
        $this->refuseFactOutsideCurrent($date, 'entryDate', $periods);
        $period = $periods->periodFor($household, $date);

        $planLine = null;

        if ($this->entryPlanLineId) {
            $planLine = PlanLine::query()
                ->where('category_id', $category->id)
                ->where('period_id', $period->id)
                ->find($this->entryPlanLineId);
        }

        $account = $this->requireAccount($this->entryAccountId, 'entryAccountId');

        if ($planLine === null) {
            $planLine = $periods->resolvePlanLine($period, $category, $account);
        }

        $transaction = $periods->record(
            $household,
            $category,
            CashflowType::from($this->entryType),
            $cents,
            $date,
            $this->entryNote !== '' ? $this->entryNote : null,
            $planLine,
            auth()->id(),
            $account,
        );

        $this->reset('entryAmount', 'entryNote', 'entryPlanLineId', 'entryAccountId');
        $this->showEntry = false;
        $this->syncPeriod($transaction->period);
        $this->tellFamily(__('новый ').$this->factPhrase(CashflowType::from($this->entryType), $cents, $category->name));
    }

    public function addPlanLine(PeriodGenerator $periods): void
    {
        $this->newPlanAmount = Money::normalize($this->newPlanAmount);

        $this->validate([
            'newPlanType' => ['required', 'in:income,expense'],
            'newPlanCategoryId' => ['required', 'integer', 'min:1'],
            'newPlanTitle' => ['nullable', 'string', 'max:80'],
            'newPlanAmount' => ['required', 'regex:/^\d+([.,]\d{1,2})?$/'],
            'newPlanDate' => ['nullable', new AppDay(__('Проверьте дату.'))],
        ], [
            'newPlanCategoryId.required' => __('Выберите категорию.'),
            'newPlanCategoryId.min' => __('Выберите категорию.'),
            'newPlanAmount.required' => __('Укажите сумму.'),
            'newPlanAmount.regex' => __('Сумма в евро, до двух знаков после запятой.'),
        ]);

        $cents = Money::toCents((string) $this->newPlanAmount);

        if ($cents === null || $cents <= 0) {
            throw ValidationException::withMessages([
                'newPlanAmount' => __('Сумма должна быть больше нуля.'),
            ]);
        }

        $period = $this->period();
        $this->refuseClosedPeriod($period, 'newPlanAmount');
        $expectedOn = null;

        if (trim($this->newPlanDate) !== '') {
            $expectedOn = AppDate::parse($this->newPlanDate);

            if ($expectedOn === null) {
                throw ValidationException::withMessages([
                    'newPlanDate' => __('Проверьте дату.'),
                ]);
            }
            $day = $expectedOn->toDateString();

            if ($day < $period->starts_on->toDateString() || $day > $period->ends_on->toDateString()) {
                throw ValidationException::withMessages([
                    'newPlanDate' => __('Дата должна быть в этом месяце.'),
                ]);
            }
        }

        $type = CashflowType::from($this->newPlanType);
        $category = Category::query()
            ->where('household_id', $this->household()->id)
            ->where('type', $type)
            ->whereNull('archived_at')
            ->find($this->newPlanCategoryId);

        if ($category === null) {
            throw ValidationException::withMessages([
                'newPlanCategoryId' => __('Выберите категорию.'),
            ]);
        }

        $title = trim((string) $this->newPlanTitle) !== '' ? trim((string) $this->newPlanTitle) : $category->name;
        $periods->addPlanLine($period, $category, $title, $cents, $expectedOn);
        $this->syncPeriod($period);
        $this->revealParent($category);
        $this->reset('newPlanTitle', 'newPlanAmount', 'newPlanCategoryId', 'newPlanDate');
        $this->newPlanIcon = CategoryIcons::fallback($this->newPlanType);
        $this->showPlan = false;
        $this->tellFamily(__('новая строка плана ').money($cents).', '.$title.', '.ru_month($period->starts_on));
    }

    public function updatedNewPlanType(): void
    {
        $this->newPlanCategoryId = null;
    }

    public function addIncomeCategory(PeriodGenerator $periods): void
    {
        $this->addExistingCategory($periods, CashflowType::Income, $this->incomeCategoryId, 'incomeCategoryId');
        $this->reset('incomeCategoryId');
    }

    public function addExpenseCategory(PeriodGenerator $periods): void
    {
        $this->addExistingCategory($periods, CashflowType::Expense, $this->expenseCategoryId, 'expenseCategoryId');
        $this->reset('expenseCategoryId');
    }

    public function addQuickCategory(PeriodGenerator $periods): void
    {
        $type = CashflowType::from($this->entryType);
        $category = $this->categoryNamed($type, $this->quickCategoryName, 'quickCategoryName', $this->quickParentId, $this->quickCategoryIcon, 'quickCategoryIcon');
        $this->revealParent($category);
        $period = $this->period();
        $line = $periods->resolvePlanLine($period, $category);
        $this->syncPeriod($period);
        $this->entryCategoryId = $category->id;
        $this->entryPlanLineId = $line->id;
        $this->reset('quickCategoryName', 'quickParentId');
        $this->quickCategoryIcon = CategoryIcons::fallback($this->entryType);
    }

    public function editTransaction(int $transactionId): void
    {
        $transaction = $this->ownedTransaction($transactionId)->loadMissing('period');

        if ($this->periodHasEnded($transaction->period)) {
            session()->flash('status', __('Прошлый месяц уже закрыт. Менять его нельзя.'));

            return;
        }

        $this->showEntry = false;
        $this->showPlan = false;
        $this->editingLineId = null;
        $this->reset('editReceipt', 'editReceiptCamera');
        $this->resetErrorBag(['editReceipt', 'editReceiptCamera']);
        $this->editingTransactionId = $transaction->id;
        $transaction->loadMissing('category');
        $this->dealsLineId = $transaction->plan_line_id;
        if ($this->dealsLineId === null) {
            $lineIds = PlanLine::query()
                ->where('period_id', $transaction->period_id)
                ->where('category_id', $transaction->category_id)
                ->pluck('id');
            $this->dealsLineId = $lineIds->count() === 1 ? (int) $lineIds->first() : null;
        }
        $this->detailCategoryId = null;
        $this->detailLineId = null;

        if ($transaction->category) {
            $this->openCategoryIds = $this->categoryPath($transaction->category);
        }
        $this->editType = $transaction->type->value;
        $this->editAmount = Money::toInput($transaction->amount_cents);
        $this->editDate = $transaction->occurred_on->toDateString();
        $this->editNote = (string) ($transaction->note ?? '');
        $this->editCategoryId = $transaction->category_id;
        $this->editAccountId = $transaction->account_id;
        $this->planMonth = $transaction->occurred_on->addMonth()->format('Y-m');
    }

    public function moveTransactionToPlan(PeriodGenerator $periods): void
    {
        if ($this->editingTransactionId === null) {
            return;
        }

        $this->validate([
            'planMonth' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ], [
            'planMonth.required' => __('Выберите месяц плана.'),
            'planMonth.regex' => __('Выберите месяц плана.'),
        ]);

        $transaction = $this->ownedTransaction($this->editingTransactionId)->loadMissing('period');
        $month = CarbonImmutable::createFromFormat('!Y-m-d', $this->planMonth.'-01');

        if ($month === false || $month->format('Y-m') !== $this->planMonth) {
            throw ValidationException::withMessages([
                'planMonth' => __('Выберите месяц плана.'),
            ]);
        }

        $current = $periods->periodFor($this->household(), $this->today());

        if ($this->periodHasEnded($transaction->period) || $month->toDateString() < $current->starts_on->toDateString()) {
            throw ValidationException::withMessages([
                'planMonth' => __('Прошлый месяц уже закрыт. Менять его нельзя.'),
            ]);
        }

        if ($transaction->receipt_path) {
            throw ValidationException::withMessages([
                'planMonth' => __('У операции есть чек. Это факт, его нельзя перенести в план.'),
            ]);
        }

        $transaction->loadMissing('category');
        $moved = money((int) $transaction->amount_cents).', '.($transaction->category->name ?? __('без категории'));
        $periods->moveFactToPlan($transaction, $month);
        $this->tellFamily(__('операция ').$moved.__(' перенесена в план ').ru_month($month));
        $this->detailCategoryId = null;
        $this->detailLineId = null;
        $this->dealsLineId = null;
        $this->resetEditTransaction();
        session()->flash('status', __('Факт убран. Сумма записана в план ').ru_month($month).__('. Деньги вернулись на счёт.'));
    }

    public function saveEditedTransaction(PeriodGenerator $periods): void
    {
        if ($this->editingTransactionId === null) {
            return;
        }

        $this->validate([
            'editAmount' => ['required', 'regex:/^\d+([.,]\d{1,2})?$/'],
            'editCategoryId' => ['required', 'integer'],
            'editAccountId' => ['required', 'integer'],
            'editDate' => ['required', new AppDay],
            'editNote' => ['nullable', 'string', 'max:200'],
        ], [
            'editAmount.required' => __('Укажите сумму.'),
            'editAmount.regex' => __('Сумма в евро, до двух знаков после запятой.'),
            'editCategoryId.required' => __('Выберите категорию.'),
            'editAccountId.required' => __('Выберите счёт.'),
            'editDate.required' => __('Укажите дату.'),
        ]);

        $cents = Money::toCents($this->editAmount);

        if ($cents === null || $cents <= 0) {
            throw ValidationException::withMessages([
                'editAmount' => __('Сумма должна быть больше нуля.'),
            ]);
        }

        $transaction = $this->ownedTransaction($this->editingTransactionId)->loadMissing('period');

        if ($this->periodHasEnded($transaction->period)) {
            throw ValidationException::withMessages([
                'editDate' => __('Прошлый месяц уже закрыт. Менять его нельзя.'),
            ]);
        }

        $editDay = $this->typedDay($this->editDate, 'editDate');
        $this->refuseFactOutsideCurrent($editDay, 'editDate', $periods);

        $category = Category::query()
            ->where('household_id', $this->household()->id)
            ->where('type', $transaction->type)
            ->find($this->editCategoryId);

        if ($category === null) {
            throw ValidationException::withMessages([
                'editCategoryId' => __('Выберите категорию.'),
            ]);
        }

        $account = $this->requireAccount($this->editAccountId, 'editAccountId');

        $updated = $periods->revise(
            $transaction,
            $category,
            $cents,
            $editDay,
            $this->editNote !== '' ? $this->editNote : null,
            $account,
        );

        $this->detailCategoryId = null;
        $this->detailLineId = null;
        $this->resetEditTransaction();
        $this->tellFamily(__('изменена операция ').money($cents).', '.$category->name);
    }

    public function cancelEdit(): void
    {
        $this->resetEditTransaction();
    }

    public function updatedEditReceipt(): void
    {
        $this->storeEditedReceipt($this->editReceipt, 'editReceipt');
    }

    public function updatedEditReceiptCamera(): void
    {
        $this->storeEditedReceipt($this->editReceiptCamera, 'editReceiptCamera');
    }

    public function removeTransactionReceipt(): void
    {
        $transaction = $this->editableExpense();
        $this->deleteReceiptFile($transaction->receipt_path);
        $transaction->update(['receipt_path' => null]);
        $this->reset('editReceipt', 'editReceiptCamera');
    }

    private function storeEditedReceipt(?TemporaryUploadedFile $receipt, string $key): void
    {
        if (! $receipt instanceof TemporaryUploadedFile) {
            return;
        }

        $this->validate([
            $key => ['image', 'max:8192'],
        ], [
            $key.'.image' => __('Чек должен быть фотографией.'),
            $key.'.max' => __('Фото чека больше 8 МБ.'),
        ]);

        $transaction = $this->editableExpense();
        $previous = $transaction->receipt_path;
        $stored = $receipt->store('receipts', 'public');
        $transaction->update(['receipt_path' => $stored]);

        if (is_string($previous) && $previous !== $stored) {
            $this->deleteReceiptFile($previous);
        }

        $this->reset($key);
    }

    private function editableExpense(): Transaction
    {
        if ($this->editingTransactionId === null) {
            abort(404);
        }

        $transaction = $this->ownedTransaction($this->editingTransactionId)->loadMissing('period');

        if ($transaction->type !== CashflowType::Expense) {
            abort(404);
        }

        $this->refuseClosedPeriod($transaction->period, 'editReceipt');

        return $transaction;
    }

    private function deleteReceiptFile(?string $path): void
    {
        if (! is_string($path) || $path === '' || str_contains($path, '..') || ! str_starts_with($path, 'receipts/')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    public function editLine(int $lineId): void
    {
        if ($this->editingLineId === $lineId) {
            $this->cancelLine();

            return;
        }

        $line = $this->ownedLine($lineId)->loadMissing('period');

        if ($this->periodHasEnded($line->period)) {
            session()->flash('status', __('Прошлый месяц уже закрыт. Менять его нельзя.'));

            return;
        }

        $this->editingTransactionId = null;
        $this->detailCategoryId = null;
        $this->detailLineId = null;
        $this->editingLineId = $line->id;
        $this->lineCategoryId = $line->category_id;
        $this->lineDate = $line->expected_on?->toDateString() ?? '';
        $this->lineAccountId = $line->account_id;
        $this->lineAmount = $line->amount_cents === 0 ? '' : Money::toInput($line->amount_cents);
    }

    public function saveLine(): void
    {
        if ($this->editingLineId === null) {
            return;
        }

        $this->validate([
            'lineCategoryId' => ['required', 'integer'],
            'lineDate' => ['nullable', new AppDay],
            'lineAccountId' => ['nullable', 'integer'],
            'lineAmount' => ['nullable', 'regex:/^\d+([.,]\d{1,2})?$/'],
        ], [
            'lineCategoryId.required' => __('Выберите категорию.'),
            'lineAmount.regex' => __('Сумма в евро, до двух знаков после запятой.'),
        ]);

        $rawAmount = trim($this->lineAmount);
        $cents = $rawAmount === '' ? 0 : Money::toCents($rawAmount);

        if ($cents === null || $cents < 0) {
            throw ValidationException::withMessages([
                'lineAmount' => __('Некорректная сумма.'),
            ]);
        }

        $line = $this->ownedLine($this->editingLineId)->load('category', 'period');
        $this->refuseClosedPeriod($line->period, 'lineAmount');
        $category = Category::query()
            ->where('household_id', $this->household()->id)
            ->where('type', $line->category->type)
            ->whereNull('archived_at')
            ->find($this->lineCategoryId);

        if ($category === null) {
            throw ValidationException::withMessages([
                'lineCategoryId' => __('Выберите категорию того же вида.'),
            ]);
        }

        $accountId = $this->lineAccountId
            ? Account::query()->where('household_id', $this->household()->id)->whereKey($this->lineAccountId)->value('id')
            : null;

        $expectedOn = null;

        if (trim($this->lineDate) !== '') {
            $expectedOn = AppDate::parse($this->lineDate)?->toDateString();

            if ($expectedOn === null) {
                throw ValidationException::withMessages([
                    'lineDate' => __('Укажите дату.'),
                ]);
            }
        }

        $line->update([
            'category_id' => $category->id,
            'amount_cents' => $cents,
            'expected_on' => $expectedOn,
            'account_id' => $accountId,
        ]);
        $this->planAmounts[$line->id] = $cents === 0 ? '' : Money::toInput($cents);
        $line->transactions()->update(['category_id' => $category->id]);
        $this->revealParent($category);
        $this->editingLineId = null;
        $this->tellFamily(__('план «').$line->title.__('» теперь ').money($cents));
    }

    public function cancelLine(): void
    {
        $this->editingLineId = null;
        $this->reset('lineAmount');
    }

    public function savePlanAmount(int $lineId): void
    {
        $line = $this->ownedLine($lineId)->loadMissing('period');

        if ($this->periodHasEnded($line->period)) {
            session()->flash('status', __('Прошлый месяц уже закрыт. Менять его нельзя.'));

            return;
        }

        $raw = trim((string) ($this->planAmounts[$lineId] ?? ''));
        $cents = $raw === '' ? 0 : Money::toCents($raw);

        if ($cents === null || $cents < 0) {
            $this->addError('planAmounts.'.$lineId, __('Некорректная сумма.'));

            return;
        }

        $before = (int) $line->amount_cents;
        $this->savingPlan = true;
        $line->update(['amount_cents' => $cents]);
        $this->planAmounts[$lineId] = $cents === 0 ? '' : Money::toInput($cents);
        $this->savingPlan = false;

        if ($before !== $cents) {
            $this->tellFamily(__('план «').$line->title.__('» теперь ').money($cents));
        }
    }

    public function deletePlanLine(int $lineId): void
    {
        $line = $this->ownedLine($lineId)->loadMissing('period');

        if ($this->periodHasEnded($line->period)) {
            session()->flash('status', __('Прошлый месяц уже закрыт. Менять его нельзя.'));

            return;
        }

        if ($this->planLineHasEntries($line)) {
            return;
        }

        $title = $line->title;
        $line->delete();
        $this->tellFamily(__('строка плана «').$title.__('» удалена'));
        unset($this->planAmounts[$lineId]);

        if ($this->editingLineId === $lineId) {
            $this->editingLineId = null;
        }
    }

    private function planLineHasEntries(PlanLine $line): bool
    {
        if ($line->transactions()->exists()) {
            return true;
        }

        $onlyLine = PlanLine::query()
            ->where('period_id', $line->period_id)
            ->where('category_id', $line->category_id)
            ->count() === 1;

        if (! $onlyLine) {
            return false;
        }

        return Transaction::query()
            ->where('period_id', $line->period_id)
            ->where('category_id', $line->category_id)
            ->whereNull('plan_line_id')
            ->exists();
    }

    public function deleteTransaction(int $transactionId): void
    {
        $transaction = $this->ownedTransaction($transactionId)->loadMissing('period');

        if ($this->periodHasEnded($transaction->period)) {
            session()->flash('status', __('Прошлый месяц уже закрыт. Менять его нельзя.'));

            return;
        }

        if ($transaction->receipt_path) {
            return;
        }

        $transaction->loadMissing('category');
        $phrase = $this->factPhrase($transaction->type, (int) $transaction->amount_cents, $transaction->category->name ?? __('без категории'));
        app(\App\Services\Budget\DebtBook::class)->reopen($transaction);
        $transaction->account?->apply($transaction->type, -$transaction->amount_cents);
        $transaction->delete();
        $this->tellFamily(__('удалён ').$phrase);

        if ($this->editingTransactionId === $transactionId) {
            $this->resetEditTransaction();
        }
    }

    /**
     * @return array{income: list<string>, expense: list<string>}
     */
    private function noteSuggestions(BudgetPeriod $period): array
    {
        $suggestions = ['income' => [], 'expense' => []];
        $seen = ['income' => [], 'expense' => []];
        $rows = Transaction::query()
            ->where('household_id', $period->household_id)
            ->whereNotNull('note')
            ->where('note', '!=', '')
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get(['type', 'note']);

        foreach ($rows as $row) {
            $type = $row->type->value;

            if (! isset($suggestions[$type]) || count($suggestions[$type]) >= 40) {
                continue;
            }

            $note = trim((string) $row->note);
            $key = mb_strtolower($note);

            if ($note === '' || isset($seen[$type][$key])) {
                continue;
            }

            $seen[$type][$key] = true;
            $suggestions[$type][] = $note;
        }

        return $suggestions;
    }

    /**
     * @return list<string>
     */
    protected function dayFirstDateFields(): array
    {
        return ['quickDate', 'transferDate', 'entryDate', 'operationDate', 'editDate', 'newPlanDate', 'lineDate'];
    }

    public function render(PeriodSummary $summary, PeriodCalendar $calendar)
    {
        $this->presentDayFirstDates();
        $period = $this->period()->load(['planLines.account', 'planLines.transactions', 'household.accounts']);
        $groups = $summary->groups($period);
        $now = CarbonImmutable::now($period->household->timezone ?: 'Europe/Riga');
        $today = $now->startOfDay();
        $figures = $summary->figures($period);
        $accounts = $period->household->accounts()->orderBy('sort')->get();
        $walletBalances = $this->postedBalances($accounts);
        $cashNow = $accounts->isEmpty()
            ? $figures->actualClosing()
            : (int) $accounts
                ->filter(fn (Account $account) => $account->role !== AccountRole::Savings)
                ->sum(fn (Account $account) => $walletBalances[$account->id] ?? 0);
        $carried = 0;
        $priorPlans = collect();

        $openPeriod = app(PeriodGenerator::class)->periodFor($period->household, $today);
        $monthNext = $today->lt($period->starts_on)
            && CarbonImmutable::parse($openPeriod->ends_on)->addDay()->toDateString() === $period->starts_on->toDateString();

        if ($today->lt($period->starts_on)) {
            $earlier = BudgetPeriod::query()
                ->where('household_id', $period->household_id)
                ->whereDate('ends_on', '>=', $today->toDateString())
                ->whereDate('starts_on', '<', $period->starts_on->toDateString())
                ->orderBy('starts_on')
                ->get()
                ->map(function (BudgetPeriod $item) use ($summary, $cashNow, &$carried) {
                    $carry = $summary->carryNet($item);
                    $carried += $carry;

                    return [
                        'label' => ru_month($item->starts_on),
                        'starts_on' => $item->starts_on->toDateString(),
                        'carry' => $carry,
                        'net' => $cashNow + $carried,
                    ];
                })
                ->values();
            $previous = $earlier->last();

            if ($previous !== null && $today->toDateString() >= $previous['starts_on'] && ($earlier->count() > 1 || $previous['carry'] !== 0)) {
                $priorPlans = collect([[
                    'label' => $previous['label'],
                    'net' => $previous['net'],
                ]]);
            }
        }

        app(DebtBook::class)->materialize($period->household);

        $debts = Debt::query()
            ->where('household_id', $period->household_id)
            ->with('installments')
            ->get();

        return view('livewire.budget.dashboard', [
            'period' => $period,
            'figures' => $figures,
            'cashNow' => $cashNow,
            'priorPlans' => $priorPlans,
            'priorPlanNet' => $carried,
            'monthNext' => $monthNext,
            'asOf' => $today->toDateString(),
            'dayRefreshAt' => $today->addDay()->getTimestamp(),
            'serverNow' => $now->getTimestamp(),
            'walletBalances' => $walletBalances,
            'accounts' => $accounts,
            'incomeGroups' => $this->ofType($groups, CashflowType::Income),
            'expenseGroups' => $this->ofType($groups, CashflowType::Expense),
            'progress' => $calendar->progress($period->starts_on, $period->ends_on, $today),
            'spendableDays' => $calendar->spendableDays($period->starts_on, $period->ends_on, $today),
            'receivableDebts' => (int) $debts->filter(fn (Debt $debt): bool => $debt->direction === DebtDirection::Lend)->sum(fn (Debt $debt): int => $debt->remainingCents()),
            'payableDebts' => (int) $debts->filter(fn (Debt $debt): bool => $debt->direction === DebtDirection::Borrow || $debt->kind === DebtKind::Bank)->sum(fn (Debt $debt): int => $debt->remainingCents()),
            'canGoForward' => app(DebtBook::class)->canOpen($period->household, CarbonImmutable::parse($period->ends_on)->addDay()->startOfDay()),
            'categories' => Category::query()
                ->where('household_id', $period->household_id)
                ->whereNull('archived_at')
                ->with('parent')
                ->orderBy('sort')
                ->get(),
            'entryCategories' => Category::query()
                ->where('household_id', $period->household_id)
                ->where('type', $this->entryType)
                ->whereNull('archived_at')
                ->with('parent')
                ->orderBy('sort')
                ->get(),
            'entryPlanLines' => $period->planLines->where('category_id', $this->entryCategoryId)->values(),
            'quickLines' => $this->quickLines(),
            'noteSuggestions' => $this->noteSuggestions($period),
            'factFrom' => ($currentPeriod = app(PeriodGenerator::class)->periodFor($period->household, $today))->starts_on->toDateString(),
            'factTo' => $currentPeriod->ends_on->toDateString(),
        ])->title('Family Money');
    }

    private function addExistingCategory(PeriodGenerator $periods, CashflowType $type, ?int $categoryId, string $errorKey): void
    {
        $this->resetErrorBag($errorKey);

        $category = Category::query()
            ->where('household_id', $this->household()->id)
            ->where('type', $type)
            ->whereNull('archived_at')
            ->find($categoryId);

        if ($category === null) {
            throw ValidationException::withMessages([
                $errorKey => __('Выберите категорию.'),
            ]);
        }

        $line = $this->ensureLine($periods, $category, $category->name, 0, $errorKey);
        $this->revealParent($category);
        $this->editLine($line->id);
    }

    private function prepareOperation(PlanLine $line): void
    {
        if ($this->operationDate === '') {
            $this->operationDate = $this->today()->toDateString();
        }

        $this->operationLineId = $line->id;
        $this->operationAccountId = $line->account_id;
        $this->reset('operationAmount', 'operationNote', 'operationReceipt');
        $this->resetErrorBag(['operationAmount', 'operationDate', 'operationNote', 'operationLineId', 'operationAccountId', 'operationReceipt']);
    }

    private function categoryNamed(CashflowType $type, string $name, ?string $errorKey = null, ?int $parentId = null, ?string $icon = null, ?string $iconErrorKey = null): Category
    {
        $name = trim($name);
        $errorKey ??= 'category';

        if ($name === '') {
            throw ValidationException::withMessages([
                $errorKey => __('Напишите название категории.'),
            ]);
        }

        if (mb_strlen($name) > 60) {
            throw ValidationException::withMessages([
                $errorKey => __('Название короче 60 символов.'),
            ]);
        }

        $parent = $this->parentCategory($type, $parentId, $errorKey);
        $household = $this->household();
        $needle = mb_strtolower($name);
        $existing = $household->categories()
            ->where('type', $type)
            ->get()
            ->first(fn (Category $category) => mb_strtolower($category->name) === $needle);

        if ($existing) {
            $this->attachParent($existing, $parent, $errorKey);

            return $existing->fresh();
        }

        if ($parent && mb_strtolower($parent->name) === $needle) {
            throw ValidationException::withMessages([
                $errorKey => __('Подкатегория не может называться так же, как материнская.'),
            ]);
        }

        $icon = CategoryIcons::resolve($icon, $type->value);

        return $household->categories()->create([
            'parent_id' => $parent?->id,
            'type' => $type,
            'name' => $name,
            'icon' => $icon,
            'sort' => (int) $household->categories()->max('sort') + 1,
            'color' => $type === CashflowType::Income ? '#0f6e6b' : '#9a3412',
        ]);
    }

    private function parentCategory(CashflowType $type, ?int $parentId, string $errorKey): ?Category
    {
        if (! $parentId) {
            return null;
        }

        $parent = Category::query()
            ->where('household_id', $this->household()->id)
            ->where('type', $type)
            ->whereNull('parent_id')
            ->find($parentId);

        if ($parent === null) {
            throw ValidationException::withMessages([
                $errorKey => __('Материнская категория должна быть верхнего уровня и того же вида.'),
            ]);
        }

        return $parent;
    }

    private function attachParent(Category $category, ?Category $parent, string $errorKey): void
    {
        if ($parent === null || (int) $category->parent_id === (int) $parent->id) {
            return;
        }

        if ((int) $category->id === (int) $parent->id) {
            throw ValidationException::withMessages([
                $errorKey => __('Категория не может быть материнской для самой себя.'),
            ]);
        }

        if ($category->parent_id !== null) {
            throw ValidationException::withMessages([
                $errorKey => '«'.$category->name.__('» уже вложена в другую категорию.'),
            ]);
        }

        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                $errorKey => __('У «').$category->name.__('» уже есть подкатегории.'),
            ]);
        }

        $category->update(['parent_id' => $parent->id]);
    }

    private function revealParent(Category $category): void
    {
        $this->openCategoryIds = $this->categoryPath($category);
    }

    /** @return list<int> */
    private function categoryPath(Category $category): array
    {
        $ids = [(int) $category->id];
        $cursor = $category;
        $guard = 0;

        while ($cursor->parent_id !== null && $guard < 30) {
            $cursor = $this->household()->categories()->find($cursor->parent_id);

            if ($cursor === null) {
                break;
            }

            array_unshift($ids, (int) $cursor->id);
            $guard++;
        }

        return $ids;
    }

    private function ensureLine(PeriodGenerator $periods, Category $category, string $title, int $cents, string $errorKey): PlanLine
    {
        $period = $this->period();
        $line = $periods->resolvePlanLine($period, $category);

        if ($title !== $category->name || $cents > 0) {
            $line->update([
                'title' => $title,
                'amount_cents' => max((int) $line->amount_cents, $cents),
            ]);
        }

        $this->syncPeriod($period);

        return $line->refresh();
    }

    private function household(): Household
    {
        return auth()->user()->currentHousehold();
    }

    private function typedDay(string $raw, string $key): CarbonImmutable
    {
        $date = AppDate::parse($raw);

        if ($date === null) {
            throw ValidationException::withMessages([
                $key => __('Укажите дату.'),
            ]);
        }

        return $date;
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->household()->timezone)->startOfDay();
    }

    private function tellFamily(string $action): void
    {
        FamilyActivity::tell($this->household(), auth()->user()->name.': '.$action);
    }

    private function factPhrase(CashflowType $type, int $cents, string $category): string
    {
        $kind = match ($type) {
            CashflowType::Income => __('приход'),
            CashflowType::LiabilityIn => __('взяли в долг'),
            CashflowType::Expense => __('расход'),
        };

        return $kind.' '.money($cents).', '.$category;
    }

    private function periodHasEnded(BudgetPeriod $period): bool
    {
        return $this->today()->toDateString() > $period->ends_on->toDateString();
    }

    private function refuseClosedPeriod(BudgetPeriod $period, string $key): void
    {
        if ($this->periodHasEnded($period)) {
            throw ValidationException::withMessages([
                $key => __('Прошлый месяц уже закрыт. Менять его нельзя.'),
            ]);
        }
    }

    private function paymentDate(PlanLine $line, PeriodGenerator $periods): CarbonImmutable
    {
        $today = $this->today();

        if ($line->expected_on === null) {
            return $today;
        }

        $expected = CarbonImmutable::parse($line->expected_on)->startOfDay();
        $current = $periods->periodFor($this->household(), $today);
        $day = $expected->toDateString();

        if ($day < $current->starts_on->toDateString() || $day > $current->ends_on->toDateString()) {
            return $today;
        }

        return $expected;
    }

    private function refuseFactOutsideCurrent(CarbonImmutable $date, string $key, PeriodGenerator $periods): void
    {
        $current = $periods->periodFor($this->household(), $this->today());
        $day = $date->toDateString();

        if ($day < $current->starts_on->toDateString() || $day > $current->ends_on->toDateString()) {
            throw ValidationException::withMessages([
                $key => __('Операцию можно записать только в текущем месяце.'),
            ]);
        }
    }

    private function period(): BudgetPeriod
    {
        return $this->ownedPeriod($this->periodId) ?? abort(404);
    }

    private function ownedPeriod(?int $id): ?BudgetPeriod
    {
        if ($id === null) {
            return null;
        }

        return BudgetPeriod::query()
            ->where('household_id', $this->household()->id)
            ->find($id);
    }

    private function syncPeriod(BudgetPeriod $period): void
    {
        if ($this->periodId !== null && (int) $this->periodId !== (int) $period->id) {
            $this->closeAccordions();
            $this->closeQuick();
            $this->closeTransfer();
            $this->showPlan = false;
            $this->showEntry = false;
        }

        $this->periodId = $period->id;
        session(['budget.period_id' => $period->id]);
        $this->planAmounts = $period->planLines()
            ->pluck('amount_cents', 'id')
            ->map(fn ($cents) => (int) $cents === 0 ? '' : Money::toInput((int) $cents))
            ->all();
    }

    /**
     * Money already posted. The operation date does not put it back:
     * a payment dated later has still left the account.
     *
     * @param  Collection<int, Account>  $accounts
     * @return array<int, int>
     */
    private function postedBalances(Collection $accounts): array
    {
        $balances = [];

        foreach ($accounts as $account) {
            $balances[$account->id] = (int) $account->balance_cents;
        }

        return $balances;
    }

    private function requireAccount(mixed $accountId, string $errorKey): Account
    {
        $account = Account::query()
            ->where('household_id', $this->household()->id)
            ->find($accountId);

        if (! $account instanceof Account) {
            throw ValidationException::withMessages([
                $errorKey => __('Выберите счёт.'),
            ]);
        }

        return $account;
    }

    private function ownedAccount(int $accountId): Account
    {
        return Account::query()
            ->where('household_id', $this->household()->id)
            ->findOrFail($accountId);
    }

    private function resetEditTransaction(): void
    {
        $this->editingTransactionId = null;
        $this->reset('editAmount', 'editDate', 'editNote', 'editCategoryId', 'editAccountId', 'planMonth', 'editReceipt', 'editReceiptCamera');
    }

    private function ownedLine(int $lineId): PlanLine
    {
        return PlanLine::query()
            ->whereIn('period_id', BudgetPeriod::query()->where('household_id', $this->household()->id)->select('id'))
            ->findOrFail($lineId);
    }

    private function ownedTransaction(int $transactionId): Transaction
    {
        return Transaction::query()
            ->where('household_id', $this->household()->id)
            ->findOrFail($transactionId);
    }

    /**
     * @param  Collection<int, \App\Services\Budget\CategorySnapshot>  $groups
     * @return Collection<int, \App\Services\Budget\CategorySnapshot>
     */
    private function ofType(Collection $groups, CashflowType $type): Collection
    {
        return $groups
            ->filter(fn ($group) => $group->category->type === $type)
            ->values();
    }
}
