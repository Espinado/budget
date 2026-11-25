<?php

namespace App\Rules;

use App\Support\AppDate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AppDay implements ValidationRule
{
    public function __construct(private string $message = '') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || AppDate::parse($value) === null) {
            $fail($this->message !== '' ? $this->message : __('Укажите дату.'));
        }
    }
}
