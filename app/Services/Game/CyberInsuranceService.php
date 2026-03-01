<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\GameLog;

/**
 * FEATURE 91: Cyber-Insurance Policy
 * 
 * Subscription-based global insurance against DDoS, Ransomware, and crisis events.
 * Covers 80% of costs/penalties during active coverage.
 * 
 * Stored in player_economy.metadata['cyber_insurance']
 */
class CyberInsuranceService
{
    public const PLANS = [
        'basic' => [
            'name' => 'Basic Cyber Shield',
            'monthly_premium' => 500,
            'coverage_rate' => 0.50, // 50% cost coverage
            'covered_types' => ['crypto_ransom', 'solar_flare'],
            'level_req' => 5,
            'description' => 'Covers 50% of costs from Ransomware and Solar Flare events.',
        ],
        'professional' => [
            'name' => 'Professional Cyber Guard',
            'monthly_premium' => 2000,
            'coverage_rate' => 0.70, // 70% cost coverage
            'covered_types' => ['crypto_ransom', 'solar_flare', 'fiber_cut', 'energy_crisis'],
            'level_req' => 12,
            'description' => 'Covers 70% of costs from most crisis types including infrastructure failures.',
        ],
        'enterprise' => [
            'name' => 'Enterprise Total Protection',
            'monthly_premium' => 8000,
            'coverage_rate' => 0.80, // 80% cost coverage
            'covered_types' => ['crypto_ransom', 'solar_flare', 'fiber_cut', 'energy_crisis', 'market_crash', 'backup_crisis', 'power_rationing', 'employee_strike', 'hardware_shortage'],
            'level_req' => 20,
            'description' => 'Covers 80% of costs from ALL crisis types. Full enterprise protection.',
        ],
    ];

    /**
     * Subscribe to a cyber-insurance plan.
     */
    public function subscribe(User $user, string $planKey): array
    {
        if (!isset(self::PLANS[$planKey])) {
            return ['success' => false, 'error' => 'Invalid insurance plan.'];
        }

        $plan = self::PLANS[$planKey];
        $economy = $user->economy;

        if ($economy->level < $plan['level_req']) {
            return ['success' => false, 'error' => "Level {$plan['level_req']} required for this plan."];
        }

        // Check if already insured
        $meta = $economy->metadata ?? [];
        if (!empty($meta['cyber_insurance']['plan'])) {
            return ['success' => false, 'error' => 'Already have an active cyber-insurance policy. Cancel first.'];
        }

        // Debit first premium
        $premium = $plan['monthly_premium'];
        if (!$economy->canAfford($premium)) {
            return ['success' => false, 'error' => "Insufficient funds. Monthly premium: \${$premium}."];
        }

        $economy->debit($premium, "Cyber-Insurance Premium: {$plan['name']}", 'insurance');

        $meta['cyber_insurance'] = [
            'plan' => $planKey,
            'subscribed_at' => now()->toIso8601String(),
            'last_premium_tick' => $economy->current_tick,
            'total_claims' => 0,
            'total_saved' => 0,
        ];
        $economy->metadata = $meta;
        $economy->save();

        GameLog::log($user, "🛡️ CYBER-INSURANCE: Subscribed to '{$plan['name']}'. Coverage: {$plan['coverage_rate']}x on crises.", 'success', 'insurance');

        return ['success' => true, 'message' => "Subscribed to {$plan['name']}."];
    }

    /**
     * Cancel the cyber-insurance policy.
     */
    public function cancel(User $user): array
    {
        $economy = $user->economy;
        $meta = $economy->metadata ?? [];

        if (empty($meta['cyber_insurance']['plan'])) {
            return ['success' => false, 'error' => 'No active cyber-insurance policy.'];
        }

        $planKey = $meta['cyber_insurance']['plan'];
        $planName = self::PLANS[$planKey]['name'] ?? $planKey;

        unset($meta['cyber_insurance']);
        $economy->metadata = $meta;
        $economy->save();

        GameLog::log($user, "❌ CYBER-INSURANCE: Cancelled '{$planName}' policy.", 'warning', 'insurance');

        return ['success' => true, 'message' => "Cancelled {$planName} policy."];
    }

