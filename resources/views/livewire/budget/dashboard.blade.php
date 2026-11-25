@php
    $periodEnded = ! $progress['underway'] && $progress['remaining'] === 0;
    $shown = $periodEnded ? $figures->actualClosing() : $cashNow;
    $target = $figures->plannedClosing();
    $delta = $target - $shown;
    $scale = max($target, 1);
    $nowPct = (int) max(0, min(100, round($shown / $scale * 100)));
    $periodPct = $progress['length'] > 0 ? (int) round($progress['elapsed'] / $progress['length'] * 100) : 0;
    $incomePct = $figures->plannedIncome > 0 ? (int) min(100, round($figures->actualIncome / $figures->plannedIncome * 100)) : 0;
    $expensePct = $figures->plannedExpense > 0 ? (int) min(100, round($figures->actualExpense / $figures->plannedExpense * 100)) : ($figures->actualExpense > 0 ? 100 : 0);
    $expenseOver = $figures->actualExpense > $figures->plannedExpense;
    $monthCurrent = $asOf >= $period->starts_on->toDateString() && $asOf <= $period->ends_on->toDateString();
    $monthFuture = $asOf < $period->starts_on->toDateString();
    $monthOpen = $asOf <= $period->ends_on->toDateString();
@endphp

<div
    class="space-y-4"
    data-budget-day="{{ $asOf }}"
    data-budget-midnight="{{ $dayRefreshAt }}"
    data-budget-now="{{ $serverNow }}"
