<div class="space-y-6">
    <div>
        <x-back-link :href="route('debts')">{{ __('← К долгам') }}</x-back-link>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ $debt->counterparty }}</h1>
        <p class="mt-2 text-base text-stone-600">{{ $debt->direction->label() }}@if ($debt->contract_number) · {{ $debt->contract_number }}@endif · {{ $debt->rateLabel() }}</p>
        @if (session('status'))
            <p class="mt-3 rounded-2xl bg-emerald-50 px-4 py-3 text-base text-emerald-800">{{ session('status') }}</p>
        @endif
    </div>

    <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
        <dl class="space-y-2 text-lg">
            <div class="flex items-start justify-between gap-3">
                <dt class="text-stone-600">{{ __('Остаток') }}</dt>
                <dd class="shrink-0 font-semibold tabular-nums">{{ money($dailyPosition['total'] ?? $debt->remainingCents()) }}</dd>
            </div>
            @if ($dailyPosition)
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-stone-600">{{ __('Тело') }}</dt>
                    <dd class="shrink-0 tabular-nums">{{ money($dailyPosition['principal']) }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-stone-600">{{ __('Проценты') }}</dt>
                    <dd class="shrink-0 tabular-nums">{{ money($dailyPosition['accrued']) }}</dd>
                </div>
            @endif
            <div class="flex items-start justify-between gap-3">
                <dt class="text-stone-600">{{ __('Ближайший платёж') }}</dt>
                <dd class="shrink-0 text-right tabular-nums">
                    @if ($next)
                        {{ $next->due_on ? ru_pay_date($next->due_on) : 'дата не указана' }} · {{ money($next->total_cents) }}
                    @else
                        выплачен
                    @endif
                </dd>
            </div>
            @if ($debt->reference)
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-stone-600">{{ __('Референция') }}</dt>
                    <dd class="shrink-0">{{ $debt->reference }}</dd>
                </div>
            @endif
            </dl>
        @if ($dailyPosition)
            <div class="mt-4">
                <button type="button" wire:click="toggleBalance" class="w-full rounded-2xl bg-sky-800 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Подправить остаток') }}</button>
                @if ($editingBalance)
                    <form wire:submit="saveBalance" class="mt-4 space-y-2">
                        <p class="text-base text-stone-600">{{ __('Остаток из приложения кредитора. Проценты обнуляются и снова капают со следующего дня.') }}</p>
                        <label class="block">
                            <span class="text-base text-stone-600">{{ __('Остаток') }}</span>
                            <input wire:model="balanceTotal" inputmode="decimal" autocomplete="off" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('balanceTotal') ? 'field-invalid' : '' }}">
                        </label>
                        @error('balanceTotal') <p class="text-base text-rose-700">{{ $message }}</p> @enderror
                        <button type="submit" class="w-full rounded-2xl bg-emerald-600 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Сохранить') }}</button>
                    </form>
                @endif
            </div>
        @endif
    </section>

    <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
        <h2 class="text-lg font-medium">{{ $dailyPosition ? __('Прогноз') : __('По месяцам') }}</h2>
        @if ($dailyPosition)
            <p class="mt-2 text-base text-stone-600">{{ __('Прогноз. Точная доля процентов и тела зависит от дня оплаты.') }}</p>
        @endif
        <div class="mt-3" style="overflow: auto; max-height: 18rem">
            <table class="w-full border-collapse text-base" style="min-width: 36rem">
                <thead>
                    <tr class="border-b border-stone-200 text-left text-stone-600" style="position: sticky; top: 0; background: #fff; z-index: 1">
                        <th class="px-2 py-2 font-medium">{{ __('Дата') }}</th>
                        <th class="px-2 py-2 text-right font-medium">{{ __('Остаток') }}</th>
                        <th class="px-2 py-2 text-right font-medium">{{ __('Основная сумма') }}</th>
                        <th class="px-2 py-2 text-right font-medium">{{ __('Проценты') }}</th>
                        <th class="px-2 py-2 text-right font-medium">{{ __('Плата') }}</th>
                        <th class="px-2 py-2 text-right font-medium">{{ __('Платёж') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($dailyPosition)
                        @foreach ($forecast as $row)
                            @php $upcoming = $loop->first; @endphp
                            <tr class="border-b border-stone-200 {{ $upcoming ? 'bg-sky-50' : '' }}">
                                <td class="px-2 py-2 whitespace-nowrap">{{ $row['due_on'] ? ru_pay_date($row['due_on']) : __('дата не указана') }}</td>
                                <td class="px-2 py-2 text-right tabular-nums whitespace-nowrap">{{ money($row['principal_before_cents']) }}</td>
                                <td class="px-2 py-2 text-right tabular-nums whitespace-nowrap">{{ money($row['principal_cents']) }}</td>
                                <td class="px-2 py-2 text-right tabular-nums whitespace-nowrap">{{ money($row['interest_cents']) }}</td>
                                <td class="px-2 py-2 text-right tabular-nums whitespace-nowrap">{{ money($row['fee_cents'] + $row['other_cents']) }}</td>
                                <td class="px-2 py-2 text-right font-medium tabular-nums whitespace-nowrap">{{ money($row['total_cents']) }}</td>
                            </tr>
                        @endforeach
                    @else
                        @foreach ($debt->installments as $installment)
                            @if ($installment->paid_transaction_id || (int) $installment->total_cents <= 0)
                                @continue
                            @endif
                            @php $upcoming = $next && $installment->id === $next->id; @endphp
                            <tr class="border-b border-stone-200 {{ $upcoming ? 'bg-sky-50' : '' }}">
                                <td class="px-2 py-2 whitespace-nowrap">{{ $installment->due_on ? ru_pay_date($installment->due_on) : __('дата не указана') }}</td>
                                <td class="px-2 py-2 text-right tabular-nums whitespace-nowrap">{{ money($installment->principal_before_cents) }}</td>
                                <td class="px-2 py-2 text-right tabular-nums whitespace-nowrap">{{ money($installment->principal_cents) }}</td>
                                <td class="px-2 py-2 text-right tabular-nums whitespace-nowrap">{{ money($installment->interest_cents) }}</td>
                                <td class="px-2 py-2 text-right tabular-nums whitespace-nowrap">{{ money($installment->fee_cents + $installment->other_cents) }}</td>
                                <td class="px-2 py-2 text-right font-medium tabular-nums whitespace-nowrap">{{ money($installment->total_cents) }}</td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
        @if ($canEditFees || $canChangeRate)
            <div class="mt-4">
                <button type="button" wire:click="toggleConditions" data-focus-open aria-expanded="{{ $editingConditions ? 'true' : 'false' }}" class="w-full rounded-2xl bg-sky-800 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Изменить условия') }}</button>
                @if ($editingConditions)
                    <form wire:submit="saveConditions" data-focus-fields class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @if ($canEditFees)
                            <p class="text-base text-stone-600 sm:col-span-2">{{ __('Новая ставка действует с ближайшего неоплаченного платежа. Дата окончания договора не меняется. Новая плата входит со следующего месяца, ближайший платёж её не меняет. Пустое поле значит, что платы нет. Плата за выдачу относится только к первому платежу.') }}</p>
                            <label class="block">
                                <span class="text-base text-stone-600">{{ __('Ежемесячная плата') }}</span>
                                <input wire:model="feeMonthly" inputmode="decimal" autocomplete="off" placeholder="{{ __('необязательно') }}" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('feeMonthly') ? 'field-invalid' : '' }}">
                            </label>
                            <label class="block">
                                <span class="text-base text-stone-600">{{ __('Плата за выдачу') }}</span>
                                <input wire:model="feeIssuance" inputmode="decimal" autocomplete="off" placeholder="{{ __('необязательно') }}" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('feeIssuance') ? 'field-invalid' : '' }}">
                            </label>
                        @endif
                        @if ($canChangeRate)
                            <p class="text-base text-stone-600 sm:col-span-2">{{ __('EURIBOR вписывается вручную. С ближайшего платежа сумма может стать больше или меньше.') }}</p>
                            <label class="block">
                                <span class="text-base text-stone-600">{{ (int) $debt->euribor_rate_bps > 0 ? __('Надбавка банка, % годовых') : __('Ставка, % годовых') }}</span>
                                <input wire:model.live="rateFixed" value="{{ $rateFixed }}" inputmode="decimal" autocomplete="off" placeholder="0" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('rateFixed') ? 'field-invalid' : '' }}">
                            </label>
                            <label class="block">
                                <span class="text-base text-stone-600">{{ __('EURIBOR, % годовых') }}</span>
                                <input wire:model.live="rateEuribor" value="{{ $rateEuribor }}" inputmode="decimal" autocomplete="off" placeholder="{{ __('необязательно') }}" class="mt-1 w-full rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide {{ $errors->has('rateEuribor') ? 'field-invalid' : '' }}">
                            </label>
                            @if ($hint = $this->rateHint())
                                <p class="text-base text-stone-600 sm:col-span-2">{{ $hint }}</p>
                            @endif
                        @endif
                        @if ($errors->has('feeMonthly') || $errors->has('feeIssuance') || $errors->has('rateFixed') || $errors->has('rateEuribor'))
                            <p class="text-base text-rose-700 sm:col-span-2">{{ $errors->first('feeMonthly') ?: $errors->first('feeIssuance') ?: $errors->first('rateFixed') ?: $errors->first('rateEuribor') }}</p>
                        @endif
                        <button type="submit" class="w-full rounded-2xl bg-emerald-600 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Сохранить') }}</button>
                        <button type="button" wire:click="cancelConditions" class="w-full rounded-2xl bg-rose-600 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Отмена') }}</button>
                    </form>
                @endif
            </div>
        @endif
    </section>
</div>
