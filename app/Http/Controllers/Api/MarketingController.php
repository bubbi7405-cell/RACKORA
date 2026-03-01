<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketingCampaign;
use App\Services\Game\MarketingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MarketingController extends Controller
{
    public function __construct(
        protected MarketingService $marketingService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $active = MarketingCampaign::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->get();

        $history = MarketingCampaign::where('user_id', $user->id)
            ->where('status', '!=', 'active')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'active' => $active,
            'history' => $history,
            'types' => MarketingService::CAMPAIGN_TYPES
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string'
        ]);

        try {
            $campaign = $this->marketingService->startCampaign($request->user(), $request->type);
            
            // --- FEATURE 35: AI REACTION ---
            app(\App\Services\Market\CompetitorAIService::class)->reactToPlayerAction($request->user(), 'marketing_campaign', ['campaign' => $campaign]);
            
            return response()->json([
                'success' => true,
                'message' => "Campaign '{$campaign->name}' started successfully!",
                'campaign' => $campaign
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function predictions(Request $request): JsonResponse
    {
        $result = $this->marketingService->getMarketPredictions($request->user());
        if (!$result['success']) {
            return response()->json($result, 400);
        }
        return response()->json($result);
    }
}
