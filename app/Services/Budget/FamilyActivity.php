<?php

namespace App\Services\Budget;

use App\Models\Household;
use App\Models\HouseholdActivity;

final class FamilyActivity
{
    public static function tell(Household $household, string $message): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        HouseholdActivity::query()->create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'message' => mb_substr(trim($message), 0, 240),
        ]);

        $cutoff = HouseholdActivity::query()
            ->where('household_id', $household->id)
            ->orderByDesc('id')
            ->skip(200)
            ->value('id');

        if ($cutoff !== null) {
            HouseholdActivity::query()
                ->where('household_id', $household->id)
                ->where('id', '<=', $cutoff)
                ->delete();
        }
    }
}
