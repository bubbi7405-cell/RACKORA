<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\GlobalCrisis;
use App\Models\GameLog;
use Carbon\Carbon;

class GlobalCrisisService
{
    private const TYPES = [
        'solar_flare' => [
            'name' => 'Solar Superstorm',
            'warning_minutes' => 5,
            'impact_minutes' => 10,
            'severity' => 4,
            'description' => 'A massive coronal mass ejection is heading towards Earth. Expect severe electrical interference.',
            'effect_desc' => 'Active servers take damage. Power grid unstable.',
        ],
        'fiber_cut' => [
            'name' => 'Trans-Atlantic Cable Cut',
            'warning_minutes' => 1,
            'impact_minutes' => 20,
            'severity' => 3,
            'description' => 'Reports of a major undersea cable severance. Global routing tables are collapsing.',
            'effect_desc' => 'Latency spikes. Bandwidth reduced by 50%.',
        ],
        'energy_crisis' => [
            'name' => 'Global Energy Crisis',
            'warning_minutes' => 3,
            'impact_minutes' => 45,
            'severity' => 4,
            'description' => 'Geopolitical tensions have caused primary fuel source prices to skyrocket.',
            'effect_desc' => 'Power costs +300%. Grid stability issues.',
        ],
        'hardware_shortage' => [
            'name' => 'Silicon Supply Shortage',
            'warning_minutes' => 10,
            'impact_minutes' => 90,
            'severity' => 2,
            'description' => 'A fire at a major semiconductor fab has halted production worldwide.',
            'effect_desc' => 'Server & Hardware prices +100%. R&D speed reduced.',
        ],
        'market_crash' => [
            'name' => 'Tech Market Crash',
            'warning_minutes' => 2,
            'impact_minutes' => 60,
            'severity' => 3,
            'description' => 'Global tech stocks are plummeting due to antitrust legislation.',
            'effect_desc' => 'Contract payouts -30%. Customer satisfaction drains faster.',
        ],
        'crypto_ransom' => [
            'name' => 'Ransomware Attack',
            'warning_minutes' => 1,
            'impact_minutes' => 30,
            'severity' => 5,
            'description' => 'A sophisticated ransomware group has encrypted your management panel.',
            'effect_desc' => 'Revenue stream frozen. Reputation bleeding.',
        ],
        'backup_crisis' => [
            'name' => 'The Great Backup Crisis',
            'warning_minutes' => 5,
            'impact_minutes' => 60,
            'severity' => 5,
            'description' => 'A polymorphic virus is silently corrupting digital backup snapshots worldwide.',
            'effect_desc' => 'Servers drop backup health rapidly. Tape Archives (F88) are immune.',
        ],
        'power_rationing' => [
            'name' => 'Grid Power Rationing',
            'warning_minutes' => 3,
            'impact_minutes' => 35,
            'severity' => 4,
            'description' => 'Extreme seasonal load has forced the regional grid operator to implement mandatory power rationing.',
            'effect_desc' => 'Infrastructure power draw is capped at 50%. Exceeding the quota results in massive fines and grid instability.',
        ],
        'employee_strike' => [
            'name' => 'General Employee Strike',
            'warning_minutes' => 5,
            'impact_minutes' => 60,
            'severity' => 5,
            'description' => 'Unsatisfied with working conditions and pay, your workforce has organized a general strike.',
            'effect_desc' => 'Operations halted! Research speed -90%, Auto-maintenance disabled. Severe reputation bleed.',
        ],
    ];

    public function __construct(
        protected \App\Services\Game\GameEventService $eventService,
        protected \App\Services\Game\CyberInsuranceService $cyberInsurance
    ) {}

    public function tick(User $user): void
    {
        $activeCrisis = GlobalCrisis::where('user_id', $user->id)
            ->whereNull('resolved_at')
            ->first();

        if ($activeCrisis) {
            $this->processActiveCrisis($user, $activeCrisis);
        } else {
            // Random chance to start a crisis if no active events?
            // Very rare. Maybe 1/1000 chance per tick.
            // Only for Level > 10.
            if ($user->economy->level >= 10 && rand(1, 2000) === 1) {
                // Pick a random type
                $types = array_keys(self::TYPES);
                $this->triggerCrisis($user, $types[array_rand($types)]);
            }
        }
    }

