<?php

namespace App\Models;

use App\Enums\PropertyObjectKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['household_id', 'name', 'kind', 'label', 'category_id'])]
class PropertyObject extends Model
{
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(PropertyContract::class)->orderBy('id');
    }

    public function debts(): BelongsToMany
    {
        return $this->belongsToMany(Debt::class, 'property_object_debts')->withTimestamps();
    }

    public function planLines(): BelongsToMany
    {
        return $this->belongsToMany(PlanLine::class, 'property_object_plan_lines')->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'kind' => PropertyObjectKind::class,
        ];
    }
}
