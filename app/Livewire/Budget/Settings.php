<?php

namespace App\Livewire\Budget;

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Household;
use App\Models\Transfer;
use App\Models\PlanLine;
use App\Models\PlanTemplate;
use App\Models\User;
use App\Notifications\FamilyInviteNotification;
use App\Services\Budget\PeriodCalendar;
use App\Services\Budget\PeriodGenerator;
use App\Services\Budget\PeriodSummary;
use App\Livewire\Concerns\NormalizesMoneyInput;
use App\Livewire\Concerns\PresentsDayFirstDates;
use App\Rules\AppDay;
use App\Support\AppDate;
use App\Support\CategoryIcons;
use App\Support\CategoryTree;
use App\Support\DeleteOwnAccount;
use App\Support\Money;
use App\Support\PersonalVault;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Livewire\Component;

class Settings extends Component
{
    use NormalizesMoneyInput;
    use PresentsDayFirstDates;

    #[Url(as: 'period')]
    public ?int $periodId = null;

    public int $anchorDay = 1;

    public string $boundStart = '';

    public string $boundEnd = '';

    public string $openingAmount = '';

    public string $accountDeletionPassword = '';

    public bool $openingManual = false;

    public string $categoryName = '';

    public bool $showNewCategory = false;

    public string $categoryType = 'expense';

    public ?int $categoryParentId = null;

    public string $categoryIcon = 'out';

    public ?int $editingCategoryId = null;

    /** @var array<int, int> */
    public array $openCategoryIds = [];

    public string $editCategoryName = '';

    public ?int $editCategoryParentId = null;

    public string $editCategoryIcon = '';

    /** @var array<int, int|string> */
    public array $templateCategoryIds = [];

    /** @var array<int, int> */
    public array $openRepeatingIds = [];

    /** @var array<int|string, string> */
    public array $templateNotes = [];

    /** @var array<int|string, string> */
    public array $templateAmounts = [];

    /** @var array<int|string, string> */
    public array $templateDates = [];

    public string $memberName = '';

    public string $memberEmail = '';

    public string $accountName = '';

    public string $accountRole = 'operating';

    public string $accountAnnualRate = '0';

    public bool $showNewAccount = false;

    public ?string $openAccountGroup = null;

    public ?int $editingAccountId = null;

    public string $editAccountName = '';

    public string $editAccountRole = 'operating';

    public string $editAccountAnnualRate = '0';

    public string $editAccountBalance = '';

    public bool $showEmailForm = false;

    public bool $showPasswordForm = false;

    public string $profileEmail = '';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPassword_confirmation = '';

    public function mount(PeriodGenerator $periods): void
    {
        $household = $this->household();
        $remembered = session('budget.period_id');
        $period = $this->ownedPeriod($this->periodId)
            ?? $this->ownedPeriod(is_numeric($remembered) ? (int) $remembered : null);
        $period ??= $periods->periodFor($household, CarbonImmutable::now($household->timezone));
        $this->fillFrom($household, $period);
    }

    public function saveAnchor(): void
    {
        $this->validate([
            'anchorDay' => ['required', 'integer', 'between:1,31'],
        ], [
            'anchorDay.between' => __('День от 1 до 31.'),
        ]);

        $this->household()->update(['anchor_day' => $this->anchorDay]);
        session()->flash('status', __('Якорный день сохранён. Уже созданные периоды остались на своих датах.'));
    }