    public function triggerCrisis(User $user, string $type): GlobalCrisis
    {
        $config = self::TYPES[$type];
        
        $crisis = new GlobalCrisis();
        $crisis->user_id = $user->id;
        $crisis->type = $type;
        $crisis->phase = 'warning';
        $crisis->started_at = now();
        $crisis->impact_starts_at = now()->addMinutes($config['warning_minutes']);
        $crisis->severity = $config['severity'];
        $crisis->data = [
            'name' => $config['name'],
            'actions_taken' => [],
            'damage_events' => 0
        ];
        $crisis->save();

        // Log it
        GameLog::log($user, "⚠️ GLOBAL ALERT: {$config['name']} detected! Warning phase initiated.", 'warning', 'crisis');
        
        // Notify user via Event Service (or direct notification)
        // ideally notify frontend via WebSocket
        
        return $crisis;
    }

    public function takeAction(User $user, string $action): void
    {
        $crisis = GlobalCrisis::where('user_id', $user->id)
            ->whereNull('resolved_at')
            ->first();

        if (!$crisis) {
            throw new \Exception("No active crisis to act upon.");
        }

        $data = $crisis->data;
        if (in_array($action, $data['actions_taken'] ?? [])) {
            throw new \Exception("Action already taken.");
        }

        // Action costs and logic
        switch ($action) {
            case 'activate_shield':
                if ($crisis->type !== 'solar_flare') throw new \Exception("Invalid action for this crisis.");
                $cost = $this->cyberInsurance->applyCoverage($user, 5000, $crisis->type); // F91
                if (!$user->economy->canAfford($cost)) throw new \Exception("Insufficient funds.");
                $user->economy->debit($cost, "Crisis Action: Emergency Shielding", 'crisis');
                GameLog::log($user, "🛡️ Shields activated! Hardware damage risk significantly reduced.", 'success', 'crisis');
                break;

            case 'emergency_shutdown':
                if ($crisis->type !== 'solar_flare') throw new \Exception("Invalid action for this crisis.");
                // Shut down all servers
                $user->servers()->update(['status' => \App\Enums\ServerStatus::OFFLINE]);
                GameLog::log($user, "🔌 EMERGENCY SHUTDOWN: All servers powered down to prevent EMP damage.", 'critical', 'crisis');
                break;

            case 'reroute_traffic':
                if ($crisis->type !== 'fiber_cut') throw new \Exception("Invalid action for this crisis.");
                $cost = $this->cyberInsurance->applyCoverage($user, 2000, $crisis->type); // F91
                if (!$user->economy->canAfford($cost)) throw new \Exception("Insufficient funds.");
                $user->economy->debit($cost, "Crisis Action: Traffic Reroute", 'crisis');
                GameLog::log($user, "📡 Network traffic rerouted through satellite links. Latency improved.", 'success', 'crisis');
                break;

            case 'pay_ransom':
                if ($crisis->type !== 'crypto_ransom') throw new \Exception("Invalid action.");
                $baseCost = 1000 * $user->economy->level;
                $cost = $this->cyberInsurance->applyCoverage($user, $baseCost, $crisis->type); // F91
                if (!$user->economy->canAfford($cost)) throw new \Exception("Insufficient funds.");
                $user->economy->debit($cost, "Crisis Action: Ransom Paid", 'crisis');
                GameLog::log($user, "💸 Ransom paid to the attackers. Encryption keys received.", 'success', 'crisis');
                $this->resolveCrisis($crisis); // Instant resolution
                return; // Don't save $crisis below, resolveCrisis already did

            case 'restore_backups':
                if ($crisis->type !== 'crypto_ransom') throw new \Exception("Invalid action.");
                $cost = 5000;
                if (!$user->economy->canAfford($cost)) throw new \Exception("Insufficient funds.");
                $user->economy->debit($cost, "Crisis Action: Bare-Metal Restore", 'crisis');
                
                $researchService = app(\App\Services\Game\ResearchService::class);
                $isFast = $researchService->hasResearch($user, 'auto_recovery');
                $hasAdvancedEncryption = $researchService->hasResearch($user, 'advanced_encryption');
                
                $successChance = $isFast ? 95 : 70;
                if ($hasAdvancedEncryption) $successChance += 15;
                if ($successChance > 100) $successChance = 100;

                if (rand(1, 100) <= $successChance) {
                    GameLog::log($user, "📀 Backup restoration successful. System control reclaimed.", 'success', 'crisis');
                    $this->resolveCrisis($crisis);
                    return;
                } else {
                    GameLog::log($user, "❌ Backup restoration failed! Files corrupted. Ransomware persistent.", 'critical', 'crisis');
                }
                break;

            case 'cooling_overdrive':
                // Can be used in any crisis to save hardware from heat
                $cost = 1000;
                if (!$user->economy->canAfford($cost)) throw new \Exception("Insufficient funds.");
                $user->economy->debit($cost, "Crisis Action: Cooling Overdrive", 'crisis');
                GameLog::log($user, "❄️ cooling systems set to 150% capacity. Power draw significantly increased.", 'success', 'crisis');
                break;

            case 'lobby_subsidies':
                if (!in_array($crisis->type, ['energy_crisis', 'market_crash'])) throw new \Exception("Invalid action.");
                $cost = 5000;
                if (!$user->economy->canAfford($cost)) throw new \Exception("Insufficient funds.");
                $user->economy->debit($cost, "Crisis Action: Government Lobbying", 'crisis');
                GameLog::log($user, "🏛️ Lobbying successful. Subsidies approved to offset market volatility.", 'success', 'crisis');
                break;

            case 'bulk_hardware_contract':
                if ($crisis->type !== 'hardware_shortage') throw new \Exception("Invalid action.");
                $cost = 8000;
                if (!$user->economy->canAfford($cost)) throw new \Exception("Insufficient funds.");
                $user->economy->debit($cost, "Crisis Action: Bulk Supply Agreement", 'crisis');
                GameLog::log($user, "📦 Supply contract signed. Hardware delivery prioritised at fixed rates.", 'success', 'crisis');
                break;

            case 'hacker_counterstrike':
                if ($crisis->type !== 'crypto_ransom') throw new \Exception("Invalid action.");
                $cost = 10000;
                if (!$user->economy->canAfford($cost)) throw new \Exception("Insufficient funds.");
                $user->economy->debit($cost, "Crisis Action: Hacker Counter-Strike", 'crisis');
                
                if (rand(1, 100) <= 50) {
                    GameLog::log($user, "⚡ Counter-strike successful! The attackers' infrastructure has been neutralized.", 'success', 'crisis');
                    $this->resolveCrisis($crisis);
                    return;
                } else {
                    GameLog::log($user, "🔥 Counter-strike failed! Attackers retaliated by leaking sensitive data.", 'critical', 'crisis');
                    $data['retaliation_active'] = true;
                }
                break;

                GameLog::log($user, "📼 Emergency Tape Migration complete. Digital snapshots air-gapped to offline magnetic storage.", 'success', 'crisis');
                break;
            
            case 'emergency_shedding':
                if ($crisis->type !== 'power_rationing') throw new \Exception("Invalid action.");
                $cost = 5000;
                if (!$user->economy->canAfford($cost)) throw new \Exception("Insufficient funds.");
                $user->economy->debit($cost, "Crisis Action: Emergency Load Shedding", 'crisis');
                
                // Shut down servers until we are under 50% capacity
                $totalCap = $user->racks()->sum('max_power_kw');
                $limit = $totalCap * 0.5;
                
                $onlineServers = $user->servers()
                    ->where('status', \App\Enums\ServerStatus::ONLINE)
                    ->orderBy('hourly_payout', 'asc') // Kill least profitable first
                    ->orderBy('tier', 'asc')
                    ->get();
                
                $shutdownCount = 0;
                $currentUsage = $user->servers()->where('status', \App\Enums\ServerStatus::ONLINE)->sum('power_draw_kw');
                
                foreach ($onlineServers as $server) {
                    if ($currentUsage <= $limit) break;
                    $server->status = \App\Enums\ServerStatus::OFFLINE;
                    $server->save();
                    $currentUsage -= $server->power_draw_kw;
                    $shutdownCount++;
                }

                GameLog::log($user, "🔌 LOAD SHEDDING: Auto-shutdown of {$shutdownCount} low-priority servers to meet grid quota.", 'warning', 'crisis');
                break;

            case 'negotiate_strike':
                if ($crisis->type !== 'employee_strike') throw new \Exception("Invalid action.");
                // This is a complex action that will be handled via a dedicated minigame/dialogue flow
                // For the backend, we just track that they've started negotiating
                break;

            default:
                throw new \Exception("Unknown crisis action.");
        }

        $data['actions_taken'][] = $action;
        $crisis->data = $data;
        $crisis->save();
    }

