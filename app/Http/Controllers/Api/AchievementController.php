<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    public function __construct(
        private AchievementService $achievementService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Ensure achievements are synced (for dev convenience)
        $this->achievementService->syncAchievements();

        $achievements = $this->achievementService->getAchievementsForUser($user);

        return response()->json([
            'success' => true,
            'data' => $achievements,
        ]);
    }
}
