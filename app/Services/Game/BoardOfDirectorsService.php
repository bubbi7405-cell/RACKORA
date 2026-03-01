<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\PlayerEconomy;
use Illuminate\Support\Facades\Log;

/**
 * FEATURE 195: The Board of Directors (AI Oversight)
 * 
 * NPC Board members issue monthly "KPI Missions".
 * Failure results in "Hostile Takeover" threats (XP/Reputation loss).
 */
class BoardOfDirectorsService
{
    /**
     * Board Member Definitions
     * Each member has a personality and generates specific KPI types.
     */
    public const BOARD_MEMBERS = [
        'cfo' => [
            'name' => 'Victoria Wells',
            'title' => 'Chief Financial Officer',
            'personality' => 'aggressive',
            'icon' => '💰',
            'kpi_types' => ['revenue_target', 'cost_reduction', 'profit_margin'],
        ],
        'cto' => [
            'name' => 'Dr. Raj Patel',
            'title' => 'Chief Technology Officer',
            'personality' => 'visionary',
            'icon' => '🔬',
            'kpi_types' => ['uptime_target', 'research_completion', 'server_count'],
        ],
        'coo' => [
            'name' => 'Klaus Bergmann',
            'title' => 'Chief Operations Officer',
            'personality' => 'methodical',
            'icon' => '⚙️',
            'kpi_types' => ['customer_satisfaction', 'sla_compliance', 'efficiency_target'],
        ],
        'cmo' => [
            'name' => 'Sarah Kim',
            'title' => 'Chief Marketing Officer',
            'personality' => 'charismatic',
            'icon' => '📢',
            'kpi_types' => ['customer_growth', 'reputation_target', 'brand_milestone'],
        ],
    ];

    /**
     * KPI Mission Templates
     */
    public const KPI_MISSIONS = [
        'revenue_target' => [
            'name' => 'Revenue Target',
            'description' => 'Achieve ${target} in hourly revenue.',
            'metric' => 'hourly_revenue',
        ],
        'cost_reduction' => [
            'name' => 'Cost Optimization',
            'description' => 'Reduce hourly expenses below ${target}.',
            'metric' => 'hourly_expenses',
            'comparator' => 'lte', // less than or equal
        ],
        'profit_margin' => [
            'name' => 'Profit Margin',
            'description' => 'Maintain a profit margin above {target}%.',
            'metric' => 'profit_margin_pct',
        ],
        'uptime_target' => [
            'name' => 'Uptime SLA',
            'description' => 'Keep average uptime above {target}%.',
            'metric' => 'avg_uptime',
        ],
        'research_completion' => [
            'name' => 'Innovation Drive',
            'description' => 'Complete {target} research project(s) this cycle.',
            'metric' => 'research_completed_count',
        ],
        'server_count' => [
            'name' => 'Infrastructure Growth',
            'description' => 'Operate at least {target} online servers.',
            'metric' => 'online_server_count',
        ],
        'customer_satisfaction' => [
            'name' => 'Customer Happiness Index',
            'description' => 'Maintain average satisfaction above {target}%.',
            'metric' => 'avg_customer_satisfaction',
        ],
        'sla_compliance' => [
            'name' => 'SLA Compliance Rate',
            'description' => 'Keep SLA breach rate below {target}%.',
            'metric' => 'sla_breach_rate',
            'comparator' => 'lte',
        ],
        'efficiency_target' => [
            'name' => 'Operational Efficiency',
            'description' => 'Achieve a PUE score below {target} across all facilities.',
            'metric' => 'avg_pue',
            'comparator' => 'lte',
        ],
        'customer_growth' => [
            'name' => 'Customer Acquisition',
            'description' => 'Grow customer base to {target} active customers.',
            'metric' => 'active_customer_count',
        ],
        'reputation_target' => [
            'name' => 'Brand Reputation',
            'description' => 'Reach a reputation score of {target}.',
            'metric' => 'reputation',
        ],
        'brand_milestone' => [
            'name' => 'Market Presence',
            'description' => 'Achieve Level {target} or higher.',
            'metric' => 'player_level',
        ],
    ];

