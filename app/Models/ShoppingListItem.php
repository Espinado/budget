<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'shopping_list_id',
    'category_id',
    'name',
    'quantity',
    'price_cents',
    'is_checked',
    'archived_at',
    'sort',
])]
class ShoppingListItem extends Model
{
    public function list(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class, 'shopping_list_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function quantityNumber(): ?float
    {
        if ($this->quantity === null) {
            return null;
        }

        $raw = str_replace(',', '.', trim((string) $this->quantity));

        if ($raw === '' || ! preg_match('/^(\d+(?:\.\d+)?)/', $raw, $matches)) {
            return null;
        }

        return (float) $matches[1];
    }

    public function lineTotalCents(): ?int
    {
        $qty = $this->quantityNumber();

        if ($qty === null || $this->price_cents === null) {
            return null;
        }

        return (int) round($qty * $this->price_cents);
    }

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'is_checked' => 'boolean',
            'archived_at' => 'datetime',
            'sort' => 'integer',
        ];
    }
}
