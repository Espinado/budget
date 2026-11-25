<?php

namespace App\Livewire\Concerns;

use App\Support\AppDate;

trait PresentsDayFirstDates
{
    protected function presentDayFirstDates(): void
    {
        foreach ($this->dayFirstDateFields() as $field) {
            $this->{$field} = $this->presentDayFirstValue($this->{$field} ?? null);
        }
    }

    /**
     * @return list<string>
     */
    protected function dayFirstDateFields(): array
    {
        return [];
    }

    private function presentDayFirstValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return AppDate::present($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            if (is_string($item)) {
                $value[$key] = AppDate::present($item);
            } elseif (is_array($item) && array_key_exists('date', $item)) {
                $item['date'] = AppDate::present((string) ($item['date'] ?? ''));
                $value[$key] = $item;
            }
        }

        return $value;
    }
}
