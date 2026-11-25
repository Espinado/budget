@php
    $visible = $groups->filter(fn ($group) => $group->lines->isNotEmpty() || $group->actual !== 0)->values();
    $plannedSum = (int) $visible->sum('planned');
    $actualSum = (int) $visible->sum('actual');
    $face = function (int $planned, int $actual) use ($kind): array {
        $over = $kind !== 'income' && $actual > $planned;
        $percent = $planned > 0 ? (int) round($actual * 100 / $planned) : null;
        $meter = $percent === null ? ($actual > 0 ? 100 : 0) : (int) min(100, max(0, $percent));
        $done = ! $over && $planned > 0 && $actual >= $planned;

        return [$meter, $done, $percent];
    };
    $rowTone = function (int $planned, int $actual) use ($kind): string {
        $over = $kind !== 'income' && $actual > $planned;

        return $over ? 'budget-over-plan' : 'budget-on-plan';
    };
    $lineDeals = function ($transactions, $lines, $line) {
        return $transactions->filter(function ($transaction) use ($lines, $line) {
            if ((int) $transaction->plan_line_id === (int) $line->id) {
                return true;
            }

            return $transaction->plan_line_id === null && $lines->count() === 1;
        })->values();
    };
    $orphanDeals = function ($transactions, $lines) use ($lineDeals) {
        if ($lines->isEmpty()) {
            return $transactions->values();
        }

        $claimed = $lines->flatMap(
            fn ($line) => $lineDeals($transactions, $lines, $line)->pluck('id'),
        )->map(fn ($id) => (int) $id)->all();

        return $transactions
            ->reject(fn ($transaction) => in_array((int) $transaction->id, $claimed, true))
            ->values();
    };
@endphp

<tr class="budget-section {{ $kind === 'income' ? 'budget-section-income' : 'budget-section-expense' }}">
    <td colspan="6" class="budget-section-title px-4 py-3">
        <span class="budget-section-head flex items-baseline justify-between gap-3">
            <span>{{ $kind === 'income' ? __('Доходы') : __('Расходы') }}</span>
            <span class="budget-section-figures">{{ __('План') }} {{ money($plannedSum) }} · {{ __('Факт') }} {{ money($actualSum) }}</span>
        </span>
    </td>
</tr>

@php
    $childrenByParent = $groups->groupBy(fn ($item) => $item->category->parent_id);
