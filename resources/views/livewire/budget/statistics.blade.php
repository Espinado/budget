<div class="space-y-6">
    <div>
        <x-back-link :href="route('dashboard')">{{ __('← К бюджету') }}</x-back-link>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('Статистика') }}</h1>
        <p class="mt-2 max-w-2xl text-stone-600">{{ __('Сколько пришло и ушло за выбранные дни. Категорию можно открыть и посмотреть покупки. Переводы в доходы и расходы не входят.') }}</p>
    </div>

    <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5">
        <div class="flex flex-wrap gap-2">
            @foreach (['month' => __('Этот месяц'), 'week' => __('Эта неделя'), 'custom' => __('Свой период')] as $value => $label)
                <button type="button" wire:click="setRange('{{ $value }}')" class="rounded-full px-4 py-2 text-base font-medium {{ $range === $value ? 'bg-sky-800 text-white' : 'bg-stone-100 text-stone-700' }}" aria-pressed="{{ $range === $value ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
        @if ($range === 'custom')
            <div class="mt-4 flex flex-wrap items-end gap-3">
                <label class="block text-base">
                    <span class="mb-1 block text-stone-600">{{ __('С') }}</span>
                    <x-date-input wire:model.live="from" class="rounded-2xl border border-stone-200 px-3 py-2" />
                </label>
                <label class="block text-base">
                    <span class="mb-1 block text-stone-600">{{ __('По') }}</span>
                    <x-date-input wire:model.live="to" class="rounded-2xl border border-stone-200 px-3 py-2" />
                </label>
            </div>
        @endif
        @if ($rangeError)
            <p class="mt-3 text-base text-rose-700">{{ $rangeError }}</p>
        @elseif ($bounds)
            <p class="mt-3 text-base text-stone-600">{{ ru_date($bounds[0], true) }} — {{ ru_date($bounds[1], true) }} · {{ __('операций') }} {{ $count }}@if ($transfers->isNotEmpty()) · {{ __('переводов') }} {{ $transfers->count() }}@endif</p>
        @endif
    </section>

    @if ($rangeError === null)
        <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5">
            <div class="stats-hero">
                <p class="text-lg font-medium">{{ __('Расходы') }}</p>
                <p class="stats-hero-amount">{{ money($expense) }}</p>
            </div>
            <div class="mt-3 space-y-1.5 border-t border-stone-200 pt-3 text-lg">
                <div class="stats-hero text-tide">
                    <span>{{ __('Доход') }}</span>
                    <span class="stats-nowrap">{{ money($income) }}</span>
                </div>
                <div class="stats-hero {{ $balance < 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                    <span>{{ __('Разница') }}</span>
                    <span class="stats-nowrap">{{ money($balance) }}</span>
                </div>
            </div>
        </section>

        @if ($count === 0 && $transfers->isEmpty())
            <p class="rounded-3xl bg-white px-4 py-6 text-lg text-stone-600 shadow-sm ring-1 ring-stone-900/5">{{ __('За этот период операций нет.') }}</p>
        @else
            @foreach (['income' => __('Доходы'), 'expense' => __('Расходы')] as $type => $heading)
                @if ($sections[$type]['rows'] !== [])
                    <section>
                        <div class="stats-section stats-section-{{ $type }}">
                            <span>{{ $heading }}</span>
                            <span class="stats-section-total">{{ money($sections[$type]['total']) }}</span>
                        </div>
                        <div class="mt-2 space-y-2">
                            @foreach ($sections[$type]['rows'] as $row)
                                @include('livewire.budget.partials.stats-branch', [
                                    'node' => $row,
                                    'depth' => 0,
                                    'kind' => $type,
                                    'operations' => $operations,
                                ])
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach

            @if ($transfers->isNotEmpty())
                @php $transferTotal = (int) $transfers->sum('amount_cents'); @endphp
                <section>
                    <div class="stats-section stats-section-transfer">
                        <span>{{ __('Переводы') }}</span>
                        <span class="stats-section-total">{{ money($transferTotal) }}</span>
                    </div>
                    <p class="mt-2 px-1 text-base text-stone-600">{{ __('Между счетами. В разницу не входят.') }}</p>
                    <div class="mt-2 space-y-2">
                        @foreach ($transferGroups as $group)
                            @php
                                $open = $this->transferOpen($group['from_id'], $group['to_id']);
                                $label = $group['from'].' → '.$group['to'];
                            @endphp
                            <article class="stats-card">
                                <button type="button" wire:click="toggleTransfer({{ $group['from_id'] }}, {{ $group['to_id'] }})" class="inline-flex items-center gap-2 text-left text-lg font-medium text-ink" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-label="{{ $open ? __('Закрыть') : __('Открыть') }} {{ $label }}">
                                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center budget-chevron rounded-full text-white" aria-hidden="true">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            @if ($open)
                                                <path d="M6 9l6 6 6-6" />
                                            @else
                                                <path d="M9 6l6 6-6 6" />
                                            @endif
                                        </svg>
                                    </span>
                                    <span>{{ $label }}</span>
                                </button>
                                <div class="mt-1">
                                    <button type="button" wire:click="toggleTransfer({{ $group['from_id'] }}, {{ $group['to_id'] }})" class="budget-figure">{{ money($group['amount']) }}</button>
                                </div>
                                @if ($open)
                                    <div class="mt-2 border-t border-stone-200">
                                        @foreach ($group['rows'] as $transfer)
                                            <div class="stats-purchase">
                                                <span class="whitespace-nowrap tabular-nums text-stone-500">{{ ru_date($transfer->occurred_on) }}</span>
                                                <span class="min-w-0 text-stone-700">{{ $transfer->note }}</span>
                                                <span class="whitespace-nowrap text-right font-medium tabular-nums">{{ money($transfer->amount_cents) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        @endif
    @endif
</div>