>
    @foreach (['income', 'expense'] as $noteKind)
        <datalist id="note-suggestions-{{ $noteKind }}">
            @foreach ($noteSuggestions[$noteKind] ?? [] as $suggestion)
                <option value="{{ $suggestion }}"></option>
            @endforeach
        </datalist>
    @endforeach
    <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 items-center gap-2">
            <button type="button" wire:click="previous" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-sky-800 text-white shadow-sm" aria-label="{{ __('Предыдущий месяц') }}">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 6l-6 6 6 6" />
                </svg>
            </button>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="inline-grid h-8 w-8 shrink-0 place-items-center rounded-full" style="background: conic-gradient(#1c1917 {{ $periodPct }}%, #e7e5e4 0)" title="День {{ $progress['elapsed'] }} из {{ $progress['length'] }}">
                        <span class="h-6 w-6 rounded-full bg-paper"></span>
                    </span>
                    <div class="min-w-0">
                        <h1 class="text-2xl font-semibold leading-tight tracking-tight">
                            {{ ru_month($period->starts_on) }}
                        </h1>
                        <p class="break-words text-base text-stone-600">
                            {{ ru_date($period->starts_on) }} — {{ ru_date($period->ends_on) }}
                            @if ($progress['underway'])
                                · день {{ $progress['elapsed'] }} из {{ $progress['length'] }}
                                @if ($progress['remaining'] > 0)
                                    · ещё {{ ru_days($progress['remaining']) }}
                                @endif
                            @elseif ($progress['remaining'] === 0)
                                Период уже закончился
                            @else
                                Период ещё не начался
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            <button type="button" wire:click="next" @disabled(! $canGoForward) class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-sky-800 text-white shadow-sm {{ $canGoForward ? '' : 'opacity-40' }}" aria-label="{{ __('Следующий месяц') }}">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 6l6 6-6 6" />
                </svg>
            </button>
        </div>
    </section>

    @if (session('status'))
        <p class="rounded-2xl bg-tide-soft px-4 py-3 text-base text-tide">{{ session('status') }}</p>
    @endif

    @php
        $stillIncome = (int) $incomeGroups->sum(fn ($group) => $group->outstanding());
        $stillExpense = (int) $expenseGroups->sum(fn ($group) => $group->outstanding());
        $operatingAccounts = $accounts->filter(fn ($account) => $account->role !== \App\Enums\AccountRole::Savings);
        $savingsAccounts = $accounts->filter(fn ($account) => $account->role === \App\Enums\AccountRole::Savings);
        $savingsTotal = (int) $savingsAccounts->sum(fn ($account) => $walletBalances[$account->id] ?? 0);
        $showWallet = $asOf >= $period->starts_on->toDateString();
        $previousBalance = $cashNow + $priorPlanNet;
        $planIncome = (int) $figures->plannedIncome;
        $planExpense = (int) $figures->plannedExpense + (int) $expenseGroups->sum(fn ($group) => $group->overspend());
        $periodBalance = $planIncome - $planExpense;
        $forecast = $periodEnded ? $figures->actualClosing() : $previousBalance + $stillIncome - $stillExpense;
        $pieSlices = [];
        foreach ($expenseGroups as $group) {
            if ($group->category->parent_id !== null) {
                continue;
            }
            $descendantIds = \App\Support\CategoryTree::descendantIds(
                $expenseGroups->map(fn ($item) => $item->category),
                $group->category->id,
            );
            $planned = $group->planned + (int) $expenseGroups
                ->filter(fn ($item) => in_array($item->category->id, $descendantIds, true))
                ->sum('planned');
            if ($planned <= 0) {
                continue;
            }
            $pieSlices[] = ['name' => $group->category->name, 'planned' => $planned];
        }
        $pieParts = [];
        $pieCursor = 0;
        $pieColors = ['#38bdf8', '#34d399', '#fde68a', '#fb923c', '#fb7185', '#a78bfa', '#94a3b8', '#f472b6', '#2dd4bf'];
        $pieTotal = max(1, (int) array_sum(array_column($pieSlices, 'planned')));
        foreach ($pieSlices as $index => $slice) {
            $share = $slice['planned'] / $pieTotal * 100;
            $color = $pieColors[$index % count($pieColors)];
            $pieParts[] = $color.' '.$pieCursor.'% '.($pieCursor + $share).'%';
            $pieSlices[$index]['color'] = $color;
            $pieCursor += $share;
        }
    @endphp

    <section class="grid gap-3 lg:grid-cols-5" wire:key="budget-summary-{{ $previousBalance }}-{{ $periodBalance }}-{{ $forecast }}-{{ $pieTotal }}">
        <article class="min-w-0 rounded-2xl bg-sky-50 p-3 ring-1 ring-sky-100 sm:p-4 lg:col-span-3">
            @if ($showWallet)
                <dl class="space-y-1.5 text-lg">
                    <div class="flex items-start justify-between gap-3 font-semibold">
                        <dt class="min-w-0">{{ __('Всего денег на счетах') }}</dt>
                        <dd class="shrink-0 whitespace-nowrap tabular-nums">{{ money($cashNow) }}</dd>
                    </div>
                    @foreach ($operatingAccounts as $account)
                        <div class="flex items-center justify-between gap-3 text-stone-600">
                            <dt class="flex min-w-0 items-center gap-2">
                                <x-account-icon :account="$account" />
                                <span class="min-w-0 break-words">{{ $account->name }}</span>
                            </dt>
                            <dd class="shrink-0 whitespace-nowrap tabular-nums">{{ money($walletBalances[$account->id]) }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($savingsAccounts->isNotEmpty())
                    <dl class="mt-3 space-y-1.5 border-t border-sky-200 pt-3 text-lg">
                        <div class="flex items-start justify-between gap-3 font-semibold">
                            <dt class="min-w-0">{{ __('Накопления') }}</dt>
                            <dd class="shrink-0 whitespace-nowrap tabular-nums">{{ money($savingsTotal) }}</dd>
                        </div>
                        @foreach ($savingsAccounts as $account)
                            <div class="flex items-center justify-between gap-3 text-stone-600">
                                <dt class="flex min-w-0 items-center gap-2">
                                    <x-account-icon :account="$account" />
                                    <span class="min-w-0 break-words">{{ $account->name }}</span>
                                </dt>
                                <dd class="shrink-0 whitespace-nowrap tabular-nums">{{ money($walletBalances[$account->id]) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            @endif
            <dl class="space-y-1.5 text-lg {{ $showWallet ? 'mt-3 border-t border-sky-200 pt-3' : '' }}">
                <div class="flex items-start justify-between gap-3 font-semibold {{ $periodBalance < 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                    <dt class="min-w-0">{{ __('Сводка') }}</dt>
                    <dd class="shrink-0 whitespace-nowrap tabular-nums">{{ $periodBalance > 0 ? '+' : '' }}{{ money($periodBalance) }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3 pl-3 text-emerald-700">
                    <dt class="min-w-0">{{ __('Доход') }}</dt>
                    <dd class="shrink-0 whitespace-nowrap tabular-nums">{{ money($planIncome) }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3 pl-3 text-stone-700">
                    <dt class="min-w-0">{{ __('Расход') }}</dt>
                    <dd class="shrink-0 whitespace-nowrap tabular-nums">{{ money($planExpense) }}</dd>
                </div>
            </dl>
            <a href="{{ route('debts') }}" class="mt-3 flex w-full flex-col gap-2 rounded-2xl bg-white px-3 py-3 text-left ring-1 ring-sky-200">
                <span class="text-lg font-medium">{{ __('Долги') }}</span>
                <span class="flex items-start justify-between gap-3 text-base font-semibold tabular-nums">
                    <span class="min-w-0 text-emerald-700">{{ __('Дебиторские долги') }}</span>
                    <span class="shrink-0 whitespace-nowrap text-emerald-700">{{ money($receivableDebts) }}</span>
                </span>
                <span class="flex items-start justify-between gap-3 text-base font-semibold tabular-nums">
                    <span class="min-w-0 text-rose-700">{{ __('Кредиторские долги') }}</span>
                    <span class="shrink-0 whitespace-nowrap text-rose-700">{{ money($payableDebts) }}</span>
                </span>
            </a>
        </article>
        <article class="min-w-0 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-stone-900/5 sm:p-4 lg:col-span-2">
            @foreach ($priorPlans as $prior)
                <div class="flex items-center justify-between gap-3 {{ $loop->first ? '' : 'mt-3 border-t border-stone-200 pt-3' }}">
                    <p class="min-w-0 text-lg font-semibold">Баланс {{ $prior['label'] }}</p>
                    <p class="shrink-0 rounded-full px-3 py-1 text-base font-semibold text-white tabular-nums {{ $prior['net'] < 0 ? 'bg-rose-600' : 'bg-emerald-600' }}">{{ money($prior['net']) }}</p>
                </div>
            @endforeach
            <div class="flex items-center justify-between gap-3 {{ $priorPlans->isNotEmpty() ? 'mt-3 border-t border-stone-200 pt-3' : '' }}">
                <p class="min-w-0 text-lg font-semibold">Баланс {{ ru_month($period->starts_on) }}</p>
                <p class="shrink-0 rounded-full px-3 py-1 text-base font-semibold text-white tabular-nums {{ $forecast < 0 ? 'bg-rose-600' : 'bg-emerald-600' }}">{{ money($forecast) }}</p>
            </div>
            @if ($periodEnded)
                <p class="mt-2 text-base text-stone-700">{{ __('Столько денег было в кошельках в конце месяца.') }}</p>
            @elseif ($priorPlans->isEmpty())
                <p class="mt-2 text-base text-stone-700">{{ __('Деньги на счетах, плюс доход по плану, который ещё не пришёл, минус расход по плану, который ещё не сделан.') }}</p>
            @endif
            <p class="mt-4 text-base font-medium">{{ __('Структура бюджета') }}</p>
            @if ($pieParts === [])
                <p class="mt-3 text-base text-stone-400">{{ __('Пока нет запланированных расходов.') }}</p>
            @else
                <div class="mt-3 flex flex-col items-start gap-4 min-[420px]:flex-row">
                    <div class="h-24 w-24 shrink-0 rounded-full" style="background: conic-gradient({{ implode(', ', $pieParts) }})"></div>
                    <ul class="grid min-w-0 flex-1 grid-cols-2 gap-x-3 gap-y-1 text-base">
                        @foreach ($pieSlices as $slice)
                            <li class="flex min-w-0 items-center gap-2">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $slice['color'] }}"></span>
                                <span class="truncate">{{ $slice['name'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <a href="{{ route('shopping') }}" class="mt-4 flex w-full items-center justify-center rounded-2xl bg-orange-50 px-4 py-4 text-lg font-semibold text-orange-900 ring-1 ring-orange-100">{{ __('Список покупок') }}</a>
            <a href="{{ route('statistics') }}" class="mt-3 flex w-full items-center justify-center rounded-2xl bg-sky-800 px-4 py-4 text-lg font-semibold text-white" style="box-shadow: 0 4px 0 #0c4a6e">{{ __('Статистика') }}</a>
        </article>
    </section>

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start">
        <div class="w-full sm:w-auto">
            <span class="hidden text-base sm:block" style="visibility: hidden" aria-hidden="true">{{ __('Дата') }}</span>
            <button type="button" wire:click="copyToNextMonth" wire:confirm="Скопировать бюджет в следующий месяц? Строки, которые уже были скопированы, не повторятся." class="mt-1 inline-flex w-full items-center justify-center gap-1.5 rounded-full bg-sky-100 px-3 py-2 text-base font-medium text-sky-900 ring-1 ring-sky-100 sm:w-auto">
                {{ __('Копировать бюджет') }}
            </button>
        </div>
        @if ($accounts->count() > 1)
            <div class="w-full sm:w-auto">
                <span class="hidden text-base sm:block" style="visibility: hidden" aria-hidden="true">{{ __('Дата') }}</span>
                <button type="button" wire:click="openTransfer" class="mt-1 inline-flex w-full items-center justify-center rounded-full bg-amber-50 px-3 py-2 text-base font-medium text-amber-800 ring-1 ring-stone-900/10 sm:w-auto {{ $showTransfer ? 'ring-2 ring-sky-800' : '' }}">
                    {{ __('Перевод денег на другой счёт') }}
                </button>
            </div>
        @endif
    </div>
    @if ($showTransfer)
        <form wire:submit="transferFunds" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5">
            <h2 class="text-lg font-semibold text-sky-900">{{ __('Перевод денег на другой счёт') }}</h2>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="text-base text-stone-500">{{ __('Откуда') }}</span>
                    <select wire:model="transferFromId" class="mt-1 w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-base {{ $errors->has('transferFromId') ? 'field-invalid' : '' }}">
                        <option value="">{{ __('Счёт') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                    @error('transferFromId') <p class="mt-1 text-base leading-snug text-rose-700">{{ $message }}</p> @enderror
                </label>
                <label class="block">
                    <span class="text-base text-stone-500">{{ __('Куда') }}</span>
                    <select wire:model="transferToId" class="mt-1 w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-base {{ $errors->has('transferToId') ? 'field-invalid' : '' }}">
                        <option value="">{{ __('Счёт') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                    @error('transferToId') <p class="mt-1 text-base leading-snug text-rose-700">{{ $message }}</p> @enderror
                </label>
                <label class="block">
                    <span class="text-base text-stone-500">{{ __('Сумма, €') }}</span>
                    <input wire:model="transferAmount" data-money inputmode="decimal" placeholder="10,00" class="mt-1 w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-base {{ $errors->has('transferAmount') ? 'field-invalid' : '' }}">
                    @error('transferAmount') <p class="mt-1 text-base leading-snug text-rose-700">{{ $message }}</p> @enderror
                </label>
                <label class="block">
                    <span class="text-base text-stone-500">{{ __('Дата') }}</span>
                    <x-date-input wire:model="transferDate" class="mt-1 block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-base {{ $errors->has('transferDate') ? 'field-invalid' : '' }}" />
                    @error('transferDate') <p class="mt-1 text-base leading-snug text-rose-700">{{ $message }}</p> @enderror
                </label>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="submit" class="rounded-xl bg-sky-800 px-4 py-2 text-base font-medium text-white">{{ __('Перевести') }}</button>
                <button type="button" wire:click="closeTransfer" class="rounded-xl bg-rose-600 px-4 py-2 text-base font-medium text-white">{{ __('Отмена') }}</button>
            </div>
        </form>
    @endif


    @if ($showEntry)
        <form wire:submit="saveTransaction" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5">
            <h2 class="font-medium">{{ __('Факт: деньги уже пришли или ушли') }}</h2>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" wire:click="$set('entryType', 'income')" class="rounded-2xl px-3 py-2.5 text-base font-medium {{ $entryType === 'income' ? 'bg-tide text-white' : 'bg-stone-100 text-stone-700' }}">{{ __('Поступление') }}</button>
                <button type="button" wire:click="$set('entryType', 'expense')" class="rounded-2xl px-3 py-2.5 text-base font-medium {{ $entryType === 'expense' ? 'bg-ink text-white' : 'bg-stone-100 text-stone-700' }}">{{ __('Расход') }}</button>
            </div>
            <label class="mt-3 block">
                <span class="text-base text-stone-600">{{ __('Сумма, €') }}</span>
                <input wire:model="entryAmount" data-money inputmode="decimal" placeholder="{{ __('Сумма') }}" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-2.5 text-3xl font-semibold outline-none focus:border-tide {{ $errors->has('entryAmount') ? 'field-invalid' : '' }}">
            </label>
            @error('entryAmount') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Категория') }}</span>
                    @include('livewire.budget.partials.category-picker', [
                        'property' => 'entryCategoryId',
                        'value' => $entryCategoryId,
                        'categories' => $entryCategories,
                        'placeholder' => __('Выберите'),
                    ])
                </label>
                <div class="mt-2 flex flex-wrap gap-2">
                    <input wire:model="quickCategoryName" class="min-w-40 flex-1 rounded-2xl border border-dashed border-stone-300 px-3 py-2.5 outline-none focus:border-tide {{ $errors->has('quickCategoryName') ? 'field-invalid' : '' }}" placeholder="{{ __('Или новая категория') }}">
                    <div class="min-w-40 flex-1">
                        @include('livewire.budget.partials.category-picker', [
                            'property' => 'quickParentId',
                            'value' => $quickParentId,
                            'categories' => $categories->filter(fn ($category) => $category->parent_id === null && $category->type->value === $entryType),
                            'placeholder' => __('Без материнской'),
                            'label' => __('Материнская категория'),
                        ])
                    </div>
                    <button type="button" wire:click="addQuickCategory" class="shrink-0 rounded-2xl bg-emerald-600 px-3 py-2.5 text-base font-medium text-white">{{ __('Добавить') }}</button>
                    @include('livewire.budget.partials.icon-picker', ['iconProperty' => 'quickCategoryIcon', 'iconValue' => $quickCategoryIcon])
                </div>
                @error('quickCategoryName') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
                @error('quickCategoryIcon') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
                <label class="block">
                    <span class="text-base text-stone-600">{{ __('Дата') }}</span>
                    <x-date-input wire:model="entryDate" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-2.5 outline-none focus:border-tide {{ $errors->has('entryDate') ? 'field-invalid' : '' }}" />
                </label>
                <label class="block sm:col-span-2">
                    <span class="text-base text-stone-600">{{ __('Счёт') }}</span>
                    <select wire:model="entryAccountId" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-2.5 outline-none focus:border-tide {{ $errors->has('entryAccountId') ? 'field-invalid' : '' }}">
                        <option value="">{{ __('Выберите счёт') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            @error('entryCategoryId') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
            @error('entryAccountId') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
            @error('entryDate') <p class="mt-1 text-base text-rose-700">{{ $message }}</p> @enderror
            @if ($entryPlanLines->isNotEmpty())
                <label class="mt-3 block">
                    <span class="text-base text-stone-600">{{ __('К какой строке плана') }}</span>
                    <select wire:model="entryPlanLineId" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-2.5 outline-none focus:border-tide">
                        <option value="">{{ __('Без привязки') }}</option>
                        @foreach ($entryPlanLines as $line)
                            <option value="{{ $line->id }}">{{ $line->title }} · {{ money($line->amount_cents) }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label class="mt-3 block">
                <span class="text-base text-stone-600">{{ __('Примечание') }}</span>
                    <input wire:model="entryNote" list="note-suggestions-{{ $entryType }}" autocomplete="off" maxlength="200" class="mt-1 w-full rounded-2xl border border-stone-200 px-3 py-2.5 outline-none focus:border-tide {{ $errors->has('entryNote') ? 'field-invalid' : '' }}" placeholder="{{ __('Например, магазин') }}">
            </label>
            <button type="submit" class="mt-3 rounded-2xl bg-emerald-600 px-4 py-2.5 text-base font-medium text-white">{{ __('Записать факт') }}</button>
        </form>
    @endif

    <div class="flex flex-col gap-2 sm:flex-row">
        <button type="button" wire:click="openQuick('income')" class="rounded-2xl bg-emerald-600 px-4 py-3 text-lg font-semibold text-white {{ $quickKind === 'income' ? 'ring-2 ring-emerald-900 ring-offset-2' : '' }}">{{ __('Добавить приход') }}</button>
        <button type="button" wire:click="openQuick('expense')" class="rounded-2xl bg-tide px-4 py-3 text-lg font-semibold text-white {{ $quickKind === 'expense' ? 'ring-2 ring-tide ring-offset-2' : '' }}">{{ __('Добавить расход') }}</button>
    </div>
    @if ($quickKind)
        @include('livewire.budget.partials.quick-entry')
    @endif
    @if (session('status'))
        <p class="rounded-2xl bg-emerald-50 px-4 py-3 text-base text-emerald-800">{{ session('status') }}</p>
    @endif

    <div class="budget-sheet">
        <table class="w-full border-collapse text-lg">
            <tbody>
                @include('livewire.budget.partials.budget-rows', ['groups' => $incomeGroups, 'kind' => 'income'])
                @include('livewire.budget.partials.budget-rows', ['groups' => $expenseGroups, 'kind' => 'expense'])
            </tbody>
        </table>
    </div>

    @if ($monthOpen)
        <button type="button" wire:click="togglePlan" class="w-full rounded-2xl bg-sky-800 px-4 py-3 text-lg font-semibold text-white shadow-sm sm:w-auto">{{ __('Добавить запись в бюджет') }}</button>
    @endif

    @if ($showPlan)
        <form id="plan-entry" wire:submit="addPlanLine" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5" style="scroll-margin-top: 1rem">
            <h2 class="font-medium">Запись в {{ ru_month($period->starts_on) }}</h2>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" wire:click="$set('newPlanType', 'expense')" class="rounded-2xl px-3 py-2.5 text-base font-medium {{ $newPlanType === 'expense' ? 'bg-ink text-white' : 'bg-stone-100 text-stone-700' }}">{{ __('Расход') }}</button>
                <button type="button" wire:click="$set('newPlanType', 'income')" class="rounded-2xl px-3 py-2.5 text-base font-medium {{ $newPlanType === 'income' ? 'bg-emerald-600 text-white' : 'bg-emerald-100 text-emerald-800' }}">{{ __('Приход') }}</button>
            </div>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="block min-w-0">
                    <span class="text-base text-stone-600">{{ __('Категория') }}</span>
                    @include('livewire.budget.partials.category-picker', [
                        'property' => 'newPlanCategoryId',
                        'value' => $newPlanCategoryId,
                        'categories' => $categories->where('type.value', $newPlanType),
                        'placeholder' => 'Выберите',
                    ])
                </div>
                <label class="block min-w-0">
                    <span class="text-base text-stone-600">{{ __('Примечание, можно пусто') }}</span>
                    <input wire:model="newPlanTitle" maxlength="80" class="mt-1 w-full min-w-0 rounded-2xl border border-stone-200 px-3 py-2.5 outline-none focus:border-tide {{ $errors->has('newPlanTitle') ? 'field-invalid' : '' }}" placeholder="{{ __('Например, страховка машины') }}">
                </label>
                <label class="block min-w-0">
                    <span class="text-base text-stone-600">{{ __('Сумма, €') }}</span>
                    <input wire:model="newPlanAmount" data-money inputmode="decimal" class="mt-1 w-full min-w-0 rounded-2xl border border-stone-200 px-3 py-2.5 outline-none focus:border-tide {{ $errors->has('newPlanAmount') ? 'field-invalid' : '' }}" placeholder="25,00">
                </label>
                <label class="block min-w-0">
                    <span class="text-base text-stone-600">{{ __('Дата платежа, можно пусто') }}</span>
                    <x-date-input wire:model="newPlanDate" class="mt-1 w-full min-w-0 rounded-2xl border border-stone-200 px-3 py-2.5 outline-none focus:border-tide {{ $errors->has('newPlanDate') ? 'field-invalid' : '' }}" />
                </label>
            </div>
            @error('newPlanCategoryId') <p class="mt-2 text-base text-rose-700">{{ $message }}</p> @enderror
            @error('newPlanAmount') <p class="mt-2 text-base text-rose-700">{{ $message }}</p> @enderror
            @error('newPlanDate') <p class="mt-2 text-base text-rose-700">{{ $message }}</p> @enderror
            @error('newPlanTitle') <p class="mt-2 text-base text-rose-700">{{ $message }}</p> @enderror
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="submit" class="rounded-2xl bg-emerald-600 px-4 py-2.5 text-base font-medium text-white">{{ __('Добавить') }}</button>
                <button type="button" wire:click="togglePlan" class="rounded-2xl bg-rose-600 px-4 py-2.5 text-base font-medium text-white">{{ __('Отмена') }}</button>
            </div>
        </form>
    @endif

    <button
        type="button"
        onclick="window.scrollTo({ top: 0, behavior: 'smooth' })"
        class="inline-grid place-items-center rounded-full bg-sky-800 text-white shadow-sm"
        style="position: fixed; right: 1rem; bottom: 1rem; z-index: 40; width: 3rem; height: 3rem; touch-action: manipulation"
        title="{{ __('Наверх') }}"
        aria-label="{{ __('Наверх') }}"
    >
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 15l6-6 6 6" />
        </svg>
    </button>
</div>