    /**
     * Generate a new set of KPI missions for a board cycle.
     * Usually triggered every ~720 ticks (≈12 hours in-game).
     */
    public function generateMissions(User $user): array
    {
        $economy = $user->economy;
        $level = $economy->level;
        $missions = [];

        // Pick 2-3 random board members
        $memberKeys = array_keys(self::BOARD_MEMBERS);
        shuffle($memberKeys);
        $activeMemberCount = $level >= 15 ? 3 : 2;
        $activeMembers = array_slice($memberKeys, 0, $activeMemberCount);

        foreach ($activeMembers as $memberKey) {
            $member = self::BOARD_MEMBERS[$memberKey];
            $kpiType = $member['kpi_types'][array_rand($member['kpi_types'])];
            $mission = self::KPI_MISSIONS[$kpiType];

            $target = $this->calculateTarget($kpiType, $level, $economy);

            $missions[] = [
                'id' => uniqid('kpi_'),
                'board_member' => $memberKey,
                'member_name' => $member['name'],
                'member_title' => $member['title'],
                'member_icon' => $member['icon'],
                'kpi_type' => $kpiType,
                'name' => $mission['name'],
                'description' => str_replace('{target}', number_format($target), $mission['description']),
                'target' => $target,
                'metric' => $mission['metric'],
                'comparator' => $mission['comparator'] ?? 'gte', // greater than or equal
                'status' => 'active', // active, completed, failed
                'created_at' => now()->toIso8601String(),
                'deadline_at' => now()->addMinutes(720)->toIso8601String(), // 12h game-hours
            ];
        }

        // Store missions in economy metadata
        $meta = $economy->metadata ?? [];
        $meta['board_missions'] = $missions;
        $meta['board_cycle_started_at'] = now()->toIso8601String();
        $economy->metadata = $meta;
        $economy->save();

        \App\Models\GameLog::log($user, "📋 The Board of Directors has issued new KPI targets. Meet them or face consequences.", 'warning', 'management');

        return $missions;
    }

    /**
     * Scale KPI targets based on player level.
     */
    private function calculateTarget(string $kpiType, int $level, PlayerEconomy $economy): float
    {
        return match ($kpiType) {
            'revenue_target' => round(50 * pow(1.3, $level), 2),
            'cost_reduction' => round(30 * pow(1.2, $level), 2),
            'profit_margin' => min(40, 10 + ($level * 2)),
            'uptime_target' => min(99.9, 90 + ($level * 0.5)),
            'research_completion' => max(1, floor($level / 5)),
            'server_count' => max(2, $level),
            'customer_satisfaction' => min(95, 60 + ($level * 2)),
            'sla_breach_rate' => max(2, 20 - $level),
            'efficiency_target' => max(1.2, 2.5 - ($level * 0.05)),
            'customer_growth' => max(3, $level * 2),
            'reputation_target' => 50 + ($level * 3),
            'brand_milestone' => max(2, $level + 2),
            default => 10 * $level,
        };
    }

    /**
     * Evaluate current KPI progress during each tick.
     * Called from GameLoopService periodically.
     */
    public function tick(User $user): void
    {
        $economy = $user->economy;
        $meta = $economy->metadata ?? [];
        $missions = $meta['board_missions'] ?? [];

        if (empty($missions)) {
            // Auto-generate missions if none exist and player is level 5+
            if ($economy->level >= 5) {
                $this->generateMissions($user);
            }
            return;
        }

        $updated = false;
        $currentMetrics = $this->getCurrentMetrics($user);

        foreach ($missions as &$mission) {
            if ($mission['status'] !== 'active') continue;

            // Check deadline
            if (now()->gt(\Carbon\Carbon::parse($mission['deadline_at']))) {
                $mission['status'] = 'failed';
                $updated = true;
                $this->applyFailurePenalty($user, $mission);
                continue;
            }

            // Check completion
            $current = $currentMetrics[$mission['metric']] ?? 0;
            $comparator = $mission['comparator'] ?? 'gte';
            
            $met = match ($comparator) {
                'lte' => $current <= $mission['target'],
                'gte' => $current >= $mission['target'],
                default => $current >= $mission['target'],
            };

            if ($met) {
                $mission['status'] = 'completed';
                $updated = true;
                $this->applySuccessReward($user, $mission);
            }
        }
        unset($mission);

        // Check if all missions resolved → start new cycle
        $allResolved = collect($missions)->every(fn($m) => $m['status'] !== 'active');
        if ($allResolved) {
            $meta['board_missions_history'][] = [
                'missions' => $missions,
                'resolved_at' => now()->toIso8601String(),
            ];
            // Keep only last 5 cycles in history
            if (count($meta['board_missions_history'] ?? []) > 5) {
                array_shift($meta['board_missions_history']);
            }
            $meta['board_missions'] = [];
            $economy->metadata = $meta;
            $economy->save();
            
            // Generate new cycle after a brief delay (next tick will pick it up)
            return;
        }

        if ($updated) {
            $meta['board_missions'] = $missions;
            $economy->metadata = $meta;
            $economy->save();
        }
    }

