@php
    $nested = $childrenByParent->get($node->category->id, collect())
        ->filter(fn ($child) => $branchActive($child))
        ->values();
    $hasNested = $nested->isNotEmpty();
    $nodeSingle = ! $hasNested && $node->lines->count() === 1;
    $nodePlanned = $node->planned;
    $nodeActual = $node->actual;
    $rollNode = function (int $parentId) use (&$rollNode, &$nodePlanned, &$nodeActual, $childrenByParent): void {
        foreach ($childrenByParent->get($parentId, collect()) as $child) {
            $nodePlanned += $child->planned;
            $nodeActual += $child->actual;
            $rollNode($child->category->id);
        }
    };
    $rollNode($node->category->id);
    [$nodeMeter, $nodeDone, $nodePercent] = $face($nodePlanned, $nodeActual);
    $pad = 3 + ($depth - 1) * 2;
    $nodeOpen = $hasNested && $this->categoryOpen($node->category->id);
@endphp
@if (! $nodeSingle)
    <tr class="budget-subcategory {{ $rowTone($nodePlanned, $nodeActual) }}">
        <td colspan="6" class="py-3 pr-3" style="padding-left: {{ $pad }}rem">
            @if ($hasNested)
                <button type="button" wire:click="toggleCategory({{ $node->category->id }})" class="inline-flex items-center gap-2 text-left text-lg font-medium" aria-expanded="{{ $nodeOpen ? 'true' : 'false' }}" aria-label="{{ $nodeOpen ? 'Закрыть' : 'Открыть' }} {{ $node->category->name }}">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center budget-chevron rounded-full text-white" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            @if ($nodeOpen)
                                <path d="M6 9l6 6 6-6" />
                            @else
                                <path d="M9 6l6 6-6 6" />
                            @endif
                        </svg>
                    </span>
                    <x-mark :for="$node->category->name" :kind="$kind" :icon="$node->category->icon" class="h-9 w-9" />
                    {{ $node->category->name }}
                </button>
            @else
                <span class="inline-flex items-center gap-2 text-lg font-medium">
                    <x-mark :for="$node->category->name" :kind="$kind" :icon="$node->category->icon" class="h-9 w-9" />
                    {{ $node->category->name }}
                </span>
            @endif
            @include('livewire.budget.partials.budget-progress', [
                'planned' => $nodePlanned,
                'actual' => $nodeActual,
                'meter' => $nodeMeter,
                'done' => $nodeDone,
                'percent' => $nodePercent,
                'click' => $hasNested ? 'toggleCategory('.$node->category->id.')' : null,
                'kind' => $kind,
                'spendableDays' => $spendableDays,
            ])
        </td>
    </tr>
@endif
@if ($nodeSingle || ! $hasNested || $nodeOpen)
@foreach ($node->lines as $line)
    @php
        $deals = $lineDeals($node->transactions, $node->lines, $line);
        $dealsOpen = $dealsLineId === $line->id && $deals->isNotEmpty();
        $received = (int) $deals->sum('amount_cents');
        [$lineMeter, $lineDone, $linePercent] = $face($line->amount_cents, $received);
        $linePad = $nodeSingle ? $pad : $pad + 2;
        $creditPay = $monthCurrent && $received === 0 && (int) $line->amount_cents > 0 && \App\Services\Budget\Solvency::tracksCreditPlan($node->category);
    @endphp
    <tr wire:key="line-{{ $line->id }}" class="{{ $nodeSingle ? 'budget-subcategory' : 'budget-nested' }} {{ $nodeSingle ? $rowTone($node->planned, $node->actual) : $rowTone($line->amount_cents, $received) }}">
        <td colspan="6" class="py-3 pr-3" style="padding-left: {{ $linePad }}rem">
            <div class="flex w-full items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
            @if ($deals->isNotEmpty())
                <button type="button" wire:click="toggleDeals({{ $line->id }})" class="inline-flex items-center gap-2 text-left text-lg font-medium" aria-expanded="{{ $dealsOpen ? 'true' : 'false' }}" aria-label="{{ $dealsOpen ? __('Закрыть операции') : __('Операции') }} {{ $nodeSingle ? $node->category->name : $line->title }}">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center budget-chevron rounded-full text-white" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            @if ($dealsOpen)
                                <path d="M6 9l6 6 6-6" />
                            @else
                                <path d="M9 6l6 6-6 6" />
                            @endif
                        </svg>
                    </span>
                    <x-mark :for="$nodeSingle ? $node->category->name : $line->title" :kind="$kind" :icon="$node->category->icon" class="h-9 w-9" />
                    <span>
                        <span class="text-lg {{ $nodeSingle ? 'font-medium' : '' }}">{{ $nodeSingle ? $node->category->name : $line->title }}</span>
                        @if ($nodeSingle && $line->title !== $node->category->name)
                            <span class="mt-0.5 block text-base font-normal text-stone-500">{{ $line->title }}</span>
                        @endif
                    </span>
                </button>
            @else
                <button type="button" @if ($monthOpen) wire:click="editLine({{ $line->id }})" @endif class="inline-flex items-center gap-2 text-left text-lg font-medium" @if ($monthOpen) aria-label="Изменить план {{ $line->title }}" @endif>
                    <x-mark :for="$nodeSingle ? $node->category->name : $line->title" :kind="$kind" :icon="$node->category->icon" class="h-9 w-9" />
                    <span>
                        <span class="text-lg {{ $nodeSingle ? 'font-medium' : '' }}">{{ $nodeSingle ? $node->category->name : $line->title }}</span>
                        @if ($nodeSingle && $line->title !== $node->category->name)
                            <span class="mt-0.5 block text-base font-normal text-stone-500">{{ $line->title }}</span>
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
            'dealPad' => $linePad.'rem',
            'dealShift' => $nodeSingle ? '0.85rem' : '1.35rem',
        ])
    @endif
@endforeach
@endif
@if ($nodeOpen || ($nodeSingle && ! $hasNested))
    @php
        $branchOrphans = $node->lines->isEmpty()
            ? $node->transactions->values()
            : $node->transactions->reject(function ($transaction) use ($node, $lineDeals) {
                foreach ($node->lines as $line) {
                    if ($lineDeals($node->transactions, $node->lines, $line)->contains('id', $transaction->id)) {
                        return true;
                    }
                }

                return false;
            })->values();
    @endphp
    @if ($branchOrphans->isNotEmpty())
        @include('livewire.budget.partials.deals', [
            'lineId' => null,
            'transactions' => $branchOrphans,
            'forceShow' => true,
            'dealPad' => $pad.'rem',
            'dealShift' => '0.85rem',
        ])
    @endif
@endif
@if (! $hasNested || $nodeOpen)
@foreach ($nested as $child)
    @include('livewire.budget.partials.budget-branch', ['node' => $child, 'depth' => $depth + 1])
@endforeach
@endif
