@php
    $parsed = \App\Support\AppDate::parse((string) ($part['date'] ?? ''));
    $today = \Carbon\CarbonImmutable::now('Europe/Riga');
    $open = $contractDatePicker === $index;
@endphp
<div data-day-picker>
    <label for="contract-part-date-{{ $index }}" class="text-base text-stone-600">{{ __('Дата') }}</label>
    <div class="mt-1 flex items-stretch gap-2">
        <x-date-input id="contract-part-date-{{ $index }}" wire:model="contractParts.{{ $index }}.date" class="min-w-0 flex-1 rounded-2xl border border-stone-200 bg-white px-3 py-3 outline-none focus:border-tide" />
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
        <div data-day-panel class="mt-2 rounded-2xl border border-stone-200 bg-white p-3">
            <div class="flex items-center justify-between gap-2">
                <button type="button" wire:click="shiftContractDateMonth(-1)" class="inline-flex items-center justify-center rounded-2xl border border-stone-300 bg-paper text-ink" style="min-width: 2.75rem; min-height: 2.75rem" aria-label="{{ __('Предыдущий месяц') }}">‹</button>
                <span class="text-base font-medium text-ink">{{ $this->contractPickerHeading() }}</span>
                <button type="button" wire:click="shiftContractDateMonth(1)" class="inline-flex items-center justify-center rounded-2xl border border-stone-300 bg-paper text-ink" style="min-width: 2.75rem; min-height: 2.75rem" aria-label="{{ __('Следующий месяц') }}">›</button>
            </div>
            <div class="contract-day-grid mt-2">
                @foreach ($this->contractPickerWeekdays() as $weekday)
                    <span class="py-1 text-center text-base text-stone-500">{{ $weekday }}</span>
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
                        <button type="button" wire:click="pickContractDate({{ $day }})" class="text-base text-ink {{ $selected ? 'is-selected' : '' }} {{ $isToday ? 'is-today' : '' }}">{{ $day }}</button>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
