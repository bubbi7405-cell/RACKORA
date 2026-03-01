<?php

namespace App\Services\Game;

use App\Models\PlayerEconomy;
use Exception;

class ManagementService
{
    /**
     * Define possible decisions and their effects
     */
    public const DECISIONS = [
        'market_focus' => [
            'id' => 'market_focus',
            'title' => 'Market Positioning',
            'description' => 'How do you want to position your hosting brand in the digital landscape?',
            'milestone_level' => 5,
            'options' => [
                'budget' => [
                    'title' => 'Budget King',
                    'description' => 'Focus on volume. Cheap prices attract more customers, but margins are thin.',
                    'effects' => 'Higher order frequency (+20%), Lower profit margins (-15%)',
                    'modifiers' => ['order_frequency' => 1.2, 'price_modifier' => 0.85]
                ],
                'premium' => [
                    'title' => 'Premium Provider',
                    'description' => 'Focus on quality. High-end hardware and SLA focus attract whale customers who pay extra.',
                    'effects' => 'Higher profit margins (+25%), Lower order frequency (-20%)',
                    'modifiers' => ['order_frequency' => 0.8, 'price_modifier' => 1.25]
                ],
                'balanced' => [
                    'title' => 'Balanced Growth',
                    'description' => 'A steady path between cost and quality. Reliable and predictable.',
                    'effects' => 'No modifiers applied.',
                    'modifiers' => ['order_frequency' => 1.0, 'price_modifier' => 1.0]
                ]
            ]
        ],
        'energy_strategy' => [
            'id' => 'energy_strategy',
            'title' => 'Energy Strategy',
            'description' => 'Data centers consume massive power. How will you source yours?',
            'milestone_level' => 10,
            'options' => [
                'green' => [
                    'title' => '100% Renewable',
                    'description' => 'Invest in solar and wind offsets. Customers love it, but the grid premium is high.',
                    'effects' => 'Reputation boost (+5/tick), Power costs (+15%)',
                    'modifiers' => ['reputation_gain' => 1.5, 'power_cost_modifier' => 1.15]
                ],
                'standard' => [
                    'title' => 'Grid Standard',
                    'description' => 'Use the default mix provided by the municipal grid. It works and it is cheap.',
                    'effects' => 'Standard operating costs.',
                    'modifiers' => ['reputation_gain' => 1.0, 'power_cost_modifier' => 1.0]
                ]
            ]
        ],
        'business_model' => [
            'id' => 'business_model',
            'title' => 'Business Model',
            'description' => 'As you scale, how do you see the future of your company?',
            'milestone_level' => 20,
            'options' => [
                'managed' => [
                    'title' => 'Managed Solutions',
                    'description' => 'Offer hands-on support and specialized consulting. High touch, high price.',
                    'effects' => 'Profit per order (+15%), Difficulty in new orders (+10%)',
                    'modifiers' => ['price_modifier' => 1.15, 'order_frequency' => 0.9]
                ],
                'infrastructure' => [
                    'title' => 'Infrastructure Only',
                    'description' => 'Just provide the iron. No hand-holding. Focus on the data center.',
                    'effects' => 'Power/Maint costs (-10%), Reputation gain (-15%)',
                    'modifiers' => ['power_cost_modifier' => 0.9, 'reputation_gain' => 0.85]
                ]
            ]
        ],
        'data_leak' => [
            'id' => 'data_leak',
            'title' => 'PR Crisis: Major Data Leak',
            'description' => 'A security breach has exposed sensitive customer data. How will your company respond to the public outcry?',
            'milestone_level' => 999, // Triggered by event, not level
            'options' => [
                'deny' => [
                    'title' => 'Plausible Deniability',
                    'description' => 'Downplay the incident and claim it was a minor localized fault. Saves money but risks massive reputation loss if caught.',
                    'effects' => 'No immediate cost, 30% chance of -40 Reputation later.',
                    'modifiers' => ['reputation_penalty' => 40, 'cost' => 0, 'risk' => 0.3]
                ],
                'compensate' => [
                    'title' => 'Refund & Apologize',
                    'description' => 'Admit full fault and offer all affected customers one month of free service. Expensive, but preserves some trust.',
                    'effects' => 'Instant cost ($2.5k - $50k), Reputation -5 (mild).',
                    'modifiers' => ['reputation_penalty' => 5, 'cost' => 1.0, 'risk' => 0]
                ],
                'investigate' => [
                    'title' => 'Hire External Auditors',
                    'description' => 'Launch a transparent investigation and publish the findings. Shows integrity but takes time and moderate funds.',
                    'effects' => 'Reputation +10 (long term), Instant cost ($10k).',
                    'modifiers' => ['reputation_penalty' => -10, 'cost' => 10000, 'risk' => 0]
                ]
            ]
        ]
    ];

