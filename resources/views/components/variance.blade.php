@props(['delta', 'kind' => 'balance'])

@php
    $tone = match (true) {
        $delta === 0 => 'bg-stone-100 text-stone-600',
        $kind === 'expense' && $delta > 0 => 'bg-rose-50 text-rose-800',
        $kind === 'expense' && $delta < 0 => 'bg-emerald-50 text-emerald-800',
        $delta > 0 => 'bg-emerald-50 text-emerald-800',
        default => 'bg-rose-50 text-rose-800',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {$tone}"]) }}>
    {{ variance_label($delta, $kind) }}
</span>