    private function processActiveCrisis(User $user, GlobalCrisis $crisis): void
    {
        $now = now();

        // Phase Transition: Warning -> Impact
        if ($crisis->phase === 'warning' && $now->gte($crisis->impact_starts_at)) {
            $crisis->phase = 'impact';
            $crisis->save();
            
            GameLog::log($user, "🚨 CRISIS IMPACT: {$crisis->type} has actively hit the infrastructure!", 'critical', 'crisis');
            
            // Initial Impact Calculation if needed
        }

        // Phase Transition: Impact -> Resolved (Time based)
        // Some crises end automatically, others need manual resolution.
        // For MVP, time-based is fine.
        $config = self::TYPES[$crisis->type];
        $impactDuration = $config['impact_minutes'];

        // Specialization V2: Cryptographer (Crisis Duration Reduction for Ransomware)
        if ($crisis->type === 'crypto_ransom') {
            $empService = app(EmployeeService::class);
            $reduction = $empService->getAggregatedBonus($user, 'crisis_duration_reduction');
            if ($reduction > 0) {
                $impactDuration *= (1.0 - min(0.9, $reduction));
            }
        }

        $impactEnd = $crisis->impact_starts_at->copy()->addSeconds($impactDuration * 60);

        if ($crisis->phase === 'impact' && $now->gte($impactEnd)) {
            $this->resolveCrisis($crisis);
            return;
        }

        // Process Effects during Impact
        if ($crisis->phase === 'impact') {
            $this->applyCrisisEffects($user, $crisis);
        }
    }

