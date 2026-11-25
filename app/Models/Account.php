<?php

namespace App\Models;

use App\Enums\AccountRole;
use App\Enums\CashflowType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['household_id', 'name', 'kind', 'role', 'balance_cents', 'annual_rate_bps', 'last_interest_on', 'sort'])]
class Account extends Model
{
    /**
     * @return list<array{name: string, kind: string, role: string, sort: int}>
     */
    public static function defaults(): array
    {
        return [
            ['name' => __('Наличные деньги'), 'kind' => 'cash', 'role' => AccountRole::Operating->value, 'sort' => 10],
            ['name' => __('Дебетная карта'), 'kind' => 'card', 'role' => AccountRole::Operating->value, 'sort' => 20],
            ['name' => __('Кредитная карта'), 'kind' => 'card', 'role' => AccountRole::Operating->value, 'sort' => 30],
        ];
    }

    /**
     * @return array{name: string, kind: string, role: string, sort: int}
     */
    public static function piggyBank(): array
    {
        return ['name' => __('Копилка'), 'kind' => 'bank', 'role' => AccountRole::Savings->value, 'sort' => 40];
    }

    public function isDebitAccount(): bool
    {
        $name = mb_strtolower($this->name);

        return str_contains($name, 'дебет')
            || str_contains($name, 'debet')
            || str_contains($name, 'debit');
    }

    public function isCreditAccount(): bool
    {
        if ($this->isDebitAccount()) {
            return false;
        }

        $name = mb_strtolower($this->name);

        return str_contains($name, 'кредит')
            || str_contains($name, 'kredīt')
            || str_contains($name, 'kredit')
            || str_contains($name, 'credit');
    }

    /**
     * @param  Collection<int, self>  $accounts
     * @return array{0: ?int, 1: ?int}
     */
    public static function transferPair(Collection $accounts): array
    {
        $checking = $accounts
            ->filter(fn (self $account) => $account->isCheckingAccount())
            ->values();
        $debit = $checking->first(fn (self $account) => $account->isDebitAccount());
        $credit = $checking->first(fn (self $account) => $account->isCreditAccount());

        if ($debit && $credit && (int) $debit->id !== (int) $credit->id) {
            return [(int) $debit->id, (int) $credit->id];
        }

        $from = $checking->get(0);
        $to = $checking->first(fn (self $account) => $from && (int) $account->id !== (int) $from->id);

        return [$from ? (int) $from->id : null, $to ? (int) $to->id : null];
    }

    public function isCheckingAccount(): bool
    {
        $role = $this->role instanceof AccountRole ? $this->role : AccountRole::tryFrom((string) $this->role);

        if ($role !== AccountRole::Operating) {
            return false;
        }

        $name = mb_strtolower($this->name);

        return ! str_contains($name, 'копил')
            && ! str_contains($name, 'krājk')
            && ! str_contains($name, 'krajk');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'from_account_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'to_account_id');
    }

    public function interestEntries(): HasMany
    {
        return $this->hasMany(AccountInterestEntry::class);
    }

    public function apply(CashflowType $type, int $cents): void
    {
        $signed = $type->increasesBalance() ? $cents : -$cents;
        $this->increment('balance_cents', $signed);
    }

    public function rateLabel(): string
    {
        return Debt::formatMilli((int) $this->annual_rate_bps).'%';
    }

    protected function casts(): array
    {
        return [
            'role' => AccountRole::class,
            'balance_cents' => 'integer',
            'annual_rate_bps' => 'integer',
            'last_interest_on' => 'date',
            'sort' => 'integer',
        ];
    }
}
