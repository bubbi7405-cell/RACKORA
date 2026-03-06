<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\GameConfig;

class SpecializationService
{
    /**
     * Get all available Specializations (Configurable via Admin Panel).
     */
    public function getDefinitions(): array
    {
        $defaults = [
            'balanced' => [
                'name' => 'Balanced Hoster',
                'description' => 'A bit of everything. Standard setup.',
                'order_types' => ['web_hosting' => 1.0, 'vserver' => 1.0, 'dedicated' => 1.0, 'ml_training' => 1.0, 'game_server' => 1.0],
                'price_modifier' => 1.0,
                'patience_modifier' => 1.0,
                'requirement_modifier' => 1.0,
                'unlock_cost' => 0,
                'passives' => []
            ],
            'budget_mass' => [
                'name' => 'Mass Market (Budget)',
                'description' => 'Volume over margin. Fast growth, lower quality requirements.',
                'order_types' => ['web_hosting' => 3.0, 'vserver' => 2.0, 'dedicated' => 0.5, 'ml_training' => 0.1, 'game_server' => 1.5],
                'price_modifier' => 0.85,
                'patience_modifier' => 0.7,
                'requirement_modifier' => 0.8,
                'unlock_cost' => 5000,
                'passives' => [
                    'hardware_cost_reduction' => 0.25,
                    'churn_penalty' => 0.40,
                    'xp_gain' => 0.15,
                    'order_frequency_boost' => 1.5,
                    'satisfaction_penalty_multiplier' => 2.0
                ]
            ],
            'high_performance' => [
                 'name' => 'Performance Elite (Premium)',
                 'description' => 'Maximum compute power. High margin but demanding infrastructure.',
                 'order_types' => ['web_hosting' => 0.1, 'vserver' => 0.5, 'dedicated' => 2.5, 'ml_training' => 4.0, 'game_server' => 2.0],
                 'price_modifier' => 1.8,
                 'patience_modifier' => 1.2,
                 'requirement_modifier' => 1.5,
                 'unlock_cost' => 25000,
                 'passives' => [
                     'income_boost' => 0.30,
                     'cooling_penalty' => 0.10,
                     'hw_failure_chance' => 0.05
                 ]
            ],
            'eco_certified' => [
                 'name' => 'Eco-Certified (Green)',
                 'description' => 'Sustainable hosting focused on reputation and efficiency.',
                 'order_types' => ['web_hosting' => 1.5, 'vserver' => 1.2, 'dedicated' => 1.0, 'ml_training' => 0.5, 'game_server' => 0.8],
                 'price_modifier' => 1.2,
                 'patience_modifier' => 1.4,
                 'requirement_modifier' => 1.0,
                 'unlock_cost' => 15000,
                 'passives' => [
                     'power_cost_reduction' => 0.20,
                     'reputation_gain' => 0.10,
                     'churn_reduction' => 0.15
                 ]
            ],
            'hpc_specialist' => [
                 'name' => 'HPC Specialist (AI)',
                 'description' => 'Cutting edge computation. Requires heavy-duty cooling and extreme hardware.',
                 'order_types' => ['web_hosting' => 0.05, 'vserver' => 0.1, 'dedicated' => 1.0, 'ml_training' => 8.0, 'game_server' => 0.5],
                 'price_modifier' => 3.0,
                 'patience_modifier' => 1.1,
                 'requirement_modifier' => 1.8,
                 'unlock_cost' => 100000,
                 'passives' => [
                     'income_boost' => 0.50,
                     'cooling_penalty' => 0.30,
                     'hw_failure_chance' => 0.15
                 ]
            ],
            'crypto_vault' => [
                'name' => 'Crypto & Ledger Hub',
                'description' => 'Focus on immutable storage and private network hosting. High risk, high volatility.',
                'order_types' => ['web_hosting' => 0.2, 'vserver' => 0.8, 'dedicated' => 2.5, 'ml_training' => 0.5, 'game_server' => 0.2],
                'price_modifier' => 2.2,
                'patience_modifier' => 0.6,
                'requirement_modifier' => 1.3,
                'unlock_cost' => 75000,
                'passives' => [
                    'bandwidth_drain' => 0.3,
                    'reputation_impact' => -0.05
                ]
            ]
        ];

        $dbConfig = GameConfig::get('specialization_definitions');
        if (is_array($dbConfig)) {
            return array_merge($defaults, $dbConfig);
        }

        return $defaults;
    }
    
    public function setSpecialization(User $user, string $specKey): void
    {
        $defs = $this->getDefinitions();
        
        if (!isset($defs[$specKey])) {
             throw new \Exception("Invalid specialization key.");
        }
        
        $spec = $defs[$specKey];
        $economy = $user->economy;

        // Level Requirement
        if ($economy->level < 10) {
            throw new \Exception("Corporate Doctrine requires Level 10 protocols.");
        }

        // Already same specialization
        if ($economy->corporate_specialization === $specKey) {
            throw new \Exception("This is already your active doctrine.");
        }
        
        // Cooldown: 24h between switches (except first selection)
        // Allow switching FROM 'balanced' or null without cooldown
        if ($economy->corporate_specialization && $economy->corporate_specialization !== 'balanced' && $user->specialization_updated_at) {
            $hoursAgo = $user->specialization_updated_at->diffInHours(now());
            if ($hoursAgo < 24) {
                $remaining = round(24 - $hoursAgo, 2);
                throw new \Exception("Rebranding cooldown: {$remaining}h remaining. Strategic shifts require preparation.");
            }
        }
        
        if ($spec['unlock_cost'] > 0) {
             if (!$economy->canAfford($spec['unlock_cost'])) {
                  throw new \Exception("Insufficient capital for rebranding.");
             }
             $economy->debit($spec['unlock_cost'], "Doctrine Protocol: {$spec['name']}", 'strategy');
        }
        
        // Reputation Impact (Initial)
        if (isset($spec['reputation_impact']) && $spec['reputation_impact'] !== 0) {
             $economy->adjustReputation((float) $spec['reputation_impact']);
        }
        
        $economy->corporate_specialization = $specKey;
        $economy->save();
        
        // Keep User model in sync for profile view  
        $user->specialization = $specKey;
        $user->specialization_updated_at = now();
        $user->save();
    }
    
    public function getActiveModifiers(User $user): array
    {
         $key = $user->economy->corporate_specialization ?? 'balanced';
         $defs = $this->getDefinitions();
         return $defs[$key] ?? $defs['balanced'];
    }
}
