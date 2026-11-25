<?php

namespace App\Services\Budget;

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\AccountInterestEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SavingsInterest
{
    /**
     * Суточный процент: годовая ставка (тысячные доли %) / 365.
     * 5% = 5000 → interest = round(balance × 5000 / 36_500_000).
     */
    public function dailyCents(int $balanceCents, int $annualMilli): int
    {
        if ($balanceCents <= 0 || $annualMilli <= 0) {
            return 0;
        }

        return (int) round($balanceCents * $annualMilli / 36_500_000);
    }

    public function accrueAccount(Account $account, ?CarbonImmutable $through = null): int
    {
        if ($account->role !== AccountRole::Savings) {
            return 0;
        }

        $rate = (int) $account->annual_rate_bps;

        if ($rate <= 0) {
            return 0;
        }

        $timezone = $account->household?->timezone ?: 'Europe/Riga';
        $today = ($through ?? CarbonImmutable::now($timezone))->startOfDay();
        $start = $account->last_interest_on === null
            ? $today
            : CarbonImmutable::parse($account->last_interest_on->toDateString(), $timezone)->addDay()->startOfDay();

        if ($start->greaterThan($today)) {
            return 0;
        }

        $total = 0;

        for ($day = $start; $day->lessThanOrEqualTo($today); $day = $day->addDay()) {
            $total += $this->accrueDay($account, $day, $rate);
            $account->refresh();
        }

        return $total;
    }

    public function accrueDue(?CarbonImmutable $through = null): int
    {
        $total = 0;

        Account::query()
            ->with('household')
            ->where('role', AccountRole::Savings)
            ->where('annual_rate_bps', '>', 0)
            ->orderBy('id')
            ->each(function (Account $account) use (&$total, $through): void {
                $total += $this->accrueAccount($account, $through);
            });

        return $total;
    }

    private function accrueDay(Account $account, CarbonImmutable $day, int $rate): int
    {
        $date = $day->toDateString();

        if ($account->last_interest_on?->toDateString() === $date) {
            return 0;
        }

        if (AccountInterestEntry::query()
            ->where('account_id', $account->id)
            ->whereDate('accrued_on', $date)
            ->exists()) {
            $account->forceFill(['last_interest_on' => $date])->save();

            return 0;
        }

        return (int) DB::transaction(function () use ($account, $date, $rate): int {
            /** @var Account $locked */
            $locked = Account::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

            if ($locked->last_interest_on?->toDateString() === $date) {
                return 0;
            }

            if (AccountInterestEntry::query()
                ->where('account_id', $locked->id)
                ->whereDate('accrued_on', $date)
                ->exists()) {
                $locked->forceFill(['last_interest_on' => $date])->save();

                return 0;
            }

            $balance = (int) $locked->balance_cents;
            $interest = $this->dailyCents($balance, $rate);

            if ($interest > 0) {
                AccountInterestEntry::query()->create([
                    'account_id' => $locked->id,
                    'household_id' => $locked->household_id,
                    'amount_cents' => $interest,
                    'annual_rate_bps' => $rate,
                    'balance_before_cents' => $balance,
                    'accrued_on' => $date,
                ]);
                $locked->increment('balance_cents', $interest);
            }

            $locked->forceFill(['last_interest_on' => $date])->save();

            return $interest;
        });
    }
}