    /**
     * Check if player has reached a milestone and trigger pending decision
     */
    public function checkMilestones(PlayerEconomy $economy): void
    {
        $pending = $economy->pending_decisions ?? [];
        $policies = $economy->strategic_policies ?? [];

        foreach (self::DECISIONS as $decisionId => $definition) {
            // Already decided?
            if (isset($policies[$decisionId])) {
                continue;
            }

            // Already pending?
            $isAlreadyPending = false;
            foreach ($pending as $p) {
                if ($p['type'] === $decisionId) {
                    $isAlreadyPending = true;
                    break;
                }
            }
            if ($isAlreadyPending) {
                continue;
            }

            // check level
            if ($economy->level >= $definition['milestone_level']) {
                $pending[] = [
                    'type' => $decisionId,
                    'title' => $definition['title'],
                    'description' => $definition['description'],
                    'options' => $definition['options'],
                    'triggered_at_level' => $economy->level
                ];
            }
        }

        $economy->pending_decisions = $pending;
        $economy->save();
    }

    /**
     * Apply a decision
     */
    public function makeDecision(PlayerEconomy $economy, string $decisionType, string $optionKey): bool
    {
        if (!isset(self::DECISIONS[$decisionType])) {
            throw new Exception("Invalid decision type: $decisionType");
        }

        if (!isset(self::DECISIONS[$decisionType]['options'][$optionKey])) {
            throw new Exception("Invalid option: $optionKey");
        }

        $option = self::DECISIONS[$decisionType]['options'][$optionKey];
        $modifiers = $option['modifiers'] ?? [];

        // Apply immediate effects
        if ($decisionType === 'data_leak') {
            if ($optionKey === 'compensate') {
                $cost = max(5000, $economy->hourly_income * 2); 
                $economy->addTransaction('expense', $cost, 'Data Leak: Customer Compensation');
                $economy->balance -= $cost;
                $economy->reputation = max(0, $economy->reputation - 5);
            } elseif ($optionKey === 'investigate') {
                $economy->addTransaction('expense', 10000, 'Security Audit: External Auditors');
                $economy->balance -= 10000;
                $economy->reputation = min(100, $economy->reputation + 10);
            } elseif ($optionKey === 'deny') {
                // Probabilistic penalty check
                if ((rand(1, 100) / 100) < ($modifiers['risk'] ?? 0)) {
                    $penalty = $modifiers['reputation_penalty'] ?? 40;
                    $economy->reputation = max(0, $economy->reputation - $penalty);
                    \App\Models\GameLog::log($economy->user, "SCANDAL: Your denial was debunked by tech journalists! Reputation tanked.", 'danger', 'security');
                }
            }
        }

        $policies = $economy->strategic_policies ?? [];
        $policies[$decisionType] = $optionKey;
        $economy->strategic_policies = $policies;

        // Remove from pending
        $pending = $economy->pending_decisions ?? [];
        $newPending = array_filter($pending, fn($p) => $p['type'] !== $decisionType);
        $economy->pending_decisions = array_values($newPending);

        $economy->save();

        if ($decisionType === 'market_focus' && $optionKey === 'budget') {
            app(\App\Services\Market\CompetitorAIService::class)->reactToPlayerAction($economy->user, 'price_cut');
        }

        return true;
    }

    /**
     * Force trigger a specific decision
     */
    public function triggerDecision(PlayerEconomy $economy, string $decisionId): void
    {
        if (!isset(self::DECISIONS[$decisionId])) return;

        $definition = self::DECISIONS[$decisionId];
        $pending = $economy->pending_decisions ?? [];

        // Already pending?
        foreach ($pending as $p) {
            if ($p['type'] === $decisionId) return;
        }

        $pending[] = [
            'type' => $decisionId,
            'title' => $definition['title'],
            'description' => $definition['description'],
            'options' => $definition['options'],
            'triggered_at_level' => $economy->level
        ];

        $economy->pending_decisions = $pending;
        $economy->save();
    }
}
