@props(['account'])

@php($mark = \App\Support\AccountIcons::mark($account))

<span {{ $attributes->merge(['class' => "inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl {$mark['tone']}"]) }} aria-hidden="true">
    <x-icon :name="$mark['icon']" />
</span>
