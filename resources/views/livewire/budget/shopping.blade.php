<div class="space-y-4 overflow-x-hidden sm:space-y-6">
    <div class="min-w-0">
        <x-back-link :href="route('dashboard')">{{ __('← К бюджету') }}</x-back-link>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('Список покупок') }}</h1>
        <p class="mt-2 max-w-2xl text-lg text-stone-600">{{ __('Что купить по категориям Питания. Галочка — куплено, без записи расхода. Количество и цена необязательны.') }}</p>
        @if (session('status'))
            <p class="mt-3 rounded-2xl bg-emerald-50 px-3 py-2.5 text-base text-emerald-800 sm:px-4 sm:py-3">{{ session('status') }}</p>
        @endif
    </div>

    @if ($creating || $list === null)
        <section class="rounded-3xl bg-white p-3 shadow-sm ring-1 ring-stone-900/5 sm:max-w-lg sm:p-5" data-focus-fields>
            <h2 class="text-lg font-medium">{{ __('Новый список') }}</h2>
            <p class="mt-1 text-base text-stone-600">{{ __('Сначала выберите категорию из дерева Питание. Потом можно добавить другие.') }}</p>
            @if ($foodCategories->isEmpty())
                <p class="mt-4 text-base text-rose-700">{{ __('Категория Питание не найдена. Создайте её в настройках.') }}</p>
            @else
                <form wire:submit="createList" class="mt-4 space-y-3">
                    <div>
                        <span class="text-base text-stone-600">{{ __('Категория') }}</span>
                        @include('livewire.budget.partials.category-picker', [
                            'categories' => $foodCategories,
                            'property' => 'createCategoryId',
                            'value' => $createCategoryId,
                            'placeholder' => __('Выберите'),
                        ])
                        @error('createCategoryId') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="rounded-2xl bg-sky-800 px-4 py-3 text-base font-medium text-white">{{ __('Создать список') }}</button>
                </form>
            @endif
        </section>
    @else
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="archiveChecked" class="rounded-2xl bg-amber-50 px-3 py-2.5 text-sm font-medium text-amber-900 ring-1 ring-amber-100 sm:px-4 sm:py-3 sm:text-base">{{ __('В архив отмеченные') }}</button>
            <button type="button" wire:click="toggleArchive" class="rounded-2xl bg-white px-3 py-2.5 text-sm font-medium text-stone-700 ring-1 ring-stone-900/10 sm:px-4 sm:py-3 sm:text-base">
                {{ $showArchive ? __('Скрыть архив') : __('Показать архив') }}
            </button>
            @if (! $addingCategory)
                <button type="button" wire:click="openAddCategory" class="rounded-2xl bg-sky-800 px-3 py-2.5 text-sm font-medium text-white sm:px-4 sm:py-3 sm:text-base">{{ __('Добавить категорию') }}</button>
            @endif
        </div>

        @if ($addingCategory)
            <section class="rounded-3xl bg-white p-3 shadow-sm ring-1 ring-stone-900/5 sm:max-w-lg sm:p-5" data-focus-fields>
                <h2 class="text-lg font-medium">{{ __('Ещё категория') }}</h2>
                <p class="mt-1 text-base text-stone-600">{{ __('Только из дерева Питание.') }}</p>
                @if ($availableFood->isEmpty())
                    <p class="mt-3 text-base text-stone-600">{{ __('Все категории Питания уже в списке.') }}</p>
                    <button type="button" wire:click="closeAddCategory" class="mt-3 rounded-2xl px-4 py-3 text-base font-medium text-stone-600 ring-1 ring-stone-900/10">{{ __('Закрыть') }}</button>
                @else
                    <form wire:submit="addCategory" class="mt-4 space-y-3">
                        <div>
                            <span class="text-base text-stone-600">{{ __('Категория') }}</span>
                            @include('livewire.budget.partials.category-picker', [
                                'categories' => $availableFood,
                                'property' => 'addCategoryId',
                                'value' => $addCategoryId,
                                'placeholder' => __('Выберите'),
                            ])
                            @error('addCategoryId') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="rounded-2xl bg-sky-800 px-4 py-3 text-base font-medium text-white">{{ __('Добавить') }}</button>
                            <button type="button" wire:click="closeAddCategory" class="rounded-2xl px-4 py-3 text-base font-medium text-stone-600 ring-1 ring-stone-900/10">{{ __('Отмена') }}</button>
                        </div>
                    </form>
                @endif
            </section>
        @endif

        @forelse ($sections as $section)
            <section wire:key="section-{{ $section['category']->id }}" class="min-w-0 overflow-hidden rounded-3xl bg-white p-3 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <h2 class="min-w-0 break-words text-xl font-medium sm:text-2xl">{{ $section['label'] }}</h2>
                    @if ($section['priceSum'] > 0)
                        <p class="shrink-0 text-lg tabular-nums text-stone-600 sm:text-xl">{{ money($section['priceSum']) }}</p>
                    @endif
                </div>
                @if ($section['overPlan'])
                    <p class="mt-2 rounded-2xl bg-amber-50 px-3 py-2 text-base text-amber-900 sm:text-lg">{{ __('Сумма списка больше остатка плана по этой категории. Это только предупреждение.') }}</p>
                @endif

                <ul class="mt-2 divide-y divide-stone-100 sm:mt-3">
                    @forelse ($section['items'] as $item)
                        @php
                            $lineTotal = $item->lineTotalCents();
                            $metaClass = $item->is_checked ? '' : 'text-stone-600';
                        @endphp
                        <li wire:key="item-{{ $item->id }}-{{ $item->is_checked ? '1' : '0' }}" class="py-2.5 sm:py-3">
                            @if ($editingItemId === $item->id)
                                <form wire:submit="saveEdit" class="flex min-w-0 flex-wrap items-center gap-2" data-focus-fields>
                                    <label class="min-w-0 flex-1">
                                        <span class="sr-only">{{ __('Название') }}</span>
                                        <input type="text" wire:model="editName" class="w-full min-w-0 rounded-2xl border border-stone-200 px-3 py-2.5 text-lg {{ $errors->has('editName') ? 'field-invalid' : '' }}" aria-label="{{ __('Название') }}">
                                    </label>
                                    <button type="submit" class="rounded-2xl bg-sky-800 px-3 py-2.5 text-base font-medium text-white sm:px-4 sm:text-lg">{{ __('Сохранить') }}</button>
                                    <button type="button" wire:click="closeEdit" class="rounded-2xl px-3 py-2.5 text-base font-medium text-stone-600 ring-1 ring-stone-900/10 sm:px-4 sm:text-lg">{{ __('Отмена') }}</button>
                                    @error('editName') <p class="basis-full text-base text-rose-700">{{ $message }}</p> @enderror
                                </form>
                            @else
                                <div @class([
                                    'flex min-w-0 flex-wrap items-start gap-x-1.5 gap-y-1 sm:flex-nowrap sm:items-center sm:gap-2',
                                    'text-stone-400' => $item->is_checked,
                                ])>
                                    <input
                                        type="checkbox"
                                        wire:click.prevent="toggleChecked({{ $item->id }})"
                                        @checked($item->is_checked)
                                        class="mt-1 h-6 w-6 shrink-0 rounded border-stone-300 text-sky-800 focus:ring-sky-800 sm:mt-0"
                                        aria-label="{{ $item->name }}"
                                    >
                                    <span @class([
                                        'min-w-0 flex-1 basis-32 break-words whitespace-normal text-lg font-medium sm:basis-auto sm:text-xl',
                                        'line-through' => $item->is_checked,
                                        'text-ink' => ! $item->is_checked,
                                    ])>{{ $item->name }}</span>

                                    <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
                                        @if ($inlineItemId === $item->id && $inlineField === 'quantity')
                                            <input
                                                type="text"
                                                inputmode="decimal"
                                                wire:model="inlineQuantity"
                                                wire:blur="saveInlineEdit"
                                                wire:keydown.enter.prevent="saveInlineEdit"
                                                wire:keydown.escape.prevent="closeInlineEdit"
                                                x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                                                class="w-12 shrink-0 rounded-xl border border-sky-300 bg-white px-1 py-1 text-center text-base tabular-nums sm:w-14 sm:text-lg {{ $item->is_checked ? 'line-through' : '' }}"
                                                aria-label="{{ __('Кол-во') }}"
                                            >
                                        @else
                                            <button
                                                type="button"
                                                wire:click="startInlineEdit({{ $item->id }}, 'quantity')"
                                                class="w-12 shrink-0 truncate rounded-xl px-0.5 py-1 text-center text-base tabular-nums hover:bg-stone-50 sm:w-14 sm:text-lg {{ $metaClass }} {{ $item->is_checked ? 'line-through' : '' }}"
                                                title="{{ __('Кол-во') }}"
                                                aria-label="{{ __('Кол-во') }}: {{ $item->quantity ?: '—' }}"
                                            >{{ $item->quantity ?: '—' }}</button>
                                        @endif

                                        @if ($inlineItemId === $item->id && $inlineField === 'price')
                                            <input
                                                type="text"
                                                inputmode="decimal"
                                                wire:model="inlinePriceAmount"
                                                wire:blur="saveInlineEdit"
                                                wire:keydown.enter.prevent="saveInlineEdit"
                                                wire:keydown.escape.prevent="closeInlineEdit"
                                                x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                                                class="w-[4.75rem] shrink-0 rounded-xl border border-sky-300 bg-white px-1 py-1 text-right text-base tabular-nums sm:w-24 sm:text-lg {{ $errors->has('inlinePriceAmount') ? 'field-invalid' : '' }} {{ $item->is_checked ? 'line-through' : '' }}"
                                                aria-label="{{ __('Цена') }}"
                                            >
                                        @else
                                            <button
                                                type="button"
                                                wire:click="startInlineEdit({{ $item->id }}, 'price')"
                                                class="w-[4.75rem] shrink-0 truncate rounded-xl px-0.5 py-1 text-right text-base tabular-nums hover:bg-stone-50 sm:w-24 sm:text-lg {{ $metaClass }} {{ $item->is_checked ? 'line-through' : '' }}"
                                                title="{{ __('Цена') }}"
                                                aria-label="{{ __('Цена') }}: {{ $item->price_cents !== null ? money($item->price_cents) : '—' }}"
                                            >{{ $item->price_cents !== null ? money($item->price_cents) : '—' }}</button>
                                        @endif

                                        <span @class([
                                            'w-[4.75rem] shrink-0 truncate text-right text-base font-medium tabular-nums sm:w-24 sm:text-lg',
                                            'line-through' => $item->is_checked,
                                            'text-ink' => ! $item->is_checked,
                                        ])>{{ $lineTotal !== null ? money($lineTotal) : '—' }}</span>

                                        <div class="flex shrink-0 items-center gap-0.5 sm:gap-1">
                                            <button
                                                type="button"
                                                wire:click="openEdit({{ $item->id }})"
                                                class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-sky-800 {{ $item->is_checked ? 'opacity-60' : '' }}"
                                                title="{{ __('Изменить') }}"
                                                aria-label="{{ __('Изменить') }}"
                                            >
                                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M12 20h9" />
                                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="deleteItem({{ $item->id }})"
                                                wire:confirm="{{ __('Удалить «:name»?', ['name' => $item->name]) }}"
                                                class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-rose-700 {{ $item->is_checked ? 'opacity-60' : '' }}"
                                                title="{{ __('Удалить') }}"
                                                aria-label="{{ __('Удалить') }}"
                                            >
                                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M4 7h16" />
                                                    <path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                                                    <path d="M7 7l1 12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-12" />
                                                    <path d="M10 11v6M14 11v6" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                @error('inlinePriceAmount') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
                            @endif
                        </li>
                    @empty
                        <li class="py-3 text-lg text-stone-500">{{ __('Пока пусто. Добавьте продукт.') }}</li>
                    @endforelse
                </ul>

                @if ($addingItemCategoryId === $section['category']->id)
                    <form wire:submit="saveItem" class="mt-3 space-y-2 border-t border-stone-100 pt-3" data-focus-fields>
                        <label class="block min-w-0">
                            <span class="text-base text-stone-600 sm:text-lg">{{ __('Название') }}</span>
                            <input type="text" wire:model="itemName" class="mt-1 w-full min-w-0 rounded-2xl border border-stone-200 px-3 py-2.5 text-lg {{ $errors->has('itemName') ? 'field-invalid' : '' }}">
                            @error('itemName') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="block min-w-0">
                                <span class="text-base text-stone-600 sm:text-lg">{{ __('Кол-во') }}</span>
                                <input type="text" inputmode="decimal" wire:model="itemQuantity" class="mt-1 w-full min-w-0 rounded-2xl border border-stone-200 px-3 py-2.5 text-lg" placeholder="{{ __('необязательно') }}">
                            </label>
                            <label class="block min-w-0">
                                <span class="text-base text-stone-600 sm:text-lg">{{ __('Цена') }}</span>
                                <input type="text" inputmode="decimal" wire:model="itemPriceAmount" class="mt-1 w-full min-w-0 rounded-2xl border border-stone-200 px-3 py-2.5 text-lg {{ $errors->has('itemPriceAmount') ? 'field-invalid' : '' }}" placeholder="{{ __('необязательно') }}">
                                @error('itemPriceAmount') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
                            </label>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="rounded-2xl bg-sky-800 px-4 py-2.5 text-base font-medium text-white sm:text-lg">{{ __('Добавить') }}</button>
                            <button type="button" wire:click="closeAddItem" class="rounded-2xl px-4 py-2.5 text-base font-medium text-stone-600 ring-1 ring-stone-900/10 sm:text-lg">{{ __('Отмена') }}</button>
                        </div>
                    </form>
                @else
                    <button type="button" wire:click="openAddItem({{ $section['category']->id }})" class="mt-3 rounded-2xl bg-sky-100 px-4 py-2.5 text-base font-medium text-sky-900 sm:text-lg">{{ __('Добавить продукт') }}</button>
                @endif
            </section>
        @empty
            <p class="rounded-3xl bg-white p-3 text-base text-stone-600 shadow-sm ring-1 ring-stone-900/5 sm:p-4">{{ __('В списке пока нет категорий.') }}</p>
        @endforelse

        @if ($showArchive)
            <section class="min-w-0 overflow-hidden rounded-3xl bg-white p-3 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
                <h2 class="text-lg font-medium">{{ __('Архив') }}</h2>
                <ul class="mt-3 divide-y divide-stone-100">
                    @forelse ($archivedItems as $item)
                        <li class="py-3 text-base text-stone-500">
                            <span class="line-through">{{ $item->name }}</span>
                            @if ($item->category)
                                <span class="text-stone-400"> · {{ $item->category->name }}</span>
                            @endif
                        </li>
                    @empty
                        <li class="py-3 text-base text-stone-500">{{ __('Архив пуст.') }}</li>
                    @endforelse
                </ul>
            </section>
        @endif
    @endif
</div>
