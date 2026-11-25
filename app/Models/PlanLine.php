<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['period_id', 'category_id', 'account_id', 'template_id', 'source_line_id', 'title', 'amount_cents', 'expected_on'])]
class PlanLine extends Model
{
    public function period(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class, 'period_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PlanTemplate::class, 'template_id');
    }

    public function sourceLine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_line_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function installment(): HasOne
    {
        return $this->hasOne(DebtInstallment::class);
    }

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'expected_on' => 'date',
        ];
    }
}
