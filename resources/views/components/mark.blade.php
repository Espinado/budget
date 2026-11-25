@props(['for' => '', 'kind' => 'expense', 'icon' => null])

@php
    $known = is_string($icon) && array_key_exists($icon, \App\Support\CategoryIcons::all());
    $chosen = $known ? $icon : \App\Support\CategoryIcons::guess((string) $for, (string) $kind);
    $tone = \App\Support\CategoryIcons::tone($chosen, (string) $kind);
@endphp

<span {{ $attributes->merge(['class' => "inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl {$tone}"]) }}>
    <x-icon :name="$chosen" />
</span>
