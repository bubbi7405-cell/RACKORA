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
     * Get updated research state
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $projects = $this->researchService->getResearchState($user);

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
            'tech_id' => 'required|string',
        ]);

        $user = $request->user();

        try {
            $research = $this->researchService->startResearch($user, $request->tech_id);
            
            return response()->json([
                'success' => true,
                'data' => $research,
                'message' => 'Research started successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
