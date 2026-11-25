<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'household_id',
    'starts_on',
    'ends_on',
    'opening_balance_cents',
    'opening_manual',
    'custom_bounds',
    'status',
    'closed_at',
])]
class BudgetPeriod extends Model
{
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function planLines(): HasMany
    {
        return $this->hasMany(PlanLine::class, 'period_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'period_id');
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'opening_balance_cents' => 'integer',
            'opening_manual' => 'boolean',
            'custom_bounds' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }
}
