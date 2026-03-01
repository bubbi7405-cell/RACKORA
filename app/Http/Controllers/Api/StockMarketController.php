<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\StockMarketService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StockMarketController extends Controller
{
    public function __construct(
        protected StockMarketService $stockMarketService
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->stockMarketService->getState($request->user())
        ]);
    }

    public function shortOwnStock(Request $request): JsonResponse
    {
        $request->validate([
            'shares' => 'required|integer|min:10|max:100000',
        ]);

        try {
            $result = $this->stockMarketService->shortSell($request->user(), $request->shares);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function closePosition(Request $request): JsonResponse
    {
        $request->validate([
            'position_id' => 'required|string',
        ]);

        try {
            $result = $this->stockMarketService->closeShort($request->user(), $request->position_id);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
