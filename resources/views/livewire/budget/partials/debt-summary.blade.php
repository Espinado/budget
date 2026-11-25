@php
    $heading = $heading ?? null;
@endphp
<div class="space-y-2">
    @if ($heading)
        <h3 class="px-1 text-base font-semibold text-stone-800">{{ $heading }}</h3>
    @endif
    @if ($rows->isEmpty())
        <p class="rounded-2xl bg-stone-50 px-3 py-4 text-base text-stone-500">{{ __('Пока пусто') }}</p>
    @else
        <ul class="space-y-2">
            @foreach ($rows as $debt)
                @php $next = $debt->installments->first(fn ($row) => $row->paid_transaction_id === null && (int) $row->total_cents > 0); @endphp
                <li>
                    <a href="{{ route('debts.show', $debt) }}" class="flex items-center justify-between gap-3 rounded-2xl bg-stone-50 px-3 py-4 hover:bg-stone-100">
                        <span class="min-w-0">
                            <span class="block truncate text-lg font-medium">{{ $debt->counterparty }}</span>
                            <span class="block text-base text-stone-600">
                                @if ($next)
                                    {{ $next->due_on ? ru_pay_date($next->due_on) : __('дата не указана') }} · {{ money($next->total_cents) }}
                                @else
                                    {{ __('выплачен') }}
                                @endif
                            </span>
                        </span>
                        <span class="shrink-0 text-lg font-semibold tabular-nums">{{ money($debt->remainingCents()) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
