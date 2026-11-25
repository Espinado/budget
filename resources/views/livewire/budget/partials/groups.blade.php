<section class="space-y-3">
    <h2 class="text-lg font-semibold">{{ $title }}</h2>
    @foreach ($groups as $group)
        <article wire:key="category-{{ $group->category->id }}" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-medium">{{ $group->category->name }}</h3>
                    <p class="mt-1 text-base text-stone-500">План {{ money($group->planned) }} · факт {{ money($group->actual) }}</p>
                </div>
                @if ($group->planned > 0 || $group->actual > 0)
                    <x-variance :delta="$group->variance()" :kind="$kind" />
                @endif
            </div>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-stone-100">
                <div class="h-full rounded-full {{ $kind === 'expense' && $group->over() ? 'bg-rose-700' : 'bg-tide' }}" style="width: {{ $group->percent() }}%"></div>
            </div>

            @if ($group->lines->isNotEmpty())
                <ul class="mt-4 space-y-2">
                    @foreach ($group->lines as $line)
                        <li wire:key="line-{{ $line->id }}" class="flex flex-wrap items-center gap-2 text-base">
                            <span class="min-w-32 flex-1">
                                {{ $line->title }}
                                @if ($line->expected_on)
                                    <span class="text-stone-400">· {{ $line->installment ? ru_pay_date($line->expected_on) : ru_date($line->expected_on) }}</span>
                                @endif
                            </span>
                            <input
                                wire:model="planAmounts.{{ $line->id }}"
                                wire:blur="savePlanAmount({{ $line->id }})"
                                data-money
                                inputmode="decimal"
                                class="w-28 rounded-xl border border-stone-200 px-2 py-2 text-right {{ $errors->has('planAmounts.'.$line->id) ? 'field-invalid' : '' }}"
                                aria-label="План: {{ $line->title }}"
                            >
                            @if ($line->transactions->isEmpty() && ! ($group->lines->count() === 1 && $group->transactions->contains(fn ($transaction) => $transaction->plan_line_id === null)))
                                <button type="button" wire:click="deletePlanLine({{ $line->id }})" wire:confirm="{{ __('Убрать «:title» из плана?', ['title' => $line->title]) }}" class="rounded-xl px-2 py-2 text-stone-400 hover:text-rose-700">{{ __('Удалить') }}</button>
                            @endif
                        </li>
                        @error('planAmounts.'.$line->id) <li class="text-base text-rose-700">{{ $message }}</li> @enderror
                    @endforeach
                </ul>
            @endif

            @if ($group->transactions->isNotEmpty())
                <ul class="mt-3 divide-y divide-stone-100">
                    @foreach ($group->transactions as $transaction)
                        <li wire:key="tx-{{ $transaction->id }}" class="flex items-center justify-between gap-3 py-2 text-base">
                            <span class="inline-block w-[11ch] shrink-0 tabular-nums text-stone-500">{{ ru_date($transaction->occurred_on) }}</span>
                            <span class="min-w-0 flex-1 truncate text-stone-500">
                                @if ($transaction->note)
                                    {{ $transaction->note }}
                                @endif
                            </span>
                            <span class="flex items-center gap-3">
                                <span class="font-medium">{{ money($transaction->amount_cents) }}</span>
                                <button type="button" wire:click="deleteTransaction({{ $transaction->id }})" wire:confirm="{{ __('Удалить эту операцию?') }}" class="text-stone-400 hover:text-rose-700">{{ __('Удалить') }}</button>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @elseif ($group->planned === 0)
                <p class="mt-3 text-base text-stone-400">{{ __('Пока пусто') }}</p>
            @endif
        </article>
    @endforeach
</section>
