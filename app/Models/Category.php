<?php

namespace App\Models;

use App\Enums\CashflowType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['household_id', 'parent_id', 'type', 'name', 'icon', 'color', 'sort', 'archived_at'])]
class Category extends Model
{
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function planLines(): HasMany
    {
        return $this->hasMany(PlanLine::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(PlanTemplate::class);
    }

    protected function casts(): array
    {
        return [
            'type' => CashflowType::class,
            'sort' => 'integer',
            'archived_at' => 'datetime',
        ];
    }
}