    private function applyCrisisEffects(User $user, GlobalCrisis $crisis): void
    {
        switch ($crisis->type) {
            case 'solar_flare':
                $this->applySolarFlareEffect($user, $crisis);
                break;
            case 'backup_crisis':
                $this->applyBackupCrisisEffect($user, $crisis);
                break;
            case 'power_rationing':
                $this->applyPowerRationingEffect($user, $crisis);
                break;
            case 'employee_strike':
                $this->applyEmployeeStrikeEffect($user, $crisis);
                break;
            // Other types handled elsewhere via global modifiers mostly
        }
    }

    private function applySolarFlareEffect(User $user, GlobalCrisis $crisis): void
    {
        $data = $crisis->data;
        $actions = $data['actions_taken'] ?? [];
        
        // If emergency shutdown was taken, no damage
        if (in_array('emergency_shutdown', $actions)) return;

        $researchService = app(\App\Services\Game\ResearchService::class);
        $hasHardening = $researchService->hasResearch($user, 'emp_hardening');
        $hasShield = in_array('activate_shield', $actions);

        $damageChance = 5; // Base 5%
        if ($hasShield) $damageChance -= 4; // Shield reduces it to 1%
        if ($hasHardening) $damageChance /= 2; // Hardening halves whatever is left
        
        $servers = $user->servers()->where('status', \App\Enums\ServerStatus::ONLINE)->get();
        
        $damageCount = 0;
        foreach ($servers as $server) {
            if (rand(1, 1000) <= ($damageChance * 10)) {
                $damage = 10;
                if ($hasHardening) $damage = 5;
                
                $server->health -= $damage;
                if ($server->health <= 0) {
                    $server->status = \App\Enums\ServerStatus::FAILED;
                    $server->health = 0;
                    // Trigger explosion event?
                }
                $server->save();
                $damageCount++;
            }
        }
        
        // Update data
        $data['damage_events'] = ($data['damage_events'] ?? 0) + $damageCount;
        $crisis->data = $data;
        $crisis->save();
    }

