<?php

namespace App\Enums;

enum PropertyObjectKind: string
{
    case Apartment = 'apartment';
    case Car = 'car';

    public function label(): string
    {
        return match ($this) {
            self::Apartment => __('Недвижимое имущество'),
            self::Car => __('Движимое имущество'),
        };
    }
}
