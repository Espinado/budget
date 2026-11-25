@php
    $bookedCents = (int) collect($operationTransactions ?? [])->sum('amount_cents');
    $leftCents = max(0, (int) $line->amount_cents - $bookedCents);
    $amountPlaceholder = $leftCents > 0 ? \App\Support\Money::toInput($leftCents) : '';
@endphp
<form wire:submit="addOperation" class="mt-3 border-t border-sky-100 pt-3">
    <p class="text-base text-stone-600">План на месяц {{ money($line->amount_cents) }}. Каждую операцию записываем отдельно, не всю сумму сразу.</p>
    <div class="mt-2 grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:items-end">
        <label class="block w-36">
            <span class="text-base text-stone-500">{{ __('Сумма операции, €') }}</span>
            <input wire:model="operationAmount" data-money inputmode="decimal" placeholder="{{ $amountPlaceholder }}" class="mt-1 w-full rounded-xl border border-stone-200 px-2 py-2 text-lg outline-none focus:border-sky-500 {{ $errors->has('operationAmount') ? 'field-invalid' : '' }}">
        </label>
        <label class="block">
            <span class="text-base text-stone-500">{{ __('Дата') }}</span>
            <input type="date" wire:model="operationDate" min="{{ $factFrom }}" max="{{ $factTo }}" class="mt-1 w-full min-w-0 rounded-xl border border-stone-200 px-2 py-2 text-base outline-none focus:border-sky-500 sm:w-auto {{ $errors->has('operationDate') ? 'field-invalid' : '' }}">
        </label>
        <label class="block min-w-36">
            <span class="text-base text-stone-500">{{ __('Счёт') }}</span>
            <select wire:model="operationAccountId" class="mt-1 w-full rounded-xl border border-stone-200 px-2 py-2 text-base outline-none focus:border-sky-500 {{ $errors->has('operationAccountId') ? 'field-invalid' : '' }}">
                <option value="">{{ __('Выберите счёт') }}</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                @endforeach
            </select>
            @error('operationAccountId') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
        </label>
        <label class="block min-w-40 flex-1">
            <span class="text-base text-stone-500">{{ __('Примечание') }}</span>
            <input wire:model="operationNote" list="note-suggestions-{{ $kind }}" autocomplete="off" maxlength="200" placeholder="{{ __('Например, поездка') }}" class="mt-1 w-full rounded-xl border border-stone-200 px-2 py-2 text-base outline-none focus:border-sky-500 {{ $errors->has('operationNote') ? 'field-invalid' : '' }}">
        </label>
        <label class="block min-w-40">
            <span class="text-base text-stone-500">{{ __('Чек') }}</span>
            <input type="file" wire:model="operationReceipt" accept="image/*" capture="environment" class="mt-1 block w-full rounded-xl border border-stone-200 px-2 py-2 text-base {{ $errors->has('operationReceipt') ? 'field-invalid' : '' }}">
            @error('operationReceipt') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
            @if ($operationReceipt)
                <img src="{{ $operationReceipt->temporaryUrl() }}" alt="{{ __('Предпросмотр чека') }}" class="mt-2 h-24 w-24 rounded-xl object-cover">
            @endif
        </label>
        <button type="submit" class="rounded-full bg-emerald-600 px-3 py-2 text-base font-medium text-white">{{ __('Добавить') }}</button>
        <button type="button" wire:click="cancelOperation" class="rounded-full bg-rose-600 px-3 py-2 text-base font-medium text-white">{{ __('Отмена') }}</button>
    </div>
    @error('operationAmount') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
    @error('operationDate') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
</form>
