<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerOrder;
use App\Services\Game\ContractNegotiationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NegotiationController extends Controller
{
    public function __construct(
        protected ContractNegotiationService $negotiationService
    ) {}

    /**
     * Submit a bid for a negotiable order
     */
    public function submitBid(Request $request, string $orderId)
    {
        $user = Auth::user();
        $order = CustomerOrder::where('id', $orderId)
            ->whereHas('customer', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->firstOrFail();

        $validated = $request->validate([
            'price' => 'required|numeric|min:1',
            'sla' => 'required|string|in:standard,premium,enterprise,whale',
            'months' => 'required|integer|min:1|max:60',
        ]);

        try {
            $result = $this->negotiationService->submitBid(
                $user,
                $order,
                $validated['price'],
                $validated['sla'],
                $validated['months']
            );

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get preview probability for a bid without submitting it
     */
    public function previewProbability(Request $request, string $orderId)
    {
        $user = Auth::user();
        $order = CustomerOrder::where('id', $orderId)
            ->whereHas('customer', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->firstOrFail();

        $validated = $request->validate([
            'price' => 'required|numeric',
            'sla' => 'required|string|in:standard,premium,enterprise,whale',
            'months' => 'required|integer',
        ]);

        $prob = $this->negotiationService->calculateAcceptanceProbability(
            $user,
            $order,
            $validated['price'],
            $validated['sla'],
            $validated['months']
        );

        return response()->json([
            'success' => true,
            'probability' => $prob
        ]);
    }
}
