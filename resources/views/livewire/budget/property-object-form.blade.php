<div class="space-y-6">
    <div>
        <x-back-link :href="route('objects.index')">{{ __('← К объектам') }}</x-back-link>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('Карточка объекта') }}</h1>
        <p class="mt-2 max-w-2xl text-base text-stone-600">{{ __('Карточка связывает существующие долги и категории. Здесь же можно добавить расход, контракт или кредит.') }}</p>
        <p class="mt-2 max-w-2xl text-base text-stone-600">{{ __('Одно название расхода живёт в одном месте.') }}</p>
        @if (session('status'))
            <p class="mt-3 rounded-2xl bg-tide-soft px-4 py-3 text-base text-tide">{{ session('status') }}</p>
        @endif
    </div>

    <form wire:submit="save" class="grid grid-cols-1 gap-4 rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
        <label class="block">
            <span class="text-base text-stone-600">{{ __('Название') }}</span>
            <input wire:model="name" type="text" maxlength="80" autocomplete="off" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('name') ? 'field-invalid' : '' }}">
        </label>
        @error('name') <p class="text-base text-rose-700">{{ $message }}</p> @enderror

        <fieldset class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <legend class="mb-2 text-base text-stone-600">{{ __('Вид имущества') }}</legend>
            <label class="flex min-h-14 items-center gap-3 rounded-2xl border border-stone-200 bg-paper px-4 py-3">
                <input type="radio" wire:model="kind" value="apartment" class="h-5 w-5 accent-tide">
                <span class="text-base font-medium">{{ __('Недвижимое имущество') }}</span>
            </label>
            <label class="flex min-h-14 items-center gap-3 rounded-2xl border border-stone-200 bg-paper px-4 py-3">
                <input type="radio" wire:model="kind" value="car" class="h-5 w-5 accent-tide">
                <span class="text-base font-medium">{{ __('Движимое имущество') }}</span>
            </label>
        </fieldset>
        @error('kind') <p class="text-base text-rose-700">{{ $message }}</p> @enderror

        <label class="block">
            <span class="text-base text-stone-600">{{ __('Короткая подпись') }}</span>
            <input wire:model="label" type="text" maxlength="120" autocomplete="off" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('label') ? 'field-invalid' : '' }}">
            <span class="mt-1 block text-base text-stone-500">{{ __('Адрес или регистрационный номер.') }}</span>
        </label>
        @error('label') <p class="text-base text-rose-700">{{ $message }}</p> @enderror

        <fieldset class="grid grid-cols-1 gap-2">
            <legend class="text-base font-medium text-ink">{{ __('Место категории') }}</legend>
            <label class="flex min-h-14 items-center gap-3 rounded-2xl border border-stone-200 bg-paper px-4 py-3">
                <input type="radio" wire:model.live="placeMode" value="under" class="h-5 w-5 accent-tide">
                <span class="text-base font-medium">{{ __('Под категорией') }}</span>
            </label>
            <label class="flex min-h-14 items-center gap-3 rounded-2xl border border-stone-200 bg-paper px-4 py-3">
                <input type="radio" wire:model.live="placeMode" value="root" class="h-5 w-5 accent-tide">
                <span class="text-base font-medium">{{ __('Сам родитель') }}</span>
            </label>
            @if ($placeMode === 'under')
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Под категорией') }}</span>
                    <select wire:model="parentCategoryId" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide">
                        <option value="">{{ __('Выберите категорию.') }}</option>
                        @foreach ($parentChoices as $choice)
                            <option value="{{ $choice->id }}">{{ $choice->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @error('parentCategoryId') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
        </fieldset>

        <fieldset class="grid grid-cols-1 gap-2">
            <legend class="text-base font-medium text-ink">{{ __('Существующие долги') }}</legend>
            <p class="text-base text-stone-600">{{ __('Карточка указывает на долг. Новый долг и категория не создаются.') }}</p>
            @forelse ($debts as $debt)
                @php($taken = $debtTakenBy[$debt->id] ?? null)
                <label class="flex min-h-14 items-start gap-3 rounded-2xl bg-paper px-3 py-3 {{ $taken ? 'opacity-70' : '' }}">
                    @if ($taken)
                        <input type="checkbox" disabled class="mt-1 h-5 w-5" aria-disabled="true">
                    @else
                        <input type="checkbox" wire:model="debtIds" value="{{ $debt->id }}" class="mt-1 h-5 w-5 accent-tide">
                    @endif
                    <span class="min-w-0">
                        <span class="block font-medium">{{ $debt->counterparty }}</span>
                        @if ($debt->category)
                            <span class="block text-base text-stone-600">{{ $debt->category->name }}</span>
                        @endif
                        @if ($taken)
                            <span class="block text-base text-rose-700">{{ __('Уже привязано к другому объекту.') }} {{ $taken }}</span>
                        @endif
                    </span>
                </label>
            @empty
                <p class="rounded-2xl bg-paper px-3 py-3 text-base text-stone-600">{{ __('Долгов пока нет. Карточку можно сохранить без долга.') }}</p>
            @endforelse
        </fieldset>
        @error('debtIds') <p class="text-base text-rose-700">{{ $message }}</p> @enderror

        <fieldset class="grid grid-cols-1 gap-3">
            <legend class="text-base font-medium text-ink">{{ __('Категории расходов') }}</legend>
            <p class="text-base text-stone-600">{{ __('В списке только категории расходов. Отметка связывает все строки этой категории. План не копируется и не меняется.') }}</p>
            <p class="text-base text-stone-600">{{ __('Отвязывание не удаляет долг и строку плана.') }}</p>
            @if ($hasExpenseCategories)
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Поиск по категории') }}</span>
                    <input wire:model.live="planSearch" type="search" autocomplete="off" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide">
                </label>
                <div class="grid grid-cols-1 gap-2" style="max-height: 70vh; overflow: auto">
                    @foreach ($planRows as $row)
                        @if ($row['check'])
                            <label class="flex min-h-14 items-center gap-3 rounded-2xl bg-paper px-3 py-3" style="margin-left: {{ $row['depth'] * 1.25 }}rem" data-plan-category="{{ $row['id'] }}">
                                <input type="checkbox" wire:model="categoryIds" value="{{ $row['id'] }}" class="h-5 w-5 accent-tide">
                                <span class="min-w-0 font-medium">{{ $row['name'] }}</span>
                            </label>
                        @else
                            <h3 class="pt-2 text-base font-semibold text-ink" style="margin-left: {{ $row['depth'] * 1.25 }}rem" data-plan-heading="{{ $row['id'] }}">{{ $row['name'] }}</h3>
                        @endif
                    @endforeach
                    @if ($takenCategories !== [])
                        <section class="grid grid-cols-1 gap-2 border-t border-stone-200 pt-3">
                            @foreach ($takenCategories as $row)
                                <div class="rounded-2xl px-3 py-3 text-base" style="background: #e7e5e4; color: #78716c" data-plan-category-taken="{{ $row['id'] }}">
                                    <span class="block font-medium">{{ $row['name'] }}</span>
                                    <span class="mt-1 block">{{ __('Уже привязано к другому объекту.') }} {{ $row['takenName'] }}</span>
                                </div>
                            @endforeach
                        </section>
                    @endif
                    @if ($planRows === [] && $takenCategories === [])
                        <p class="rounded-2xl bg-paper px-3 py-3 text-base text-stone-600">{{ __('Ничего не найдено.') }}</p>
                    @endif
                </div>
            @else
                <p class="rounded-2xl bg-paper px-3 py-3 text-base text-stone-600">{{ __('Строк плана пока нет. Карточку можно сохранить без строк.') }}</p>
            @endif
        </fieldset>
        @error('planLineIds') <p class="text-base text-rose-700">{{ $message }}</p> @enderror

        <button type="submit" class="w-full rounded-2xl bg-tide px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Сохранить') }}</button>
    </form>

    @if ($objectId)
        <div class="grid grid-cols-1 gap-2">
            <button type="button" wire:click="openExpense" class="min-h-14 w-full rounded-2xl bg-white px-4 py-3 text-base font-medium text-ink" style="border:2px solid #0f6e6b">{{ __('Новый расход') }}</button>
            <button type="button" wire:click="openContract" class="min-h-14 w-full rounded-2xl bg-tide-soft px-4 py-3 text-base font-medium text-tide" style="border:2px solid #1c1917">{{ __('Новый контракт') }}</button>
            <button type="button" wire:click="openCredit" class="min-h-14 w-full rounded-2xl bg-ink px-4 py-3 text-base font-medium" style="border:2px solid #e6f3f2;color:#f3efe6">{{ __('Новый кредит') }}</button>
        </div>

        @if ($showExpense)
            <form wire:submit="saveExpense" class="grid grid-cols-1 gap-3 rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
                <h2 class="text-lg font-medium text-ink">{{ __('Новый расход') }}</h2>
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Название') }}</span>
                    <input wire:model="expenseName" type="text" maxlength="80" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide">
                </label>
                @error('expenseName') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Сумма') }}</span>
                    <input wire:model="expenseAmount" data-money inputmode="decimal" placeholder="0,00" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide">
                </label>
                @error('expenseAmount') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Дата') }}</span>
                    <x-date-input wire:model="expenseDate" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide" />
                </label>
                @error('expenseDate') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                <button type="submit" class="w-full rounded-2xl bg-tide px-4 py-3 text-base font-medium text-white">{{ __('Записать расход') }}</button>
            </form>
        @endif

        @if ($showContract)
            <form wire:submit="saveContract" class="grid grid-cols-1 gap-3 rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
                <h2 class="text-lg font-medium text-ink">{{ $editingContractId ? __('Изменить контракт') : __('Новый контракт') }}</h2>
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Название') }}</span>
                    <input wire:model="contractTitle" type="text" maxlength="80" placeholder="{{ __('ERGO или Госпошлина') }}" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide">
                </label>
                @error('contractTitle') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Заметка') }}</span>
                    <input wire:model="contractNote" type="text" maxlength="200" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide">
                    <span class="mt-1 block text-base text-stone-500">{{ __('Страховщик и номер полиса. В план не попадает.') }}</span>
                </label>
                @once
                    <style>
                        .contract-day-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 0.25rem; }
                        .contract-day-grid button { min-height: 2.75rem; border-radius: 0.75rem; }
                        .contract-day-grid button.is-selected { background: #0f6e6b; color: #fff; }
                        .contract-day-grid button.is-today:not(.is-selected) { box-shadow: inset 0 0 0 1px #0f6e6b; }
                    </style>
                @endonce
                @foreach ($contractParts as $index => $part)
                    <div class="grid grid-cols-1 gap-2" wire:key="contract-part-{{ $editingContractId }}-{{ $index }}">
                        <label class="block">
                            <span class="text-base text-stone-600">{{ __('Сумма') }}</span>
                            <input wire:model="contractParts.{{ $index }}.amount" data-money inputmode="decimal" placeholder="0,00" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide">
                        </label>
                        @error('contractParts.'.$index.'.amount') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                        @include('livewire.budget.partials.contract-day-picker', ['index' => $index, 'part' => $part])
                        @error('contractParts.'.$index.'.date') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                    </div>
                @endforeach
                @error('contractParts') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                <button type="button" wire:click="addContractPart" class="w-full rounded-2xl border border-stone-300 bg-paper px-4 py-3 text-base font-medium text-ink">{{ __('Добавить часть') }}</button>
                <div>
                    <span class="text-base text-stone-600">{{ __('Документ') }}</span>
                    <span class="mt-1 block text-base text-stone-500">{{ __('Фотография или PDF. Новый файл заменяет предыдущий.') }}</span>
                    @if ($savedContractDocument)
                        <div class="mt-2">
                            @include('livewire.budget.partials.contract-document', [
                                'documentUrl' => route('objects.contracts.document', $savedContractDocument),
                                'downloadUrl' => route('objects.contracts.document', ['contract' => $savedContractDocument, 'download' => 1]),
                                'documentMime' => $savedContractDocument->document_mime,
                                'documentTitle' => $savedContractDocument->document_name ?: $savedContractDocument->title,
                            ])
                        </div>
                        <button type="button" wire:click="clearContractDocument" class="mt-2 text-base font-medium text-rose-700">{{ __('Убрать документ') }}</button>
                    @endif
                    @if ($draftDocument)
                        <div class="mt-2">
                            @include('livewire.budget.partials.contract-document', [
                                'documentUrl' => $draftDocument['url'],
                                'downloadUrl' => $draftDocument['url'],
                                'documentMime' => $draftDocument['mime'],
                                'documentTitle' => $draftDocument['title'],
                            ])
                        </div>
                        <button type="button" wire:click="clearContractDocument" class="mt-2 text-base font-medium text-rose-700">{{ __('Убрать документ') }}</button>
                    @endif
                    @error('contractDocument') <p class="mt-2 text-base text-rose-700">{{ $message }}</p> @enderror
                    <div class="mt-2 grid grid-cols-1 gap-2">
                        <label class="relative flex min-h-14 w-full cursor-pointer items-center justify-center overflow-hidden rounded-2xl border border-stone-300 bg-paper px-4 py-3 text-base font-medium text-ink">
                            {{ $replacingDocument ? __('Заменить документ') : __('Загрузить документ') }}
                            <input type="file" wire:model="contractDocument" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" style="position:absolute;inset:0;width:100%;height:100%;opacity:0;font-size:100px">
                        </label>
                        <label class="relative flex min-h-14 w-full cursor-pointer items-center justify-center overflow-hidden rounded-2xl px-4 py-3 text-base font-medium text-white" style="background:#0f6e6b">
                            {{ __('Сфотографировать') }}
                            <input type="file" wire:model="contractDocument" accept="image/*" capture="environment" style="position:absolute;inset:0;width:100%;height:100%;opacity:0;font-size:100px">
                        </label>
                    </div>
                </div>
                <button type="submit" class="w-full rounded-2xl bg-tide px-4 py-3 text-base font-medium text-white">{{ __('Сохранить контракт') }}</button>
                <button type="button" wire:click="cancelContract" class="w-full rounded-2xl border border-stone-300 bg-paper px-4 py-3 text-base font-medium text-ink">{{ __('Отменить') }}</button>
            </form>
        @endif

        @if ($showNew)
            @include('livewire.budget.partials.new-debt')
        @endif

        <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium text-ink">{{ __('Контракты') }}</h2>
            @forelse ($contracts as $contract)
                <article class="mt-3 rounded-2xl bg-paper px-3 py-3" data-contract="{{ $contract->id }}" wire:key="contract-{{ $contract->id }}">
                    <div class="flex items-start gap-3">
                        @if ($contract->hasViewableDocument())
                            <div class="shrink-0">
                                @include('livewire.budget.partials.contract-document', [
                                    'documentUrl' => route('objects.contracts.document', $contract),
                                    'downloadUrl' => route('objects.contracts.document', ['contract' => $contract, 'download' => 1]),
                                    'documentMime' => $contract->document_mime,
                                    'documentTitle' => $contract->document_name ?: $contract->title,
                                ])
                            </div>
                        @endif
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-ink">{{ $contract->title }}</h3>
                            @if ($contract->note)
                                <p class="mt-1 text-base text-stone-600">{{ $contract->note }}</p>
                            @endif
                            @foreach ($contract->planLines as $line)
                                <p class="mt-1 text-base text-ink">{{ $line->expected_on?->format('d.m.Y') }} · {{ money($line->amount_cents) }}</p>
                            @endforeach
                        </div>
                    </div>
                    <button type="button" wire:click="editContract({{ $contract->id }})" class="mt-3 min-h-11 w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-base font-medium text-ink">{{ __('Изменить контракт') }}</button>
                </article>
            @empty
                <p class="mt-2 text-base text-stone-600">{{ __('Контрактов пока нет.') }}</p>
            @endforelse
        </section>

        <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <h2 class="text-lg font-medium">{{ __('Удалить карточку') }}</h2>
            @if ($debtAttached)
                <p class="mt-2 text-base text-stone-700">{{ __('Долг, который ещё привязан, не даёт удалить карточку. Строки плана не мешают.') }}</p>
            @else
                <p class="mt-2 text-base text-stone-700">{{ __('Удаляется карточка и её связи. Долги, строки плана и папка, в которой они уже лежат, остаются. Пустая папка карточки убирается, чтобы то же название не осталось вторым местом.') }}</p>
            @endif
            @error('delete') <p class="mt-3 rounded-2xl bg-rose-50 px-4 py-3 text-base text-rose-700">{{ $message }}</p> @enderror
            <button type="button" wire:click="delete" class="mt-4 w-full rounded-2xl bg-rose-700 px-4 py-3 text-base font-medium text-white">{{ __('Удалить карточку') }}</button>
        </section>
    @endif
</div>
