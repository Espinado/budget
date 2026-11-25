@php
    $icons = \App\Support\CategoryIcons::all();
    $selected = is_string($iconValue ?? null) ? $iconValue : '';
@endphp

<div class="w-full basis-full rounded-3xl border border-stone-200 bg-stone-50 p-4 {{ $errors->has($iconProperty) ? 'field-invalid' : '' }}">
    <p class="text-lg font-medium text-ink">{{ __('Выберите иконку') }}</p>
    <div class="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-8">
        @foreach ($icons as $key => $label)
            <button
                type="button"
                wire:click="$set('{{ $iconProperty }}', '{{ $key }}')"
                aria-label="{{ $label }}"
                aria-pressed="{{ $selected === $key ? 'true' : 'false' }}"
                title="{{ $label }}"
                class="flex h-14 items-center justify-center rounded-2xl {{ $selected === $key ? 'bg-white ring-2 ring-sky-800' : 'hover:bg-white' }}"
            >
                <x-mark :icon="$key" :for="$label" class="h-11 w-11" />
            </button>
        @endforeach
    </div>
</div>
