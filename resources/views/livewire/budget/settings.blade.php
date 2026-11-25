<div class="space-y-6">
    <div>
        <x-back-link :href="route('dashboard', ['period' => $period->id])">{{ __('← К периоду') }}</x-back-link>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('Категории') }}</h1>
        <p class="mt-2 max-w-2xl text-stone-600">{{ __('Название можно поменять. Материнскую категорию можно назначить, сменить или убрать. Категорию вместе с её подкатегориями можно вложить в другую. Кнопки «Удалить» нет, если внутри есть подкатегории или по категории уже есть план, операции или повторяющаяся строка.') }}</p>
    </div>

    @if (session('status'))
        <p class="rounded-2xl bg-tide-soft px-4 py-3 text-base text-tide">{{ session('status') }}</p>
    @endif

    <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
        <h2 class="text-lg font-medium">{{ __('Профиль') }}</h2>
        <p class="mt-1 text-base text-stone-600">{{ __('Почта для входа: :email. Для смены нужен текущий пароль.', ['email' => auth()->user()->email]) }}</p>
        @if ($showEmailForm)
            <form wire:submit="saveEmail" class="mt-4 grid grid-cols-1 gap-2 sm:max-w-md">
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Новая почта') }}</span>
                    <input type="email" wire:model="profileEmail" autocomplete="username" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('profileEmail') ? 'field-invalid' : '' }}">
                </label>
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Текущий пароль') }}</span>
                    <input type="password" wire:model="currentPassword" autocomplete="current-password" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('currentPassword') ? 'field-invalid' : '' }}">
                </label>
                @error('profileEmail') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                @error('currentPassword') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="w-full rounded-2xl bg-emerald-600 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Сохранить') }}</button>
                    <button type="button" wire:click="cancelEmailForm" class="w-full rounded-2xl bg-rose-600 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Отмена') }}</button>
                </div>
            </form>
        @elseif ($showPasswordForm)
            <form wire:submit="savePassword" class="mt-4 grid grid-cols-1 gap-2 sm:max-w-md">
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Текущий пароль') }}</span>
                    <input type="password" wire:model="currentPassword" autocomplete="current-password" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('currentPassword') ? 'field-invalid' : '' }}">
                </label>
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Новый пароль') }}</span>
                    <input type="password" wire:model="newPassword" autocomplete="new-password" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('newPassword') ? 'field-invalid' : '' }}">
                </label>
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Пароль ещё раз') }}</span>
                    <input type="password" wire:model="newPassword_confirmation" autocomplete="new-password" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('newPassword') ? 'field-invalid' : '' }}">
                </label>
                @error('currentPassword') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                @error('newPassword') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="w-full rounded-2xl bg-emerald-600 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Сохранить') }}</button>
                    <button type="button" wire:click="cancelPasswordForm" class="w-full rounded-2xl bg-rose-600 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Отмена') }}</button>
                </div>
            </form>
        @else
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" wire:click="openEmailForm" class="w-full rounded-2xl bg-emerald-600 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Сменить почту') }}</button>
                <button type="button" wire:click="openPasswordForm" class="w-full rounded-2xl bg-sky-800 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Сменить пароль') }}</button>
            </div>
        @endif
    </section>

    <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
        <h2 class="text-lg font-medium">{{ __('Семья') }}</h2>
        <p class="mt-1 text-base text-stone-600">{{ __('Один бюджет на всех. Каждый входит со своей почтой. Когда кто-то записывает приход, расход, перевод или строку плана, у остальных это появляется сразу, вместе с уведомлением.') }}</p>
        <ul class="mt-3 space-y-1 text-lg">
            @foreach ($household->users()->orderBy('id')->get() as $member)
                <li>{{ $member->name }} — {{ $member->email }}</li>
            @endforeach
        </ul>
        <form wire:submit="inviteMember" class="mt-4 grid grid-cols-1 gap-2 sm:max-w-md">
            <label class="block">
                <span class="text-base text-stone-600">{{ __('Имя') }}</span>
                <input wire:model="memberName" maxlength="80" autocomplete="off" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('memberName') ? 'field-invalid' : '' }}">
            </label>
            <label class="block">
                <span class="text-base text-stone-600">{{ __('Почта') }}</span>
                <input type="email" wire:model="memberEmail" maxlength="190" autocomplete="off" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('memberEmail') ? 'field-invalid' : '' }}">
            </label>
            @error('memberName') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
            @error('memberEmail') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
            <button type="submit" class="w-full rounded-2xl bg-sky-800 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Пригласить') }}</button>
        </form>
    </section>

    <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
        <h2 class="text-lg font-medium">{{ __('Счета') }}</h2>
        <p class="mt-1 text-base text-stone-600">{{ __('Счета собраны в два списка: расчётный и накопительный. У накопительного можно указать % годовых — проценты начисляются каждый день. Кнопки «Удалить» нет, если на счёте есть остаток или по нему уже был перевод.') }}</p>
        @error('accountDelete') <p class="mt-2 text-base text-rose-700">{{ $message }}</p> @enderror
        <div class="mt-3 overflow-hidden rounded-2xl border border-stone-200">
            @foreach (\App\Enums\AccountRole::cases() as $role)
                @php
                    $groupAccounts = $accounts->filter(fn ($account) => $account->role === $role)->values();
                    $groupOpen = $openAccountGroup === $role->value;
                @endphp
                <div class="{{ $loop->first ? '' : 'border-t border-stone-200' }}">
                    <button type="button" wire:click="toggleAccountGroup('{{ $role->value }}')" class="flex w-full items-center justify-between gap-2 px-3 py-3 text-left text-lg font-medium" aria-expanded="{{ $groupOpen ? 'true' : 'false' }}">
                        <span>{{ $role->label() }}</span>
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true" class="shrink-0 text-stone-500" @if ($groupOpen) style="transform: rotate(180deg)" @endif>
                            <path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    @if ($groupOpen)
                        <ul class="space-y-2 px-3 pb-3 text-lg" style="max-height: 16rem; overflow-y: auto; overscroll-behavior: contain">
                            @forelse ($groupAccounts as $account)
                                <li class="flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-stone-50 px-3 py-2">
                                    @if ($editingAccountId === $account->id)
                                        <form wire:submit="saveAccount" class="flex w-full flex-wrap items-center gap-2">
                                            <input wire:model="editAccountName" class="min-w-40 flex-1 rounded-2xl border border-stone-200 bg-white px-3 py-2 {{ $errors->has('editAccountName') ? 'field-invalid' : '' }}" aria-label="{{ __('Название счёта') }}">
                                            <select wire:model.live="editAccountRole" aria-label="{{ __('Тип счёта') }}" class="rounded-2xl border border-stone-200 bg-white px-3 py-2 {{ $errors->has('editAccountRole') ? 'field-invalid' : '' }}">
                                                @foreach (\App\Enums\AccountRole::cases() as $option)
                                                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                                @endforeach
                                            </select>
                                            @if ($editAccountRole === \App\Enums\AccountRole::Savings->value)
                                                <label class="flex items-center gap-2 text-base text-stone-600">
                                                    <span class="whitespace-nowrap">{{ __('% годовых') }}</span>
                                                    <input wire:model="editAccountAnnualRate" inputmode="decimal" placeholder="0" class="w-24 rounded-2xl border border-stone-200 bg-white px-3 py-2 tabular-nums {{ $errors->has('editAccountAnnualRate') ? 'field-invalid' : '' }}" aria-label="{{ __('Годовая ставка') }}">
                                                </label>
                                            @endif
                                            <label class="flex items-center gap-2 text-base text-stone-600">
                                                <span class="whitespace-nowrap">{{ __('Изменить остаток') }}</span>
                                                <input wire:model="editAccountBalance" data-money inputmode="decimal" placeholder="0,00" class="w-28 rounded-2xl border border-stone-200 bg-white px-3 py-2 tabular-nums {{ $errors->has('editAccountBalance') ? 'field-invalid' : '' }}" aria-label="{{ __('Изменить остаток') }}">
                                            </label>
                                            <button type="submit" class="rounded-full bg-emerald-600 px-3 py-1.5 text-base font-medium text-white">{{ __('Сохранить') }}</button>
                                            <button type="button" wire:click="cancelAccountEdit" class="rounded-full bg-rose-600 px-3 py-1.5 text-base font-medium text-white">{{ __('Отмена') }}</button>
                                            @error('editAccountName') <p class="basis-full text-base text-rose-700">{{ $message }}</p> @enderror
                                            @error('editAccountRole') <p class="basis-full text-base text-rose-700">{{ $message }}</p> @enderror
                                            @error('editAccountAnnualRate') <p class="basis-full text-base text-rose-700">{{ $message }}</p> @enderror
                                            @error('editAccountBalance') <p class="basis-full text-base text-rose-700">{{ $message }}</p> @enderror
                                        </form>
                                    @else
                                        <span class="min-w-0 break-words">
                                            {{ $account->name }}
                                            @if ($account->role === \App\Enums\AccountRole::Savings && (int) $account->annual_rate_bps > 0)
                                                <span class="ml-1 text-base text-emerald-800">{{ $account->rateLabel() }}</span>
                                            @endif
                                        </span>
                                        <span class="flex shrink-0 items-center gap-3">
                                            <span class="tabular-nums text-stone-500">{{ money($account->balance_cents) }}</span>
                                            <button type="button" wire:click="editAccount({{ $account->id }})" class="text-base font-medium text-sky-800">{{ __('Изменить') }}</button>
                                            @if ((int) $account->balance_cents === 0 && (int) $account->outgoing_transfers_count === 0 && (int) $account->incoming_transfers_count === 0)
                                                <button type="button" wire:click="deleteAccount({{ $account->id }})" wire:confirm="{{ __('Удалить счёт «:name»?', ['name' => $account->name]) }}" class="text-base font-medium text-rose-700">{{ __('Удалить') }}</button>
                                            @endif
                                        </span>
                                    @endif
                                </li>
                            @empty
                                <li class="px-1 text-base text-stone-500">{{ __('Пока нет счетов.') }}</li>
                            @endforelse
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
        @if ($showNewAccount)
            <form wire:submit="addAccount" class="mt-4 flex flex-wrap gap-2">
                <input wire:model="accountName" placeholder="{{ __('Новый счёт') }}" class="min-w-40 flex-1 rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('accountName') ? 'field-invalid' : '' }}">
                <select wire:model.live="accountRole" aria-label="{{ __('Тип нового счёта') }}" class="rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('accountRole') ? 'field-invalid' : '' }}">
                    @foreach (\App\Enums\AccountRole::cases() as $role)
                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                    @endforeach
                </select>
                @if ($accountRole === \App\Enums\AccountRole::Savings->value)
                    <label class="flex items-center gap-2 text-base text-stone-600">
                        <span class="whitespace-nowrap">{{ __('% годовых') }}</span>
                        <input wire:model="accountAnnualRate" inputmode="decimal" placeholder="0" class="w-24 rounded-2xl border border-stone-200 px-3 py-3 tabular-nums {{ $errors->has('accountAnnualRate') ? 'field-invalid' : '' }}" aria-label="{{ __('Годовая ставка') }}">
                    </label>
                @endif
                <button type="submit" class="rounded-2xl bg-emerald-600 px-4 py-3 text-base font-medium text-white">{{ __('Сохранить') }}</button>
                <button type="button" wire:click="cancelNewAccount" class="rounded-2xl bg-rose-600 px-4 py-3 text-base font-medium text-white">{{ __('Отмена') }}</button>
            </form>
            @error('accountName') <p class="mt-2 text-base text-rose-700">{{ $message }}</p> @enderror
            @error('accountRole') <p class="mt-2 text-base text-rose-700">{{ $message }}</p> @enderror
            @error('accountAnnualRate') <p class="mt-2 text-base text-rose-700">{{ $message }}</p> @enderror
        @else
            <button type="button" wire:click="openNewAccount" class="mt-4 w-full rounded-2xl bg-emerald-600 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Создать новый счёт') }}</button>
        @endif
    </section>

    <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
        <h2 class="text-lg font-medium">{{ __('Категории') }}</h2>
        @error('categoryDelete') <p class="mt-2 text-base text-rose-700">{{ $message }}</p> @enderror
        <ul class="mt-3 text-lg rounded-2xl" style="max-height: 16rem; overflow-y: auto; overscroll-behavior: contain">
            @php
                $categoryParents = $categories->whereNull('parent_id')->values();
                $categoryChildren = $categories->groupBy('parent_id');
                $canDelete = function ($item): bool {
                    return (int) $item->children_count === 0
                        && (int) $item->plan_lines_count === 0
                        && (int) $item->transactions_count === 0
                        && (int) $item->templates_count === 0;
                };
            @endphp
            @foreach ($categoryParents as $category)
                @include('livewire.budget.partials.category-node', ['category' => $category, 'depth' => 0])
            @endforeach
        </ul>
        @if ($showNewCategory)
            <form wire:submit="addCategory" class="mt-4 grid grid-cols-1 gap-2 sm:flex sm:flex-wrap">
                <input wire:model="categoryName" placeholder="{{ __('Новая категория') }}" class="w-full min-w-0 rounded-2xl border border-stone-200 px-3 py-3 sm:min-w-48 sm:flex-1 {{ $errors->has('categoryName') ? 'field-invalid' : '' }}">
                <select wire:model.live="categoryType" class="w-full rounded-2xl border border-stone-200 px-3 py-3 sm:w-auto">
                    <option value="expense">{{ __('Расход') }}</option>
                    <option value="income">{{ __('Поступление') }}</option>
                </select>
                <div class="w-full min-w-0 sm:min-w-48 sm:flex-1">
                    @include('livewire.budget.partials.category-picker', [
                        'property' => 'categoryParentId',
                        'value' => $categoryParentId,
                        'categories' => $categories->filter(fn ($category) => $category->type->value === $categoryType),
                        'placeholder' => __('Без материнской'),
                        'label' => __('Материнская категория'),
                    ])
                </div>
                @include('livewire.budget.partials.icon-picker', ['iconProperty' => 'categoryIcon', 'iconValue' => $categoryIcon])
                <div class="flex w-full flex-wrap gap-2">
                    <button type="submit" class="w-full rounded-2xl bg-emerald-600 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Сохранить') }}</button>
                    <button type="button" wire:click="cancelNewCategory" class="w-full rounded-2xl bg-rose-600 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Отмена') }}</button>
                </div>
            </form>
            @error('categoryName') <p class="mt-2 text-base text-rose-700">{{ $message }}</p> @enderror
            @error('categoryParentId') <p class="mt-2 text-base text-rose-700">{{ $message }}</p> @enderror
            @error('categoryIcon') <p class="mt-2 text-base text-rose-700">{{ $message }}</p> @enderror
        @else
            <button type="button" wire:click="openNewCategory" class="mt-4 w-full rounded-2xl bg-emerald-600 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Создать категорию') }}</button>
        @endif
    </section>

    <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
        <h2 class="text-lg font-medium">{{ __('Повторяется каждый период') }}</h2>
        <p class="mt-1 text-base text-stone-600">{{ __('Отмеченные категории сами появляются в плане каждого месяца. Примечание, сумма и дата необязательны. Снятая галочка убирает строку начиная со следующего месяца. Поля открываются при новой отметке, а у уже отмеченных — по нажатию на название.') }}</p>
        @once
            <style>
                .repeating-row summary { list-style: none; cursor: pointer; }
                .repeating-row summary::-webkit-details-marker { display: none; }
                .repeating-row[open] summary svg { transform: rotate(180deg); }
            </style>
        @endonce
        <form wire:submit="syncTemplates" class="mt-4">
            <div class="rounded-2xl border border-stone-200" style="max-height: 16rem; overflow-y: auto; overscroll-behavior: contain">
                @foreach (\App\Support\CategoryTree::choices($categories) as $choice)
                    @php
                        $category = $choice['category'];
                        $choicePad = number_format(0.75 + ((int) $choice['depth'] * 1.25), 2, '.', '');
                        $repeating = $choice['selectable'] && in_array((string) $category->id, array_map('strval', $templateCategoryIds), true);
                        $expanded = in_array((int) $category->id, array_map('intval', $openRepeatingIds), true)
                            || $errors->has('templateNotes.'.$category->id)
                            || $errors->has('templateAmounts.'.$category->id)
                            || $errors->has('templateDates.'.$category->id);
                    @endphp
                    @if (! $choice['selectable'])
                        <div class="py-2 text-base text-ink {{ $loop->first ? '' : 'border-t border-stone-100' }}" data-category-choice="{{ $category->id }}" data-depth="{{ $choice['depth'] }}" data-selectable="0" style="padding-left: {{ $choicePad }}rem; padding-right: 0.75rem; font-weight: 700">{{ $category->name }}</div>
                        @continue
                    @endif
                    <div wire:key="repeating-{{ $category->id }}" data-category-choice="{{ $category->id }}" data-depth="{{ $choice['depth'] }}" data-selectable="1" style="padding-left: {{ $choicePad }}rem; padding-right: 0.75rem; font-weight: {{ (int) $choice['depth'] === 0 ? 700 : 400 }}" class="py-2 {{ $loop->first ? '' : 'border-t border-stone-100' }}">
                        <div class="flex items-start gap-2">
                            <input type="checkbox" wire:model.live="templateCategoryIds" value="{{ $category->id }}" class="mt-1 h-4 w-4" aria-label="{{ $category->name }}">
                            @if ($repeating)
                                <details class="repeating-row min-w-0 flex-1" @if ($expanded) open @endif>
                                    <summary class="flex items-center justify-between gap-2 text-base">
                                        <span class="{{ (int) $choice['depth'] === 0 ? 'text-ink' : 'text-stone-700' }}" @if ((int) $choice['depth'] === 0) style="font-weight: 700" @endif>{{ $category->type->label() }} · {{ $category->name }}</span>
                                        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true" class="shrink-0 text-stone-500">
                                            <path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </summary>
                                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                                        <input wire:model="templateNotes.{{ $category->id }}" maxlength="80" placeholder="{{ __('Примечание, можно пусто') }}" class="min-w-0 rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('templateNotes.'.$category->id) ? 'field-invalid' : '' }}">
                                        <input wire:model="templateAmounts.{{ $category->id }}" data-money inputmode="decimal" placeholder="{{ __('Сумма, можно пусто') }}" class="min-w-0 rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('templateAmounts.'.$category->id) ? 'field-invalid' : '' }}">
                                        <input type="date" wire:model="templateDates.{{ $category->id }}" aria-label="{{ __('Дата, можно пусто') }}" class="min-w-0 rounded-2xl border border-stone-200 px-3 py-3 {{ $errors->has('templateDates.'.$category->id) ? 'field-invalid' : '' }}">
                                    </div>
                                    @error('templateNotes.'.$category->id) <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
                                    @error('templateAmounts.'.$category->id) <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
                                    @error('templateDates.'.$category->id) <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
                                </details>
                            @else
                                <span class="text-base {{ (int) $choice['depth'] === 0 ? 'text-ink' : 'text-stone-700' }}" @if ((int) $choice['depth'] === 0) style="font-weight: 700" @endif>{{ $category->type->label() }} · {{ $category->name }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <button type="submit" class="mt-3 w-full rounded-2xl bg-emerald-600 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Сохранить') }}</button>
        </form>
    </section>
    <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-rose-200 sm:p-5">
        <h2 class="text-lg font-medium text-rose-800">{{ __('Удалить аккаунт') }}</h2>
        @if ($lastMember)
            <p class="mt-2 text-base text-stone-700">{{ __('Вы последний в семье. Вместе с входом удалится и бюджет: счета, операции, долги, списки покупок и чеки. Личные записи сейфа и загруженные документы тоже удалятся.') }}</p>
        @else
            <p class="mt-2 text-base text-stone-700">{{ __('Удалится ваш вход, личные записи сейфа и загруженные документы. Общий бюджет останется у других участников семьи.') }}</p>
        @endif
        <form wire:submit="deleteOwnAccount" class="mt-4 space-y-3">
            <label class="block">
                <span class="text-base text-stone-600">{{ __('Текущий пароль') }}</span>
                <input type="password" wire:model="accountDeletionPassword" autocomplete="current-password" class="mt-1 w-full max-w-sm rounded-2xl border border-stone-200 px-3 py-3 outline-none focus:border-tide">
            </label>
            @error('accountDeletionPassword') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
            <button type="submit" wire:confirm="{{ $lastMember ? __('Удалить аккаунт и весь бюджет семьи? Это нельзя отменить.') : __('Удалить аккаунт? Общий бюджет останется у других участников. Это нельзя отменить.') }}" class="rounded-2xl bg-rose-700 px-4 py-3 text-base font-medium text-white">{{ __('Удалить аккаунт') }}</button>
        </form>
    </section>
    <p class="text-base"><a href="{{ route('privacy') }}" class="font-medium text-sky-800 underline-offset-2 hover:underline">{{ __('Политика конфиденциальности') }}</a></p>
</div>
