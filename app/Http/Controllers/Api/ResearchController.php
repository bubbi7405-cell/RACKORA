<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\ResearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResearchController extends Controller
{
    public function __construct(
        private ResearchService $researchService
    ) {}

    /**
     * Get available research projects
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $projects = $this->researchService->getAvailableResearches($user);

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    /**
     * Start a research project
     */
    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'research_key' => 'required|string',
        ]);

        $user = $request->user();

        $result = $this->researchService->startResearch($user, $request->research_key);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'message' => 'Research started successfully.',
        ]);
    }
}
