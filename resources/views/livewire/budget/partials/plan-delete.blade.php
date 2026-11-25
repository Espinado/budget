<button type="button" wire:click="deletePlanLine({{ $line->id }})" wire:confirm="Убрать «{{ $line->title }}» из плана?" class="plan-delete" title="{{ __('Удалить запись') }}" aria-label="{{ __('Удалить запись') }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M4 7h16" />
        <path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
        <path d="M7 7l1 12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-12" />
        <path d="M10 11v6M14 11v6" />
    </svg>
</button>
