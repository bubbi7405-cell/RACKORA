<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\RankingService;
use Illuminate\Http\JsonResponse;

class RankingController extends Controller
{
    public function __construct(
        protected RankingService $rankingService
    ) {}

    public function getLatest(): JsonResponse
    {
        $rankings = $this->rankingService->getLatestRankings();

        return response()->json([
            'success' => true,
            'data' => $rankings->map(function ($r) {
                return [
                    'rank' => $r->rank,
                    'user_id' => $r->user_id,
                    'company_name' => $r->user->company_name, // Falls vorhanden
                    'name' => $r->user->name,
                    'balance' => $r->balance,
                    'reputation' => $r->reputation,
                    'level' => $r->level,
                    'year' => $r->year,
                    'week' => $r->week
                ];
            })
        ]);
    }
}
