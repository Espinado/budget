<?php

namespace App\Livewire\Budget;

use App\Enums\CashflowType;
use App\Enums\PropertyObjectKind;
use App\Livewire\Budget\Concerns\RecordsDebt;
use App\Livewire\Concerns\NormalizesMoneyInput;
use App\Livewire\Concerns\PresentsDayFirstDates;
use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtInstallment;
use App\Models\Household;
use App\Models\PlanLine;
use App\Models\PlanTemplate;
use App\Models\PropertyContract;
use App\Models\Transaction;
use App\Models\PropertyObject;
use App\Services\Budget\PeriodGenerator;
use App\Support\AppDate;
use App\Support\CategoryTree;
use App\Support\ContractDocuments;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class PropertyObjectForm extends Component
{
    use NormalizesMoneyInput {
        updated as normalizeMoneyUpdated;
    }
    use PresentsDayFirstDates;
    use RecordsDebt;
    use WithFileUploads;

    public ?int $objectId = null;

    public string $name = '';

    public string $kind = 'apartment';

    public string $label = '';

    /** @var list<int|string> */
    public array $debtIds = [];

    /** @var list<int|string> */
    public array $categoryIds = [];

    public string $planSearch = '';

    public string $placeMode = 'root';

    public ?int $parentCategoryId = null;

    public bool $showExpense = false;

    public string $expenseName = '';

    public string $expenseAmount = '';

    public string $expenseDate = '';

    public bool $showContract = false;

    public string $contractTitle = '';

    public string $contractNote = '';

    /** @var list<array{amount: string, date: string}> */
    public array $contractParts = [['amount' => '', 'date' => '']];

    public ?int $contractDatePicker = null;

    public int $contractPickerYear = 2026;

    public int $contractPickerMonth = 1;

    public mixed $contractDocument = null;

    public ?int $editingContractId = null;

    public bool $dropContractDocument = false;

    public function mount(PropertyObject|string|null $propertyObject = null): void
    {
        if ($propertyObject === null || $propertyObject === '') {
            $this->suggestPlacement($this->household());

            return;
        }

        if (! $propertyObject instanceof PropertyObject) {
            $propertyObject = PropertyObject::query()->findOrFail($propertyObject);
        }

        abort_unless((int) $propertyObject->household_id === (int) $this->household()->id, 404);

        $this->objectId = $propertyObject->id;
        $this->name = $propertyObject->name;
        $this->kind = $propertyObject->kind->value;
        $this->label = $propertyObject->label;
        $this->debtIds = $propertyObject->debts()->allRelatedIds()->map(fn ($id): int => (int) $id)->all();
        $attached = $propertyObject->planLines()->allRelatedIds()->map(fn ($id): int => (int) $id)->all();
        $this->categoryIds = $this->categoryIdsForLines((int) $propertyObject->household_id, $attached);
        $this->fillPlacement($propertyObject);
    }

    public function openExpense(): void
    {
        $this->showExpense = true;
        $this->showContract = false;
        $this->showNew = false;
    }

    public function openContract(): void
    {
        $this->showContract = true;
        $this->showExpense = false;
        $this->showNew = false;
        $this->resetContractForm();
    }

    public function editContract(int $contractId): void
    {
        $contract = $this->ownedContract($contractId);
        $parts = [];

        foreach ($contract->planLines()->orderBy('expected_on')->orderBy('id')->get() as $line) {
            $parts[] = [
                'amount' => Money::toInput((int) $line->amount_cents),
                'date' => $line->expected_on?->format('d.m.Y') ?? '',
            ];
        }

        $this->showContract = true;
        $this->showExpense = false;
        $this->showNew = false;
        $this->editingContractId = $contract->id;
        $this->dropContractDocument = false;
        $this->contractDocument = null;
        $this->contractTitle = $contract->title;
        $this->contractNote = (string) ($contract->note ?? '');
        $this->contractParts = $parts === [] ? [['amount' => '', 'date' => '']] : $parts;
        $this->contractDatePicker = null;
        $this->resetErrorBag();
    }

    public function updatedContractDocument(): void
    {
        if (! $this->contractDocument instanceof TemporaryUploadedFile) {
            return;
        }

        try {
            ContractDocuments::assertValid($this->contractDocument, 'contractDocument');
        } catch (ValidationException $exception) {
            $this->contractDocument = null;

            throw $exception;
        }
    }

    public function clearContractDocument(): void
    {
        if ($this->contractDocument instanceof TemporaryUploadedFile) {
            $this->contractDocument = null;
            $this->resetErrorBag('contractDocument');

            return;
        }

        if ($this->editingContractId !== null) {
            $this->dropContractDocument = true;
        }

        $this->contractDocument = null;
        $this->resetErrorBag('contractDocument');
    }

    /**
     * @return array{url: string, mime: string, title: string}|null
     */
    public function draftDocumentPreview(): ?array
    {
        if (! $this->contractDocument instanceof TemporaryUploadedFile) {
            return null;
        }

        try {
            $checked = ContractDocuments::assertValid($this->contractDocument, 'contractDocument');
        } catch (ValidationException) {
            return null;
        }

        return [
            'url' => $this->contractDocument->temporaryUrl(),
            'mime' => $checked['mime'],
            'title' => $checked['extension'] === 'pdf'
                ? (string) $this->contractDocument->getClientOriginalName()
                : __('Просмотр документа'),
        ];
    }

    public function addContractPart(): void
    {
        $this->contractParts[] = ['amount' => '', 'date' => ''];
    }

    public function updated(string $name, mixed $value): void
    {
        $this->normalizeMoneyUpdated($name, $value);
        $this->followOpenContractDate($name, $value);
    }

    private function followOpenContractDate(string $name, mixed $value): void
    {
        $index = $this->contractDatePicker;

        if ($index === null) {
            return;
        }

        $typed = null;

        if (preg_match('/^contractParts\.(\d+)\.date$/', $name, $match) === 1) {
            if ((int) $match[1] !== $index) {
                return;
            }

            $typed = $value;
        } elseif ($name === 'contractParts' && is_array($value)) {
            $part = $value[$index] ?? null;
            $typed = is_array($part) ? ($part['date'] ?? null) : null;
        }

        if (! is_string($typed)) {
            return;
        }

        $parsed = AppDate::parse($typed);

        if ($parsed === null) {
            return;
        }

        $this->contractPickerYear = (int) $parsed->year;
        $this->contractPickerMonth = (int) $parsed->month;
    }

    public function openContractDate(int $index): void
    {
        if (! isset($this->contractParts[$index]) || ! is_array($this->contractParts[$index])) {
            return;
        }

        if ($this->contractDatePicker === $index) {
            $this->contractDatePicker = null;

            return;
        }

        $parsed = AppDate::parse((string) ($this->contractParts[$index]['date'] ?? ''));
        $cursor = $parsed ?? CarbonImmutable::now('Europe/Riga');
        $this->contractPickerYear = (int) $cursor->year;
        $this->contractPickerMonth = (int) $cursor->month;
        $this->contractDatePicker = $index;
    }

    public function shiftContractDateMonth(int $delta): void
    {
        if ($this->contractDatePicker === null) {
            return;
        }

        $cursor = CarbonImmutable::create(
            $this->contractPickerYear,
            $this->contractPickerMonth,
            1,
            0,
            0,
            0,
            'Europe/Riga',
        )->addMonths(max(-24, min(24, $delta)));

        if ($cursor->year < 1900 || $cursor->year > 2100) {
            return;
        }

        $this->contractPickerYear = (int) $cursor->year;
        $this->contractPickerMonth = (int) $cursor->month;
    }

    public function pickContractDate(int $day): void
    {
        $index = $this->contractDatePicker;

        if ($index === null || ! isset($this->contractParts[$index]) || ! is_array($this->contractParts[$index])) {
            return;
        }

        if (! checkdate($this->contractPickerMonth, $day, $this->contractPickerYear)) {
            return;
        }

        $this->contractParts[$index]['date'] = sprintf('%02d.%02d.%04d', $day, $this->contractPickerMonth, $this->contractPickerYear);
        $this->contractDatePicker = null;
    }

    /**
     * @return list<string>
     */
    public function contractPickerWeekdays(): array
    {
        if (app()->getLocale() === 'lv') {
            return ['Pr', 'Ot', 'Tr', 'Ce', 'Pk', 'Se', 'Sv'];
        }

        return ['пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс'];
    }

    public function contractPickerHeading(): string
    {
        $months = app()->getLocale() === 'lv'
            ? [1 => 'Janvāris', 2 => 'Februāris', 3 => 'Marts', 4 => 'Aprīlis', 5 => 'Maijs', 6 => 'Jūnijs', 7 => 'Jūlijs', 8 => 'Augusts', 9 => 'Septembris', 10 => 'Oktobris', 11 => 'Novembris', 12 => 'Decembris']
            : [1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель', 5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август', 9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'];

        return ($months[$this->contractPickerMonth] ?? '').' '.$this->contractPickerYear;
    }

    /**
     * @return list<int|null>
     */
    public function contractPickerCells(): array
    {
        $first = CarbonImmutable::create(
            $this->contractPickerYear,
            $this->contractPickerMonth,
            1,
            0,
            0,
            0,
            'Europe/Riga',
        );
        $cells = array_fill(0, $first->dayOfWeekIso - 1, null);

        for ($day = 1; $day <= $first->daysInMonth; $day++) {
            $cells[] = $day;
        }

        return $cells;
    }

    public function openCredit(): void
    {
        $this->showExpense = false;
        $this->showContract = false;
        $this->showNew = true;
        $this->chooseBank();
        $this->resetErrorBag();
    }

    public function cancelContract(): void
    {
        $this->showContract = false;
        $this->resetContractForm();
    }

    public function saveExpense(PeriodGenerator $periods): void
    {
        $this->expenseAmount = Money::normalize($this->expenseAmount);
        $object = $this->ownedObject();
        $name = trim($this->expenseName);
        $cents = Money::toCents($this->expenseAmount);
        $date = $this->parseDate($this->expenseDate);

        if ($name === '') {
            throw ValidationException::withMessages(['expenseName' => __('Укажите название.')]);
        }

        if ($cents === null || $cents <= 0) {
            throw ValidationException::withMessages(['expenseAmount' => __('Укажите сумму.')]);
        }

        if ($date === null) {
            throw ValidationException::withMessages(['expenseDate' => __('Укажите дату.')]);
        }

        $category = $object->category;

        if ($category === null) {
            throw ValidationException::withMessages(['expenseName' => __('Сначала сохраните место категории.')]);
        }

        $categoryId = null;

        DB::transaction(function () use ($periods, $object, $category, $name, $cents, $date, &$categoryId): void {
            $child = $this->childCategory($category, $name);
            $this->putPlanLine($periods, $object, $child, $name, $cents, $date, 'expenseName');
            $categoryId = (int) $child->id;
        });

        $this->rememberIfOurs($object, $categoryId);

        $this->showExpense = false;
        $this->expenseName = '';
        $this->expenseAmount = '';
        $this->expenseDate = '';
        session()->flash('status', __('Расход записан.'));
    }

    public function saveContract(PeriodGenerator $periods): void
    {
        foreach ($this->contractParts as $index => $part) {
            if (! is_array($part)) {
                continue;
            }

            $part['amount'] = Money::normalize((string) ($part['amount'] ?? ''));
            $this->contractParts[$index] = $part;
        }

        $object = $this->ownedObject();
        $title = trim($this->contractTitle);
        $note = trim($this->contractNote);
        $category = $object->category;

        if ($title === '') {
            throw ValidationException::withMessages(['contractTitle' => __('Укажите название.')]);
        }

        if ($category === null) {
            throw ValidationException::withMessages(['contractTitle' => __('Сначала сохраните место категории.')]);
        }

        $parts = [];

        foreach ($this->contractParts as $index => $part) {
            $cents = Money::toCents((string) ($part['amount'] ?? ''));
            $date = $this->parseDate((string) ($part['date'] ?? ''));

            if (($part['amount'] ?? '') === '' && ($part['date'] ?? '') === '') {
                continue;
            }

            if ($cents === null || $cents <= 0) {
                throw ValidationException::withMessages(['contractParts.'.$index.'.amount' => __('Укажите сумму.')]);
            }

            if ($date === null) {
                throw ValidationException::withMessages(['contractParts.'.$index.'.date' => __('Укажите дату.')]);
            }

            $parts[] = ['cents' => $cents, 'date' => $date];
        }

        if ($parts === []) {
            throw ValidationException::withMessages(['contractParts' => __('Укажите сумму и дату.')]);
        }

        $stored = null;

        if ($this->contractDocument instanceof TemporaryUploadedFile) {
            $stored = ContractDocuments::put($this->contractDocument, 'contractDocument');
        }

        $categoryId = null;
        $removePath = null;
        $wasEdit = $this->editingContractId !== null;

        try {
            DB::transaction(function () use ($periods, $object, $category, $title, $note, $parts, $stored, $wasEdit, &$categoryId, &$removePath): void {
                $child = $this->childCategory($category, $title, 'contractTitle');

                if ($wasEdit) {
                    $contract = $this->ownedContract((int) $this->editingContractId);
                    $lineIds = $this->syncContractParts($periods, $object, $child, $contract, $title, $parts);
                    $removePath = $this->replacedDocumentPath($contract, $stored);
                    $contract->update([
                        'title' => $title,
                        'note' => $note === '' ? null : $note,
                        ...$this->documentColumns($contract, $stored),
                    ]);
                    $contract->planLines()->sync($lineIds);
                } else {
                    $lineIds = [];

                    foreach ($parts as $part) {
                        $line = $this->putPlanLine($periods, $object, $child, $title, $part['cents'], $part['date'], 'contractTitle', true);
                        $lineIds[] = $line->id;
                    }

                    $contract = PropertyContract::query()->create([
                        'property_object_id' => $object->id,
                        'title' => $title,
                        'note' => $note === '' ? null : $note,
                        'document_path' => $stored['document_path'] ?? null,
                        'document_mime' => $stored['document_mime'] ?? null,
                        'document_name' => $stored['document_name'] ?? null,
                    ]);
                    $contract->planLines()->sync($lineIds);
                }

                $categoryId = (int) $child->id;
            });
        } catch (\Throwable $exception) {
            ContractDocuments::delete($stored['document_path'] ?? null);

            throw $exception;
        }

        if (is_string($removePath) && $removePath !== ($stored['document_path'] ?? null)) {
            ContractDocuments::delete($removePath);
        }

        $this->rememberIfOurs($object, $categoryId);

        $this->showContract = false;
        $this->resetContractForm();
        session()->flash('status', $wasEdit ? __('Контракт сохранён.') : __('Контракт записан.'));
    }

    private function resetContractForm(): void
    {
        $this->editingContractId = null;
        $this->dropContractDocument = false;
        $this->contractTitle = '';
        $this->contractNote = '';
        $this->contractParts = [['amount' => '', 'date' => '']];
        $this->contractDocument = null;
        $this->contractDatePicker = null;
        $this->resetErrorBag();
    }

    protected function afterDebtRecorded(Debt $debt): void
    {
        $object = $this->ownedObject();
        $parent = $object->category;

        if ($parent === null) {
            return;
        }

        DB::transaction(function () use ($debt, $object, $parent): void {
            $schedule = $this->childCategory($parent, $debt->counterparty, 'newCounterparty');
            $lineIds = $debt->installments()
                ->whereNotNull('plan_line_id')
                ->pluck('plan_line_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($lineIds !== []) {
                PlanLine::query()
                    ->whereIn('id', $lineIds)
                    ->whereDoesntHave('transactions')
                    ->update(['category_id' => $schedule->id]);

                foreach ($lineIds as $lineId) {
                    $this->claimPlanLine($object, PlanLine::query()->findOrFail($lineId), 'newCounterparty');
                }
            }

            $object->debts()->syncWithoutDetaching([$debt->id]);
        });

        if (! in_array($debt->id, $this->normalizeIds($this->debtIds), true)) {
            $this->debtIds[] = $debt->id;
        }
    }

    public function save(): void
    {
        $this->name = trim($this->name);
        $this->label = trim($this->label);

        $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'kind' => ['required', 'in:apartment,car'],
            'label' => ['required', 'string', 'max:120'],
        ], [
            'name.required' => __('Укажите название.'),
            'name.max' => __('Название слишком длинное.'),
            'kind.required' => __('Выберите недвижимое или движимое имущество.'),
            'kind.in' => __('Выберите недвижимое или движимое имущество.'),
            'label.required' => __('Укажите адрес или регистрационный номер.'),
            'label.max' => __('Подпись слишком длинная.'),
        ]);

        $household = $this->household();
        $debtIds = $this->normalizeIds($this->debtIds);
        $kind = PropertyObjectKind::from($this->kind);
        $createdId = null;

        DB::transaction(function () use ($household, $debtIds, $kind, &$createdId): void {
            $existingIds = $this->objectId === null
                ? []
                : $this->ownedObject()->planLines()->allRelatedIds()->map(fn ($id): int => (int) $id)->all();
            $lineIds = $this->resolveLineIds($household->id, $this->normalizeIds($this->categoryIds), $existingIds, $debtIds);
            if ($this->objectId === null) {
                $object = PropertyObject::query()->create([
                    'household_id' => $household->id,
                    'name' => $this->name,
                    'kind' => $kind,
                    'label' => $this->label,
                ]);
                $createdId = $object->id;
            } else {
                $object = $this->ownedObject();
                $object->update([
                    'name' => $this->name,
                    'kind' => $kind,
                    'label' => $this->label,
                ]);
            }

            $this->placeObjectCategory($object, $household);
            $this->guardLinks($household->id, $object->id, $debtIds, $lineIds);
            $object->debts()->sync($debtIds);
            $object->planLines()->sync($lineIds);
        });

        session()->flash('status', __('Карточка сохранена.'));

        if ($createdId !== null) {
            $this->redirect(route('objects.edit', $createdId));
        }
    }

    public function delete(): void
    {
        $object = $this->ownedObject();

        if ($object->debts()->exists()) {
            throw ValidationException::withMessages([
                'delete' => __('Нельзя удалить карточку, пока к ней привязан долг. Отвяжите долг и сохраните. Сам долг в бюджете останется.'),
            ]);
        }

        DB::transaction(function () use ($object): void {
            $category = $object->category;
            $object->delete();

            if ($category instanceof Category) {
                $this->releaseObjectCategory($category);
            }
        });

        $this->redirect(route('objects.index'));
    }

    /**
     * @return list<string>
     */
    protected function dayFirstDateFields(): array
    {
        return ['expenseDate', 'contractParts', 'newDate', 'newEnd', 'newReset'];
    }

    public function render()
    {
        $this->presentDayFirstDates();
        $household = $this->household();
        $debts = Debt::query()
            ->where('household_id', $household->id)
            ->with('category:id,name')
            ->orderBy('counterparty')
            ->orderBy('id')
            ->get();

        $picker = $this->planPicker($household->id, $this->objectId);
        $object = $this->objectId === null ? null : $this->ownedObject();
        $contracts = $object === null
            ? collect()
            : $object->contracts()->with(['planLines' => fn ($query) => $query->orderBy('expected_on')->orderBy('id')])->get();
        $draftDocument = $this->showContract ? $this->draftDocumentPreview() : null;
        $editingContract = $contracts->firstWhere('id', $this->editingContractId);
        $savedContractDocument = $editingContract instanceof PropertyContract
            && ! $this->dropContractDocument
            && $draftDocument === null
            && $editingContract->hasViewableDocument()
            ? $editingContract
            : null;

        return view('livewire.budget.property-object-form', [
            'debts' => $debts,
            'debtTakenBy' => $this->takenDebts($household->id, $this->objectId),
            'planRows' => $picker['rows'],
            'takenCategories' => $picker['taken'],
            'hasExpenseCategories' => $picker['hasAny'],
            'debtAttached' => $object !== null && $object->debts()->exists(),
            'parentChoices' => $this->parentChoices($household, $object?->category_id),
            'contracts' => $contracts,
            'draftDocument' => $draftDocument,
            'savedContractDocument' => $savedContractDocument,
            'replacingDocument' => $editingContract instanceof PropertyContract
                && ! $this->dropContractDocument
                && $editingContract->hasViewableDocument(),
            'operatingAccounts' => $household->accounts()->where('role', \App\Enums\AccountRole::Operating)->orderBy('sort')->orderBy('id')->get(),
            'preview' => $this->showNew ? $this->preview() : null,
        ])->title(__('Карточка объекта — Family Money'));
    }

    private function household(): Household
    {
        return auth()->user()->currentHousehold();
    }

    private function suggestPlacement(Household $household): void
    {
        $home = $this->homeCategory($household);
        $this->placeMode = $home === null ? 'root' : 'under';
        $this->parentCategoryId = $home?->id;
    }

    private function fillPlacement(PropertyObject $object): void
    {
        $category = $object->category;

        if ($category === null) {
            $this->suggestPlacement($object->household);

            return;
        }

        if ($category->parent_id === null) {
            $this->placeMode = 'root';
            $this->parentCategoryId = $this->homeCategory($object->household)?->id;

            return;
        }

        $this->placeMode = 'under';
        $this->parentCategoryId = (int) $category->parent_id;
    }

    private function homeCategory(Household $household): ?Category
    {
        return Category::query()
            ->where('household_id', $household->id)
            ->where('type', CashflowType::Expense)
            ->where('name', 'Дом')
            ->orderBy('id')
            ->first();
    }

    private function placeObjectCategory(PropertyObject $object, Household $household): Category
    {
        $parentId = null;

        if ($this->placeMode === 'under') {
            $parent = Category::query()
                ->where('household_id', $household->id)
                ->where('type', CashflowType::Expense)
                ->find($this->parentCategoryId);

            if (! $parent instanceof Category) {
                throw ValidationException::withMessages(['parentCategoryId' => __('Выберите категорию.')]);
            }

            $parentId = (int) $parent->id;
        }

        $category = $object->category;

        if (! $category instanceof Category) {
            $taken = PropertyObject::query()
                ->where('household_id', $household->id)
                ->whereNotNull('category_id')
                ->pluck('category_id');
            $category = Category::query()
                ->where('household_id', $household->id)
                ->where('type', CashflowType::Expense)
                ->where('name', $object->name)
                ->whereNotIn('id', $taken)
                ->orderBy('id')
                ->first();

            if ($category instanceof Category && $parentId !== null && (int) $category->id === $parentId) {
                $category = null;
            }

            if (! $category instanceof Category) {
                $category = Category::query()->create([
                    'household_id' => $household->id,
                    'parent_id' => $parentId,
                    'type' => CashflowType::Expense,
                    'name' => $object->name,
                    'sort' => ((int) Category::query()->where('household_id', $household->id)->max('sort')) + 1,
                ]);
            }

            $object->update(['category_id' => $category->id]);
        }

        if ($parentId !== null && $this->categoryIsUnder($household, $parentId, (int) $category->id)) {
            throw ValidationException::withMessages(['parentCategoryId' => __('Выберите категорию.')]);
        }

        if ((int) ($category->parent_id ?? 0) !== (int) ($parentId ?? 0)) {
            $category->update(['parent_id' => $parentId]);
        }

        $category = $category->fresh();
        $this->foldSameName($category);
        $this->nameTheObjectCategory($category->fresh(), $object->name);
        $category = $category->fresh();
        $this->foldSameName($category);
        $descendants = CategoryTree::descendantIds(
            Category::query()->where('household_id', $household->id)->get(['id', 'parent_id']),
            (int) $category->id,
        );

        foreach (Category::query()->whereIn('id', $descendants)->orderBy('id')->get() as $child) {
            $this->foldSameName($child);
        }

        return $category;
    }

    private function categoryIsUnder(Household $household, int $categoryId, int $ancestorId): bool
    {
        if ($categoryId === $ancestorId) {
            return true;
        }

        $categories = Category::query()->where('household_id', $household->id)->get(['id', 'parent_id']);

        return in_array($categoryId, CategoryTree::descendantIds($categories, $ancestorId), true);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Category>
     */
    private function parentChoices(Household $household, ?int $objectCategoryId)
    {
        $categories = Category::query()
            ->where('household_id', $household->id)
            ->where('type', CashflowType::Expense)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'parent_id']);
        $skip = $objectCategoryId === null
            ? []
            : [$objectCategoryId, ...CategoryTree::descendantIds($categories, $objectCategoryId)];

        return $categories
            ->reject(fn (Category $category): bool => in_array((int) $category->id, $skip, true))
            ->values();
    }

    private function rememberCategory(int $categoryId): void
    {
        $key = (string) $categoryId;

        if (! in_array($key, array_map(strval(...), $this->categoryIds), true)) {
            $this->categoryIds[] = $key;
        }
    }

    /**
     * The object folder wears the card name. An empty leaf of that name is
     * folded by the usual rule. A folder that already has children, a debt
     * header, or another object's category stays put, and the card keeps its
     * own folder instead of opening a second one.
     */
    private function nameTheObjectCategory(Category $category, string $name): void
    {
        if ($category->name === $name) {
            return;
        }

        $others = Category::query()
            ->where('household_id', $category->household_id)
            ->where('type', $category->type)
            ->where('name', $name)
            ->whereKeyNot($category->id)
            ->orderBy('id')
            ->get();

        $owner = $this->objectOwning($category);

        foreach ($others as $other) {
            if ($this->heldByAnotherObject($owner, $other) || ! $this->categoryCanJoin($category, $other)) {
                throw ValidationException::withMessages(['name' => __('Такая категория уже есть.')]);
            }
        }

        $category->update(['name' => $name]);
    }

    /**
     * One expense name lives in one place. A category already under this object
     * is reused. A free category of the same name is moved here. A leaf that
     * already belongs to another object stays on that card: the new line is
     * filed there and the folder is not pulled across. A debt header, an object
     * folder, and a folder with children are refused instead of copied.
     */
    private function childCategory(Category $parent, string $name, string $errorKey = 'expenseName'): Category
    {
        $parent->loadMissing('household');
        $current = PropertyObject::query()->where('category_id', $parent->id)->first();
        $sameName = Category::query()
            ->where('household_id', $parent->household_id)
            ->where('type', CashflowType::Expense)
            ->where('name', $name)
            ->orderBy('id')
            ->get();

        $existing = $sameName->first(
            fn (Category $candidate): bool => (int) ($candidate->parent_id ?? 0) === (int) $parent->id,
        );

        if ($existing instanceof Category) {
            $this->foldSameName($existing);

            return $existing->fresh();
        }

        $heldLeaf = $sameName->first(function (Category $candidate) use ($parent, $current): bool {
            return $this->heldByAnotherObject($current, $candidate)
                && $this->categoryCanBeObjectFolder($candidate)
                && ! $this->categoryIsUnder($parent->household, (int) $parent->id, (int) $candidate->id);
        });

        if ($heldLeaf instanceof Category) {
            $this->foldSameName($heldLeaf);

            return $heldLeaf->fresh();
        }

        $movable = $sameName->first(
            fn (Category $candidate): bool => $this->categoryCanJoin($parent, $candidate),
        );

        if ($movable instanceof Category) {
            if ((int) ($movable->parent_id ?? 0) !== (int) $parent->id) {
                $movable->update(['parent_id' => $parent->id]);
            }

            $this->foldSameName($movable);

            return $movable->fresh();
        }

        if ($sameName->isNotEmpty()) {
            throw ValidationException::withMessages([$errorKey => __('Такая категория уже есть.')]);
        }

        return Category::query()->create([
            'household_id' => $parent->household_id,
            'parent_id' => $parent->id,
            'type' => CashflowType::Expense,
            'name' => $name,
            'sort' => ((int) Category::query()->where('household_id', $parent->household_id)->max('sort')) + 1,
        ]);
    }

    private function foldSameName(Category $keeper): void
    {
        $owner = $this->objectOwning($keeper);
        $others = Category::query()
            ->where('household_id', $keeper->household_id)
            ->where('type', $keeper->type)
            ->where('name', $keeper->name)
            ->whereKeyNot($keeper->id)
            ->orderBy('id')
            ->get();

        foreach ($others as $extra) {
            if ($this->heldByAnotherObject($owner, $extra) || ! $this->categoryCanJoin($keeper, $extra)) {
                continue;
            }

            $this->repointCategory($extra, $keeper);
            $extra->delete();
        }
    }

    private function objectOwning(Category $category): ?PropertyObject
    {
        $category->loadMissing('household');
        $objects = PropertyObject::query()
            ->where('household_id', $category->household_id)
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->get();

        foreach ($objects as $object) {
            if ((int) $object->category_id === (int) $category->id) {
                return $object;
            }

            if ($this->categoryIsUnder($category->household, (int) $category->id, (int) $object->category_id)) {
                return $object;
            }
        }

        return null;
    }

    /**
     * True when another card already owns this category: it sits in that
     * card's folder, or one of its plan lines is pivoted to that card.
     */
    private function heldByAnotherObject(?PropertyObject $current, Category $candidate): bool
    {
        $candidate->loadMissing('household');
        $others = PropertyObject::query()
            ->where('household_id', $candidate->household_id)
            ->whereNotNull('category_id')
            ->when($current instanceof PropertyObject, fn ($query) => $query->whereKeyNot($current->id))
            ->orderBy('id')
            ->get(['id', 'category_id']);

        foreach ($others as $other) {
            if ((int) $other->category_id === (int) $candidate->id) {
                return true;
            }

            if ($this->categoryIsUnder($candidate->household, (int) $candidate->id, (int) $other->category_id)) {
                return true;
            }
        }

        $lineIds = PlanLine::query()->where('category_id', $candidate->id)->pluck('id');

        if ($lineIds->isEmpty()) {
            return false;
        }

        return DB::table('property_object_plan_lines')
            ->whereIn('plan_line_id', $lineIds)
            ->when($current instanceof PropertyObject, fn ($query) => $query->where('property_object_id', '!=', $current->id))
            ->exists();
    }

    /**
     * A leaf expense can join the object. An object folder, a debt header such
     * as «Кредит», and any category that still has children stay where they are.
     */
    private function categoryCanJoin(Category $parent, Category $candidate): bool
    {
        if ((int) $candidate->id === (int) $parent->id) {
            return false;
        }

        if (! $this->categoryCanBeObjectFolder($candidate)) {
            return false;
        }

        $parent->loadMissing('household');

        return ! $this->categoryIsUnder($parent->household, (int) $parent->id, (int) $candidate->id);
    }

    private function categoryCanBeObjectFolder(Category $candidate): bool
    {
        if ($candidate->children()->exists()) {
            return false;
        }

        if (PropertyObject::query()->where('category_id', $candidate->id)->exists()) {
            return false;
        }

        return ! Debt::query()->where('category_id', $candidate->id)->exists();
    }

    private function repointCategory(Category $extra, Category $keeper): void
    {
        PlanLine::query()->where('category_id', $extra->id)->update(['category_id' => $keeper->id]);
        Transaction::query()->where('category_id', $extra->id)->update(['category_id' => $keeper->id]);
        PlanTemplate::query()->where('category_id', $extra->id)->update(['category_id' => $keeper->id]);

        $rows = DB::table('shopping_list_categories')->where('category_id', $extra->id)->get();

        foreach ($rows as $row) {
            $taken = DB::table('shopping_list_categories')
                ->where('shopping_list_id', $row->shopping_list_id)
                ->where('category_id', $keeper->id)
                ->exists();

            if ($taken) {
                DB::table('shopping_list_categories')->where('id', $row->id)->delete();
            } else {
                DB::table('shopping_list_categories')->where('id', $row->id)->update(['category_id' => $keeper->id]);
            }
        }

        DB::table('shopping_list_items')->where('category_id', $extra->id)->update(['category_id' => $keeper->id]);
    }

    private function putPlanLine(PeriodGenerator $periods, PropertyObject $object, Category $category, string $title, int $cents, CarbonImmutable $date, string $errorKey = 'expenseName', bool $forContract = false): PlanLine
    {
        $period = $periods->periodFor($object->household, $date);
        $existing = PlanLine::query()
            ->where('period_id', $period->id)
            ->where('category_id', $category->id)
            ->where('title', $title)
            ->where('amount_cents', $cents)
            ->whereDate('expected_on', $date->toDateString())
            ->first();

        if ($existing instanceof PlanLine) {
            $this->claimPlanLine($object, $existing, $errorKey, $forContract);

            return $existing;
        }

        $line = $periods->addPlanLine($period, $category, $title, $cents, $date);
        $this->claimPlanLine($object, $line, $errorKey, $forContract);

        return $line;
    }

    /**
     * Attach a plan line to this card. A line already on another card is left
     * there, so the unique plan_line_id pivot cannot collide.
     */
    private function claimPlanLine(PropertyObject $object, PlanLine $line, string $errorKey, bool $forContract = false): void
    {
        $ownerId = DB::table('property_object_plan_lines')
            ->where('plan_line_id', $line->id)
            ->value('property_object_id');

        if ($ownerId !== null && (int) $ownerId !== (int) $object->id) {
            throw ValidationException::withMessages([
                $errorKey => __('Эта строка плана уже привязана к другому объекту.'),
            ]);
        }

        if ($forContract && DB::table('property_contract_plan_lines')->where('plan_line_id', $line->id)->exists()) {
            throw ValidationException::withMessages([
                $errorKey => __('Эта строка плана уже привязана к другому объекту.'),
            ]);
        }

        if ($ownerId === null) {
            $object->planLines()->syncWithoutDetaching([$line->id]);
        }
    }

    private function rememberIfOurs(PropertyObject $object, int $categoryId): void
    {
        $lineIds = PlanLine::query()->where('category_id', $categoryId)->pluck('id');
        $shared = $lineIds->isNotEmpty() && DB::table('property_object_plan_lines')
            ->whereIn('plan_line_id', $lineIds)
            ->where('property_object_id', '!=', $object->id)
            ->exists();

        if (! $shared) {
            $this->rememberCategory($categoryId);
        }
    }

    /**
     * The card goes away. A folder that already holds expenses or plan lines
     * stays in the budget. An empty folder of the same name is removed so the
     * name is not left in a second place.
     */
    private function releaseObjectCategory(Category $category): void
    {
        $fresh = $category->fresh();

        if (! $fresh instanceof Category) {
            return;
        }

        $name = $fresh->name;
        $householdId = (int) $fresh->household_id;
        $type = $fresh->type;
        $keepId = (int) $fresh->id;

        if (! $this->categoryIsProtectedFolder($fresh) && ! $this->categoryHasBudgetUse($fresh)) {
            $fresh->delete();
            $keepId = 0;
        }

        $this->removeEmptyDuplicateNames($householdId, $type, $name, $keepId);
    }

    private function categoryIsProtectedFolder(Category $category): bool
    {
        if (PropertyObject::query()->where('category_id', $category->id)->exists()) {
            return true;
        }

        return Debt::query()->where('category_id', $category->id)->exists();
    }

    private function categoryHasBudgetUse(Category $category): bool
    {
        if ($category->children()->exists() || $category->planLines()->exists() || $category->transactions()->exists() || $category->templates()->exists()) {
            return true;
        }

        if (DB::table('shopping_list_categories')->where('category_id', $category->id)->exists()) {
            return true;
        }

        return DB::table('shopping_list_items')->where('category_id', $category->id)->exists();
    }

    private function removeEmptyDuplicateNames(int $householdId, CashflowType $type, string $name, int $keepId): void
    {
        $duplicates = Category::query()
            ->where('household_id', $householdId)
            ->where('type', $type)
            ->where('name', $name)
            ->when($keepId > 0, fn ($query) => $query->whereKeyNot($keepId))
            ->orderBy('id')
            ->get();

        foreach ($duplicates as $duplicate) {
            if ($this->categoryIsProtectedFolder($duplicate) || $this->categoryHasBudgetUse($duplicate)) {
                continue;
            }

            $duplicate->delete();
        }
    }

    /**
     * @param  list<array{cents: int, date: CarbonImmutable}>  $parts
     * @return list<int>
     */
    private function syncContractParts(PeriodGenerator $periods, PropertyObject $object, Category $child, PropertyContract $contract, string $title, array $parts): array
    {
        $existing = $contract->planLines()->orderBy('expected_on')->orderBy('id')->get();
        $used = [];
        $lineIds = [];

        foreach ($parts as $part) {
            $match = $existing->first(function (PlanLine $line) use ($part, $title, $used): bool {
                return ! in_array($line->id, $used, true)
                    && $line->title === $title
                    && (int) $line->amount_cents === $part['cents']
                    && $line->expected_on?->toDateString() === $part['date']->toDateString();
            });

            if ($match instanceof PlanLine) {
                $used[] = $match->id;
                $lineIds[] = $match->id;

                continue;
            }

            $spare = $existing->first(fn (PlanLine $line): bool => ! in_array($line->id, $used, true));

            if ($spare instanceof PlanLine) {
                $kept = $this->retargetPlanLine($periods, $object, $child, $spare, $title, $part['cents'], $part['date']);
                $used[] = $spare->id;
                $lineIds[] = $kept->id;

                continue;
            }

            $created = $this->putPlanLine($periods, $object, $child, $title, $part['cents'], $part['date'], 'contractTitle', true);
            $lineIds[] = $created->id;
        }

        foreach ($existing as $line) {
            if (! in_array($line->id, $lineIds, true)) {
                $this->dropUnusedContractLine($line);
            }
        }

        return $lineIds;
    }

    private function retargetPlanLine(PeriodGenerator $periods, PropertyObject $object, Category $child, PlanLine $line, string $title, int $cents, CarbonImmutable $date): PlanLine
    {
        $period = $periods->periodFor($object->household, $date);
        $twin = PlanLine::query()
            ->where('period_id', $period->id)
            ->where('category_id', $child->id)
            ->where('title', $title)
            ->where('amount_cents', $cents)
            ->whereDate('expected_on', $date->toDateString())
            ->whereKeyNot($line->id)
            ->first();

        if ($twin instanceof PlanLine) {
            $this->claimPlanLine($object, $twin, 'contractTitle', true);
            $this->dropUnusedContractLine($line);

            return $twin;
        }

        $line->update([
            'period_id' => $period->id,
            'category_id' => $child->id,
            'title' => $title,
            'amount_cents' => $cents,
            'expected_on' => $date->toDateString(),
        ]);

        return $line;
    }

    private function dropUnusedContractLine(PlanLine $line): void
    {
        if ($line->transactions()->exists() || $line->installment()->exists()) {
            return;
        }

        $line->delete();
    }

    /**
     * @param  array{document_path: string, document_mime: string, document_name: string}|null  $stored
     */
    private function replacedDocumentPath(PropertyContract $contract, ?array $stored): ?string
    {
        $previous = $contract->document_path;

        if (! is_string($previous) || $previous === '') {
            return null;
        }

        if ($stored !== null) {
            return $previous === $stored['document_path'] ? null : $previous;
        }

        return $this->dropContractDocument ? $previous : null;
    }

    /**
     * @param  array{document_path: string, document_mime: string, document_name: string}|null  $stored
     * @return array{document_path: ?string, document_mime: ?string, document_name: ?string}
     */
    private function documentColumns(PropertyContract $contract, ?array $stored): array
    {
        if ($stored !== null) {
            return $stored;
        }

        if ($this->dropContractDocument) {
            return [
                'document_path' => null,
                'document_mime' => null,
                'document_name' => null,
            ];
        }

        return [
            'document_path' => $contract->document_path,
            'document_mime' => $contract->document_mime,
            'document_name' => $contract->document_name,
        ];
    }

    private function ownedObject(): PropertyObject
    {
        $object = PropertyObject::query()->findOrFail($this->objectId);
        abort_unless((int) $object->household_id === (int) $this->household()->id, 404);

        return $object;
    }

    private function ownedContract(int $contractId): PropertyContract
    {
        $contract = $this->ownedObject()->contracts()->whereKey($contractId)->first();
        abort_unless($contract instanceof PropertyContract, 404);

        return $contract;
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private function normalizeIds(array $ids): array
    {
        $clean = [];

        foreach ($ids as $id) {
            $id = (int) $id;

            if ($id > 0) {
                $clean[$id] = $id;
            }
        }

        return array_values($clean);
    }

    /**
     * @param  list<int>  $debtIds
     * @param  list<int>  $lineIds
     */
    private function guardLinks(int $householdId, int $objectId, array $debtIds, array $lineIds): void
    {
        $messages = [];

        if ($debtIds !== []) {
            $owned = Debt::query()
                ->where('household_id', $householdId)
                ->whereIn('id', $debtIds)
                ->pluck('id');

            if ($owned->count() !== count($debtIds)) {
                $messages['debtIds'] = __('Выберите долг своей семьи.');
            } elseif (DB::table('property_object_debts')
                ->whereIn('debt_id', $debtIds)
                ->where('property_object_id', '!=', $objectId)
                ->exists()) {
                $messages['debtIds'] = __('Этот долг уже привязан к другому объекту.');
            }
        }

        if ($lineIds !== []) {
            $owned = PlanLine::query()
                ->whereIn('id', $lineIds)
                ->whereHas('period', fn ($query) => $query->where('household_id', $householdId))
                ->pluck('id');

            if ($owned->count() !== count($lineIds)) {
                $messages['planLineIds'] = __('Выберите строку плана своей семьи.');
            } elseif (DB::table('property_object_plan_lines')
                ->whereIn('plan_line_id', $lineIds)
                ->where('property_object_id', '!=', $objectId)
                ->exists()) {
                $messages['planLineIds'] = __('Эта строка плана уже привязана к другому объекту.');
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    /**
     * @param  list<int>  $lineIds
     * @return list<string>
     */
    private function categoryIdsForLines(int $householdId, array $lineIds): array
    {
        if ($lineIds === []) {
            return [];
        }

        $ids = [];
        $scheduleCategories = $this->scheduleCategories($householdId);

        foreach ($this->expenseLineIdsByCategory($householdId) as $categoryId => $idsInCategory) {
            if (isset($scheduleCategories[$categoryId])) {
                continue;
            }

            foreach ($idsInCategory as $id) {
                if (in_array($id, $lineIds, true)) {
                    $ids[$categoryId] = (string) $categoryId;
                    break;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * A checked category attaches every plan line that belongs directly to it.
     * Income lines, and lines of a category another object already holds, stay
     * as they are. Nothing new is created.
     *
     * @param  list<int>  $categoryIds
     * @param  list<int>  $existingIds
     * @param  list<int>  $debtIds
     * @return list<int>
     */
    private function resolveLineIds(int $householdId, array $categoryIds, array $existingIds, array $debtIds): array
    {
        $byCategory = $this->expenseLineIdsByCategory($householdId);
        $scheduleCategories = $this->scheduleCategories($householdId);
        $scheduleLines = [];

        foreach ($scheduleCategories as $categoryId => $debtId) {
            foreach ($byCategory[$categoryId] ?? [] as $lineId) {
                $scheduleLines[$lineId] = $debtId;
            }
        }

        $lineCategory = [];

        foreach ($byCategory as $categoryId => $ids) {
            foreach ($ids as $id) {
                $lineCategory[$id] = $categoryId;
            }
        }

        $takenCategories = [];
        $takenLines = [];

        foreach ($this->takenLines($householdId, $this->objectId) as $lineId => $name) {
            $lineId = (int) $lineId;
            $takenLines[$lineId] = true;
            $categoryId = $lineCategory[$lineId] ?? null;

            if ($categoryId !== null) {
                $takenCategories[$categoryId] = true;
            }
        }

        $selected = [];

        foreach ($categoryIds as $categoryId) {
            if (isset($scheduleCategories[$categoryId]) || ! isset($byCategory[$categoryId])) {
                continue;
            }

            array_push($selected, ...$byCategory[$categoryId]);
        }

        $keep = [];

        foreach ($existingIds as $id) {
            if (isset($scheduleLines[$id])) {
                continue;
            }

            $categoryId = $lineCategory[$id] ?? null;

            if ($categoryId === null || isset($takenCategories[$categoryId])) {
                $keep[] = $id;
            }
        }

        $checkedDebts = array_fill_keys($debtIds, true);
        $fromDebts = [];

        foreach ($scheduleLines as $lineId => $debtId) {
            if (isset($checkedDebts[$debtId]) && ! isset($takenLines[$lineId])) {
                $fromDebts[] = $lineId;
            }
        }

        return array_values(array_unique([...$selected, ...$keep, ...$fromDebts]));
    }

    /**
     * @return array{rows: list<array<string, mixed>>, taken: list<array<string, mixed>>, hasAny: bool}
     */
    private function planPicker(int $householdId, ?int $excludeObjectId): array
    {
        $byCategory = $this->expenseLineIdsByCategory($householdId);
        $scheduleCategories = $this->scheduleCategories($householdId);
        $categories = Category::query()
            ->where('household_id', $householdId)
            ->where('type', CashflowType::Expense)
            ->get(['id', 'name', 'parent_id']);
        $byId = $categories->keyBy(fn (Category $category): int => (int) $category->id);
        $takenBy = $this->takenLines($householdId, $excludeObjectId);
        $open = [];
        $taken = [];

        foreach ($byCategory as $categoryId => $lineIds) {
            if (isset($scheduleCategories[$categoryId])) {
                continue;
            }

            $category = $byId->get($categoryId);

            if (! $category instanceof Category) {
                continue;
            }

            $owner = null;

            foreach ($lineIds as $lineId) {
                if (isset($takenBy[$lineId])) {
                    $owner = $takenBy[$lineId];
                    break;
                }
            }

            if ($owner !== null) {
                $taken[] = [
                    'id' => $categoryId,
                    'name' => $category->name,
                    'takenName' => $owner,
                ];

                continue;
            }

            $open[$categoryId] = $category;
        }

        $search = mb_strtolower(trim($this->planSearch));
        $visible = [];

        foreach ($open as $categoryId => $category) {
            if ($search !== '' && ! $this->categoryMatchesSearch($byId, $category, $search)) {
                continue;
            }

            $this->includeWithAncestors($visible, $byId, $category);
        }

        $rows = [];

        if ($visible !== []) {
            foreach (CategoryTree::choices(collect($visible)) as $choice) {
                $category = $choice['category'];
                $id = (int) $category->id;
                $rows[] = [
                    'id' => $id,
                    'name' => (string) $category->name,
                    'depth' => (int) $choice['depth'],
                    'check' => isset($open[$id]),
                ];
            }
        }

        $hasAny = $open !== [] || $taken !== [];

        if ($search !== '') {
            $taken = array_values(array_filter(
                $taken,
                fn (array $row): bool => str_contains(mb_strtolower($row['name']), $search),
            ));
        }

        usort($taken, fn (array $a, array $b): int => mb_strtolower($a['name']) <=> mb_strtolower($b['name']));

        return [
            'rows' => $rows,
            'taken' => $taken,
            'hasAny' => $hasAny,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Category>  $byId
     */
    private function categoryMatchesSearch($byId, Category $category, string $search): bool
    {
        $cursor = $category;
        $guard = 0;

        while ($guard < 30) {
            if (str_contains(mb_strtolower((string) $cursor->name), $search)) {
                return true;
            }

            $parentId = (int) ($cursor->parent_id ?? 0);

            if ($parentId === 0 || ! $byId->has($parentId)) {
                return false;
            }

            $cursor = $byId->get($parentId);
            $guard++;
        }

        return false;
    }

    /**
     * @param  array<int, Category>  $visible
     * @param  \Illuminate\Support\Collection<int, Category>  $byId
     */
    private function includeWithAncestors(array &$visible, $byId, Category $category): void
    {
        $cursor = $category;
        $guard = 0;

        while ($guard < 30 && ! isset($visible[(int) $cursor->id])) {
            $visible[(int) $cursor->id] = $cursor;
            $parentId = (int) ($cursor->parent_id ?? 0);

            if ($parentId === 0 || ! $byId->has($parentId)) {
                break;
            }

            $cursor = $byId->get($parentId);
            $guard++;
        }
    }

    /**
     * A category whose own plan lines are one debt's installments, and which is
     * not the category the debt itself is filed under. «Кредит» stays, because
     * that is the debt's category. «Luminor Bank» under «Прушу» is hidden.
     *
     * @return array<int, int> category id => debt id
     */
    private function scheduleCategories(int $householdId): array
    {
        $lineDebt = [];

        $installments = DebtInstallment::query()
            ->whereNotNull('plan_line_id')
            ->whereHas('debt', fn ($query) => $query->where('household_id', $householdId))
            ->get(['debt_id', 'plan_line_id']);

        foreach ($installments as $installment) {
            $lineDebt[(int) $installment->plan_line_id] = (int) $installment->debt_id;
        }

        $filedUnder = Debt::query()
            ->where('household_id', $householdId)
            ->pluck('category_id', 'id');
        $schedule = [];

        foreach ($this->expenseLineIdsByCategory($householdId) as $categoryId => $lineIds) {
            $debtIds = [];

            foreach ($lineIds as $lineId) {
                if (! isset($lineDebt[$lineId])) {
                    $debtIds = [];
                    break;
                }

                $debtIds[$lineDebt[$lineId]] = true;
            }

            if (count($debtIds) !== 1) {
                continue;
            }

            $debtId = (int) array_key_first($debtIds);

            if ((int) ($filedUnder[$debtId] ?? 0) === $categoryId) {
                continue;
            }

            $schedule[$categoryId] = $debtId;
        }

        return $schedule;
    }

    /**
     * @return array<int, list<int>>
     */
    private function expenseLineIdsByCategory(int $householdId): array
    {
        $rows = PlanLine::query()
            ->whereHas('period', fn ($query) => $query->where('household_id', $householdId))
            ->whereHas('category', fn ($query) => $query->where('household_id', $householdId)->where('type', CashflowType::Expense))
            ->orderBy('id')
            ->get(['id', 'category_id']);
        $byCategory = [];

        foreach ($rows as $line) {
            $byCategory[(int) $line->category_id][] = (int) $line->id;
        }

        return $byCategory;
    }

    /**
     * @return array<int, string>
     */
    private function takenDebts(int $householdId, ?int $excludeObjectId): array
    {
        return DB::table('property_object_debts')
            ->join('property_objects', 'property_objects.id', '=', 'property_object_debts.property_object_id')
            ->where('property_objects.household_id', $householdId)
            ->when($excludeObjectId !== null, fn ($query) => $query->where('property_object_debts.property_object_id', '!=', $excludeObjectId))
            ->pluck('property_objects.name', 'property_object_debts.debt_id')
            ->map(fn ($name): string => (string) $name)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function takenLines(int $householdId, ?int $excludeObjectId): array
    {
        return DB::table('property_object_plan_lines')
            ->join('property_objects', 'property_objects.id', '=', 'property_object_plan_lines.property_object_id')
            ->where('property_objects.household_id', $householdId)
            ->when($excludeObjectId !== null, fn ($query) => $query->where('property_object_plan_lines.property_object_id', '!=', $excludeObjectId))
            ->pluck('property_objects.name', 'property_object_plan_lines.plan_line_id')
            ->map(fn ($name): string => (string) $name)
            ->all();
    }
}