    public function saveBounds(PeriodGenerator $periods): void
    {
        $this->validate([
            'boundStart' => ['required', 'date'],
            'boundEnd' => ['required', 'date'],
        ], [
            'boundStart.required' => __('Укажите начало.'),
            'boundEnd.required' => __('Укажите конец.'),
        ]);

        try {
            $periods->updateBounds(
                $this->period(),
                CarbonImmutable::parse($this->boundStart),
                CarbonImmutable::parse($this->boundEnd),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('bounds', $exception->getMessage());

            return;
        }

        $this->fillFrom($this->household(), $this->period()->fresh());
        session()->flash('status', __('Границы этого периода обновлены.'));
    }

    public function align(PeriodGenerator $periods): void
    {
        try {
            $periods->alignToAnchor($this->period());
        } catch (InvalidArgumentException $exception) {
            $this->addError('bounds', $exception->getMessage());

            return;
        }

        $this->fillFrom($this->household(), $this->period()->fresh());
        session()->flash('status', __('Текущий период подогнан под якорный день.'));
    }

    public function saveOpening(PeriodSummary $summary): void
    {
        $period = $this->period();
        $hasPrevious = $this->hasPrevious($period);

        if (! $hasPrevious) {
            $this->openingManual = true;
        }

        if ($this->openingManual || ! $hasPrevious) {
            $cents = Money::toCents($this->openingAmount);

            if ($cents === null) {
                throw ValidationException::withMessages([
                    'openingAmount' => __('Укажите остаток в евро.'),
                ]);
            }

            $period->update([
                'opening_balance_cents' => $cents,
                'opening_manual' => true,
            ]);
        } else {
            $period->update(['opening_manual' => false]);
        }

        $this->openingAmount = Money::toInput($summary->openingCents($period->fresh()));
        session()->flash('status', __('Остаток на начало сохранён.'));
    }

    public function updatedCategoryType(): void
    {
        $previous = CategoryIcons::fallback($this->categoryType === 'income' ? 'expense' : 'income');

        if ($this->categoryIcon === '' || $this->categoryIcon === $previous) {
            $this->categoryIcon = CategoryIcons::fallback($this->categoryType);
        }
    }

    public function addCategory(): void
    {
        $this->categoryIcon = CategoryIcons::resolve($this->categoryIcon, $this->categoryType);

        $this->validate([
            'categoryName' => ['required', 'string', 'max:60'],
            'categoryType' => ['required', 'in:income,expense'],
            'categoryIcon' => ['required', Rule::in(array_keys(CategoryIcons::all()))],
        ], [
            'categoryName.required' => __('Назовите категорию.'),
        ]);

        $household = $this->household();
        $sort = (int) $household->categories()->max('sort') + 1;

        $parentId = $this->resolveParentId($this->categoryType, $this->categoryParentId ? (int) $this->categoryParentId : null, null, 'categoryParentId');

        Category::create([
            'household_id' => $household->id,
            'parent_id' => $parentId,
            'type' => $this->categoryType,
            'name' => trim($this->categoryName),
            'icon' => $this->categoryIcon,
            'sort' => $sort,
            'color' => $this->categoryType === 'income' ? '#0f6e6b' : '#9a3412',
        ]);

        $this->reset('categoryName', 'categoryParentId');
        $this->categoryIcon = CategoryIcons::fallback($this->categoryType);
        $this->showNewCategory = false;

        if ($parentId) {
            $this->openCategoryIds = [$parentId];
        }

        session()->flash('status', __('Категория добавлена.'));
    }

    public function openNewCategory(): void
    {
        $this->showNewCategory = true;
    }

    public function cancelNewCategory(): void
    {
        $this->showNewCategory = false;
        $this->reset('categoryName', 'categoryParentId');
        $this->resetErrorBag(['categoryName', 'categoryIcon']);
        $this->categoryIcon = CategoryIcons::fallback($this->categoryType);
    }

    public function toggleCategory(int $categoryId): void
    {
        $open = array_map('intval', $this->openCategoryIds);

        if ($open === [$categoryId]) {
            $this->closeAccordions();

            return;
        }

        $this->closeAccordions();
        $this->openCategoryIds = [$categoryId];
    }

    public function closeAccordions(): void
    {
        $this->openCategoryIds = [];
        $this->openRepeatingIds = [];
        $this->openAccountGroup = null;
        $this->cancelCategoryEdit();
        $this->cancelAccountEdit();
    }

    public function editCategory(int $categoryId): void
    {
        $category = $this->ownedCategory($categoryId);
        $this->editingCategoryId = $category->id;
        $this->editCategoryName = $category->name;
        $this->editCategoryParentId = $category->parent_id;
        $this->editCategoryIcon = CategoryIcons::resolve($category->icon, $category->type->value);

        if ($category->parent_id) {
            $this->openCategoryIds = [(int) $category->parent_id];
        }
    }

    public function cancelCategoryEdit(): void
    {
        $this->editingCategoryId = null;
        $this->reset('editCategoryName', 'editCategoryParentId', 'editCategoryIcon');
    }

    public function saveCategory(): void
    {
        if ($this->editingCategoryId === null) {
            return;
        }

        $category = $this->ownedCategory($this->editingCategoryId);
        $this->editCategoryIcon = CategoryIcons::resolve($this->editCategoryIcon, $category->type->value);

        $this->validate([
            'editCategoryName' => ['required', 'string', 'max:60'],
            'editCategoryIcon' => ['required', Rule::in(array_keys(CategoryIcons::all()))],
        ], [
            'editCategoryName.required' => __('Напишите название.'),
            'editCategoryName.max' => __('Название короче 60 символов.'),
        ]);

        $previousName = $category->name;
        $name = trim($this->editCategoryName);
        $needle = mb_strtolower($name);
        $duplicate = $this->household()->categories()
            ->where('type', $category->type)
            ->whereKeyNot($category->id)
            ->get()
            ->contains(fn (Category $other) => mb_strtolower($other->name) === $needle);

        if ($duplicate) {
            throw ValidationException::withMessages([
                'editCategoryName' => __('Такая категория уже есть.'),
            ]);
        }

        $parentId = $this->resolveParentId(
            $category->type->value,
            $this->editCategoryParentId ? (int) $this->editCategoryParentId : null,
            $category,
            'editCategoryParentId',
        );

        $category->update([
            'name' => $name,
            'parent_id' => $parentId,
            'icon' => $this->editCategoryIcon,
        ]);

        if ($previousName !== $name) {
            PlanLine::query()
                ->where('category_id', $category->id)
                ->where('title', $previousName)
                ->update(['title' => $name]);
            PlanTemplate::query()
                ->where('category_id', $category->id)
                ->where('title', $previousName)
                ->update(['title' => $name]);
        }

        $this->cancelCategoryEdit();

        if ($parentId) {
            $this->openCategoryIds = [(int) $parentId];
        }

        session()->flash('status', __('Категория сохранена.'));
    }

    private function resolveParentId(string $type, ?int $parentId, ?Category $moving, string $errorKey): ?int
    {
        if (! $parentId) {
            return null;
        }

        $parent = Category::query()
            ->where('household_id', $this->household()->id)
            ->where('type', $type)
            ->find($parentId);

        if ($parent === null) {
            throw ValidationException::withMessages([
                $errorKey => __('Материнская категория должна быть того же вида.'),
            ]);
        }

        if ($moving !== null) {
            $catalog = $this->household()->categories()->get(['id', 'parent_id']);
            $insideItself = (int) $moving->id === (int) $parent->id
                || in_array((int) $parent->id, CategoryTree::descendantIds($catalog, $moving->id), true);

            if ($insideItself) {
                throw ValidationException::withMessages([
                    $errorKey => __('Нельзя вложить категорию саму в себя или в её подкатегорию.'),
                ]);
            }
        }

        return $parent->id;
    }

    public function deleteCategory(int $categoryId): void
    {
        $category = $this->ownedCategory($categoryId);
        $childIds = $category->children()->pluck('id');

        if ($childIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'categoryDelete' => '«'.$category->name.__('» нельзя удалить, пока внутри есть подкатегории.'),
            ]);
        }

