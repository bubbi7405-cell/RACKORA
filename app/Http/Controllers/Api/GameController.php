<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\GameStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(
        private GameStateService $gameStateService,
        private \App\Services\Game\ActivityService $activityService,
        private \App\Services\Game\NewsService $newsService
    ) {}

    public function getSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $summary = $this->activityService->generateLoginSummary($user);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get full game state
     */
    public function getState(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $state = $this->gameStateService->getFullState($user);

        return response()->json([
            'success' => true,
            'data' => $state,
        ]);
    }

    /**
     * Initialize new player
     */
    public function initialize(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->economy) {
            return response()->json([
                'success' => false,
                'error' => 'Player already initialized',
            ], 400);
        }

        $this->gameStateService->initializePlayer($user);
        $state = $this->gameStateService->getFullState($user);

        return response()->json([
            'success' => true,
            'data' => $state,
        ]);
    }

    public function updateTutorial(Request $request): JsonResponse
    {
        $request->validate([
            'step' => 'required|integer',
            'completed' => 'boolean'
        ]);

        $user = $request->user();
        $user->tutorial_step = $request->input('step');
        if ($request->has('completed')) {
            $user->tutorial_completed = $request->input('completed');
        }
        $user->save();

        return response()->json([
            'success' => true
        ]);
    }
    public function getLogs(Request $request): JsonResponse
    {
        $user = $request->user();
        $logs = \App\Models\GameLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    public function setSpeed(Request $request): JsonResponse
    {
        $request->validate([
            'speed' => 'required|integer|min:0|max:5',
        ]);

        $user = $request->user();
        $economy = $user->economy;
        
        $speed = $request->input('speed');
        
        if ($speed === 0) {
            $economy->is_paused = true;
        } else {
            $economy->is_paused = false;
            $economy->game_speed = $speed;
        }
        
        $economy->save();

        return response()->json([
            'success' => true,
            'speed' => $economy->game_speed,
            'paused' => $economy->is_paused
        ]);
    }

    public function getNews(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'data' => $this->newsService->getHeadlines($user),
        ]);
    }
}
