<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorldEvent;
use Illuminate\Http\JsonResponse;

class WorldEventController extends Controller
{
    /**
     * Get currently active world events.
     */
    public function getActive(): JsonResponse
    {
        $events = WorldEvent::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->get();

        return response()->json([
            'success' => true,
            'events' => $events
        ]);
    }
}
