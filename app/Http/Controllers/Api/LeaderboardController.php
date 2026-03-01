<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlayerEconomy;
use App\Models\WeeklyRanking;
use App\Models\Server;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class LeaderboardController extends Controller
{
    /**
     * Get the global leaderboard.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'sort_by' => 'in:balance,reputation,level,total_revenue,global_market_share,composite',
            'limit' => 'integer|min:1|max:100'
        ]);

        $sortBy = $request->input('sort_by', 'composite');
        $limit = $request->input('limit', 50);

        if ($sortBy === 'composite') {
            return $this->getCompositeLeaderboard($request, $limit);
        }

        // Map frontend sort keys to DB columns if needed
        $column = match ($sortBy) {
            'level' => 'experience_points',
            default => $sortBy
        };

        $topPlayers = PlayerEconomy::with('user:id,name')
            ->orderByDesc($column)
            ->limit($limit)
            ->get()
            ->map(function ($economy, $index) use ($sortBy, $request) {
                $score = match ($sortBy) {
                    'balance' => $economy->balance,
                    'reputation' => $economy->reputation,
                    'level' => $economy->level,
                    'total_revenue' => $economy->total_revenue,
                    'global_market_share' => (float)($economy->global_market_share ?? 0),
                    default => 0
                };

                // Get previous week rank for trend indicator
                $prevRank = $this->getPreviousRank($economy->user_id, $sortBy);

                return [
                    'rank' => $index + 1,
                    'player_name' => $economy->user->name ?? 'Unknown CEO',
                    'company_name' => $economy->metadata['company_name'] ?? 'Startup Inc.',
                    'level' => $economy->level,
                    'reputation' => (float) $economy->reputation,
                    'balance' => (float) $economy->balance,
                    'marketShare' => (float)($economy->global_market_share ?? 0),
                    'score' => $score,
                    'is_me' => $request->user() && $economy->user_id === $request->user()->id,
                    'trend' => $prevRank ? ($prevRank > ($index + 1) ? 'up' : ($prevRank < ($index + 1) ? 'down' : 'same')) : 'new',
                    'trend_diff' => $prevRank ? abs($prevRank - ($index + 1)) : 0,
                    'badges' => $this->getPlayerBadges($economy),
                ];
            });

        return response()->json([
            'success' => true,
            'leaderboard' => $topPlayers,
            'sort_by' => $sortBy,
            'total_players' => PlayerEconomy::count(),
        ]);
    }

    /**
     * Composite score leaderboard — the "true" ranking.
     * Score = Balance×0.3 + (Level×1000)×0.25 + (Reputation×50)×0.25 + (MarketShare×500)×0.2
     */
    private function getCompositeLeaderboard(Request $request, int $limit): JsonResponse
    {
        $topPlayers = PlayerEconomy::with('user:id,name')
            ->selectRaw('*, (balance * 0.3 + (level * 1000) * 0.25 + (reputation * 50) * 0.25 + (COALESCE(global_market_share, 0) * 500) * 0.2) as composite_score')
            ->orderByDesc('composite_score')
            ->limit($limit)
            ->get()
            ->map(function ($economy, $index) use ($request) {
                $prevRank = $this->getPreviousRank($economy->user_id, 'composite');

                return [
                    'rank' => $index + 1,
                    'player_name' => $economy->user->name ?? 'Unknown CEO',
                    'company_name' => $economy->metadata['company_name'] ?? 'Startup Inc.',
                    'level' => $economy->level,
                    'reputation' => (float) $economy->reputation,
                    'balance' => (float) $economy->balance,
                    'marketShare' => (float)($economy->global_market_share ?? 0),
                    'score' => round((float) $economy->composite_score, 0),
                    'is_me' => $request->user() && $economy->user_id === $request->user()->id,
                    'trend' => $prevRank ? ($prevRank > ($index + 1) ? 'up' : ($prevRank < ($index + 1) ? 'down' : 'same')) : 'new',
                    'trend_diff' => $prevRank ? abs($prevRank - ($index + 1)) : 0,
                    'badges' => $this->getPlayerBadges($economy),
                ];
            });

        return response()->json([
            'success' => true,
            'leaderboard' => $topPlayers,
            'sort_by' => 'composite',
            'total_players' => PlayerEconomy::count(),
        ]);
    }

    /**
     * Get the current user's rank across all categories.
     */
    public function myRank(Request $request): JsonResponse
    {
        $user = $request->user();
        $economy = $user->economy;
        
        if (!$economy) {
             return response()->json(['success' => false, 'error' => 'No economy found'], 404);
        }

        $rankBalance = PlayerEconomy::where('balance', '>', $economy->balance)->count() + 1;
        $rankReputation = PlayerEconomy::where('reputation', '>', $economy->reputation)->count() + 1;
        $rankLevel = PlayerEconomy::where('experience_points', '>', $economy->experience_points)->count() + 1;
        $rankMarketShare = PlayerEconomy::where('global_market_share', '>', ($economy->global_market_share ?? 0))->count() + 1;

        $compositeScore = ($economy->balance * 0.3)
            + ($economy->level * 1000 * 0.25)
            + ($economy->reputation * 50 * 0.25)
            + (($economy->global_market_share ?? 0) * 500 * 0.2);

        $rankComposite = PlayerEconomy::selectRaw('COUNT(*) as cnt')
            ->whereRaw('(balance * 0.3 + (level * 1000) * 0.25 + (reputation * 50) * 0.25 + (COALESCE(global_market_share, 0) * 500) * 0.2) > ?', [$compositeScore])
            ->value('cnt') + 1;

        $totalPlayers = PlayerEconomy::count();

        // Percentile calculation
        $percentile = $totalPlayers > 1
            ? round((1 - ($rankComposite - 1) / ($totalPlayers)) * 100, 1)
            : 100;

        return response()->json([
            'success' => true,
            'ranks' => [
                'balance' => $rankBalance,
                'reputation' => $rankReputation,
                'level' => $rankLevel,
                'global_market_share' => $rankMarketShare,
                'composite' => $rankComposite,
            ],
            'stats' => [
                'balance' => (float) $economy->balance,
                'reputation' => (float) $economy->reputation,
                'level' => $economy->level,
                'global_market_share' => (float) ($economy->global_market_share ?? 0),
                'composite_score' => round($compositeScore, 0),
            ],
            'percentile' => $percentile,
            'total_players' => $totalPlayers,
            'badges' => $this->getPlayerBadges($economy),
        ]);
    }

    /**
     * Get weekly ranking history.
     */
    public function weeklyHistory(Request $request): JsonResponse
    {
        $request->validate([
            'weeks' => 'integer|min:1|max:12'
        ]);

        $weeksBack = $request->input('weeks', 4);
        $user = $request->user();

        $history = WeeklyRanking::where('user_id', $user->id)
            ->orderByDesc('year')
            ->orderByDesc('week')
            ->limit($weeksBack)
            ->get()
            ->map(fn($r) => [
                'year' => $r->year,
                'week' => $r->week,
                'rank' => $r->rank,
                'balance' => (float) $r->balance,
                'reputation' => (float) $r->reputation,
                'level' => $r->level,
                'reward_granted' => $r->reward_granted,
            ]);

        // Also get Top 3 of current week for the podium display
        $currentYear = now()->year;
        $currentWeek = now()->weekOfYear;

        $podium = WeeklyRanking::where('year', $currentYear)
            ->where('week', $currentWeek)
            ->orderBy('rank')
            ->limit(3)
            ->with('user:id,name')
            ->get()
            ->map(fn($r) => [
                'rank' => $r->rank,
                'player_name' => $r->user->name ?? 'Unknown',
                'balance' => (float) $r->balance,
                'level' => $r->level,
                'is_me' => $r->user_id === $user->id,
            ]);

        return response()->json([
            'success' => true,
            'history' => $history,
            'podium' => $podium,
            'current_week' => $currentWeek,
            'current_year' => $currentYear,
        ]);
    }

    /**
     * Get player achievements/badges based on their economy state.
     */
    private function getPlayerBadges(PlayerEconomy $economy): array
    {
        $badges = [];

        // Wealth badges
        if ($economy->balance >= 10_000_000) $badges[] = ['icon' => '💎', 'label' => 'Diamond Mogul'];
        elseif ($economy->balance >= 1_000_000) $badges[] = ['icon' => '👑', 'label' => 'Millionaire'];
        elseif ($economy->balance >= 100_000) $badges[] = ['icon' => '💰', 'label' => 'Wealthy'];

        // Level badges
        if ($economy->level >= 50) $badges[] = ['icon' => '🌟', 'label' => 'Legendary'];
        elseif ($economy->level >= 30) $badges[] = ['icon' => '⭐', 'label' => 'Elite'];
        elseif ($economy->level >= 20) $badges[] = ['icon' => '🏅', 'label' => 'Expert'];

        // Reputation badges
        if ($economy->reputation >= 90) $badges[] = ['icon' => '🛡️', 'label' => 'Trusted'];
        elseif ($economy->reputation >= 70) $badges[] = ['icon' => '✅', 'label' => 'Reputable'];

        // Specialization badges (from specialized_reputation)
        $specRep = $economy->specialized_reputation ?? [];
        if (($specRep['green'] ?? 0) >= 50) $badges[] = ['icon' => '🌿', 'label' => 'Eco-Warrior'];
        if (($specRep['hpc'] ?? 0) >= 50) $badges[] = ['icon' => '🧠', 'label' => 'AI Pioneer'];
        if (($specRep['premium'] ?? 0) >= 50) $badges[] = ['icon' => '🏆', 'label' => 'Premium Host'];

        return $badges;
    }

    /**
     * Get the player's rank from the previous weekly snapshot.
     */
    private function getPreviousRank(int $userId, string $category): ?int
    {
        $lastWeek = WeeklyRanking::where('user_id', $userId)
            ->orderByDesc('year')
            ->orderByDesc('week')
            ->first();

        return $lastWeek?->rank;
    }
}
