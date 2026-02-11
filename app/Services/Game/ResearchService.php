<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\UserResearch;
use Illuminate\Support\Facades\DB;

class ResearchService
{
    // Research Catalog
    public const PROJECTS = [
        'cooling_efficiency' => [
            'name' => 'Advanced Cooling',
            'description' => 'Optimizes airflow and cooling systems, reducing overall power consumption.',
            'levels' => 5,
            'base_cost' => 1000,
            'cost_multiplier' => 1.8,
            'base_duration' => 300, // 5 minutes (ticks)
            'duration_multiplier' => 1.5,
            'effect_key' => 'power_efficiency',
            'effect_value' => 0.10, // 10% per level additive
        ],
        'provisioning_speed' => [
            'name' => 'Automated Provisioning',
            'description' => 'Streamlines OS installation, reducing server setup time.',
            'levels' => 3,
            'base_cost' => 2500,
            'cost_multiplier' => 2.0,
            'base_duration' => 600, // 10 minutes
            'duration_multiplier' => 1.5,
            'effect_key' => 'provisioning_speed',
            'effect_value' => 0.20,
        ],
        'marketing_campaign' => [
            'name' => 'Marketing Strategy',
            'description' => 'Increases brand awareness, attracting higher-tier customers.',
            'levels' => 5,
            'base_cost' => 5000,
            'cost_multiplier' => 2.5,
            'base_duration' => 900, // 15 minutes
            'duration_multiplier' => 1.2,
            'effect_key' => 'customer_quality',
            'effect_value' => 0.15,
        ],
        'high_density_racks' => [
            'name' => 'High-Density Architecture',
            'description' => 'Unlocks 42U Rack support.',
            'levels' => 1,
            'base_cost' => 15000,
            'cost_multiplier' => 1,
            'base_duration' => 1800, // 30 minutes
            'duration_multiplier' => 1,
            'effect_key' => 'unlock_rack_42u',
            'effect_value' => 1,
        ],
    ];

    /**
     * Get available research projects for the user with current status
     */
    public function getAvailableResearches(User $user): array
    {
        // Get all completed or active researches
        $userResearches = UserResearch::where('user_id', $user->id)
            ->get()
            ->groupBy('research_key');
        
        $result = [];
        foreach (self::PROJECTS as $key => $config) {
            $researches = $userResearches->get($key); // Collection of levels
            
            // Calculate current max level achieved
            $maxLevelAchieved = 0;
            $activeResearch = null;
            
            if ($researches) {
                foreach ($researches as $r) {
                    if ($r->status === 'completed') {
                        $maxLevelAchieved = max($maxLevelAchieved, $r->level);
                    } elseif ($r->status === 'active') {
                        $activeResearch = $r;
                    }
                }
            }
            
            // Check if fully maxed
            if ($maxLevelAchieved >= $config['levels']) {
                $result[] = [
                    'key' => $key,
                    'name' => $config['name'],
                    'description' => $config['description'],
                    'status' => 'maxed',
                    'currentLevel' => $maxLevelAchieved,
                    'maxLevel' => $config['levels'],
                    'cost' => 0,
                    'duration' => 0,
                    'progress' => 100,
                ];
                continue;
            }

            // Next level logic
            $nextLevel = $maxLevelAchieved + 1;
            
            // Calculate cost/duration for next level
            $cost = $config['base_cost'] * pow($config['cost_multiplier'], $nextLevel - 1);
            $duration = $config['base_duration'] * pow($config['duration_multiplier'] ?? 1.0, $nextLevel - 1);

            // Determine status
            $status = 'available';
            $progress = 0;
            
            if ($activeResearch && $activeResearch->level === $nextLevel) {
                $status = 'active';
                $progress = $activeResearch->progress;
            } elseif ($activeResearch) {
                // Should not happen (active but not next level?), unless data inconsistency
                // Or maybe we allow researching Level 5 directly? No.
            }

            // Check if user has active research elsewhere
            $anyActive = UserResearch::where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();
            
            if ($status === 'available' && $anyActive) {
                $status = 'locked'; // Another research is active
            }

            $result[] = [
                'key' => $key,
                'name' => $config['name'],
                'description' => $config['description'],
                'status' => $status,
                'currentLevel' => $maxLevelAchieved,
                'nextLevel' => $nextLevel,
                'maxLevel' => $config['levels'],
                'cost' => (int)$cost,
                'duration' => (int)$duration, 
                'progress' => $progress,
            ];
        }

        return $result;
    }

