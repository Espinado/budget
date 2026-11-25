<dialog class="receipt-dialog" onclick="if (event.target === this) this.close()">
    <div class="receipt-dialog-bar">
        <a href="{{ $receiptUrl }}" download target="_blank" rel="noopener" class="rounded-full bg-tide px-3 py-2 text-base font-medium text-white">{{ __('Скачать') }}</a>
        <button type="button" onclick="this.closest('dialog').close()" class="rounded-full px-3 py-2 text-base font-medium text-stone-700">{{ __('Закрыть') }}</button>
    </div>
    <div class="receipt-stage">
        <img src="{{ $receiptUrl }}" alt="Чек от {{ $transaction->occurred_on->format('d.m.Y') }}" draggable="false">
    </div>
    <div class="receipt-zoom">
        <button type="button" data-receipt-zoom="out" aria-label="{{ __('Уменьшить') }}">−</button>
        <button type="button" data-receipt-zoom="in" aria-label="{{ __('Увеличить') }}">+</button>
    </div>
</dialog>
