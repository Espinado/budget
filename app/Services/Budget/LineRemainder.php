<?php

namespace App\Services\Budget;

use Illuminate\Support\Collection;

/**
 * Splits a category into money still on the plan and money the month is committed to.
 * Each plan line keeps its own remainder. A payment already taken from an account
 * is not subtracted again, and it does not shrink a different line.
 */
final class LineRemainder
{
    /**
     * @param  Collection<int, object>  $lines
     * @param  Collection<int, object>  $transactions
     * @return array{outstanding: int, committed: int}
     */
    public static function split(Collection $lines, Collection $transactions): array
    {
        $lineCount = $lines->count();
        $claimed = [];
        $outstanding = 0;
        $committed = 0;

        foreach ($lines as $line) {
            $received = 0;

            foreach ($transactions as $transaction) {
                $linked = (int) ($transaction->plan_line_id ?? 0) === (int) $line->id;
                $sole = $transaction->plan_line_id === null && $lineCount === 1;

                if (! $linked && ! $sole) {
                    continue;
                }

                $claimed[(int) $transaction->id] = true;
                $received += (int) $transaction->amount_cents;
            }

            $planned = (int) $line->amount_cents;
            $outstanding += max(0, $planned - $received);
            $committed += max($planned, $received);
        }

        foreach ($transactions as $transaction) {
            if (isset($claimed[(int) $transaction->id])) {
                continue;
            }

            $committed += (int) $transaction->amount_cents;
        }

        return [
            'outstanding' => $outstanding,
            'committed' => $committed,
        ];
    }
}
