<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\SabotageService;
use App\Models\Competitor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SabotageController extends Controller
{
    public function __construct(
        protected SabotageService $sabotageService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'types' => $this->sabotageService->getAvailableSabotages($user),
            'history' => \App\Models\Sabotage::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get(),
            'targets' => Competitor::all(), // List all competitors as targets
        ]);
    }

    public function attempt(Request $request): JsonResponse
    {
        $request->validate([
            'target_id' => 'required|string',
            'target_type' => 'required|in:competitor,user',
            'sabotage_type' => 'required|string',
        ]);

        $user = $request->user();

        try {
            $result = $this->sabotageService->attemptSabotage(
                $user,
                $request->input('target_id'),
                $request->input('target_type'),
                $request->input('sabotage_type')
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => $result['success'] ? 'Operation executed.' : 'Operation failed.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
