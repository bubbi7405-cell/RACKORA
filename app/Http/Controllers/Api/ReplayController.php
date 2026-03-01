<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\ReplayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReplayController extends Controller
{
    public function __construct(
        protected ReplayService $replayService
    ) {}

    /**
     * Get replay/timeline data
     */
    public function getReplayData(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);

        $limit = max(10, min(200, (int) $request->input('limit', 50)));

        $data = $this->replayService->getReplayData($user, $limit);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}
