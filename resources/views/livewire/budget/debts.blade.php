<div class="space-y-8">
    <div>
        <div class="solvency-bar flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-2xl bg-sky-100 px-3 py-2 text-base font-medium text-sky-900 ring-1 ring-sky-200 hover:bg-sky-200">{{ __('← К бюджету') }}</a>
            @include('livewire.budget.partials.solvency')
        </div>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('Долги') }}</h1>
        <p class="mt-2 max-w-2xl text-stone-600">{{ __('Личные долги — с людьми. Кредиты — банк и лизинг. Нажмите контрагента, чтобы открыть обязательство.') }}</p>
        @if ($showNew)
            @include('livewire.budget.partials.new-debt')
        @else
            <button type="button" wire:click="openNew" class="mt-4 rounded-2xl bg-sky-800 px-4 py-3 text-base font-medium text-white">{{ __('Новый долг') }}</button>
        @endif
        @if (session('status'))
            <p class="mt-3 rounded-2xl bg-emerald-50 px-4 py-3 text-base text-emerald-800">{{ session('status') }}</p>
        @endif
    </div>

    <section class="space-y-3">
        <h2 class="px-1 text-xl font-semibold tracking-tight text-stone-900">{{ __('Личные') }}</h2>
        <div data-multi-open class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
            @include('livewire.budget.partials.debt-accordion', [
                'title' => __('Дебиторские долги'),
                'tone' => 'owe-us',
                'rows' => $personalOwing,
            ])
            @include('livewire.budget.partials.debt-accordion', [
                'title' => __('Кредиторские долги'),
                'tone' => 'we-owe',
                'rows' => $personalOwed,
            ])
        </div>
    </section>

    <section class="space-y-3">
        <h2 class="px-1 text-xl font-semibold tracking-tight text-stone-900">{{ __('Кредиты') }}</h2>
        <div data-multi-open class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
            @include('livewire.budget.partials.debt-accordion', [
                'title' => __('Дебиторские долги'),
                'tone' => 'owe-us',
                'rows' => $bankOwing,
            ])
            @include('livewire.budget.partials.debt-accordion', [
                'title' => __('Кредиторские долги'),
                'tone' => 'we-owe',
                'rows' => $bankOwed,
            ])
        </div>
    </section>

    @include('livewire.budget.partials.credit-calculator')
    @include('livewire.budget.partials.solvency-room')
</div>
