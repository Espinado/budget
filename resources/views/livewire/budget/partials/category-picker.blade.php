@once
    <style>
        .category-picker summary { list-style: none; cursor: pointer; }
        .category-picker summary::-webkit-details-marker { display: none; }
        .category-picker[open] summary svg { transform: rotate(180deg); }
    </style>
@endonce
@php
    $choices = \App\Support\CategoryTree::choices($categories);
    $chosen = $categories->firstWhere('id', (int) $value);
    $chosenLabel = $chosen ? \App\Support\CategoryTree::label($categories, $chosen) : ($placeholder ?? 'Выберите');
    $pickerLabel = isset($label) ? $label : __('Категория');
@endphp
<details class="category-picker mt-1 {{ $errors->has($property) ? 'field-invalid' : '' }}" ontoggle="if (this.open) this.querySelector('[data-selected]')?.scrollIntoView({ block: 'nearest' })">
    <summary class="flex w-full min-w-0 items-center justify-between gap-2 rounded-2xl border border-stone-200 bg-white px-3 py-2.5 text-base {{ $value ? 'text-ink' : 'text-stone-500' }}">
        <span class="min-w-0">{{ $chosenLabel }}</span>
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true" class="shrink-0 text-stone-500">
            <path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </summary>
    <div class="mt-1 divide-y divide-stone-100 rounded-2xl border border-stone-200 bg-white" role="listbox" aria-label="{{ $pickerLabel }}" style="max-height: 15rem; overflow-y: auto; overscroll-behavior: contain">
        @if ($placeholder !== null)
            <button type="button" role="option" @if (! $value) data-selected aria-selected="true" @else aria-selected="false" @endif class="block w-full px-3 py-2.5 text-left text-base {{ $value ? 'text-stone-700' : 'bg-sky-100 font-medium text-ink' }}" wire:click="$set('{{ $property }}', null)" onclick="this.closest('details').open = false">{{ $placeholder }}</button>
        @endif
        @foreach ($choices as $choice)
            @php
                $branchCategory = $choice['category'];
                $depth = (int) $choice['depth'];
                $pad = number_format(0.75 + ($depth * 1.25), 2, '.', '');
                $selected = $choice['selectable'] && (string) $value === (string) $branchCategory->id;
                $weight = $depth === 0 ? 700 : ($selected ? 500 : 400);
            @endphp
            @if ($choice['selectable'])
                <button type="button" role="option" data-category-choice="{{ $branchCategory->id }}" data-depth="{{ $depth }}" data-selectable="1" style="padding-left: {{ $pad }}rem; font-weight: {{ $weight }}" @if ($selected) data-selected aria-selected="true" @else aria-selected="false" @endif class="block w-full py-2.5 pr-3 text-left text-base {{ $selected ? 'bg-sky-100 text-ink' : ($depth === 0 ? 'text-ink' : 'text-stone-700') }}" wire:click="$set('{{ $property }}', {{ $branchCategory->id }})" onclick="this.closest('details').open = false">{{ $branchCategory->name }}</button>
            @else
                <div role="presentation" data-category-choice="{{ $branchCategory->id }}" data-depth="{{ $depth }}" data-selectable="0" style="padding-left: {{ $pad }}rem; font-weight: 700" class="py-2.5 pr-3 text-base text-ink">{{ $branchCategory->name }}</div>
            @endif
        @endforeach
    </div>
</details>
