<?php

namespace App\Models;

use App\Enums\DebtDirection;
use App\Enums\DebtKind;
use App\Enums\InterestMode;
use App\Support\DailyInterest;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['household_id', 'category_id', 'account_id', 'issued_transaction_id', 'direction', 'kind', 'counterparty', 'contract_number', 'reference', 'annual_rate_bps', 'fixed_rate_bps', 'euribor_rate_bps', 'euribor_tenor_months', 'rate_reset_on', 'monthly_fee_cents', 'issuance_fee_cents', 'residual_cents', 'schedule', 'opened_on', 'interest_mode', 'principal_cents', 'accrued_cents', 'interest_through', 'prepay_log'])]
class Debt extends Model
{
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function issuedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'issued_transaction_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(DebtInstallment::class)->orderBy('due_on')->orderBy('sequence');
    }

    public function usesDailyInterest(): bool
    {
        return $this->interest_mode === InterestMode::Daily;
    }

    public function remainingCents(): int
    {
        if ($this->usesDailyInterest()) {
            return app(DailyInterest::class)->position($this)['total'];
        }

        $next = $this->installments->first(fn (DebtInstallment $row): bool => $row->paid_transaction_id === null && (int) $row->total_cents > 0);

        return $next === null ? 0 : (int) $next->principal_before_cents;
    }

    public function rateLabel(): string
    {
        return self::formatMilli((int) $this->annual_rate_bps).'%';
    }

    public static function formatMilli(int $milli): string
    {
        $milli = max(0, $milli);
        $whole = intdiv($milli, 1000);
        $fraction = str_pad((string) ($milli % 1000), 3, '0', STR_PAD_LEFT);
        $trimmed = rtrim($fraction, '0');

        if (strlen($trimmed) < 2) {
            $trimmed = str_pad($trimmed, 2, '0');
        }

        return $whole.','.$trimmed;
    }

    public static function milliFromInput(string $raw): ?int
    {
        $raw = str_replace([' ', "\u{00A0}"], '', trim($raw));
        $raw = str_replace('.', ',', $raw);

        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, ',')) {
            $raw = '0'.$raw;
        }

        $raw = str_replace(',', '.', $raw);

        if (! preg_match('/^\d+(\.\d{1,3})?$/', $raw)) {
            return null;
        }

        $parts = explode('.', $raw);
        $fraction = substr(($parts[1] ?? '').'000', 0, 3);

        return ((int) $parts[0] * 1000) + (int) $fraction;
    }

    protected function casts(): array
    {
        return [
            'direction' => DebtDirection::class,
            'kind' => DebtKind::class,
            'interest_mode' => InterestMode::class,
            'annual_rate_bps' => 'integer',
            'fixed_rate_bps' => 'integer',
            'euribor_rate_bps' => 'integer',
            'euribor_tenor_months' => 'integer',
            'rate_reset_on' => 'date',
            'monthly_fee_cents' => 'integer',
            'issuance_fee_cents' => 'integer',
            'residual_cents' => 'integer',
            'opened_on' => 'date',
            'interest_through' => 'date',
            'prepay_log' => 'array',
        ];
    }
}
