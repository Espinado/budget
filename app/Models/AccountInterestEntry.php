<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'account_id',
    'household_id',
    'amount_cents',
    'annual_rate_bps',
    'balance_before_cents',
    'accrued_on',
])]
class AccountInterestEntry extends Model
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'annual_rate_bps' => 'integer',
            'balance_before_cents' => 'integer',
            'accrued_on' => 'date',
        ];
    }
}
