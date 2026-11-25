@php
    $isImage = str_starts_with((string) $documentMime, 'image/');
    $downloadUrl = $downloadUrl ?? $documentUrl;
@endphp
<button type="button" class="receipt-open" onclick="this.nextElementSibling.showModal()" aria-label="{{ __('Открыть документ') }}">
    @if ($isImage)
        <img class="receipt-preview" src="{{ $documentUrl }}" width="48" height="64" alt="{{ __('Просмотр документа') }}">
    @else
        <span class="receipt-preview contract-document-thumb" style="display:flex;align-items:flex-end;justify-content:center;padding-bottom:0.3rem;font-size:0.65rem;font-weight:700;letter-spacing:0.04em;color:#0f6e6b" aria-hidden="true">PDF</span>
    @endif
</button>
<dialog class="receipt-dialog" onclick="if (event.target === this) this.close()" @unless ($isImage) style="width:min(96vw,64rem);max-width:96vw" @endunless>
    <div class="receipt-dialog-bar">
        <a href="{{ $downloadUrl }}" download target="_blank" rel="noopener" class="rounded-full bg-tide px-3 py-2 text-base font-medium text-white">{{ __('Скачать') }}</a>
        <button type="button" onclick="this.closest('dialog').close()" class="rounded-full px-3 py-2 text-base font-medium text-stone-700">{{ __('Закрыть') }}</button>
    </div>
    @if ($isImage)
        <div class="receipt-stage">
            <img src="{{ $documentUrl }}" alt="{{ $documentTitle }}" draggable="false">
        </div>
        <div class="receipt-zoom">
            <button type="button" data-receipt-zoom="out" aria-label="{{ __('Уменьшить') }}">−</button>
            <button type="button" data-receipt-zoom="in" aria-label="{{ __('Увеличить') }}">+</button>
        </div>
    @else
        <iframe src="{{ $documentUrl }}" title="{{ $documentTitle }}" style="width:100%;height:min(78vh,40rem);border:0;border-radius:0.75rem;background:#fff"></iframe>
    @endif
</dialog>
