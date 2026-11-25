<?php

use App\Support\Money;

function money(int $cents): string
{
    return Money::format($cents);
}

function variance_label(int $delta, string $kind): string
{
    if ($delta === 0) {
        return __('Совпало');
    }

    $amount = money(abs($delta));

    return match ($kind) {
        'income' => $delta > 0 ? __('Больше на :amount', ['amount' => $amount]) : __('Меньше на :amount', ['amount' => $amount]),
        'expense' => $delta > 0 ? __('Перерасход :amount', ['amount' => $amount]) : __('Экономия :amount', ['amount' => $amount]),
        default => $delta > 0 ? __('Выше плана на :amount', ['amount' => $amount]) : __('Ниже плана на :amount', ['amount' => $amount]),
    };
}

function app_days(int $count): string
{
    $count = abs($count);
    $mod10 = $count % 10;
    $mod100 = $count % 100;

    if (app()->getLocale() === 'lv') {
        if ($mod10 === 1 && $mod100 !== 11) {
            return $count.' '.__('день');
        }

        return $count.' '.__('дней');
    }

    if ($mod10 === 1 && $mod100 !== 11) {
        return $count.' '.__('день');
    }

    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
        return $count.' '.__('дня');
    }

    return $count.' '.__('дней');
}

/** @deprecated Use app_days() */
function ru_days(int $count): string
{
    return app_days($count);
}

function app_date(mixed $date, bool $withYear = false): string
{
    // dd/mm/yyyy for every locale. Year is always included so list rows share
    // one width; $withYear is kept for existing callers.
    return \Illuminate\Support\Carbon::parse($date)->format('d/m/Y');
}

/** @deprecated Use app_date() */
function ru_date(mixed $date, bool $withYear = false): string
{
    return app_date($date, $withYear);
}

function app_pay_date(mixed $date): string
{
    return \Illuminate\Support\Carbon::parse($date)->format('d.m.Y');
}

/** @deprecated Use app_pay_date() */
function ru_pay_date(mixed $date): string
{
    return app_pay_date($date);
}

function app_month(mixed $date): string
{
    $label = \Illuminate\Support\Carbon::parse($date)
        ->locale(app()->getLocale())
        ->isoFormat('MMMM YYYY');

    return mb_strtoupper(mb_substr($label, 0, 1)).mb_substr($label, 1);
}

/** @deprecated Use app_month() */
function ru_month(mixed $date): string
{
    return app_month($date);
}
