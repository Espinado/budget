<?php

namespace App\Livewire\Budget;

use App\Enums\CashflowType;
use App\Enums\PropertyObjectKind;
use App\Livewire\Budget\Concerns\RecordsDebt;
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

    public mixed $contractDocument = null;

    /** @var array<int, mixed> */
    public array $replacementFiles = [];

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
        $this->contractParts = [['amount' => '', 'date' => '']];
        $this->contractDocument = null;
        $this->resetErrorBag('contractDocument');
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

    public function updatedReplacementFiles(mixed $value, ?string $key = null): void
    {
        if (! $value instanceof TemporaryUploadedFile || $key === null || ! ctype_digit($key)) {
            return;
        }

        $this->replaceContractDocument((int) $key);
    }

    public function clearContractDocument(): void
    {
        $this->contractDocument = null;
        $this->resetErrorBag('contractDocument');
    }

    public function removeContractDocument(int $contractId): void
    {
        $contract = $this->ownedContract($contractId);
        ContractDocuments::delete($contract->document_path);
        $contract->update([
            'document_path' => null,
            'document_mime' => null,
            'document_name' => null,
        ]);
    }

    public function replaceContractDocument(int $contractId): void
    {
        $file = $this->replacementFiles[$contractId] ?? null;
        unset($this->replacementFiles[$contractId]);

        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        $stored = ContractDocuments::put($file, 'replacementFiles.'.$contractId);
        $contract = $this->ownedContract($contractId);
        $previous = $contract->document_path;
        $contract->update($stored);

        if (is_string($previous) && $previous !== $stored['document_path']) {
            ContractDocuments::delete($previous);
        }
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

    public function openCredit(): void
    {
        $this->showExpense = false;
        $this->showContract = false;
        $this->showNew = true;
        $this->chooseBank();
        $this->resetErrorBag();
    }

    public function saveExpense(PeriodGenerator $periods): void
    {
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

        $child = $this->childCategory($category, $name);
        $this->putPlanLine($periods, $object, $child, $name, $cents, $date);
        $this->rememberCategory($child->id);

        $this->showExpense = false;
        $this->expenseName = '';
        $this->expenseAmount = '';
        $this->expenseDate = '';
        session()->flash('status', __('Расход записан.'));
    }

    public function saveContract(PeriodGenerator $periods): void
    {
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
        $contract = null;

        if ($this->contractDocument instanceof TemporaryUploadedFile) {
            $stored = ContractDocuments::put($this->contractDocument, 'contractDocument');
        }

        try {
            $child = $this->childCategory($category, $title, 'contractTitle');
            $this->rememberCategory($child->id);
            $lineIds = [];

            foreach ($parts as $part) {
                $line = $this->putPlanLine($periods, $object, $child, $title, $part['cents'], $part['date']);
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
        } catch (\Throwable $exception) {
            if ($contract instanceof PropertyContract && $contract->exists) {
                $contract->update([
                    'document_path' => null,
                    'document_mime' => null,
                    'document_name' => null,
                ]);
            }

            ContractDocuments::delete($stored['document_path'] ?? null);

            throw $exception;
        }

        $this->showContract = false;
        $this->contractTitle = '';
        $this->contractNote = '';
        $this->contractParts = [['amount' => '', 'date' => '']];
        $this->contractDocument = null;
        session()->flash('status', __('Контракт записан.'));
    }

    protected function afterDebtRecorded(Debt $debt): void
    {
        $object = $this->ownedObject();
        $parent = $object->category;

        if ($parent === null) {
            return;
        }

        $schedule = $this->childCategory($parent, $debt->counterparty);
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
            $object->planLines()->syncWithoutDetaching($lineIds);
        }

        $object->debts()->syncWithoutDetaching([$debt->id]);

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

        $object->delete();

        $this->redirect(route('objects.index'));
    }

    public function render()
    {
        $household = $this->household();
        $debts = Debt::query()
            ->where('household_id', $household->id)
            ->with('category:id,name')
            ->orderBy('counterparty')
            ->orderBy('id')
            ->get();

        $picker = $this->planPicker($household->id, $this->objectId);
        $object = $this->objectId === null ? null : $this->ownedObject();

        return view('livewire.budget.property-object-form', [
            'debts' => $debts,
            'debtTakenBy' => $this->takenDebts($household->id, $this->objectId),
            'planRows' => $picker['rows'],
            'takenCategories' => $picker['taken'],
            'hasExpenseCategories' => $picker['hasAny'],
            'debtAttached' => $object !== null && $object->debts()->exists(),
            'parentChoices' => $this->parentChoices($household, $object?->category_id),
            'contracts' => $object === null
                ? collect()
                : $object->contracts()->with(['planLines' => fn ($query) => $query->orderBy('expected_on')->orderBy('id')])->get(),
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

        foreach ($others as $other) {
            if (! $this->categoryCanJoin($category, $other)) {
                throw ValidationException::withMessages(['name' => __('Такая категория уже есть.')]);
            }
        }

        $category->update(['name' => $name]);
    }

    /**
     * One expense name lives in one place. A category already under the object
     * is reused. The same name elsewhere is moved here, and any leftover copy
     * is folded in. Nothing is copied, and a debt's own category stays put.
     */
    private function childCategory(Category $parent, string $name, string $errorKey = 'expenseName'): Category
    {
        $existing = Category::query()
            ->where('household_id', $parent->household_id)
            ->where('type', CashflowType::Expense)
            ->where('parent_id', $parent->id)
            ->where('name', $name)
            ->first();

        if ($existing instanceof Category) {
            $this->foldSameName($existing);

            return $existing->fresh();
        }

        $movable = Category::query()
            ->where('household_id', $parent->household_id)
            ->where('type', CashflowType::Expense)
            ->where('name', $name)
            ->orderBy('id')
            ->get()
            ->first(fn (Category $candidate): bool => $this->categoryCanJoin($parent, $candidate));

        if ($movable instanceof Category) {
            if ((int) ($movable->parent_id ?? 0) !== (int) $parent->id) {
                $movable->update(['parent_id' => $parent->id]);
            }

            $this->foldSameName($movable);

            return $movable->fresh();
        }

        $blocked = Category::query()
            ->where('household_id', $parent->household_id)
            ->where('type', CashflowType::Expense)
            ->where('name', $name)
            ->exists();

        if ($blocked) {
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
        $others = Category::query()
            ->where('household_id', $keeper->household_id)
            ->where('type', $keeper->type)
            ->where('name', $keeper->name)
            ->whereKeyNot($keeper->id)
            ->orderBy('id')
            ->get();

        foreach ($others as $extra) {
            if (! $this->categoryCanJoin($keeper, $extra)) {
                continue;
            }

            $this->repointCategory($extra, $keeper);
            $extra->delete();
        }
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

    private function putPlanLine(PeriodGenerator $periods, PropertyObject $object, Category $category, string $title, int $cents, CarbonImmutable $date): PlanLine
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
            $object->planLines()->syncWithoutDetaching([$existing->id]);

            return $existing;
        }

        $line = $periods->addPlanLine($period, $category, $title, $cents, $date);
        $object->planLines()->syncWithoutDetaching([$line->id]);

        return $line;
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
