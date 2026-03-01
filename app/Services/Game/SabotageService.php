<?php

namespace App\Services\Game;

use App\Models\Sabotage;
use App\Models\User;
use App\Models\Competitor;
use App\Models\GameLog;
use Illuminate\Support\Facades\DB;
use App\Services\Game\PlayerSkillService;

class SabotageService
{
    public const TYPES = [
        'ddos' => [
            'name' => 'DDoS Attack',
            'category' => 'network',
            'cost' => 15000,
            'base_chance' => 75,
            'detection_chance' => 15,
            'description' => 'Disrupt competitor services. Reduces their market share slightly and increases latency.',
        ],
        'data_theft' => [
            'name' => 'Corporate Espionage',
            'category' => 'intelligence',
            'cost' => 35000,
            'base_chance' => 55,
            'detection_chance' => 20,
            'description' => 'Steal proprietary data. Reveals hidden competitor stats and strategy insights.',
        ],
        'poaching' => [
            'name' => 'Poach Talent',
            'category' => 'social',
            'cost' => 60000,
            'base_chance' => 45,
            'detection_chance' => 30,
            'description' => 'Attempt to hire a key employee. Temporarily increases competitor decision cooldown.',
        ],
        'slander' => [
            'name' => 'Slander Campaign',
            'category' => 'social',
            'cost' => 45000,
            'base_chance' => 70,
            'detection_chance' => 25,
            'description' => 'Leak fake news about competitor security. Damages reputation and slows customer acquisition.',
        ],
        'patent_countersuit' => [
            'name' => 'Patent Countersuit',
            'category' => 'legal',
            'cost' => 85000,
            'base_chance' => 60,
            'detection_chance' => 10,
            'description' => 'Force a legal settlement. Requires high enmity. Major financial gain on success.',
        ],
        'power_cut' => [
            'name' => 'Grid Sabotage',
            'category' => 'infrastructure',
            'cost' => 125000,
            'base_chance' => 35,
            'detection_chance' => 55,
            'description' => 'Complete blackout of rival datacenter. Massive market share hit, very high detection risk.',
        ],
        'mole_infiltration' => [
            'name' => 'Infiltrate Mole',
            'category' => 'espionage',
            'cost' => 250000,
            'base_chance' => 25,
            'detection_chance' => 45,
            'description' => 'FEATURE 261: Plant an operative to subtly misconfigure cooling or delete backups over time.',
        ]
    ];

    public function __construct(
        protected PlayerSkillService $skillService,
        protected EmployeeService $employeeService
    ) {}

    public function getAvailableSabotages(User $user): array
    {
        return self::TYPES;
    }

    public function attemptSabotage(User $user, string $targetId, string $targetType, string $sabotageType): array
    {
        $config = self::TYPES[$sabotageType] ?? null;
        if (!$config) {
            throw new \Exception("Invalid sabotage type");
        }

        // --- REQUIREMENTS CHECK ---
        if ($sabotageType === 'patent_countersuit' && $targetType === 'competitor') {
            $comp = Competitor::find($targetId);
            if ($comp && $comp->player_enmity < 50) {
                throw new \Exception("Legal grounds insufficient. Requires at least 50 ENMITY to countersuit.");
            }
        }

        if ($user->economy->balance < $config['cost']) {
            throw new \Exception("Insufficient funds. Operation requires $" . number_format($config['cost']));
        }
        
        $user->economy->balance -= $config['cost'];
        $user->economy->save();
        // ... (rest of logic remains same, applying effects below) ...
        return $this->processAttempt($user, $targetId, $targetType, $sabotageType, $config);
    }

