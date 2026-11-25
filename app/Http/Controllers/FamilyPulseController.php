<?php

namespace App\Http\Controllers;

use App\Models\HouseholdActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyPulseController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $household = $request->user()->currentHousehold();
        $after = max(0, (int) $request->query('after', 0));
        $latest = (int) (HouseholdActivity::query()
            ->where('household_id', $household->id)
            ->max('id') ?? 0);

        $events = HouseholdActivity::query()
            ->where('household_id', $household->id)
            ->where('id', '>', $after)
            ->where('user_id', '!=', $request->user()->id)
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'message']);

        return response()->json([
            'latest' => $latest,
            'events' => $events->map(fn (HouseholdActivity $event): array => [
                'id' => $event->id,
                'message' => $event->message,
            ])->values(),
        ]);
    }
}
