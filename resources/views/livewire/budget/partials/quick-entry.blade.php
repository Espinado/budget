@php
    $quickCategories = $categories->filter(fn ($category) => $category->type->value === $quickKind);
@endphp

<form wire:submit="saveQuick" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5">
    <h2 class="text-lg font-semibold {{ $quickKind === 'income' ? 'text-emerald-800' : 'text-rose-800' }}">
        {{ $quickKind === 'income' ? 'Новый приход' : 'Новый расход' }}
    </h2>
    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <label class="block">
            <span class="text-base text-stone-500">{{ __('Сумма, €') }}</span>
            <input wire:model="quickAmount" data-money inputmode="decimal" placeholder="10,00" class="mt-1 w-full rounded-xl border border-stone-200 px-3 py-2 text-lg outline-none focus:border-sky-500 {{ $errors->has('quickAmount') ? 'field-invalid' : '' }}">
            @error('quickAmount') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
        </label>
        <label class="block">
            <span class="text-base text-stone-500">{{ __('Дата') }}</span>
            <x-date-input wire:model.live="quickDate" class="mt-1 w-full rounded-xl border border-stone-200 px-3 py-2 text-base outline-none focus:border-sky-500 {{ $errors->has('quickDate') ? 'field-invalid' : '' }}" />
            @error('quickDate') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
        </label>
        <div class="block min-w-0 sm:col-span-2">
            <span class="text-base text-stone-500">{{ __('Категория') }}</span>
            @include('livewire.budget.partials.category-picker', [
                'property' => 'quickCategoryId',
                'value' => $quickCategoryId,
                'categories' => $quickCategories,
                'placeholder' => 'Выберите категорию',
            ])
            @error('quickCategoryId') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
        </div>
        @if ($quickLines->count() > 1)
            <label class="block sm:col-span-2">
                <span class="text-base text-stone-500">{{ __('Пункт плана') }}</span>
                <select wire:model="quickLineId" class="mt-1 w-full rounded-xl border border-stone-200 px-3 py-2 text-base outline-none focus:border-sky-500 {{ $errors->has('quickLineId') ? 'field-invalid' : '' }}">
                    <option value="">{{ __('Выберите пункт') }}</option>
                    @foreach ($quickLines as $line)
                        <option value="{{ $line->id }}">{{ $line->title }}{{ $line->expected_on ? ' · '.($line->installment ? ru_pay_date($line->expected_on) : ru_date($line->expected_on)) : '' }}</option>
                    @endforeach
                </select>
                @error('quickLineId') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
            </label>
        @endif
        <label class="block">
            <span class="text-base text-stone-500">{{ __('Счёт') }}</span>
            <select wire:model="quickAccountId" class="mt-1 w-full rounded-xl border border-stone-200 px-3 py-2 text-base outline-none focus:border-sky-500 {{ $errors->has('quickAccountId') ? 'field-invalid' : '' }}">
                <option value="">{{ __('Выберите счёт') }}</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                @endforeach
            </select>
            @error('quickAccountId') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
        </label>
        <label class="block">
            <span class="text-base text-stone-500">{{ __('Примечание') }}</span>
            <input wire:model="quickNote" list="note-suggestions-{{ $quickKind }}" autocomplete="off" maxlength="200" placeholder="{{ __('Например, магазин') }}" class="mt-1 w-full rounded-xl border border-stone-200 px-3 py-2 text-base outline-none focus:border-sky-500 {{ $errors->has('quickNote') ? 'field-invalid' : '' }}">
            @error('quickNote') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
        </label>
        <label class="block sm:col-span-2">
            <span class="text-base text-stone-500">{{ __('Чек') }}</span>
            <input type="file" wire:model="quickReceipt" accept="image/*" capture="environment" class="mt-1 block w-full rounded-xl border border-stone-200 px-2 py-2 text-base {{ $errors->has('quickReceipt') ? 'field-invalid' : '' }}">
            @error('quickReceipt') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
            @if ($quickReceipt)
                <img src="{{ $quickReceipt->temporaryUrl() }}" alt="{{ __('Предпросмотр чека') }}" class="mt-2 h-28 w-28 rounded-xl object-cover">
            @endif
        </label>
    </div>
    <div class="mt-3 flex flex-wrap gap-2">
        <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-base font-medium text-white">{{ __('Записать') }}</button>
        <button type="button" wire:click="closeQuick" class="rounded-full bg-rose-600 px-4 py-2 text-base font-medium text-white">{{ __('Отмена') }}</button>
    </div>
</form>
