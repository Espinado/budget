<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class AppDate
{
    public static function format(mixed $date): string
    {
        if ($date instanceof DateTimeInterface) {
            return $date->format('d.m.Y');
        }

        if (! is_string($date) || trim($date) === '') {
            return '';
        }

        return self::parse($date)?->format('d.m.Y') ?? '';
    }

    public static function present(string $value): string
    {
        return self::parse($value)?->format('d.m.Y') ?? $value;
    }

    public static function parse(string $raw): ?CarbonImmutable
    {
        $raw = trim($raw);

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $raw, $match) === 1) {
            return self::fromParts((int) $match[3], (int) $match[2], (int) $match[1]);
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $match) === 1) {
            return self::fromParts((int) $match[1], (int) $match[2], (int) $match[3]);
        }

        return null;
    }

    private static function fromParts(int $year, int $month, int $day): ?CarbonImmutable
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, 'Europe/Riga');
    }
}
