@php
    $parsed = \App\Support\AppDate::parse((string) ($part['date'] ?? ''));
    $today = \Carbon\CarbonImmutable::now('Europe/Riga');
    $open = $contractDatePicker === $index;
@endphp
<div data-day-picker>
    <label for="contract-part-date-{{ $index }}" class="text-base text-stone-600">{{ __('Дата') }}</label>
    <div class="mt-1 flex items-stretch gap-2">
        <x-date-input id="contract-part-date-{{ $index }}" wire:model.live.debounce.200ms="contractParts.{{ $index }}.date" class="min-w-0 flex-1 rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide" />
        <button
            type="button"
            wire:click="openContractDate({{ $index }})"
            data-day-open
            class="inline-flex shrink-0 items-center justify-center rounded-2xl border border-stone-300 bg-paper px-3 text-ink"
            style="min-width: 3.25rem"
            aria-label="{{ __('Календарь') }}"
            aria-expanded="{{ $open ? 'true' : 'false' }}"
        >
            <x-icon name="calendar" />
        </button>
    </div>
    @if ($open)
        <div data-day-panel class="contract-day-panel">
            <div class="contract-day-nav">
                <button type="button" wire:click="shiftContractDateMonth(-1)" aria-label="{{ __('Предыдущий месяц') }}">‹</button>
                <span class="contract-day-title">{{ $this->contractPickerHeading() }}</span>
                <button type="button" wire:click="shiftContractDateMonth(1)" aria-label="{{ __('Следующий месяц') }}">›</button>
            </div>
            <div class="contract-day-grid">
                @foreach ($this->contractPickerWeekdays() as $weekday)
                    <span>{{ $weekday }}</span>
                @endforeach
                @foreach ($this->contractPickerCells() as $day)
                    @if ($day === null)
                        <span></span>
                    @else
                        @php
                            $selected = $parsed !== null
                                && (int) $parsed->year === $contractPickerYear
                                && (int) $parsed->month === $contractPickerMonth
                                && (int) $parsed->day === $day;
                            $isToday = (int) $today->year === $contractPickerYear
                                && (int) $today->month === $contractPickerMonth
                                && (int) $today->day === $day;
                        @endphp
                        <button type="button" wire:click="pickContractDate({{ $day }})" class="{{ $selected ? 'is-selected' : '' }} {{ $isToday ? 'is-today' : '' }}" @if ($selected) data-selected="{{ $day }}" @endif>{{ $day }}</button>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