    private function applyBackupCrisisEffect(User $user, GlobalCrisis $crisis): void
    {
        // Corrupt digital backups during impact
        $servers = $user->servers()->where('backup_plan', '!=', 'none')->get();
        
        $damageTaken = 0;
        foreach ($servers as $server) {
            if ($server->backup_plan === \App\Enums\BackupPlan::TAPE) {
                // Tape is physically isolated and immune
                continue;
            }
            
            if ($server->backup_health > 0) {
                // Standard digital backups get corrupted rapidly
                $drop = rand(2, 6); 
                $server->backup_health = max(0, $server->backup_health - $drop);
                $server->save();
                $damageTaken++;
            }
        }
        
        if ($damageTaken > 0 && rand(1, 150) <= 5) { // Occasional log
            GameLog::log($user, "⚠️ VIRUS DETECTED: 'The Great Backup Crisis' is actively destroying digital snapshots! Physical Tape Archives are strongly recommended.", 'critical', 'crisis');
        }
    }

    private function applyPowerRationingEffect(User $user, GlobalCrisis $crisis): void
    {
        $totalCap = (float) $user->racks()->sum('max_power_kw');
        $limit = $totalCap * 0.5;
        $currentUsage = (float) $user->servers()->where('status', \App\Enums\ServerStatus::ONLINE)->sum('power_draw_kw');

        if ($currentUsage > $limit) {
            $excess = $currentUsage - $limit;
            $fine = $excess * 50; // $50 fine per excess kW per tick
            $fine = $this->cyberInsurance->applyCoverage($user, $fine, 'power_rationing'); // F91
            
            $economy = $user->economy;
            $economy->debit($fine, "Grid Overload Penalty ($excess kW excess)", 'utility');
            
            // Grid Instability: Chance to pop a rack
            if (rand(1, 100) <= 10) {
                $rack = $user->racks()->inRandomOrder()->first();
                if ($rack) {
                    $rack->status = 'failed'; 
                    $rack->save();
                    GameLog::log($user, "💥 GRID INSTABILITY: A power surge has tripped breakers on rack '{$rack->name}'. Rack OFFLINE.", 'danger', 'infrastructure');
                }
            }
            
            if (rand(1, 100) <= 5) {
                GameLog::log($user, "⚠️ QUOTA EXCEEDED: You are drawing " . round($currentUsage, 2) . "kW (Limit: " . round($limit, 2) . "kW). Grid fines applied!", 'critical', 'energy');
            }
        }
    }

    private function applyEmployeeStrikeEffect(User $user, GlobalCrisis $crisis): void
    {
        // Reputation bleed
        $economy = $user->economy;
        $economy->reputation = max(0, $economy->reputation - 0.1);
        $economy->save();

        if (rand(1, 100) <= 5) {
            GameLog::log($user, "🪧 STRIKE ACTIVE: Picket lines are blocking access to the data center. Staff efficiency is near zero.", 'critical', 'hr');
        }
    }

    public function resolveCrisis(GlobalCrisis $crisis): void
    {
        $crisis->resolved_at = now();
        $crisis->phase = 'resolved';
        $crisis->save();
        
        // Log
        $user = $crisis->user; // Helper I added
        GameLog::log($user, "✅ CRISIS ENDED: The {$crisis->type} has passed.", 'success', 'crisis');
    }

