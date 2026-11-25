@php
    $children = $categoryChildren->get($category->id, collect());
    $descendantIds = \App\Support\CategoryTree::descendantIds($categories, $category->id);
    $openIds = array_map('intval', $openCategoryIds);
    $showChildren = $children->isNotEmpty() && (
        in_array((int) $category->id, $openIds, true)
        || count(array_intersect($descendantIds, $openIds)) > 0
    );
@endphp
<li class="{{ $depth === 0 ? 'budget-parent-item' : 'budget-nested-item' }}" @if ($depth > 1) style="margin-left: {{ 1.15 * $depth }}rem" @endif>
    @if ($editingCategoryId === $category->id)
        <form wire:submit="saveCategory" class="flex flex-wrap items-center gap-2">
            <input wire:model="editCategoryName" class="min-w-40 flex-1 rounded-2xl border border-stone-200 px-3 py-2 {{ $errors->has('editCategoryName') ? 'field-invalid' : '' }}" aria-label="{{ __('Название категории') }}">
            @include('livewire.budget.partials.icon-picker', ['iconProperty' => 'editCategoryIcon', 'iconValue' => $editCategoryIcon])
            <div class="min-w-40 flex-1">
                @include('livewire.budget.partials.category-picker', [
                    'property' => 'editCategoryParentId',
                    'value' => $editCategoryParentId,
                    'categories' => $categories->filter(fn ($parentOption) => $parentOption->type === $category->type && (int) $parentOption->id !== (int) $category->id && ! in_array((int) $parentOption->id, $descendantIds, true)),
                    'placeholder' => __('Без материнской'),
                    'label' => __('Материнская категория'),
                ])
            </div>
            <button type="submit" class="rounded-full bg-sky-800 px-3 py-1.5 text-base font-medium text-white">{{ __('Сохранить') }}</button>
            <button type="button" wire:click="cancelCategoryEdit" class="rounded-full bg-rose-600 px-3 py-1.5 text-base font-medium text-white">{{ __('Отмена') }}</button>
        </form>
        @error('editCategoryName') <p class="mt-1 basis-full text-base text-rose-700">{{ $message }}</p> @enderror
        @error('editCategoryParentId') <p class="mt-1 basis-full text-base text-rose-700">{{ $message }}</p> @enderror
        @error('editCategoryIcon') <p class="mt-1 basis-full text-base text-rose-700">{{ $message }}</p> @enderror
    @else
        <div class="flex flex-wrap items-center justify-between gap-2">
            @if ($children->isNotEmpty())
                <button type="button" wire:click="toggleCategory({{ $category->id }})" class="inline-flex min-w-0 items-center gap-2 break-words text-left" aria-expanded="{{ $showChildren ? 'true' : 'false' }}" aria-label="{{ $showChildren ? 'Закрыть' : 'Открыть' }} {{ $category->name }}">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500 text-white" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            @if ($showChildren)
                                <path d="M6 9l6 6 6-6" />
                            @else
                                <path d="M9 6l6 6-6 6" />
                            @endif
                        </svg>
                    </span>
                    <x-mark :icon="$category->icon" :for="$category->name" :kind="$category->type->value" class="h-9 w-9" />
                    {{ $category->name }}
                </button>
            @else
                <span class="flex min-w-0 items-center gap-2 break-words"><x-mark :icon="$category->icon" :for="$category->name" :kind="$category->type->value" class="h-9 w-9" />{{ $category->name }}</span>
            @endif
            <span class="flex shrink-0 items-center gap-3">
                <span class="text-stone-400">{{ $category->type->label() }}</span>
                <button type="button" wire:click="editCategory({{ $category->id }})" class="text-base font-medium text-sky-800">{{ __('Изменить') }}</button>
                @if ($canDelete($category))
                    <button type="button" wire:click="deleteCategory({{ $category->id }})" wire:confirm="{{ __('Удалить «:name»?', ['name' => $category->name]) }}" class="text-base font-medium text-rose-700">{{ __('Удалить') }}</button>
                @endif
            </span>
        </div>
    @endif
</li>
@if ($showChildren)
    @foreach ($children as $child)
        @include('livewire.budget.partials.category-node', ['category' => $child, 'depth' => $depth + 1])
    @endforeach
@endif
