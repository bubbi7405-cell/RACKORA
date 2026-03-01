<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\WeeklyRanking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RankingService
{
    /**
     * Generate rankings for the current year and week.
     */
    public function generateRankings(): void
    {
        $year = now()->year;
        $week = now()->weekOfYear;

        Log::info("Generating Weekly Rankings for {$year}W{$week}");

        // Find all players with an economy
        $players = User::whereHas('economy')->with('economy')->get();

        if ($players->isEmpty()) {
            return;
        }

        // Calculate scores for ranking
        $scores = $players->map(function ($player) {
            $economy = $player->economy;
            
            // Weighted score formula for the leaderboard:
            // Level is most important, then Reputation, then Balance (diminishing returns via log)
            $score = ($economy->level * 10000) 
                   + ($economy->reputation * 500) 
                   + (log(max(1, (float)$economy->balance), 1.1) * 10);
            
            return [
                'user_id' => $player->id,
                'score' => $score,
                'data' => [
                    'balance' => (float) $economy->balance,
                    'reputation' => (float) $economy->reputation,
                    'level' => (int) $economy->level
                ]
            ];
        });

        // Sort by score DESC
        $sorted = $scores->sortByDesc('score')->values();

        // Save rankings in a transaction
        DB::transaction(function () use ($sorted, $year, $week) {
            // Optional: Archive or cleanup very old records if needed
            
            foreach ($sorted as $index => $item) {
                WeeklyRanking::updateOrCreate(
                    ['year' => $year, 'week' => $week, 'user_id' => $item['user_id']],
                    [
                        'rank' => $index + 1,
                        'balance' => $item['data']['balance'],
                        'reputation' => $item['data']['reputation'],
                        'level' => $item['data']['level'],
                    ]
                );
            }
        });

        Log::info("Successfully generated " . $sorted->count() . " ranking entries.");
        
        // Distribute rewards for the PREVIOUS week if not yet done
        $this->distributeRewards();
    }

    /**
     * Distribute rewards to top players for the last week.
     */
    public function distributeRewards(): void
    {
        // We look for rankings of the last week that haven't been rewarded yet
        // A week starts on Monday, so we check for last week's snapshots
        $lastWeek = now()->subWeek();
        $year = $lastWeek->year;
        $week = $lastWeek->weekOfYear;

        $rankings = WeeklyRanking::where('year', $year)
            ->where('week', $week)
            ->where('reward_granted', false)
            ->orderBy('rank', 'asc')
            ->get();

        if ($rankings->isEmpty()) {
            return;
        }

        Log::info("Distributing rewards for {$year}W{$week} to " . $rankings->count() . " players.");

        foreach ($rankings as $ranking) {
            $user = $ranking->user;
            if (!$user || !$user->economy) {
                $ranking->reward_granted = true;
                $ranking->save();
                continue;
            }

            $bonus = 0;
            $skillPoints = 0;

            // Prize Table
            if ($ranking->rank === 1) {
                $bonus = 250000;
                $skillPoints = 5;
            } elseif ($ranking->rank === 2) {
                $bonus = 100000;
                $skillPoints = 3;
            } elseif ($ranking->rank === 3) {
                $bonus = 50000;
                $skillPoints = 2;
            } elseif ($ranking->rank <= 10) {
                $bonus = 10000;
                $skillPoints = 1;
            } elseif ($ranking->rank <= 50) {
                $bonus = 2500;
            }

            if ($bonus > 0 || $skillPoints > 0) {
                $economy = $user->economy;
                $economy->balance += $bonus;
                
                if ($skillPoints > 0) {
                    $economy->skill_points = ($economy->skill_points ?? 0) + $skillPoints;
                }
                
                $economy->save();

                $msg = "WÖCHENTLICHE BELOHNUNG: Platz #{$ranking->rank} in Woche {$week} erreicht! ";
                $msg .= $bonus > 0 ? "+$" . number_format($bonus) . " " : "";
                $msg .= $skillPoints > 0 ? "+{$skillPoints} Skill-Punkte" : "";
                
                \App\Models\GameLog::log($user, $msg, 'success', 'economy');
            }

            $ranking->reward_granted = true;
            $ranking->save();
        }
    }

    /**
     * Get the latest rankings.
     */
    public function getLatestRankings(int $limit = 50)
    {
        $current = WeeklyRanking::orderBy('year', 'desc')->orderBy('week', 'desc')->first();
        if (!$current) return collect();

        return WeeklyRanking::where('year', $current->year)
            ->where('week', $current->week)
            ->with('user')
            ->orderBy('rank', 'asc')
            ->limit($limit)
            ->get();
    }
}
