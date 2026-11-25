@php
    $tone = $tone ?? 'owe-us';
    $rows = $rows ?? collect();
    $count = $rows->count();
    $total = $rows->sum(fn ($debt) => $debt->remainingCents());
    $open = $open ?? false;
    $titleClass = match ($tone) {
        'owe-us' => 'text-emerald-900',
        'we-owe' => 'text-rose-900',
        default => 'text-ink',
    };
    $toneBar = match ($tone) {
        'owe-us' => 'bg-emerald-50 ring-emerald-100',
        'we-owe' => 'bg-rose-50 ring-rose-100',
        default => 'bg-stone-50 ring-stone-100',
    };
    $countLabel = $count === 1
        ? __('контрагент')
        : ($count < 5 ? __('контрагента') : __('контрагентов'));
@endphp
<details class="debt-accordion group rounded-3xl bg-white shadow-sm ring-1 ring-stone-900/5" @if ($open) open @endif>
    <summary class="flex cursor-pointer list-none items-start justify-between gap-3 p-4 sm:p-5 [&::-webkit-details-marker]:hidden">
        <span class="min-w-0 flex-1">
            <span class="block rounded-2xl px-3 py-2 text-base font-semibold ring-1 sm:text-lg {{ $titleClass }} {{ $toneBar }}">{{ $title }} <strong>({{ $count }})</strong></span>
            <span class="mt-2 block px-1 text-base text-stone-600">
                @if ($count === 0)
                    {{ __('Пока пусто') }}
                @else
                    {{ $count }} {{ $countLabel }} · {{ __('остаток') }} {{ money($total) }}
                @endif
            </span>
        </span>
        <span class="mt-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sky-800 text-white shadow-sm ring-1 ring-stone-900/5 transition-transform duration-150 group-open:rotate-180" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                <path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
    </summary>
    <div class="border-t border-stone-100 px-4 pb-4 pt-4 sm:px-5 sm:pb-5">
        @include('livewire.budget.partials.debt-summary', ['rows' => $rows])
    </div>
</details>
