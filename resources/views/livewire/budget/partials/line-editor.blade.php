@php
    $lineCategories = $categories->filter(fn ($category) => $category->type->value === $kind);
@endphp
<form wire:submit="saveLine" class="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:items-end">
    <label class="block w-36">
        <span class="text-base text-stone-500">{{ __('Сумма плана, €') }}</span>
        <input wire:model="lineAmount" data-money inputmode="decimal" placeholder="0" aria-label="Сумма плана {{ $line->title }}" class="mt-1 w-full rounded-xl border border-stone-200 px-2 py-1.5 text-lg outline-none focus:border-sky-500 {{ $errors->has('lineAmount') ? 'field-invalid' : '' }}">
    </label>
    <div class="block min-w-40 flex-1">
        <span class="text-base text-stone-500">{{ __('Категория') }}</span>
        @include('livewire.budget.partials.category-picker', [
            'property' => 'lineCategoryId',
            'value' => $lineCategoryId,
            'categories' => $lineCategories,
            'placeholder' => null,
        ])
    </div>
    <label class="block">
        <span class="text-base text-stone-500">{{ __('Дата, можно пусто') }}</span>
        <input type="date" wire:model="lineDate" min="{{ $period->starts_on->toDateString() }}" max="{{ $period->ends_on->toDateString() }}" class="mt-1 w-full min-w-0 rounded-xl border border-stone-200 px-2 py-1.5 text-base outline-none focus:border-sky-500 sm:w-auto {{ $errors->has('lineDate') ? 'field-invalid' : '' }}">
    </label>
    <label class="block min-w-36">
        <span class="text-base text-stone-500">{{ __('Счёт') }}</span>
        <select wire:model="lineAccountId" class="mt-1 w-full rounded-xl border border-stone-200 px-2 py-1.5 text-base outline-none focus:border-sky-500 {{ $errors->has('lineAccountId') ? 'field-invalid' : '' }}">
            <option value="">{{ __('Не выбран') }}</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}">{{ $account->name }}</option>
            @endforeach
        </select>
    </label>
    <button type="submit" class="rounded-full bg-sky-800 px-3 py-1.5 text-base font-medium text-white">{{ __('Сохранить') }}</button>
    <button type="button" wire:click="cancelLine" class="rounded-full bg-rose-600 px-3 py-1.5 text-base font-medium text-white">{{ __('Отмена') }}</button>
    @if ($deals->isEmpty())
        <button type="button" wire:click="deletePlanLine({{ $line->id }})" wire:confirm="{{ __('Убрать «:title» из плана?', ['title' => $line->title]) }}" class="rounded-full px-3 py-1.5 text-base font-medium text-rose-700">{{ __('Удалить') }}</button>
    @endif
</form>
@error('lineAmount') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
@error('lineCategoryId') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
@error('lineDate') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
