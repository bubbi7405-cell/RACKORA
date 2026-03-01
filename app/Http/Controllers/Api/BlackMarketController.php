<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\BlackMarketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlackMarketController extends Controller
{
    public function __construct(
        protected BlackMarketService $blackMarketService
    ) {}

    /**
     * Get available night deals.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->blackMarketService->getNightDeals($request->user())
        ]);
    }

    /**
     * Purchase a deal from the black market.
     */
    public function purchase(Request $request): JsonResponse
    {
        $request->validate([
            'deal_id' => 'required|string',
        ]);

        $result = $this->blackMarketService->purchaseDeal($request->user(), $request->deal_id);

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}
