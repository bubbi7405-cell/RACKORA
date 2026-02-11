<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameEvent;
use App\Services\Game\GameEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private GameEventService $eventService
    ) {}

    /**
     * Get active game events for the current user
     */
    public function getActive(Request $request): JsonResponse
    {
        $user = $request->user();

        $events = GameEvent::where('user_id', $user->id)
            ->whereIn('status', ['warning', 'active', 'escalated'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($e) => $e->toGameState());

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Resolve a game event using a specific action
     */
    public function resolve(Request $request): JsonResponse
    {
        $request->validate([
            'event_id' => 'required|uuid|exists:game_events,id',
            'action_id' => 'required|string',
        ]);

        $user = $request->user();
        $event = GameEvent::findOrFail($request->event_id);

        $result = $this->eventService->resolveEvent($user, $event, $request->action_id);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        // Award XP for resolving an event
        if (($result['outcome'] ?? null) === 'success') {
            $user->economy->addExperience(25);
            $user->economy->adjustReputation(2);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'event' => $event->fresh()->toGameState(),
                'outcome' => $result['outcome'],
                'message' => $result['message'],
            ],
        ]);
    }

    /**
     * Get event history
     */
    public function getHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        $events = GameEvent::where('user_id', $user->id)
            ->whereIn('status', ['resolved', 'failed'])
            ->orderBy('resolved_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn($e) => $e->toGameState());

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }
}
