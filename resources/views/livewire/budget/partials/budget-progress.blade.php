@php
    $dailyCents = ($kind ?? null) === 'expense' && isset($spendableDays)
        ? app(\App\Services\Budget\DailyPace::class)->centsPerDay((int) $planned, (int) $actual, (int) $spendableDays)
        : false;
    $dealCount = (int) ($dealCount ?? 0);
    $meterLabel = $percent === null
        ? null
        : $percent.'%'.($dealCount > 0 ? ' ('.$dealCount.')' : '');
@endphp
<div class="mt-2 flex items-baseline justify-between gap-2">
    @if ($click)
        <button type="button" wire:click="{{ $click }}" class="budget-ratio" @isset($label) aria-label="{{ $label }}" @endisset>
            <span class="budget-figure {{ $done ? 'budget-figure-done' : '' }}">{{ money($actual) }}</span>
            <span class="budget-plan">/ {{ money($planned) }}</span>
        </button>
    @else
        <span class="budget-ratio">
            <span class="budget-figure {{ $done ? 'budget-figure-done' : '' }}">{{ money($actual) }}</span>
            <span class="budget-plan">/ {{ money($planned) }}</span>
        </span>
    @endif
    @if (! empty($record) || ! empty($pay))
        <span class="flex shrink-0 items-center gap-3">
            @if (! empty($pay) && (int) ($payingLineId ?? 0) !== (int) $pay)
                <button type="button" wire:click="payLine({{ $pay }})" class="budget-record">{{ __('Оплатить') }}</button>
            @endif
            @if (! empty($record))
                <button type="button" wire:click="toggleDetail({{ $record }})" class="budget-record">{{ __('Записать') }}</button>
            @endif
        </span>
    @endif
</div>
@if (! empty($pay) && (int) ($payingLineId ?? 0) === (int) $pay)
    @php
        $checkingAccounts = collect($accounts ?? [])->filter(fn ($account) => $account->role === \App\Enums\AccountRole::Operating)->values();
        $chosenAccount = $checkingAccounts->first(fn ($account) => (int) $account->id === (int) ($payAccountId ?? 0));
        $payAsk = 'Оплатить «'.($payTitle ?? '').'» полностью, '.money((int) ($payCents ?? 0)).($chosenAccount ? ' со счёта '.$chosenAccount->name : '').'?';
    @endphp
    <div class="mt-2 flex flex-wrap items-center gap-2">
        <label class="block min-w-40">
            <span class="text-sm text-stone-500">{{ __('Счёт') }}</span>
            <select wire:model.live="payAccountId" class="mt-1 w-full rounded-xl border border-stone-200 px-2 py-1.5 text-base outline-none focus:border-sky-500">
                <option value="">{{ __('Выберите счёт') }}</option>
                @foreach ($checkingAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                @endforeach
            </select>
        </label>
        <button type="button" wire:click="payLine({{ $pay }})" @if ($chosenAccount) wire:confirm="{{ $payAsk }}" @endif class="budget-record">{{ __('Оплатить') }}</button>
        <button type="button" wire:click="cancelPay" class="text-base text-stone-500">{{ __('Отмена') }}</button>
    </div>
@endif
<div class="budget-meter-row">
    <div class="budget-meter {{ $done ? 'budget-meter-done' : '' }}" aria-hidden="true"><span style="width: {{ $meter }}%"></span></div>
    @if ($meterLabel !== null)<span class="budget-meter-percent">{{ $meterLabel }}</span>@endif
</div>
@if ($dailyCents !== false)
    <p class="mt-1 text-sm tabular-nums text-stone-500">
        @if ($dailyCents === null)
            {{ __('в день') }} —
        @else
            {{ __('в день ~:amount', ['amount' => money($dailyCents)]) }}
        @endif
    </p>
@endif
