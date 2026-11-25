<div class="mt-2 border-t border-stone-200">
    @foreach ($operations as $transaction)
        <div class="stats-purchase">
            <span class="whitespace-nowrap tabular-nums text-stone-500">{{ ru_date($transaction->occurred_on) }}</span>
            <span class="min-w-0 text-stone-700">{{ $transaction->note }}</span>
            <span class="whitespace-nowrap text-right font-medium tabular-nums">{{ money($transaction->amount_cents) }}</span>
        </div>
    @endforeach
</div>
