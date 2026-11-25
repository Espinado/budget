@php
    $category = $node['category'];
    $hasChildren = $node['children'] !== [];
    $open = $hasChildren && $this->categoryOpen($category->id);
    $showPurchases = ! $hasChildren && (int) $this->category === (int) $category->id && ! $this->own;
    $purchasesOpen = $showPurchases ? 'true' : 'false';
@endphp
<article class="stats-card" @if ($depth > 0) style="margin-left: {{ min($depth, 4) * 0.75 }}rem" @endif>
    <button
        type="button"
        @if ($hasChildren) wire:click="toggleCategory({{ $category->id }})" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-label="{{ $open ? 'Закрыть' : 'Открыть' }} {{ $category->name }}" @else wire:click="selectCategory({{ $category->id }})" aria-expanded="{{ $purchasesOpen }}" aria-label="{{ $showPurchases ? 'Закрыть покупки' : 'Покупки' }} {{ $category->name }}" @endif
        class="inline-flex items-center gap-2 text-left text-lg font-medium text-ink"
    >
        @if ($hasChildren)
            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center budget-chevron rounded-full text-white" aria-hidden="true">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    @if ($open)
                        <path d="M6 9l6 6 6-6" />
                    @else
                        <path d="M9 6l6 6-6 6" />
                    @endif
                </svg>
            </span>
        @endif
        <x-mark :for="$category->name" :kind="$kind" :icon="$category->icon" class="h-9 w-9" />
        <span>{{ $category->name }}</span>
    </button>
    <div class="mt-1">
        <button type="button" wire:click="{{ $hasChildren ? 'toggleCategory('.$category->id.')' : 'selectCategory('.$category->id.')' }}" class="budget-figure">{{ money($node['amount']) }}</button>
    </div>
    @if ($showPurchases)
        @include('livewire.budget.partials.stats-purchases')
    @endif
</article>
@if ($open)
    @if ($node['own'] > 0)
        @php
            $ownOpen = (int) $this->category === (int) $category->id && $this->own;
        @endphp
        <article class="stats-card" style="margin-left: {{ min($depth + 1, 4) * 0.75 }}rem">
            <button type="button" wire:click="selectCategory({{ $category->id }}, true)" class="inline-flex items-center gap-2 text-left text-lg font-medium text-ink" aria-expanded="{{ $ownOpen ? 'true' : 'false' }}" aria-label="{{ $ownOpen ? 'Закрыть покупки' : 'Покупки' }} без подкатегории">
                <x-mark :for="$category->name" :kind="$kind" :icon="$category->icon" class="h-9 w-9" />
                <span>{{ __('Без подкатегории') }}</span>
            </button>
            <div class="mt-1">
                <button type="button" wire:click="selectCategory({{ $category->id }}, true)" class="budget-figure">{{ money($node['own']) }}</button>
            </div>
            @if ($ownOpen)
                @include('livewire.budget.partials.stats-purchases')
            @endif
        </article>
    @endif
    @foreach ($node['children'] as $child)
        @include('livewire.budget.partials.stats-branch', ['node' => $child, 'depth' => $depth + 1, 'kind' => $kind, 'operations' => $operations])
    @endforeach
@endif