    /**
     * Process hourly premiums (called in game loop every 60 ticks).
     */
    public function processHourlyPremium(User $user): float
    {
        $economy = $user->economy;
        $meta = $economy->metadata ?? [];

        if (empty($meta['cyber_insurance']['plan'])) {
            return 0;
        }

        $planKey = $meta['cyber_insurance']['plan'];
        $plan = self::PLANS[$planKey] ?? null;
        if (!$plan) return 0;

        $lastTick = $meta['cyber_insurance']['last_premium_tick'] ?? 0;
        $ticksSinceLast = $economy->current_tick - $lastTick;

        // Charge premium every 60 ticks (1 game hour)
        if ($ticksSinceLast >= 60) {
            $hourlyRate = $plan['monthly_premium'] / 720; // ~720 ticks per month
            $hours = floor($ticksSinceLast / 60);
            $premium = $hourlyRate * $hours;

            if ($economy->canAfford($premium)) {
                $economy->debit($premium, "Cyber-Insurance Premium: {$plan['name']}", 'insurance');
                $meta['cyber_insurance']['last_premium_tick'] = $economy->current_tick;
                $economy->metadata = $meta;
                $economy->save();
                return $premium;
            } else {
                // Can't afford → auto-cancel
                unset($meta['cyber_insurance']);
                $economy->metadata = $meta;
                $economy->save();
                GameLog::log($user, "❌ CYBER-INSURANCE: Policy lapsed due to insufficient funds!", 'danger', 'insurance');
            }
        }

        return 0;
    }

    /**
     * Get the coverage rate for a specific crisis type.
     * Returns 0 if not covered.
     */
    public function getCoverageRate(User $user, string $crisisType): float
    {
        $meta = $user->economy->metadata ?? [];
        if (empty($meta['cyber_insurance']['plan'])) return 0;

        $planKey = $meta['cyber_insurance']['plan'];
        $plan = self::PLANS[$planKey] ?? null;
        if (!$plan) return 0;

        if (in_array($crisisType, $plan['covered_types'])) {
            return $plan['coverage_rate'];
        }

        return 0;
    }

    /**
     * Apply insurance coverage to a crisis cost (reduces the cost).
     * Returns the reduced cost after coverage.
     */
    public function applyCoverage(User $user, float $cost, string $crisisType): float
    {
        $rate = $this->getCoverageRate($user, $crisisType);
        if ($rate <= 0) return $cost;

        $covered = $cost * $rate;
        $finalCost = $cost - $covered;

        // Track claims
        $economy = $user->economy;
        $meta = $economy->metadata ?? [];
        $meta['cyber_insurance']['total_claims'] = ($meta['cyber_insurance']['total_claims'] ?? 0) + 1;
        $meta['cyber_insurance']['total_saved'] = ($meta['cyber_insurance']['total_saved'] ?? 0) + $covered;
        $economy->metadata = $meta;
        $economy->save();

        return $finalCost;
    }

    /**
     * Get insurance state for API/frontend.
     */
    public function getState(User $user): array
    {
        $meta = $user->economy->metadata ?? [];
        $insurance = $meta['cyber_insurance'] ?? null;

        if (!$insurance || empty($insurance['plan'])) {
            return [
                'active' => false,
                'plans' => self::PLANS,
            ];
        }

        $planKey = $insurance['plan'];
        $plan = self::PLANS[$planKey] ?? null;

        return [
            'active' => true,
            'currentPlan' => $planKey,
            'planDetails' => $plan,
            'subscribedAt' => $insurance['subscribed_at'] ?? null,
            'totalClaims' => $insurance['total_claims'] ?? 0,
            'totalSaved' => $insurance['total_saved'] ?? 0,
            'plans' => self::PLANS,
        ];
    }
}
