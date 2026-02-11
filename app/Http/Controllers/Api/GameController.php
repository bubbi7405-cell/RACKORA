<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\GameStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(
        private GameStateService $gameStateService
    ) {}

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
}
