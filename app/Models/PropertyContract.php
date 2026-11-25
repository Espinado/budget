<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['property_object_id', 'title', 'note'])]
class PropertyContract extends Model
{
    public function propertyObject(): BelongsTo
    {
        return $this->belongsTo(PropertyObject::class);
    }

    public function planLines(): BelongsToMany
    {
        return $this->belongsToMany(PlanLine::class, 'property_contract_plan_lines')->withTimestamps();
    }
}
