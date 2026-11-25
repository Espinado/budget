<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['household_id', 'category_id', 'title', 'amount_cents', 'expected_day', 'active'])]
class PlanTemplate extends Model
{
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'expected_day' => 'integer',
            'active' => 'boolean',
        ];
    }
}
