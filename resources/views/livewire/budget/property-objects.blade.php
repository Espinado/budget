<div class="space-y-6">
    <div>
        <x-back-link :href="route('dashboard')">{{ __('← К бюджету') }}</x-back-link>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('Объекты') }}</h1>
        <p class="mt-2 max-w-2xl text-base text-stone-600">{{ __('Карточка только указывает на уже существующие долги и строки плана. Платежи, категории и строки бюджета не создаются и не меняются.') }}</p>
        <a href="{{ route('objects.create') }}" class="mt-4 inline-flex w-full items-center justify-center rounded-2xl bg-sky-800 px-4 py-3 text-base font-medium text-white sm:w-auto">{{ __('Новая карточка') }}</a>
    </div>

    @if ($objects->isEmpty())
        <p class="rounded-3xl bg-white px-4 py-4 text-base text-stone-600 shadow-sm ring-1 ring-stone-900/5">{{ __('Пока нет карточек. Можно добавить недвижимое или движимое имущество.') }}</p>
    @else
        <div class="grid grid-cols-1 gap-3">
            @foreach ($objects as $object)
                <a href="{{ route('objects.edit', $object) }}" class="block rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5">
                    <span class="block text-lg font-semibold text-ink">{{ $object->name }}</span>
                    <span class="mt-1 block text-base text-stone-600">{{ $object->kind->label() }} · {{ $object->label }}</span>
                    <span class="mt-2 block text-base text-tide">{{ __('Привязано долгов: :debts, строк плана: :lines', ['debts' => $object->debts_count, 'lines' => $object->plan_lines_count]) }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