@endphp
@foreach ($groups as $group)
    @continue($group->category->parent_id !== null)
    @php
        $branchActive = function ($snapshot) use (&$branchActive, $childrenByParent): bool {
            if ($snapshot->lines->isNotEmpty() || $snapshot->actual !== 0) {
                return true;
            }

            foreach ($childrenByParent->get($snapshot->category->id, collect()) as $child) {
                if ($branchActive($child)) {
                    return true;
                }
            }

            return false;
        };
        $rolledPlanned = $group->planned;
        $rolledActual = $group->actual;
        $rollDescendants = function (int $parentId) use (&$rollDescendants, &$rolledPlanned, &$rolledActual, $childrenByParent): void {
            foreach ($childrenByParent->get($parentId, collect()) as $child) {
                $rolledPlanned += $child->planned;
                $rolledActual += $child->actual;
                $rollDescendants($child->category->id);
            }
        };
        $rollDescendants($group->category->id);
        $kids = $childrenByParent->get($group->category->id, collect())
            ->filter(fn ($kid) => $branchActive($kid))
            ->values();
        $hasChildren = $kids->isNotEmpty();
        if (! $hasChildren && $group->lines->isEmpty() && $group->actual === 0) {
            continue;
        }
        $single = ! $hasChildren && $group->lines->count() === 1;
        [$groupMeter, $groupDone, $groupPercent] = $face($rolledPlanned, $rolledActual);
    @endphp

    @php $open = $this->categoryOpen($group->category->id); @endphp
    @if (! $single)
        <tr class="budget-parent {{ $rowTone($rolledPlanned, $rolledActual) }}">
            <td colspan="6" class="px-3 py-3">
                <button type="button" wire:click="toggleCategory({{ $group->category->id }})" class="inline-flex items-center gap-2 text-left text-lg font-medium" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-label="{{ $open ? 'Закрыть' : 'Открыть' }} {{ $group->category->name }}">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center budget-chevron rounded-full text-white" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            @if ($open)
                                <path d="M6 9l6 6 6-6" />
                            @else
                                <path d="M9 6l6 6-6 6" />
                            @endif
                        </svg>
                    </span>
                    <x-mark :for="$group->category->name" :kind="$kind" :icon="$group->category->icon" class="h-9 w-9" />
                    {{ $group->category->name }}
                </button>
                @include('livewire.budget.partials.budget-progress', [
                    'planned' => $rolledPlanned,
                    'actual' => $rolledActual,
                    'meter' => $groupMeter,
                    'done' => $groupDone,
                    'percent' => $groupPercent,
                    'click' => 'toggleCategory('.$group->category->id.')',
                    'kind' => $kind,
                    'spendableDays' => $spendableDays,
                ])
            </td>
        </tr>
    @endif

    @if ($single || $open)
    @foreach ($group->lines as $line)
        @php
            $deals = $lineDeals($group->transactions, $group->lines, $line);
            $dealsOpen = $dealsLineId === $line->id && $deals->isNotEmpty();
            $received = (int) $deals->sum('amount_cents');
            [$lineMeter, $lineDone, $linePercent] = $face($line->amount_cents, $received);
            $matchedChild = $kids->first(fn ($kid) => mb_strtolower($kid->category->name) === mb_strtolower($line->title));
            $creditPay = $monthCurrent && $received === 0 && (int) $line->amount_cents > 0 && \App\Services\Budget\Solvency::tracksCreditPlan($group->category);
        @endphp
        <tr wire:key="line-{{ $line->id }}" class="{{ $single ? 'budget-parent' : 'budget-nested' }} {{ $single ? $rowTone($group->planned, $group->actual) : $rowTone($line->amount_cents, $received) }}">
            <td colspan="6" class="px-3 py-3 {{ $single ? '' : 'pl-12' }}">
                <div class="flex w-full items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                @if ($deals->isNotEmpty())
                    <button type="button" wire:click="toggleDeals({{ $line->id }})" class="inline-flex items-center gap-2 text-left text-lg font-medium" aria-expanded="{{ $dealsOpen ? 'true' : 'false' }}" aria-label="{{ $dealsOpen ? __('Закрыть операции') : __('Операции') }} {{ $single ? $group->category->name : $line->title }}">
                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center budget-chevron rounded-full text-white" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                @if ($dealsOpen)
                                    <path d="M6 9l6 6 6-6" />
                                @else
                                    <path d="M9 6l6 6-6 6" />
                                @endif
                            </svg>
                        </span>
                        <x-mark :for="$single ? $group->category->name : $line->title" :kind="$kind" :icon="$single ? $group->category->icon : $matchedChild?->category->icon" class="h-9 w-9" />
                        <span>
                            <span class="text-lg {{ $single ? 'font-medium' : '' }}">{{ $single ? $group->category->name : $line->title }}</span>
                            @if ($single && $line->title !== $group->category->name)
                                <span class="mt-0.5 block text-base font-normal text-stone-500">{{ $line->title }}</span>
                            @endif
                            @if ($line->expected_on || $line->account)
                                <span class="mt-0.5 block text-base font-normal text-stone-400">
                                    @if ($line->expected_on){{ $line->installment ? ru_pay_date($line->expected_on) : ru_date($line->expected_on) }}@endif
                                    @if ($line->account) {{ $line->expected_on ? '·' : '' }} {{ $line->account->name }}@endif
                                </span>
                            @endif
                        </span>
                    </button>
                @else
                    <button type="button" @if ($monthOpen) wire:click="editLine({{ $line->id }})" @endif class="inline-flex items-center gap-2 text-left text-lg font-medium" @if ($monthOpen) aria-label="Изменить план {{ $line->title }}" @endif>
                        <x-mark :for="$single ? $group->category->name : $line->title" :kind="$kind" :icon="$single ? $group->category->icon : $matchedChild?->category->icon" class="h-9 w-9" />
                        <span>
                            <span class="text-lg {{ $single ? 'font-medium' : '' }}">{{ $single ? $group->category->name : $line->title }}</span>
                            @if ($single && $line->title !== $group->category->name)
                                <span class="mt-0.5 block text-base font-normal text-stone-500">{{ $line->title }}</span>
                            @endif
                            @if ($line->expected_on || $line->account)
                                <span class="mt-0.5 block text-base font-normal text-stone-400">
                                    @if ($line->expected_on){{ $line->installment ? ru_pay_date($line->expected_on) : ru_date($line->expected_on) }}@endif
                                    @if ($line->account) {{ $line->expected_on ? '·' : '' }} {{ $line->account->name }}@endif
                                </span>
                            @endif
                        </span>
                    </button>
                @endif
                </div>
                @if ($deals->isEmpty() && $monthOpen)
                    @include('livewire.budget.partials.plan-delete')
                @endif
                </div>
                @include('livewire.budget.partials.budget-progress', [
                    'planned' => $line->amount_cents,
                    'actual' => $received,
                    'meter' => $lineMeter,
                    'done' => $lineDone,
                    'percent' => $linePercent,
                    'click' => $monthOpen ? 'editLine('.$line->id.')' : null,
                    'label' => 'Изменить план '.$line->title,
                    'record' => $monthCurrent ? $line->id : null,
                    'pay' => $creditPay ? $line->id : null,
                    'payTitle' => $line->title,
                    'payCents' => (int) $line->amount_cents,
                    'kind' => $kind,
                    'spendableDays' => $spendableDays,
                    'dealCount' => $deals->count(),
                ])
                @if ((int) ($detailLineId ?? 0) === (int) $line->id)
                    @include('livewire.budget.partials.operation-entry', [
                        'line' => $line,
                        'operationTransactions' => $deals,
                    ])
                @endif
            </td>
        </tr>
        @if ($editingLineId === $line->id)
            <tr class="bg-sky-50/70">
                <td colspan="6" class="px-3 py-2">
                    @include('livewire.budget.partials.line-editor')
                </td>
            </tr>
        @endif
        @error('planAmounts.'.$line->id)
            <tr><td colspan="6" class="px-3 pb-1 text-base text-rose-700">{{ $message }}</td></tr>
        @enderror
        @error('pay.'.$line->id)
            <tr><td colspan="6" class="px-3 pb-1 text-base text-rose-700">{{ $message }}</td></tr>
        @enderror
        @if ($dealsOpen)
            @include('livewire.budget.partials.deals', [
                'lineId' => $line->id,
                'transactions' => $deals,
                'dealClass' => $single ? '' : 'budget-deals-nested',
                'dealShift' => $single ? null : '1.35rem',
            ])
        @endif
    @endforeach
        @if ($open)
            @php $unlinked = $orphanDeals($group->transactions, $group->lines); @endphp
            @if ($unlinked->isNotEmpty())
                @include('livewire.budget.partials.deals', [
                    'lineId' => null,
                    'transactions' => $unlinked,
                    'forceShow' => true,
                ])
            @endif
            @foreach ($kids as $child)
                @include('livewire.budget.partials.budget-branch', ['node' => $child, 'depth' => 1])
            @endforeach
        @endif
    @endif

@endforeach
