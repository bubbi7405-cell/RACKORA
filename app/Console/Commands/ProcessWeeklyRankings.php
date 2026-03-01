<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WeeklyRanking;
use App\Models\PlayerEconomy;
use App\Models\GameLog;

class ProcessWeeklyRankings extends Command
{
    protected $signature = 'game:process-weekly-rankings';
    protected $description = 'Snapshots the current leaderboard, saves to weekly history, and awards rewards.';

    /**
     * Reward tiers based on rank.
     */
    private const RANK_REWARDS = [
        1 => ['cash' => 100000, 'xp' => 2000, 'rep' => 15, 'title' => '🏆 #1 Global Champion'],
        2 => ['cash' => 50000,  'xp' => 1500, 'rep' => 10, 'title' => '🥈 Silver Contender'],
        3 => ['cash' => 25000,  'xp' => 1000, 'rep' => 8,  'title' => '🥉 Bronze Contender'],
        // Top 10
        10 => ['cash' => 10000, 'xp' => 500,  'rep' => 5,  'title' => '⭐ Top 10 CEO'],
        // Top 25
        25 => ['cash' => 5000,  'xp' => 250,  'rep' => 3,  'title' => '🏅 Top 25 CEO'],
        // Top 50
        50 => ['cash' => 2000,  'xp' => 100,  'rep' => 1,  'title' => '📊 Top 50 CEO'],
    ];

    public function handle()
    {
        $year = now()->year;
        $week = now()->weekOfYear;

        $this->info("Processing Weekly Rankings for Year: $year, Week: $week");

        // Use a composite score: Balance + (Level × 1000) + (Reputation × 50) + (MarketShare × 500)
        $topPlayers = PlayerEconomy::select('*')
            ->selectRaw('(balance * 0.3 + (level * 1000) * 0.25 + (reputation * 50) * 0.25 + (COALESCE(global_market_share, 0) * 500) * 0.2) as competition_score')
            ->orderByDesc('competition_score')
            ->limit(100)
            ->get();

        $count = 0;
        foreach ($topPlayers as $index => $economy) {
            $rank = $index + 1;

            $existingEntry = WeeklyRanking::where('year', $year)
                ->where('week', $week)
                ->where('user_id', $economy->user_id)
                ->first();

            $alreadyRewarded = $existingEntry?->reward_granted;

            WeeklyRanking::updateOrCreate(
                [
                    'year' => $year,
                    'week' => $week,
                    'user_id' => $economy->user_id,
                ],
                [
                    'rank' => $rank,
                    'balance' => $economy->balance,
                    'reputation' => $economy->reputation,
                    'level' => $economy->level,
                ]
            );

            // Award rewards if not already granted this week
            if (!$alreadyRewarded) {
                $this->awardReward($economy, $rank, $year, $week);
            }

            $count++;
        }

        $this->info("Successfully snapshotted $count players.");
    }

    /**
     * Award tiered rewards based on rank.
     */
    private function awardReward(PlayerEconomy $economy, int $rank, int $year, int $week): void
    {
        $reward = null;

        // Find the best matching reward tier
        foreach (self::RANK_REWARDS as $maxRank => $r) {
            if ($rank <= $maxRank) {
                $reward = $r;
                break;
            }
        }

        if (!$reward) return;

        // Apply rewards
        $economy->balance += $reward['cash'];
        $economy->addExperience($reward['xp']);
        $economy->adjustReputation($reward['rep']);
        $economy->save();

        // Mark as rewarded
        WeeklyRanking::where('year', $year)
            ->where('week', $week)
            ->where('user_id', $economy->user_id)
            ->update(['reward_granted' => true]);

        // Log it
        $user = $economy->user;
        if ($user) {
            $cashFormatted = number_format($reward['cash']);
            GameLog::log(
                $user,
                "{$reward['title']} — Week $week Ranking Reward: +\${$cashFormatted}, +{$reward['xp']} XP, +{$reward['rep']} Rep",
                'success',
                'ranking'
            );
        }

        $this->line("  → Rank #{$rank}: Awarded {$reward['title']} to user {$economy->user_id}");
    }
}
