<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\GameConfig;

class PlayerSkillService
{
    public const SKILLS = [
        'hardware' => [
            'label' => 'Energy & Cooling',
            'skills' => [
                'cooling_mastery' => [
                    'name' => 'Precision Cooling',
                    'description' => 'Fine-tune your airflow. Increases cooling efficiency by 15%.',
                    'cost' => 1,
                    'bonuses' => ['cooling_efficiency' => 0.15]
                ],
                'power_saving' => [
                    'name' => 'Green Thumb',
                    'description' => 'Optimize BIOS settings for power saving. Reduces server power draw by 10%.',
                    'cost' => 1,
                    'bonuses' => ['power_draw' => -0.10]
                ],
                'overclocking' => [
                    'name' => 'Overclocking Veteran',
                    'description' => 'Pushes hardware to the limit. +20% CPU capacity, but +25% heat output.',
                    'cost' => 2,
                    'bonuses' => ['cpu_capacity' => 0.20, 'heat_penalty' => 0.25]
                ]
            ]
        ],
        'economy' => [
            'label' => 'Business & Taxes',
            'skills' => [
                'negotiator' => [
                    'name' => 'Expert Negotiator',
                    'description' => 'Get better deals from customers. Increases all revenue by 5%.',
                    'cost' => 1,
                    'bonuses' => ['revenue_bonus' => 0.05]
                ],
                'tax_optimization' => [
                    'name' => 'Tax Optimization',
                    'description' => 'International shell companies... Reduces regional taxes by 25%.',
                    'cost' => 2,
                    'bonuses' => ['tax_reduction' => 0.25]
                ],
                'wholesale' => [
                    'name' => 'Bulk Purchasing',
                    'description' => 'Direct relationships with vendors. Reduces hardware purchase costs by 15%.',
                    'cost' => 2,
                    'bonuses' => ['purchase_discount' => 0.15]
                ]
            ]
        ],
        'ops' => [
            'label' => 'Operations & Incidents',
            'skills' => [
                'emergency_response' => [
                    'name' => 'Fast Responder',
                    'description' => 'Your team stays cool under pressure. Increases event deadlines by 60 seconds.',
                    'cost' => 1,
                    'bonuses' => ['deadline_bonus_s' => 60]
                ],
                'ddos_prevention' => [
                    'name' => 'Edge Shield',
                    'description' => 'Better ingress filtering. Reduces probability of DDoS attacks by 30%.',
                    'cost' => 2,
                    'bonuses' => ['ddos_risk_reduction' => 0.30]
                ],
                'backup_architect' => [
                    'name' => 'Backup Architect',
                    'description' => 'High-redundancy storage setups. Backups are 20% more reliable.',
                    'cost' => 2,
                    'bonuses' => ['backup_reliability' => 0.20]
                ]
            ]
        ],
        'security' => [
            'label' => 'Cyber Security',
            'skills' => [
                'netsec_training' => [
                    'name' => 'NetSec Awareness',
                    'description' => 'Staff training programs. +20 defense against social engineering.',
                    'cost' => 1,
                    'bonuses' => ['security_defense' => 0.20]
                ],
                'pen_testing' => [
                    'name' => 'Penetration Testing',
                    'description' => 'Proactive vulnerability scanning. +15% sabotage success chance.',
                    'cost' => 2,
                    'bonuses' => ['covert_ops' => 0.15]
                ],
                'dark_web_monitoring' => [
                    'name' => 'Dark Web Monitoring',
                    'description' => 'Monitor chatter. +30% stealth rating to avoid detection.',
                    'cost' => 2,
                    'bonuses' => ['stealth' => 0.30]
                ]
            ]
        ]
    ];

    public function getTree(): array
    {
        return self::SKILLS;
    }

    public function unlockSkill(User $user, string $skillId): bool
    {
        $economy = $user->economy;
        $unlocked = $economy->unlocked_skills ?? [];

        if (in_array($skillId, $unlocked)) {
            throw new \Exception("Skill already unlocked.");
        }

        // Find skill in tree
        $skillData = null;
        foreach (self::SKILLS as $category) {
            if (isset($category['skills'][$skillId])) {
                $skillData = $category['skills'][$skillId];
                break;
            }
        }

        if (!$skillData) {
            throw new \Exception("Invalid skill ID.");
        }

        if ($economy->skill_points < $skillData['cost']) {
            throw new \Exception("Insufficient skill points.");
        }

        $economy->skill_points -= $skillData['cost'];
        $unlocked[] = $skillId;
        $economy->unlocked_skills = $unlocked;
        $economy->save();

        \App\Models\GameLog::log($user, "Specialization Unlocked: {$skillData['name']}", 'success', 'management');

        return true;
    }

    public function getBonus(User $user, string $bonusKey): float
    {
        $economy = $user->economy;
        $unlocked = $economy->unlocked_skills ?? [];
        $totalBonus = 0;

        foreach ($unlocked as $skillId) {
            foreach (self::SKILLS as $category) {
                if (isset($category['skills'][$skillId])) {
                    $skill = $category['skills'][$skillId];
                    if (isset($skill['bonuses'][$bonusKey])) {
                        $totalBonus += $skill['bonuses'][$bonusKey];
                    }
                }
            }
        }

        return $totalBonus;
    }

    public function hasSkill(User $user, string $skillId): bool
    {
        $unlocked = $user->economy->unlocked_skills ?? [];
        return in_array($skillId, $unlocked);
    }
}
