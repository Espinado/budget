<div class="mx-auto max-w-lg">
    <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('Начальные остатки') }}</h1>
    <p class="mt-2 text-stone-600">{{ __('Сколько сейчас на каждом счёте. Пустое поле означает ноль. Позже остаток меняется операциями.') }}</p>

    <form wire:submit="save" class="mt-6 space-y-4 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-900/5">
        @foreach ($accounts as $account)
            <label class="block">
                <span class="text-base text-stone-600">{{ $account->name }}</span>
                <input wire:model="accountAmounts.{{ $account->id }}" data-money inputmode="decimal" placeholder="0,00" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-3 text-lg outline-none focus:border-tide {{ $errors->has('accountAmounts.'.$account->id) ? 'field-invalid' : '' }}">
                @error('accountAmounts.'.$account->id) <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
            </label>
        @endforeach
        <button type="submit" class="w-full rounded-2xl bg-tide px-4 py-3 font-medium text-white">{{ __('Сохранить и продолжить') }}</button>
        <button type="button" wire:click="skip" class="w-full rounded-2xl px-4 py-3 text-base font-medium text-stone-600">{{ __('Пропустить') }}</button>
    </form>
</div>