    /**
     * Start a research project
     */
    public function startResearch(User $user, string $key): array
    {
        if (!isset(self::PROJECTS[$key])) {
            return ['success' => false, 'error' => 'Invalid research project'];
        }
        $config = self::PROJECTS[$key];

        // Check active research (limit 1)
        if (UserResearch::where('user_id', $user->id)->where('status', 'active')->exists()) {
            return ['success' => false, 'error' => 'Another research is already in progress'];
        }

        // Determine level
        $completedLevels = UserResearch::where('user_id', $user->id)
            ->where('research_key', $key)
            ->where('status', 'completed')
            ->max('level') ?? 0;

        if ($completedLevels >= $config['levels']) {
            return ['success' => false, 'error' => 'Research already maxed out'];
        }

        $nextLevel = $completedLevels + 1;

        // Check if entry for next level already exists (e.g. paused/failed? for now we don't support pause)
        // If it exists but is not completed, we might resume?
        // Let's assume strict sequential creation for simplicity.

        // Calculate Cost
        $cost = $config['base_cost'] * pow($config['cost_multiplier'], $nextLevel - 1);
        
        if (!$user->economy->canAfford($cost)) {
            return ['success' => false, 'error' => 'Insufficient funds'];
        }

        return DB::transaction(function () use ($user, $key, $nextLevel, $cost) {
            $research = UserResearch::create([
                'user_id' => $user->id,
                'research_key' => $key,
                'level' => $nextLevel,
                'status' => 'active',
                'progress' => 0,
                'started_at' => now(),
            ]);

            if (!$user->economy->debit($cost, "Started research: $key (Level $nextLevel)", 'research', $research)) {
                throw new \Exception("Insufficient funds");
            }

            return ['success' => true, 'data' => $research];
        });
    }

    /**
     * Advance research progress (called by GameLoop)
     */
    public function tick(User $user): void
    {
        $active = UserResearch::where('user_id', $user->id)->where('status', 'active')->first();
        if (!$active) return;

        $config = self::PROJECTS[$active->research_key];
        
        $duration = $config['base_duration'] * pow($config['duration_multiplier'] ?? 1.0, $active->level - 1);
        
        // Progress per tick (assuming tick is 60s)
        $progressPerTick = (60 / $duration) * 100;
        
        // Ensure at least 1% progress if duration is very long
        $progressPerTick = max(1, $progressPerTick);
        
        $newProgress = $active->progress + $progressPerTick;
        
        if ($newProgress >= 100) {
            $active->progress = 100;
            $active->status = 'completed';
            $active->completed_at = now();
            
            // Award XP for completing research
            $user->economy->addExperience(50);
        } else {
            $active->progress = (int)$newProgress;
        }
        
        $active->save();
    }
    
    /**
     * Helper to check bonuses
     */
    public function getBonus(User $user, string $effectKey): float
    {
        $researches = UserResearch::where('user_id', $user->id)
            ->where('status', 'completed')
            ->get();
            
        $total = 0.0;
        
        foreach ($researches as $r) {
            $config = self::PROJECTS[$r->research_key] ?? null;
            if ($config && $config['effect_key'] === $effectKey) {
                // Additive bonus
                $total += $config['effect_value'];
            }
        }
        
        return $total;
    }
    
    /**
     * Check if a specific technology is unlocked (boolean check)
     */
    public function isUnlocked(User $user, string $unlockKey): bool
    {
        return $this->getBonus($user, $unlockKey) > 0;
    }
}
