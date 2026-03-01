<?php

namespace App\Services\Game;

use App\Models\Achievement;
use App\Models\User;
use App\Events\AchievementUnlocked;
use Illuminate\Support\Collection;

class AchievementService
{
    public const ACHIEVEMENTS = [
        // INFRASTRUCTURE
        'first_server' => [
            'name' => 'Hello World',
            'description' => "The fans spin up, the LEDs flicker. You've officially entered the digital arena. It's just one box, but it's the beginning of an empire.",
            'category' => 'infrastructure',
            'icon' => '🖥️',
            'points' => 10,
        ],
        'full_rack' => [
            'name' => 'Maximum Density',
            'description' => "You've mastered the geometry of the server room. Every U-slot is humming with data. There's literally no room for improvement in this rack.",
            'category' => 'infrastructure',
            'icon' => '📦',
            'points' => 25,
        ],
        'data_center_owner' => [
            'name' => 'Industry Mogul',
            'description' => "The basement was for hobbyists. The garage was for startups. This? This is where the world's data lives. You've arrived.",
            'category' => 'infrastructure',
            'icon' => '🏢',
            'points' => 100,
        ],

        // ECONOMY
        'millionaire' => [
            'name' => 'Seven Figures',
            'description' => "1,000,000. It's not just a number; it's a statement. You're no longer just 'paying the bills'. You're a market force.",
            'category' => 'economy',
            'icon' => '💰',
            'points' => 50,
        ],
        'high_reputation' => [
            'name' => 'The Gold Standard',
            'description' => "When CEOs think of uptime, they think of you. Your brand is synonymous with reliability. You are the hoster they trust.",
            'category' => 'economy',
            'icon' => '🌟',
            'points' => 50,
        ],

        // CRISIS MANAGEMENT
        'crisis_survivor' => [
            'name' => 'Zero-Day Veteran',
            'description' => "Fires, hacks, and outages. You've seen them all and walked away stronger. Ten major crises contained, and your pulse didn't even skip.",
            'category' => 'events',
            'icon' => '🔥',
            'points' => 30,
        ],
        'perfect_grade' => [
            'name' => 'Incident Commander',
            'description' => "Textbook resolution. S-Grade management means you resolved the crisis before the customer even realized there was a problem.",
            'category' => 'events',
            'icon' => '🏅',
            'points' => 25,
        ],

        // SPECIALIZATION
        'green_peace' => [
            'name' => 'Digital Forest',
            'description' => "100% renewable, 100% ethical. Your servers are carbon-neutral, and your conscience is as clean as your air-handling units.",
            'category' => 'specialization',
            'icon' => '🌿',
            'points' => 40,
        ],
        'premium_elite' => [
            'name' => 'The Velvet Rope',
            'description' => "Your hardware is bespoke, your SLA is absolute. You only host the best, for the best. You've reached the summit of Premium Hosting.",
            'category' => 'specialization',
            'icon' => '💎',
            'points' => 40,
        ],
    ];

    /**
     * Ensure all achievements exist in the database
     */
    public function syncAchievements(): void
    {
        foreach (self::ACHIEVEMENTS as $key => $data) {
            Achievement::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'category' => $data['category'],
                    'icon' => $data['icon'],
                    'points' => $data['points'],
                    'requirements' => $data['requirements'] ?? [],
                ]
            );
        }
    }

    /**
     * Check and unlock achievements for a user
     */
    public function checkAchievements(User $user): void
    {
        $unlockedKeys = $user->achievements()->pluck('key')->toArray();
        $economy = $user->economy;

        // Millionaire
        if (!in_array('millionaire', $unlockedKeys) && $economy->balance >= 1000000) {
            $this->unlock($user, 'millionaire');
        }

        // High Reputation
        if (!in_array('high_reputation', $unlockedKeys) && $economy->reputation >= 90) {
            $this->unlock($user, 'high_reputation');
        }

        // Green Peace
        if (!in_array('green_peace', $unlockedKeys) && ($economy->specialized_reputation['green'] ?? 0) >= 90) {
            $this->unlock($user, 'green_peace');
        }

        // Premium Elite
        if (!in_array('premium_elite', $unlockedKeys) && ($economy->specialized_reputation['premium'] ?? 0) >= 90) {
            $this->unlock($user, 'premium_elite');
        }

        // Infrastructure checks
        $serverCount = $user->servers()->where('status', 'online')->count();
        if (!in_array('first_server', $unlockedKeys) && $serverCount >= 1) {
            $this->unlock($user, 'first_server');
        }

        // Data Center Owner
        $hasDC = $user->rooms()->where('type', 'data_center')->exists();
        if (!in_array('data_center_owner', $unlockedKeys) && $hasDC) {
            $this->unlock($user, 'data_center_owner');
        }
    }

    /**
     * Manually unlock an achievement
     */
    public function unlock(User $user, string $key): bool
    {
        $achievement = Achievement::where('key', $key)->first();
        if (!$achievement) return false;

        if ($user->achievements()->where('achievement_id', $achievement->id)->exists()) {
            return false;
        }

        $user->achievements()->attach($achievement->id, ['unlocked_at' => now()]);
        
        // Broadcast event for frontend toast
        broadcast(new AchievementUnlocked($user, $achievement));

        return true;
    }

    /**
     * Get user achievement progress
     */
    public function getAchievementsForUser(User $user): Collection
    {
        $all = Achievement::all();
        $unlocked = $user->achievements()->get()->keyBy('key');

        return $all->map(function ($ach) use ($unlocked) {
            $isUnlocked = $unlocked->has($ach->key);
            return [
                'id' => $ach->id,
                'key' => $ach->key,
                'name' => $ach->name,
                'description' => $ach->description,
                'category' => $ach->category,
                'icon' => $ach->icon,
                'points' => $ach->points,
                'isUnlocked' => $isUnlocked,
                'unlockedAt' => $isUnlocked ? $unlocked->get($ach->key)->pivot->unlocked_at->toIso8601String() : null,
            ];
        });
    }
}