    public function skipCrisis(User $user): void
    {
        $crisis = GlobalCrisis::where('user_id', $user->id)
            ->whereNull('resolved_at')
            ->first();

        if ($crisis) {
            $crisis->resolved_at = now();
            $crisis->phase = 'resolved';
            $crisis->save();
            
            GameLog::log($user, "🎉 MANUAL OVERRIDE SUCCESS: The {$crisis->type} has been bypassed via manual intervention!", 'success', 'crisis');
        }
    }

    /**
     * Get active modifiers for other services to consume
     */
    public function getActiveModifiers(User $user): array
    {
        $crisis = GlobalCrisis::where('user_id', $user->id)
            ->where('phase', 'impact')
            ->whereNull('resolved_at')
            ->first();

        if (!$crisis) return [];

        $actions = $crisis->data['actions_taken'] ?? [];
        $researchService = app(\App\Services\Game\ResearchService::class);
        $mods = [];

        // Global Action Modifiers
        $powerBaseMod = 1.0;
        $coolingBaseEff = 1.0;
        
        if (in_array('cooling_overdrive', $actions)) {
            $powerBaseMod += 1.0;
            $coolingBaseEff += 0.5;
        }

        switch ($crisis->type) {
            case 'solar_flare':
                $powerMod = 1.5;
                if (in_array('activate_shield', $actions)) $powerMod += 0.5; // Shielding uses even more power
                $mods = ['power_cost' => $powerMod];
                break;

            case 'fiber_cut':
                $latency = 200;
                $bandwidthCost = 2.0;
                
                if (in_array('reroute_traffic', $actions)) {
                    $latency -= 150; // Reroute helps latency
                    $bandwidthCost += 1.0; // But costs more
                }
                
                if ($researchService->hasResearch($user, 'redundant_backbone')) {
                    $latency /= 2;
                }

                $mods = ['latency' => $latency, 'bandwidth_cost' => $bandwidthCost];
                break;

            case 'market_crash':
                $payout = in_array('lobby_subsidies', $actions) ? 0.85 : 0.7;
                if ($researchService->hasResearch($user, 'market_hedging')) $payout += 0.15;
                $mods = ['contract_payout' => $payout];
                break;

            case 'crypto_ransom':
                $repPenalty = 0.1;
                if (!empty($crisis->data['retaliation_active'])) $repPenalty *= 3;
                $mods = [
                    'contract_payout' => 0.0, // Revenue frozen
                    'satisfaction_drain' => empty($crisis->data['retaliation_active']) ? 0.05 : 0.15,
                    'reputation_penalty' => $repPenalty
                ];
                break;

            case 'energy_crisis':
                $powerCost = in_array('lobby_subsidies', $actions) ? 2.5 : 4.0;
                $mods = [
                    'power_cost' => $powerCost, 
                    'cooling_efficiency' => 0.8
                ];
                break;

            case 'hardware_shortage':
                $hwCost = in_array('bulk_hardware_contract', $actions) ? 1.4 : 2.0;
                $resSpeed = in_array('bulk_hardware_contract', $actions) ? 0.4 : 0.5;
                $mods = [
                    'hardware_cost' => $hwCost,
                    'research_speed' => $resSpeed
                ];
                break;

            case 'employee_strike':
                $mods = [
                    'research_speed' => 0.05, // 95% reduction
                    'employee_efficiency' => 0.05,
                    'contract_payout' => 0.5, // Customers unhappy with service quality
                ];
                break;

            default:
                $mods = [];
        }

        // Apply Global Overlays
        if (isset($mods['power_cost'])) $mods['power_cost'] *= $powerBaseMod;
        else if ($powerBaseMod > 1.0) $mods['power_cost'] = $powerBaseMod;

        if (isset($mods['cooling_efficiency'])) $mods['cooling_efficiency'] *= $coolingBaseEff;
        else if ($coolingBaseEff > 1.0) $mods['cooling_efficiency'] = $coolingBaseEff;

        return $mods;
    }
}