        $hasRecords = $category->planLines()->exists()
            || $category->transactions()->exists()
            || $category->household->templates()->where('category_id', $category->id)->exists();

        if ($hasRecords) {
            throw ValidationException::withMessages([
                'categoryDelete' => '«'.$category->name.__('» нельзя удалить: есть записи с этой категорией.'),
            ]);
        }

        if ($this->editingCategoryId === $category->id) {
            $this->cancelCategoryEdit();
        }

        $category->delete();
        session()->flash('status', '«'.$category->name.__('» удалена.'));
    }

    public function syncTemplates(PeriodGenerator $periods): void
    {
        $this->validate([
            'templateCategoryIds' => ['array'],
            'templateCategoryIds.*' => ['integer'],
            'templateNotes.*' => ['nullable', 'string', 'max:80'],
            'templateAmounts.*' => ['nullable', 'regex:/^\d+([.,]\d{1,2})?$/'],
            'templateDates.*' => ['nullable', new AppDay],
        ], [
            'templateNotes.*.max' => __('Примечание не длиннее 80 знаков.'),
            'templateAmounts.*.regex' => __('Сумма в евро, до двух знаков после запятой.'),
        ]);

        $household = $this->household();
        $period = $this->period();
        $visibleIds = $household->categories()->whereNull('archived_at')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $wantedIds = collect($this->templateCategoryIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->intersect($visibleIds)
            ->values();
        $categories = Category::query()->whereIn('id', $wantedIds)->get()->keyBy('id');
        $active = $household->templates()->where('active', true)->orderBy('id')->get()->groupBy(fn (PlanTemplate $template) => (int) $template->category_id);

        foreach ($active as $categoryId => $group) {
            if (! in_array((int) $categoryId, $visibleIds, true) || $wantedIds->contains((int) $categoryId)) {
                continue;
            }

            foreach ($group as $template) {
                $periods->retireTemplate($template, $period);
            }
        }

        foreach ($wantedIds as $categoryId) {
            $category = $categories->get($categoryId);

            if ($category === null) {
                continue;
            }

            $group = $active->get($categoryId, collect());
            $template = $group->first();
            $note = trim($this->repeatingValue($this->templateNotes, $categoryId));
            $amountRaw = trim($this->repeatingValue($this->templateAmounts, $categoryId));
            $dateRaw = trim($this->repeatingValue($this->templateDates, $categoryId));
            $expectedDay = null;

            if ($dateRaw !== '') {
                $expectedDay = AppDate::parse($dateRaw);

                if ($expectedDay === null) {
                    throw ValidationException::withMessages([
                        'templateDates.'.$categoryId => __('Укажите дату.'),
                    ]);
                }
            }

            $cents = $amountRaw === '' ? 0 : Money::toCents($amountRaw);

            if ($cents === null || $cents < 0) {
                throw ValidationException::withMessages([
                    'templateAmounts.'.$categoryId => __('Некорректная сумма.'),
                ]);
            }

            $attributes = [
                'title' => $note !== '' ? $note : $category->name,
                'amount_cents' => $cents,
                'expected_day' => $expectedDay?->day,
                'active' => true,
            ];

            if ($template === null) {
                $template = PlanTemplate::create([
                    'household_id' => $household->id,
                    'category_id' => $category->id,
                    ...$attributes,
                ]);
            } else {
                $template->update($attributes);

                foreach ($group->skip(1) as $extra) {
                    $periods->retireTemplate($extra, $period);
                }
            }

            $periods->placeTemplate($template->fresh('category'), $period);
        }

        $this->loadRepeating($household, $period);
        session()->flash('status', __('Повторяющиеся категории сохранены. Они есть в плане этого месяца и следующих.'));
    }

    public function addAccount(): void
    {
        $this->validate([
            'accountName' => ['required', 'string', 'max:60'],
            'accountRole' => ['required', Rule::enum(AccountRole::class)],
        ], [
            'accountName.required' => __('Назовите счёт.'),
            'accountRole.required' => __('Выберите тип счёта.'),
        ]);

        $household = $this->household();
        $name = trim($this->accountName);
        $rate = $this->parsedAccountRate($this->accountAnnualRate, 'accountAnnualRate');

        if ($household->accounts()->where('name', $name)->exists()) {
            throw ValidationException::withMessages([
                'accountName' => __('Такой счёт уже есть.'),
            ]);
        }

        $household->accounts()->create([
            'name' => $name,
            'kind' => 'bank',
            'role' => $this->accountRole,
            'balance_cents' => 0,
            'annual_rate_bps' => $this->accountRole === AccountRole::Savings->value ? $rate : 0,
            'sort' => (int) $household->accounts()->max('sort') + 10,
        ]);

        $this->reset('accountName', 'accountRole', 'accountAnnualRate');
        $this->accountAnnualRate = '0';
        $this->showNewAccount = false;
        session()->flash('status', __('Счёт добавлен.'));
    }

    public function openEmailForm(): void
    {
        $this->cancelPasswordForm();
        $this->showEmailForm = true;
        $this->profileEmail = (string) auth()->user()->email;
        $this->reset('currentPassword');
        $this->resetErrorBag(['profileEmail', 'currentPassword']);
    }

    public function cancelEmailForm(): void
    {
        $this->showEmailForm = false;
        $this->reset('profileEmail', 'currentPassword');
        $this->resetErrorBag(['profileEmail', 'currentPassword']);
    }

    public function saveEmail(): void
    {
        $this->profileEmail = trim($this->profileEmail);
        $user = auth()->user();

        $this->validate([
            'profileEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'currentPassword' => ['required', 'string', 'current_password'],
        ], [
            'profileEmail.required' => __('Укажите почту.'),
            'profileEmail.email' => __('Почта выглядит неверно.'),
            'profileEmail.unique' => __('Эта почта уже зарегистрирована.'),
            'currentPassword.required' => __('Укажите текущий пароль.'),
            'currentPassword.current_password' => __('Неверный текущий пароль.'),
        ]);

        if (strcasecmp($user->email, $this->profileEmail) === 0) {
            throw ValidationException::withMessages([
                'profileEmail' => __('Это уже ваша почта.'),
            ]);
        }

        $user->forceFill(['email' => $this->profileEmail])->save();
        $this->cancelEmailForm();
        session()->flash('status', __('Почта обновлена.'));
    }

    public function openPasswordForm(): void
    {
        $this->cancelEmailForm();
        $this->showPasswordForm = true;
        $this->reset('currentPassword', 'newPassword', 'newPassword_confirmation');
        $this->resetErrorBag(['currentPassword', 'newPassword']);
    }

    public function cancelPasswordForm(): void
    {
        $this->showPasswordForm = false;
        $this->reset('currentPassword', 'newPassword', 'newPassword_confirmation');
        $this->resetErrorBag(['currentPassword', 'newPassword']);
    }

    public function savePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required', 'string', 'current_password'],
            'newPassword' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'currentPassword.required' => __('Укажите текущий пароль.'),
            'currentPassword.current_password' => __('Неверный текущий пароль.'),
            'newPassword.required' => __('Придумайте пароль.'),
            'newPassword.min' => __('Пароль не короче 8 символов.'),
            'newPassword.confirmed' => __('Пароли не совпадают.'),
        ]);

        auth()->user()->forceFill(['password' => $this->newPassword])->save();
        PersonalVault::clearBlock();
        PersonalVault::lock();
        $this->cancelPasswordForm();
        session()->flash('status', __('Пароль обновлён.'));
    }

    public function openNewAccount(): void
    {
        $this->cancelAccountEdit();
        $this->showNewAccount = true;
        $this->accountAnnualRate = '0';
        $this->resetErrorBag(['accountName', 'accountRole', 'accountAnnualRate']);
    }

    public function cancelNewAccount(): void
    {
        $this->showNewAccount = false;
        $this->reset('accountName', 'accountRole', 'accountAnnualRate');
        $this->accountAnnualRate = '0';
        $this->resetErrorBag(['accountName', 'accountRole', 'accountAnnualRate']);
    }

    public function toggleAccountGroup(string $role): void
    {
        if (! in_array($role, [AccountRole::Operating->value, AccountRole::Savings->value], true)) {
            return;
        }

        if ($this->openAccountGroup === $role) {
            $this->openAccountGroup = null;
            $this->cancelAccountEdit();

            return;
        }

        $this->cancelAccountEdit();
        $this->openAccountGroup = $role;
    }

    public function editAccount(int $accountId): void
    {
        $account = $this->ownedAccount($accountId);
        $this->showNewAccount = false;
        $this->openAccountGroup = $account->role->value;
        $this->editingAccountId = $account->id;
        $this->editAccountName = $account->name;
        $this->editAccountRole = $account->role->value;
        $this->editAccountAnnualRate = Debt::formatMilli((int) $account->annual_rate_bps);
        $this->editAccountBalance = Money::toInput((int) $account->balance_cents);
        $this->resetErrorBag(['editAccountName', 'editAccountRole', 'editAccountAnnualRate', 'editAccountBalance']);
    }

    public function cancelAccountEdit(): void
    {
        $this->editingAccountId = null;
        $this->reset('editAccountName', 'editAccountRole', 'editAccountAnnualRate', 'editAccountBalance');
        $this->editAccountAnnualRate = '0';
        $this->editAccountBalance = '';
    }

    public function saveAccount(): void
    {
        if ($this->editingAccountId === null) {
            return;
        }

        $this->editAccountBalance = Money::normalize($this->editAccountBalance);

        $this->validate([
            'editAccountName' => ['required', 'string', 'max:60'],
            'editAccountRole' => ['required', Rule::enum(AccountRole::class)],
        ], [
            'editAccountName.required' => __('Назовите счёт.'),
            'editAccountRole.required' => __('Выберите тип счёта.'),
        ]);

        $household = $this->household();
        $name = trim($this->editAccountName);
        $rate = $this->parsedAccountRate($this->editAccountAnnualRate, 'editAccountAnnualRate');
        $balanceRaw = trim($this->editAccountBalance);
        $balanceCents = $balanceRaw === '' ? 0 : Money::toCents($balanceRaw);

        if ($balanceCents === null || $balanceCents < 0) {
            throw ValidationException::withMessages([
                'editAccountBalance' => __('Укажите остаток в евро, ноль или больше.'),
            ]);
        }

        $taken = $household->accounts()
            ->where('name', $name)
            ->whereKeyNot($this->editingAccountId)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'editAccountName' => __('Такой счёт уже есть.'),
            ]);
        }

        $this->ownedAccount($this->editingAccountId)->update([
            'name' => $name,
            'role' => $this->editAccountRole,
            'annual_rate_bps' => $this->editAccountRole === AccountRole::Savings->value ? $rate : 0,
            'balance_cents' => $balanceCents,
        ]);
        $this->cancelAccountEdit();
        session()->flash('status', __('Счёт сохранён.'));
    }

    public function deleteAccount(int $accountId): void
    {
        $account = $this->ownedAccount($accountId);

        if ((int) $account->balance_cents !== 0) {
            $this->addError('accountDelete', __('Счёт с остатком удалить нельзя.'));

            return;
        }

        $usedInTransfer = Transfer::query()
            ->where('household_id', $account->household_id)
            ->where(function ($query) use ($account) {
                $query->where('from_account_id', $account->id)
                    ->orWhere('to_account_id', $account->id);
            })
            ->exists();

        if ($usedInTransfer) {
            $this->addError('accountDelete', __('По счёту есть переводы, удалить его нельзя.'));

            return;
        }

        $account->delete();

        if ($this->editingAccountId === $accountId) {
            $this->cancelAccountEdit();
        }

        session()->flash('status', __('Счёт удалён.'));
    }

    public function inviteMember(): void
    {
        $this->validate([
            'memberName' => ['required', 'string', 'max:80'],
            'memberEmail' => ['required', 'email', 'max:190'],
        ], [
            'memberName.required' => __('Укажите имя.'),
            'memberEmail.required' => __('Укажите почту.'),
            'memberEmail.email' => __('Проверьте почту.'),
        ]);

        $household = $this->household();
        $email = mb_strtolower(trim($this->memberEmail));
        $name = trim($this->memberName);

        if ($email === mb_strtolower((string) auth()->user()->email)) {
            throw ValidationException::withMessages([
                'memberEmail' => __('Это ваша почта. Укажите почту другого человека.'),
            ]);
        }

        $existing = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($existing !== null && $household->users()->whereKey($existing->id)->exists()) {
            throw ValidationException::withMessages([
                'memberEmail' => __('Этот человек уже в семье.'),
            ]);
        }

        if ($existing !== null && $existing->households()->exists()) {
            throw ValidationException::withMessages([
                'memberEmail' => __('Этот адрес уже используется в другом бюджете.'),
            ]);
        }

        $created = $existing === null;
        $user = $existing ?? User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Str::password(32),
        ]);

        if (! $created && $user->name !== $name) {
            $user->forceFill(['name' => $name])->save();
        }

        $household->users()->attach($user->id, ['role' => 'member']);

        try {
            $user->notify(new FamilyInviteNotification(
                Password::broker()->createToken($user),
                $household->name,
                auth()->user()->name,
            ));
        } catch (\Throwable) {
            $household->users()->detach($user->id);

            if ($created) {
                $user->delete();
            }

            throw ValidationException::withMessages([
                'memberEmail' => __('Письмо не отправилось. Проверьте адрес и попробуйте ещё раз.'),
            ]);
        }

        $this->reset('memberName', 'memberEmail');
        session()->flash('status', __('Приглашение отправлено на ').$email.__('. По ссылке из письма задаётся пароль, и можно входить в общий бюджет.'));
    }

    /**
     * @return list<string>
     */
    protected function dayFirstDateFields(): array
    {
        return ['templateDates'];
    }

    public function render(PeriodCalendar $calendar, PeriodSummary $summary)
    {
        $this->presentDayFirstDates();
        $household = $this->household();
        $period = $this->period();
        $anchor = max(1, min(31, (int) $this->anchorDay));
        [$previewStart, $previewEnd] = $calendar->boundsFor(
            CarbonImmutable::now($household->timezone),
            $anchor,
        );

        return view('livewire.budget.settings', [
            'period' => $period,
            'household' => $household,
            'categories' => $household->categories()->with('parent')->withCount(['children', 'planLines', 'transactions', 'templates'])->whereNull('archived_at')->orderBy('sort')->get(),
            'accounts' => $household->accounts()->withCount(['outgoingTransfers', 'incomingTransfers'])->orderBy('sort')->get(),
            'previewStart' => $previewStart,
            'previewEnd' => $previewEnd,
            'resolvedOpening' => $summary->openingCents($period),
            'hasPrevious' => $this->hasPrevious($period),
            'lastMember' => $household->users()->where('users.id', '!=', auth()->id())->doesntExist(),
        ])->title(__('Категории — Family Money'));
    }

    private function fillFrom(Household $household, BudgetPeriod $period): void
    {
        $this->periodId = $period->id;
        $this->anchorDay = $household->anchor_day;
        $this->boundStart = $period->starts_on->toDateString();
        $this->boundEnd = $period->ends_on->toDateString();
        $this->openingManual = $period->opening_manual || ! $this->hasPrevious($period);
        $this->openingAmount = Money::toInput(app(PeriodSummary::class)->openingCents($period));
        $this->loadRepeating($household, $period);
    }

    private function loadRepeating(Household $household, BudgetPeriod $period): void
    {
        $calendar = app(PeriodCalendar::class);
        $ids = [];
        $notes = [];
        $amounts = [];
        $dates = [];

        foreach ($household->templates()->with('category')->where('active', true)->orderBy('id')->get() as $template) {
            $id = (int) $template->category_id;

            if (in_array($id, $ids, true) || $template->category === null) {
                continue;
            }

            $ids[] = $id;
            $note = trim((string) $template->title);
            $notes[$id] = $note !== '' && $note !== $template->category->name ? $note : '';
            $amounts[$id] = $template->amount_cents > 0 ? Money::toInput($template->amount_cents) : '';
            $dates[$id] = $calendar->expectedOn($period->starts_on, $period->ends_on, $template->expected_day)?->toDateString() ?? '';
        }

        $this->templateCategoryIds = $ids;
        $this->templateNotes = $notes;
        $this->templateAmounts = $amounts;
        $this->templateDates = $dates;
        $this->openRepeatingIds = [];
    }

    public function updatingTemplateCategoryIds(array $value): void
    {
        $previous = collect($this->templateCategoryIds)->map(fn ($id) => (int) $id);
        $next = collect($value)->map(fn ($id) => (int) $id);

        $this->openRepeatingIds = collect($this->openRepeatingIds)
            ->map(fn ($id) => (int) $id)
            ->merge($next->diff($previous))
            ->diff($previous->diff($next))
            ->unique()
            ->values()
            ->all();
    }

    private function repeatingValue(array $values, int $categoryId): string
    {
        return (string) ($values[$categoryId] ?? $values[(string) $categoryId] ?? '');
    }

    private function hasPrevious(BudgetPeriod $period): bool
    {
        return BudgetPeriod::query()
            ->where('household_id', $period->household_id)
            ->whereDate('ends_on', CarbonImmutable::parse($period->starts_on)->subDay()->toDateString())
            ->exists();
    }

    private function parsedAccountRate(string $raw, string $errorKey): int
    {
        $trimmed = trim($raw);

        if ($trimmed === '') {
            return 0;
        }

        $rate = Debt::milliFromInput($trimmed);

        if ($rate === null) {
            throw ValidationException::withMessages([
                $errorKey => __('Укажите ставку, например 3,5.'),
            ]);
        }

        if ($rate > 100_000) {
            throw ValidationException::withMessages([
                $errorKey => __('Ставка не больше 100%.'),
            ]);
        }

        return $rate;
    }

    private function ownedAccount(int $id): Account
    {
        return Account::query()
            ->where('household_id', $this->household()->id)
            ->findOrFail($id);
    }

    private function ownedCategory(int $id): Category
    {
        return Category::query()
            ->where('household_id', $this->household()->id)
            ->findOrFail($id);
    }

    public function deleteOwnAccount(DeleteOwnAccount $delete): void
    {
        $this->validate([
            'accountDeletionPassword' => ['required', 'string', 'current_password'],
        ], [
            'accountDeletionPassword.required' => __('Укажите пароль.'),
            'accountDeletionPassword.current_password' => __('Неверный пароль.'),
        ]);

        $user = auth()->user();
        Auth::logout();
        $delete($user);
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('home'));
    }

    private function household(): Household
    {
        return auth()->user()->currentHousehold();
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
}
