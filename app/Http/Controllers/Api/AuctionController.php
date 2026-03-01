<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\AuctionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuctionController extends Controller
{
    public function __construct(
        protected AuctionService $auctionService
    ) {}

    /**
     * List all active auctions.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->auctionService->getActiveAuctions(),
        ]);
    }

    /**
     * Place a bid on a specific auction.
     */
    public function placeBid(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01'
        ]);

        $result = $this->auctionService->placeBid($request->user(), $id, $request->amount);

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }
}
