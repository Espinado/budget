<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['debt_id', 'sequence', 'due_on', 'principal_before_cents', 'principal_cents', 'interest_cents', 'other_cents', 'fee_cents', 'total_cents', 'plan_line_id', 'paid_transaction_id'])]
class DebtInstallment extends Model
{
    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function planLine(): BelongsTo
    {
        return $this->belongsTo(PlanLine::class);
    }

    public function paidTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'paid_transaction_id');
    }

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_on' => 'date',
            'principal_before_cents' => 'integer',
            'principal_cents' => 'integer',
            'interest_cents' => 'integer',
            'other_cents' => 'integer',
            'fee_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }
}