    protected function processAttempt(User $user, string $targetId, string $targetType, string $sabotageType, array $config): array
    {
        $offenseSkill = $this->skillService->getBonus($user, 'covert_ops') * 100;
        $successChance = min(95, max(5, $config['base_chance'] + $offenseSkill));

        $roll = rand(1, 100);
        $isSuccess = $roll <= $successChance;

        $stealthSkill = $this->skillService->getBonus($user, 'stealth') * 100;
        $detectionChance = max(5, $config['detection_chance'] - $stealthSkill);

        if ($targetType === 'user') {
            $targetUser = User::find($targetId);
            if ($targetUser) {
                $securityEngineers = \App\Models\Employee::where('user_id', $targetUser->id)
                    ->where('type', 'security_engineer')->get();
                $counterIntelBonus = 0;
                foreach ($securityEngineers as $eng) {
                    if ($this->employeeService->hasPerk($eng, 'counter_intelligence')) $counterIntelBonus += 20;
                }
                $detectionChance = min(100, $detectionChance + $counterIntelBonus);
            }
        }

        $isDetected = rand(1, 100) <= $detectionChance;

        $sabotage = new Sabotage([
            'user_id' => $user->id,
            'type' => $sabotageType,
            'cost' => $config['cost'],
            'detected' => $isDetected,
            'status' => $isSuccess ? 'success' : 'failed',
            'resolved_at' => now(),
        ]);

        if ($targetType === 'competitor') {
            $sabotage->target_competitor_id = $targetId;
            $targetModel = Competitor::find($targetId);
            $targetName = $targetModel?->name ?? 'Competitor';
        } else {
            $sabotage->target_user_id = $targetId;
            $targetModel = User::find($targetId);
            $targetName = $targetModel?->company_name ?? 'Rival';
        }

        $resultData = [];
        if ($isSuccess) {
            $resultData = $this->applySabotageEffects($user, $targetId, $targetType, $sabotageType);
            GameLog::log($user, "Retaliation success: {$config['name']} against {$targetName}", 'success', 'security');
            
            // Success reduces Enmity slightly (intimidation)
            if ($targetType === 'competitor' && $targetModel) {
                $targetModel->player_enmity = max(0, $targetModel->player_enmity - 5);
                $targetModel->save();
            }
        } else {
            $resultData = ['message' => 'The operation failed. Resistance was too high.'];
            GameLog::log($user, "Retaliation failed: {$config['name']} against {$targetName}", 'warning', 'security');
            
            // Failure INCREASES enmity
            if ($targetType === 'competitor' && $targetModel) {
                $targetModel->player_enmity = min(100, $targetModel->player_enmity + 10);
                $targetModel->save();
            }
        }

        if ($isDetected) {
            $user->economy->reputation -= 15;
            $user->economy->save();
            $resultData['detection_penalty'] = 'TRACED! Your signal was triangulated. Reputation -15.';
            GameLog::log($user, "EXPOSURE ALERT: Your covert operations were traced to your HQ!", 'error', 'security');
        }

        $sabotage->result = $resultData;
        $sabotage->save();

        return ['success' => $isSuccess,'detected' => $isDetected,'result' => $resultData];
    }

    protected function applySabotageEffects(User $user, string $targetId, string $targetType, string $type): array
    {
        if ($targetType === 'competitor') {
            $competitor = Competitor::find($targetId);
            if (!$competitor) return ['error' => 'Target not found'];

            switch ($type) {
                case 'ddos':
                    $loss = rand(20, 80) / 100;
                    $competitor->market_share = max(0, $competitor->market_share - $loss);
                    $competitor->latency_score = min(1000, $competitor->latency_score + 150);
                    $competitor->save();
                    return ['damage' => "DDoS successful. Market share -{$loss}%. Latency spiked."];
                
                case 'data_theft':
                    return [
                        'intel' => [
                            'strategy' => $competitor->pricing_strategy,
                            'innovation' => $competitor->innovation_index,
                            'aggression' => $competitor->aggression,
                            'enmity' => $competitor->player_enmity
                        ],
                        'message' => 'Critical data exfiltrated.'
                    ];

                case 'poaching':
                    $competitor->decision_cooldown += 20; // Stun them
                    $competitor->save();
                    return ['message' => 'Key personnel defected. Competitor reaction time slowed.'];

                case 'slander':
                    $repLoss = rand(5, 12);
                    $shareLoss = rand(30, 100) / 100;
                    $competitor->reputation = max(0, $competitor->reputation - $repLoss);
                    $competitor->market_share = max(0, $competitor->market_share - $shareLoss);
                    $competitor->save();
                    return ['damage' => "Slander success. Reputation -{$repLoss}, Market share -{$shareLoss}%"];

                case 'patent_countersuit':
                    $payout = rand(80000, 120000);
                    $user->economy->balance += $payout;
                    $user->economy->save();
                    $competitor->assets_value = max(0, $competitor->assets_value - $payout);
                    $competitor->save();
                    return ['message' => "Settlement reached. Regained $" . number_format($payout) . " from competitor assets."];

                case 'power_cut':
                    $loss = rand(200, 500) / 100;
                    $competitor->market_share = max(0, $competitor->market_share - $loss);
                    $competitor->reputation -= 15;
                    $competitor->uptime_score = max(0, $competitor->uptime_score - 0.2);
                    $competitor->save();
                    return ['damage' => "GRID SABOTAGE: Rival offline. Market share -{$loss}%. Reputation tanked."];

                case 'mole_infiltration':
                    // Plants a hidden flag that deals damage over time in the GameLoop
                    $competitor->has_mole = true;
                    $competitor->save();
                    return ['damage' => "MOLE INFILTRATION: Operative successfully planted. Passive damage incoming..."];
            }
        }
        return ['message' => 'Effect applied'];
    }
}
