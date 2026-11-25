<?php

namespace App\Models;

use App\Enums\CashflowType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'household_id',
    'period_id',
    'category_id',
    'plan_line_id',
    'account_id',
    'type',
    'amount_cents',
    'occurred_on',
    'note',
    'receipt_path',
    'created_by',
])]
class Transaction extends Model
{
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class, 'period_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function planLine(): BelongsTo
    {
        return $this->belongsTo(PlanLine::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected function casts(): array
    {
        return [
            'type' => CashflowType::class,
            'amount_cents' => 'integer',
            'occurred_on' => 'date',
        ];
    }
}