    /**
     * Collect current gameplay metrics for KPI evaluation.
     */
    private function getCurrentMetrics(User $user): array
    {
        $economy = $user->economy;
        $servers = \App\Models\Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->get();
        $onlineServers = $servers->where('status', \App\Enums\ServerStatus::ONLINE);
        $customers = \App\Models\Customer::where('user_id', $user->id)->get();
        $rooms = $user->rooms;

        $avgUptime = $onlineServers->count() > 0
            ? ($onlineServers->count() / max(1, $servers->count())) * 100
            : 0;

        $avgSatisfaction = $customers->count() > 0
            ? $customers->avg('satisfaction')
            : 0;

        $avgPue = $rooms->count() > 0
            ? $rooms->avg(fn($r) => $r->calculatePue())
            : 2.5;

        $recentResearch = \App\Models\Research::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subMinutes(720))
            ->count();

        return [
            'hourly_revenue' => (float) ($economy->metadata['last_hourly_income'] ?? 0),
            'hourly_expenses' => (float) ($economy->metadata['last_hourly_expenses'] ?? 0),
            'profit_margin_pct' => $economy->metadata['last_hourly_income'] > 0
                ? (($economy->metadata['last_hourly_income'] - ($economy->metadata['last_hourly_expenses'] ?? 0)) / $economy->metadata['last_hourly_income']) * 100
                : 0,
            'avg_uptime' => $avgUptime,
            'research_completed_count' => $recentResearch,
            'online_server_count' => $onlineServers->count(),
            'avg_customer_satisfaction' => $avgSatisfaction,
            'sla_breach_rate' => (float) ($economy->metadata['sla_breach_rate'] ?? 0),
            'avg_pue' => $avgPue,
            'active_customer_count' => $customers->where('status', 'active')->count(),
            'reputation' => (float) $economy->reputation,
            'player_level' => (int) $economy->level,
        ];
    }

    /**
     * Reward for meeting a KPI target.
     */
    private function applySuccessReward(User $user, array $mission): void
    {
        $economy = $user->economy;
        $xpReward = 200;
        $repReward = 5.0;

        $economy->addExperience($xpReward);
        $economy->adjustReputation($repReward);
        $economy->save();

        \App\Models\GameLog::log(
            $user,
            "✅ Board KPI met: {$mission['name']}! {$mission['member_icon']} {$mission['member_name']} is pleased. (+{$xpReward} XP, +{$repReward} Rep)",
            'success',
            'management'
        );
    }

    /**
     * Penalty for failing a KPI target (Hostile Takeover Threat).
     */
    private function applyFailurePenalty(User $user, array $mission): void
    {
        $economy = $user->economy;
        $repPenalty = -10.0;
        $xpPenalty = 100;

        $economy->adjustReputation($repPenalty);
        // XP can't go negative, but we reduce skill points instead
        $economy->skill_points = max(0, ($economy->skill_points ?? 0) - 1);
        $economy->save();

        \App\Models\GameLog::log(
            $user,
            "❌ HOSTILE TAKEOVER THREAT: {$mission['member_icon']} {$mission['member_name']} is furious! KPI '{$mission['name']}' failed. ({$repPenalty} Rep, -1 Skill Point)",
            'danger',
            'management'
        );
    }

    /**
     * Get current board state for API response.
     */
    public function getBoardState(User $user): array
    {
        $economy = $user->economy;
        $meta = $economy->metadata ?? [];
        $missions = $meta['board_missions'] ?? [];
        $history = $meta['board_missions_history'] ?? [];

        $currentMetrics = !empty($missions) ? $this->getCurrentMetrics($user) : [];

        // Enrich missions with current progress
        foreach ($missions as &$mission) {
            if ($mission['status'] === 'active') {
                $current = $currentMetrics[$mission['metric']] ?? 0;
                $mission['current_value'] = $current;
                $mission['progress_pct'] = $mission['target'] > 0
                    ? min(100, ($current / $mission['target']) * 100)
                    : 0;
            }
        }
        unset($mission);

        return [
            'members' => self::BOARD_MEMBERS,
            'active_missions' => $missions,
            'history' => array_slice($history, -3), // Last 3 cycles
            'cycle_started_at' => $meta['board_cycle_started_at'] ?? null,
            'unlocked' => $economy->level >= 5,
        ];
    }
}
