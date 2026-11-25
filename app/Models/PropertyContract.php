<?php

namespace App\Models;

use App\Support\ContractDocuments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['property_object_id', 'title', 'note', 'document_path', 'document_mime', 'document_name'])]
class PropertyContract extends Model
{
    public function propertyObject(): BelongsTo
    {
        return $this->belongsTo(PropertyObject::class);
    }

    public function hasViewableDocument(): bool
    {
        return is_string($this->document_path)
            && $this->document_path !== ''
            && in_array($this->document_mime, ContractDocuments::MIMES, true);
    }

    public function planLines(): BelongsToMany
    {
        return $this->belongsToMany(PlanLine::class, 'property_contract_plan_lines')->withTimestamps();
    }
}
