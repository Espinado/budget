@foreach ($groups as $group)
    @foreach ($group->lines as $line)
        @php
            $received = (int) $group->transactions->where('plan_line_id', $line->id)->sum('amount_cents');
            $left = $line->amount_cents - $received;
            $ratio = $line->amount_cents > 0 ? (int) min(100, round($received / $line->amount_cents * 100)) : 0;
            $over = $line->amount_cents > 0 && $left < 0;
            $unplanned = $line->amount_cents === 0;
        @endphp
        <article wire:key="line-{{ $line->id }}" class="px-3 py-2.5">
            <div class="flex items-start gap-3">
                <x-mark :for="$line->title.' '.$group->category->name" :kind="$kind" :icon="$group->category->icon" />
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate font-medium">{{ $line->title }}</h3>
                            @php
                                $meta = [];
                                if ($group->category->name !== $line->title) {
                                    $meta[] = $group->category->name;
                                }
                                if ($line->expected_on) {
                                    $meta[] = $line->installment ? ru_pay_date($line->expected_on) : ru_date($line->expected_on);
                                }
                                if ($line->account) {
                                    $meta[] = $line->account->name;
                                }
                            @endphp
                            @if ($meta !== [])
                                <p class="truncate text-xs text-stone-400">{{ implode(' · ', $meta) }}</p>
                            @endif
                        </div>
                        <p class="shrink-0 text-right">
                            <span class="block text-xs text-stone-400">{{ $kind === 'income' ? 'Пришло' : 'Факт' }}</span>
                            <span class="font-semibold tabular-nums">{{ money($received) }}</span>
                        </p>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <label class="inline-flex items-center gap-1.5 rounded-xl bg-paper px-2 py-1 ring-1 ring-stone-900/10">
                            <span class="text-xs font-medium text-stone-500">{{ __('План') }}</span>
                            <input
                                wire:model.live.blur="planAmounts.{{ $line->id }}"
                                data-money
                                inputmode="decimal"
                                placeholder="{{ __('сумма') }}"
                                aria-label="План {{ $line->title }}"
                                class="w-20 bg-transparent text-base font-semibold tabular-nums outline-none placeholder:font-normal placeholder:text-stone-400"
                            >
                            <span class="text-xs text-stone-400">€</span>
                        </label>
                        <button type="button" wire:click="openFact({{ $line->id }})" class="rounded-full bg-ink px-2.5 py-1 text-xs font-medium text-white">{{ __('Факт') }}</button>
                        <p class="text-xs {{ $over ? 'text-rose-700' : 'text-stone-500' }}">
                            @if ($unplanned && $received === 0)
                                впишите план
                            @elseif ($unplanned)
                                план не задан
                            @elseif ($kind === 'income')
                                @if ($received === 0)
                                    ещё не пришло
                                @elseif ($left === 0)
                                    пришло
                                @elseif ($left > 0)
                                    ещё {{ money($left) }}
                                @else
                                    больше плана на {{ money(-$left) }}
                                @endif
                            @else
                                @if ($received === 0)
                                    осталось {{ money($line->amount_cents) }}
                                @elseif ($left === 0)
                                    совпало
                                @elseif ($left > 0)
                                    осталось {{ money($left) }}
                                @else
                                    больше плана на {{ money(-$left) }}
                                @endif
                            @endif
                        </p>
                    </div>
                    @error('planAmounts.'.$line->id) <p class="mt-1 text-xs text-rose-700">{{ $message }}</p> @enderror
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-stone-100">
                        <div class="h-full rounded-full {{ $over ? 'bg-rose-500' : ($kind === 'income' ? 'bg-tide' : 'bg-amber-500') }}" style="width: {{ $ratio }}%"></div>
                    </div>
                </div>
            </div>
        </article>
    @endforeach
@endforeach
