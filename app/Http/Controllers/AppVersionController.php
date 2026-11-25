<?php

namespace App\Http\Controllers;

use App\Support\AppRelease;
use Illuminate\Http\JsonResponse;

class AppVersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'version' => AppRelease::current(),
        ])->header('Cache-Control', 'no-store');
    }
}
