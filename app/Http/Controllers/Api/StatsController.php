<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function __construct(
        protected StatsService $statsService
    ) {}

    public function history(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 50);
        $history = $this->statsService->getHistory($request->user(), (int) $limit);
        
        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }
}
