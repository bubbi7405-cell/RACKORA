<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorldEvent;
use Illuminate\Http\JsonResponse;

class WorldEventController extends Controller
{
    /**
     * Get currently active world events with regional data.
     */
    public function getActive(): JsonResponse
    {
        $events = WorldEvent::getActiveEventsForDisplay();

        return response()->json([
            'success' => true,
            'events' => $events
        ]);
    }
}
