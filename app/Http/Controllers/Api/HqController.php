<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CorporateHq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $hq = $user->hq;

        if (!$hq) {
            return response()->json([
                'success' => true,
                'owned' => false,
                'build_cost' => 100000,
                'message' => 'Establish a Corporate Headquarters to unlock high-prestige contracts.'
            ]);
        }

        return response()->json([
            'success' => true,
            'owned' => true,
            'data' => $hq,
        ]);
    }

    public function build(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->hq) {
            return response()->json(['success' => false, 'error' => 'You already have an HQ.'], 400);
        }

        $cost = 100000;
        if (!$user->economy->debit($cost, "Established Corporate Headquarters", "expansion")) {
            return response()->json(['success' => false, 'error' => 'Insufficient funds ($100,000 required).'], 400);
        }

        $hq = CorporateHq::create([
            'user_id' => $user->id,
            'level' => 1,
            'prestige_score' => 100.0,
            'visual_style' => 'classic_office',
            'amenities' => [],
        ]);

        return response()->json([
            'success' => true,
            'data' => $hq,
            'message' => 'HQ established!'
        ]);
    }

    public function upgrade(Request $request): JsonResponse
    {
        $user = $request->user();
        $hq = $user->hq;
        if (!$hq) return response()->json(['success' => false, 'error' => 'No HQ found.'], 404);

        $nextLevel = $hq->level + 1;
        $cost = $nextLevel * 50000;

        if (!$user->economy->debit($cost, "HQ Upgrade to Lvl {$nextLevel}", "expansion")) {
            return response()->json(['success' => false, 'error' => "Insufficient funds (\$" . number_format($cost) . " required)."], 400);
        }

        $hq->level = $nextLevel;
        $hq->prestige_score += 150.0;
        $hq->save();

        return response()->json([
            'success' => true,
            'data' => $hq,
            'message' => "HQ upgraded to Level {$nextLevel}!"
        ]);
    }
}
