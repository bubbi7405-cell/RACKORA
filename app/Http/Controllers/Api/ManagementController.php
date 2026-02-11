<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\ManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ManagementController extends Controller
{
    public function __construct(
        protected ManagementService $managementService
    ) {}

    /**
     * Commit a strategic decision
     */
    public function makeDecision(Request $request)
    {
        $request->validate([
            'decision_type' => 'required|string',
            'option_key' => 'required|string',
        ]);

        $user = Auth::user();
        $economy = $user->economy;

        if (!$economy) {
            return response()->json(['success' => false, 'error' => 'Economy not initialized'], 400);
        }

        try {
            $this->managementService->makeDecision(
                $economy,
                $request->decision_type,
                $request->option_key
            );

            return response()->json([
                'success' => true,
                'message' => 'Strategic decision recorded.',
                'gameState' => [
                    'economy' => $economy->toGameState()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
