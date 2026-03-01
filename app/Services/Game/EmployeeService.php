<?php

namespace App\Services\Game;

use App\Models\Employee;
use App\Models\User;
use App\Models\Server;
use App\Models\SupportTicket;
use App\Enums\ServerStatus;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    private const XP_PER_LEVEL_BASE = 500;
    private const MAX_LEVEL = 20;

    public const SPECIALIZATIONS = [
        'sys_admin' => [
            'hardware_expert' => [
                'name' => 'Hardware Pro',
                'description' => 'Reduces server wear by 50% and repair costs by 20%.',
                'bonus' => ['wear_reduction' => 0.5, 'repair_cost_reduction' => 0.2]
            ],
            'network_guru' => [
                'name' => 'Network Guru',
                'description' => 'Reduces network jitter by 30% and improves throughput by 5%.',
                'bonus' => ['jitter_reduction' => 0.3, 'throughput_bonus' => 1.05]
            ],
            'ops_automation' => [
                'name' => 'DevOps Guru',
                'description' => 'Optimizes server resource efficiency. Reduces base power draw by 15%.',
                'bonus' => ['power_draw_reduction' => 0.15]
            ]
        ],
        'support_agent' => [
            'retention_specialist' => [
                'name' => 'Retention Specialist',
                'description' => 'Passive satisfaction bonus +10% for all managed customers.',
                'bonus' => ['satisfaction_bonus' => 1.1]
            ],
            'crisis_negotiator' => [
                'name' => 'Crisis Negotiator',
                'description' => 'Reduces reputational damage during Global Crises by 40%.',
                'bonus' => ['crisis_rep_loss_reduction' => 0.4]
            ],
            'pr_guru' => [
                'name' => 'PR Liaison',
                'description' => 'Improves public image. Passive reputation gain +0.01 per tick.',
                'bonus' => ['passive_reputation_gain' => 0.01]
            ]
        ],
        'compliance_officer' => [
            'audit_expert' => [
                'name' => 'Audit Expert',
                'description' => 'Reduces penalties from failed compliance checks by 50%.',
                'bonus' => ['compliance_penalty_reduction' => 0.5]
            ],
            'tax_whisperer' => [
                'name' => 'Tax Whisperer',
                'description' => 'Optimizes regional tax strategy. Reduces corporate tax by 10%.',
                'bonus' => ['tax_reduction_flat' => 0.1]
            ]
        ],
        'security_engineer' => [
            'penetration_tester' => [
                'name' => 'Pen-Tester',
                'description' => 'Increases hack detection speed by 50%.',
                'bonus' => ['detection_speed' => 1.5]
            ],
            'cryptographer' => [
                'name' => 'Cryptographer',
                'description' => 'Reduces Ransomware crisis duration by 25%.',
                'bonus' => ['crisis_duration_reduction' => 0.25]
            ]
        ],
        'network_engineer' => [
            'bgp_optimizer' => [
                'name' => 'BGP Optimizer',
                'description' => 'Reduces global latency by an additional 10ms.',
                'bonus' => ['latency_reduction_flat' => 10]
            ],
            'cdn_expert' => [
                'name' => 'CDN Expert',
                'description' => 'Increases bandwidth capacity bonus from research by 50%.',
                'bonus' => ['capacity_bonus_multiplier' => 1.5]
            ]
        ],
        'manager' => [
            'talent_scout' => [
                'name' => 'Talent Scout',
                'description' => 'Reduces recruitment costs for all employees by 30%.',
                'bonus' => ['hiring_cost_reduction' => 0.3]
            ],
            'morale_booster' => [
                'name' => 'Morale Booster',
                'description' => 'Decreases team stress accumulation by 20%.',
                'bonus' => ['stress_reduction' => 0.2]
            ]
        ]
    ];

    public const SKILL_TREES = [
        'sys_admin' => [
            'cli_wizard' => [
                'name' => 'CLI Wizard',
                'description' => 'Repair speed +20%',
                'cost' => 1,
                'level_req' => 2,
                'effect' => ['repair_speed' => 1.2]
            ],
            'script_kiddie' => [
                'name' => 'Script Kiddie',
                'description' => 'Automation tasks drain 20% less energy',
                'cost' => 1,
                'level_req' => 5,
                'prerequisite' => 'cli_wizard',
                'effect' => ['energy_efficiency' => 1.2]
            ],
            'server_whisperer' => [
                'name' => 'Server Whisperer',
                'description' => '5% chance to repair instantly for free',
                'cost' => 2,
                'level_req' => 10,
                'prerequisite' => 'script_kiddie',
                'effect' => ['instant_fix_chance' => 0.05]
            ]
        ],
        'support_agent' => [
            'empathy_chip' => [
                'name' => 'Empathy Chip',
                'description' => 'Churn reduction effect +20%',
                'cost' => 1,
                'level_req' => 2,
                'effect' => ['churn_reduction' => 1.2]
            ],
            'ticket_slayer' => [
                'name' => 'Ticket Slayer',
                'description' => 'Support tasks are 20% faster',
                'cost' => 1,
                'level_req' => 5,
                'prerequisite' => 'empathy_chip',
                'effect' => ['support_speed' => 1.2]
            ],
            'crisis_manager' => [
                'name' => 'Crisis Manager',
                'description' => 'Prevents churn from outages while active',
                'cost' => 2,
                'level_req' => 10,
                'prerequisite' => 'ticket_slayer',
                'effect' => ['prevent_churn_outage' => true]
            ]
        ],
        'security_engineer' => [
            'firewall_master' => [
                'name' => 'Firewall Master',
                'description' => 'Cyber-defense effectiveness +20%',
                'cost' => 1,
                'level_req' => 2,
                'effect' => ['defense_bonus' => 1.2]
            ],
            'trace_route' => [
                'name' => 'Trace Route',
                'description' => 'Hack detection is 30% faster',
                'cost' => 1,
                'level_req' => 5,
                'prerequisite' => 'firewall_master',
                'effect' => ['detection_speed' => 1.3]
            ],
            'black_hat_past' => [
                'name' => 'Black Hat Past',
                'description' => 'Can launch counter-attacks (Sabotage +10%)',
                'cost' => 2,
                'level_req' => 10,
                'prerequisite' => 'trace_route',
                'effect' => ['counter_attack_bonus' => 0.1]
            ]
        ],
        'manager' => [
            'headhunter' => [
                'name' => 'Headhunter',
                'description' => 'Recruitment speed +20%',
                'cost' => 1,
                'level_req' => 2,
                'effect' => ['hiring_speed' => 1.2]
            ],
            'office_politics' => [
                'name' => 'Office Politics',
                'description' => 'Reduces attrition risk by 15%',
                'cost' => 1,
                'level_req' => 5,
                'prerequisite' => 'headhunter',
                'effect' => ['attrition_reduction' => 0.15]
            ],
            'strategic_vision' => [
                'name' => 'Strategic Vision',
                'description' => 'All employee efficiency +5%',
                'cost' => 2,
                'level_req' => 10,
                'prerequisite' => 'office_politics',
                'effect' => ['global_efficiency_bonus' => 0.05]
            ]
        ]
    ];

    /**
     * Respec an employee's talent tree.
     */
    public function respec(User $user, string $employeeId): array
    {
        $employee = Employee::where('user_id', $user->id)->find($employeeId);
        if (!$employee) return ['success' => false, 'error' => 'Mitarbeiter nicht gefunden.'];

        $cost = $employee->level * 100; // Respec cost scales with level
        if (!$user->economy->canAfford($cost)) {
            return ['success' => false, 'error' => "Respec kostet \${$cost}. Nicht genug Guthaben."];
        }

        return DB::transaction(function () use ($user, $employee, $cost) {
            $user->economy->debit($cost, "Talent Respec: {$employee->name}", 'hr', $employee);

            // Refund skill points
            $refundPoints = 0;
            $tree = self::SKILL_TREES[$employee->type] ?? [];
            foreach ($employee->perks as $perkId) {
                $refundPoints += ($tree[$perkId]['cost'] ?? 1);
            }

            $employee->skill_points += $refundPoints;
            $employee->perks = [];
            $employee->save();

            \App\Models\GameLog::log($user, "⚡ RESPEC: {$employee->name} hat alle Talente verlernt. {$refundPoints} Skill-Punkte erstattet.", 'info', 'hr');

            return [
                'success' => true,
                'message' => 'Talente zurückgesetzt.',
                'refunded_points' => $refundPoints,
            ];
        });
    }
    private function getTypes(): array
    {
        return \App\Models\GameConfig::get('employee_types', []);
    }

    public function getAvailableTypes(): array
    {
        return $this->getTypes();
    }

    protected array $employeeCache = [];

    public function getEmployees(User $user)
    {
        if (!isset($this->employeeCache[$user->id])) {
            $this->employeeCache[$user->id] = Employee::where('user_id', $user->id)->get();
        }
        return $this->employeeCache[$user->id];
    }

    public function hire(User $user, string $type): array
    {
        $types = $this->getTypes();
        if (!isset($types[$type])) {
            return ['success' => false, 'error' => 'Invalid employee type'];
        }

        $config = $types[$type];
        
        $hiringCost = $config['hiring_cost'];
        $reduction = $this->getAggregatedBonus($user, 'hiring_cost_reduction');
        if ($reduction > 0) {
            $hiringCost *= (1.0 - min(0.9, $reduction));
        }

        if (!$user->economy->canAfford($hiringCost)) {
            return ['success' => false, 'error' => 'Insufficient funds. Hiring cost: $' . number_format($hiringCost, 2)];
        }

        return DB::transaction(function () use ($user, $type, $config, $hiringCost) {
            $specs = self::SPECIALIZATIONS[$type] ?? [];
            $assignedSpec = !empty($specs) ? array_rand($specs) : null;

            $employee = Employee::create([
                'user_id' => $user->id,
                'type' => $type,
                'specialization' => $assignedSpec,
                'name' => $this->generateName(),
                'salary' => $config['base_salary'],
                'efficiency' => 1.0,
                'level' => 1,
                'hired_at' => now(),
                'xp' => 0,
                'perks' => [],
            ]);

            if (!$user->economy->debit($hiringCost, "Hired {$config['name']}", 'hr', $employee)) {
                 throw new \Exception("Insufficient funds transaction failed");
            }

            // Award XP to player for hiring
            $user->economy->addExperience(200);

            \App\Models\GameLog::log($user, "STAFF_HIRED: {$employee->type} - {$employee->name}", 'success', 'hr');

            return ['success' => true, 'data' => $employee, 'message' => "Welcome, {$employee->name}!"];
        });
    }

    public function fire(User $user, string $employeeId): array
    {
        $employee = Employee::where('user_id', $user->id)->where('id', $employeeId)->first();
        
        if (!$employee) {
            return ['success' => false, 'error' => 'Employee not found'];
        }

        $severance = $employee->salary;
        
        if (!$user->economy->debit($severance, "Severance pay for {$employee->name}", 'hr', $employee)) {
             return ['success' => false, 'error' => 'Cannot afford severance pay'];
        }

        \App\Models\GameLog::log($user, "STAFF_TERMINATED: {$employee->name}", 'warning', 'hr');

        $employee->delete();
        
        return ['success' => true, 'message' => "Employee terminated."];
    }

    public function train(User $user, string $employeeId): array
    {
        $employee = Employee::where('user_id', $user->id)->where('id', $employeeId)->first();
        if (!$employee) return ['success' => false, 'error' => 'Employee not found'];

        if ($employee->status === 'training') return ['success' => false, 'error' => 'Already in training'];

        $cost = $employee->salary * 20; 
        if (!$user->economy->canAfford($cost)) return ['success' => false, 'error' => 'Insufficient funds'];

        return DB::transaction(function() use ($user, $employee, $cost) {
            $user->economy->debit($cost, "Training: {$employee->name}", 'hr', $employee);
            
            // Training now awards XP instead of raw efficiency
            $xpGain = 500;
            $this->awardXp($employee, $xpGain);
            
            $employee->salary *= 1.05; // 5% raise expected
            $employee->stress += 20;
            // FEATURE 89: Raise and investment increases loyalty
            $employee->loyalty = min(100, ($employee->loyalty ?? 50) + 10);
            $employee->save();

            return ['success' => true, 'message' => "{$employee->name} completed training. +{$xpGain} XP gained."];
        });
    }

    public function awardXp(Employee $employee, int $amount): void
    {
        $user = $employee->user;
        $bonuses = $this->getAllActiveBonuses($user);
        
        if ($employee->level < 5 && isset($bonuses['trainee_xp_boost'])) {
            $amount = (int) ($amount * (1 + $bonuses['trainee_xp_boost']));
        }

        $employee->xp += $amount;
        $this->checkLevelUp($employee);
        $employee->save();
    }

    private function checkLevelUp(Employee $employee): void
    {
        if ($employee->level >= self::MAX_LEVEL) return;

        $xpReq = $employee->level * self::XP_PER_LEVEL_BASE;
        while ($employee->xp >= $xpReq && $employee->level < self::MAX_LEVEL) {
            $employee->xp -= $xpReq;
            $employee->level++;
            $employee->skill_points++;
            $employee->efficiency += 0.05; // 5% efficiency per level
            
            // Recalculate requirement for next loop
            $xpReq = $employee->level * self::XP_PER_LEVEL_BASE;
        }
    }

    public function unlockPerk(User $user, string $employeeId, string $perkId): array
    {
        $employee = Employee::where('user_id', $user->id)->find($employeeId);
        if (!$employee) return ['success' => false, 'error' => 'Employee not found'];

        $tree = self::SKILL_TREES[$employee->type] ?? null;
        if (!$tree || !isset($tree[$perkId])) {
            return ['success' => false, 'error' => 'Perk not found in this skill tree'];
        }

        $perk = $tree[$perkId];
        $currentPerks = $employee->perks ?? [];

        if (in_array($perkId, $currentPerks)) {
            return ['success' => false, 'error' => 'Perk already unlocked'];
        }

        if ($employee->level < $perk['level_req']) {
            return ['success' => false, 'error' => "Level {$perk['level_req']} required"];
        }

        if ($employee->skill_points < $perk['cost']) {
            return ['success' => false, 'error' => 'Insufficient skill points'];
        }

        // Check prerequisite
        if (isset($perk['prerequisite']) && !in_array($perk['prerequisite'], $currentPerks)) {
            $preName = $tree[$perk['prerequisite']]['name'] ?? $perk['prerequisite'];
            return ['success' => false, 'error' => "Bedarf Talent '{$preName}' als Voraussetzung."];
        }

        $employee->skill_points -= $perk['cost'];
        $currentPerks[] = $perkId;
        $employee->perks = $currentPerks;
        $employee->save();

        return ['success' => true, 'message' => "Unlocked {$perk['name']}!"];
    }

    public function hasPerk(Employee $employee, string $perkId): bool
    {
        return in_array($perkId, $employee->perks ?? []);
    }

    public function getAggregatedBonus(User $user, string $bonusKey): float
    {
        $employees = $this->getEmployees($user);
        $total = 0.0;

        foreach ($employees as $employee) {
            $efficiencyScale = 0.5 + ($employee->efficiency * 0.5);

            if ($employee->specialization) {
                $specConfig = self::SPECIALIZATIONS[$employee->type][$employee->specialization] ?? null;
                if ($specConfig && isset($specConfig['bonus'][$bonusKey])) {
                    $total += $specConfig['bonus'][$bonusKey] * $efficiencyScale;
                }
            }

            if (!empty($employee->perks) && is_array($employee->perks)) {
                $tree = self::SKILL_TREES[$employee->type] ?? [];
                foreach ($employee->perks as $perkId) {
                    if (isset($tree[$perkId]['effect'][$bonusKey])) {
                        $total += $tree[$perkId]['effect'][$bonusKey] * $efficiencyScale;
                    }
                }
            }
        }

        return $total;
    }

    public function getAllActiveBonuses(User $user): array
    {
        $employees = $this->getEmployees($user);
        $bonuses = [];

        // 1. Employee Bonuses (Specializations & Perks)
        foreach ($employees as $employee) {
            $efficiencyScale = 0.5 + ($employee->efficiency * 0.5);

            if ($employee->specialization) {
                $specConfig = self::SPECIALIZATIONS[$employee->type][$employee->specialization] ?? null;
                if ($specConfig && isset($specConfig['bonus'])) {
                    foreach ($specConfig['bonus'] as $key => $value) {
                        $bonuses[$key] = ($bonuses[$key] ?? 0) + ($value * $efficiencyScale);
                    }
                }
            }

            if (!empty($employee->perks) && is_array($employee->perks)) {
                $tree = self::SKILL_TREES[$employee->type] ?? [];
                foreach ($employee->perks as $perkId) {
                    if (isset($tree[$perkId]['effect'])) {
                        foreach ($tree[$perkId]['effect'] as $key => $value) {
                            // If effect is a boolean flag, just set it to true if it exists
                            if (is_bool($value)) {
                                $bonuses[$key] = true;
                            } else {
                                $bonuses[$key] = ($bonuses[$key] ?? 0) + ($value * $efficiencyScale);
                            }
                        }
                    }
                }
            }
        }

        // 3. Synergy Bonuses (Combinations of employees)
        $synergies = $this->calculateSynergies($user);
        foreach ($synergies as $bonus) {
            foreach ($bonus['effects'] as $key => $value) {
                if (is_numeric($value)) {
                    $bonuses[$key] = ($bonuses[$key] ?? 0) + $value;
                } else if (is_bool($value)) {
                    $bonuses[$key] = true;
                }
            }
        }

        // 2. Research Bonuses
        $researchService = app(ResearchService::class);
        $researchBonuses = $researchService->getAllActiveBonuses($user);
        
        foreach ($researchBonuses as $key => $value) {
            if (is_numeric($value)) {
                $bonuses[$key] = ($bonuses[$key] ?? 0) + $value;
            } else if (is_array($value)) {
                if (!isset($bonuses[$key])) $bonuses[$key] = [];
                if (is_array($bonuses[$key])) {
                    $bonuses[$key] = array_merge($bonuses[$key], $value);
                }
            }
        }

        return $bonuses;
    }

    private function calculateSynergies(User $user): array
    {
        $employees = $this->getEmployees($user);
        if ($employees->count() < 2) return [];

        $synergies = [];
        $types = $employees->pluck('type')->toArray();
        $levels = $employees->pluck('level')->toArray();

        // 1. SysAdmin + Security Engineer: "Hardened Ops"
        if (in_array('sys_admin', $types) && in_array('security_engineer', $types)) {
            $synergies[] = [
                'name' => 'Hardened Ops',
                'effects' => ['breach_avoidance' => 0.15, 'wear_reduction' => 0.10]
            ];
        }

        // 2. Manager + Support Agent: "Happy Fleet"
        if (in_array('manager', $types) && in_array('support_agent', $types)) {
            $synergies[] = [
                'name' => 'Happy Fleet',
                'effects' => ['support_speed' => 0.20]
            ];
        }

        // 3. Network Engineer + SysAdmin: "SDN Synergy"
        if (in_array('network_engineer', $types) && in_array('sys_admin', $types)) {
            $synergies[] = [
                'name' => 'SDN Synergy',
                'effects' => ['bandwidth_efficiency' => 0.10]
            ];
        }

        // 4. Compliance Officer + Manager: "Audit Shield"
        if (in_array('compliance_officer', $types) && in_array('manager', $types)) {
            $synergies[] = [
                'name' => 'Audit Shield',
                'effects' => ['audit_fine_reduction' => 0.30]
            ];
        }

        // 5. Mentorship (Senior Lvl 10+ and Junior Lvl < 5)
        $hasSenior = $employees->contains(fn($e) => $e->level >= 10);
        $hasJunior = $employees->contains(fn($e) => $e->level < 5);
        if ($hasSenior && $hasJunior) {
            $synergies[] = [
                'name' => 'Mentorship',
                'effects' => ['trainee_xp_boost' => 0.50]
            ];
        }

        return $synergies;
    }

    public function giveRaise(User $user, string $employeeId, float $amount): array
    {
        $employee = Employee::where('user_id', $user->id)->where('id', $employeeId)->first();
        if (!$employee) return ['success' => false, 'error' => 'Employee not found'];

        // Amount is percentage increase, e.g. 0.05 for 5%
        if ($amount <= 0) return ['success' => false, 'error' => 'Invalid raise amount'];

        $employee->salary *= (1 + $amount);
        $employee->stress = max(0, $employee->stress - ($amount * 100)); // 10% raise reduces 10 stress
        $employee->save();

        return ['success' => true, 'message' => "{$employee->name} received a raise. Morale improved."];
    }
    
    public function getTotalHourlySalary(User $user): float
    {
        return (float) Employee::where('user_id', $user->id)->sum('salary');
    }

    public function processAutomation(User $user)
    {
        $employees = $this->getEmployees($user);
        
        $activeEventsCount = \App\Models\GameEvent::where('user_id', $user->id)
            ->whereIn('status', ['active', 'escalated'])
            ->count();

        foreach ($employees as $employee) {
            // FEATURE 284: Skip sabbatical employees
            if ($employee->isOnSabbatical()) {
                $employee->current_task = '🏖️ On Sabbatical';
                $employee->stress = max(0, $employee->stress - 5);
                $employee->energy = min(100, $employee->energy + 10);
                $employee->save();
                continue;
            }

            // Check if sabbatical just ended
            if ($employee->sabbatical_until && !$employee->isOnSabbatical()) {
                $employee->sabbatical_until = null;
                $employee->stress = 0;
                $employee->energy = 100;
                $employee->efficiency = 1.0;
                $employee->current_task = 'Returned from Sabbatical';
                $employee->save();

                \App\Models\GameLog::log($user, "🏖️ {$employee->name} ist aus dem Sabbatical zurückgekehrt. Stress: 0, Energie: 100%!", 'success', 'hr');
            }

            // FEATURE 129: Skip burned out employees (Medical Leave)
            $meta = $employee->metadata ?? [];
            if (isset($meta['burnout_until'])) {
                if (now()->lt(\Carbon\Carbon::parse($meta['burnout_until']))) {
                    $employee->current_task = '⚕️ Medical Leave (Burnout)';
                    $employee->stress = max(0, $employee->stress - 1);
                    $employee->energy = min(100, $employee->energy + 2);
                    $employee->save();
                    continue;
                } else {
                    unset($meta['burnout_until']);
                    $employee->metadata = $meta;
                    $employee->stress = 0;
                    $employee->energy = 100;
                    $employee->efficiency = 1.0;
                    $employee->current_task = 'Returned from Medical Leave';
                    $employee->save();
                    \App\Models\GameLog::log($user, "⚕️ {$employee->name} returned from medical leave. Stress reset.", 'success', 'hr');
                }
            }

            $this->simulateEmployee($employee, $activeEventsCount);
        }

        // --- System Admins Logic ---
        $sysAdmins = $employees->where('type', 'sys_admin');
        if ($sysAdmins->count() > 0) {
            foreach ($sysAdmins as $admin) {
                if ($admin->energy < 10) {
                    $admin->current_task = 'Resting / Low Energy';
                    $admin->save();
                    continue;
                }

                // Find damaged servers
                $damagedServer = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
                    ->where('health', '<', 100)
                    ->where('status', '!=', ServerStatus::PROVISIONING)
                    ->orderBy('health', 'asc')
                    ->first();

                if ($damagedServer) {
                    $admin->current_task = "Repairing Server #{$damagedServer->id}";
                    
                    // Perk: CLI Wizard (20% faster repair)
                    $multiplier = $admin->efficiency;
                    if ($this->hasPerk($admin, 'cli_wizard')) {
                         $multiplier *= 1.2;
                    }
                    
                    $admin->task_progress = min(100, $admin->task_progress + (10 * $multiplier));
                    
                    if ($admin->task_progress >= 100) {
                        $repairCost = $damagedServer->purchase_cost * 0.15;
                        
                        // Perk: Server Whisperer (5% chance to fix for free instantly)
                        $isFreeFix = false;
                        if ($this->hasPerk($admin, 'server_whisperer') && rand(1, 100) <= 5) {
                             $isFreeFix = true;
                        }

                        if ($isFreeFix || $user->economy->canAfford($repairCost)) {
                            if (!$isFreeFix) {
                                $user->economy->debit($repairCost, "Staff Repair: {$damagedServer->model_name}", 'maintenance', $damagedServer);
                            } else {
                                Log::info("Server Whisperer procced for {$admin->name} on server {$damagedServer->id}");
                            }

                            $damagedServer->health = 100;
                            if ($damagedServer->status === ServerStatus::OFFLINE) {
                                $damagedServer->status = ServerStatus::ONLINE;
                            }
                            $damagedServer->save();
                            $admin->total_actions++;
                            $admin->task_progress = 0;
                            
                            // Free fix also saves energy/stress
                            if (!$isFreeFix) {
                                $admin->energy -= 5;
                                $admin->stress += 2;
                                $this->awardXp($admin, 50); // XP for repair
                            } else {
                                $this->awardXp($admin, 20); // free fix XP
                            }
                        }
                    }
                } else {
                    // PREVENTATIVE MAINTENANCE (Specialization V2)
                    if ($admin->specialization === 'hardware_expert' && $user->economy->spare_parts_count > 0) {
                        $needsMaint = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
                            ->where('health', '<', 90)
                            ->where('status', 'online')
                            ->orderBy('health', 'asc')
                            ->first();
                            
                        if ($needsMaint) {
                            $admin->current_task = "Preventative Maintenance: {$needsMaint->model_name}";
                            $needsMaint->health = min(100, $needsMaint->health + 10);
                            $needsMaint->addMaintenanceLogEntry('maintenance', "Staff preventative maintenance by {$admin->name}", 0);
                            $needsMaint->save();
                            
                            $user->economy->spare_parts_count--;
                            $user->economy->save();
                            
                            $admin->energy -= 2;
                            $admin->stress += 1;
                            $this->awardXp($admin, 10); // XP for preventative maintenance
                        } else {
                            $admin->current_task = 'Monitoring Infrastructure';
                        }
                    } else {
                        $admin->current_task = 'Monitoring Infrastructure';
                    }
                    $admin->task_progress = 0;
                }
                $admin->save();
            }
        }

        // --- Support Agents Logic ---
        $supportAgents = $employees->where('type', 'support_agent');
        foreach ($supportAgents as $agent) {
             if ($agent->energy < 10) {
                 $agent->current_task = 'Taking Break';
                 $agent->save();
                 continue;
             }
             
             // Check for open tickets
             $ticket = SupportTicket::where('user_id', $user->id)
                ->whereIn('status', ['open', 'in_progress'])
                ->orderBy('priority', 'desc')
                ->orderBy('created_at', 'asc')
                ->first();

             if ($ticket) {
                 $ticket->status = 'in_progress';
                 $ticket->assigned_employee_id = $agent->id;
                 
                 $allBonuses = $this->getAllActiveBonuses($user);
                 $speedMod = 1.0 + ($allBonuses['support_speed'] ?? 0);
                 $speed = $agent->efficiency * 12 * $speedMod;
                 if ($this->hasPerk($agent, 'ticket_slayer')) {
                      $speed *= 1.35;
                 }
                 
                 $ticket->progress = min(100, $ticket->progress + $speed);
                 $agent->current_task = "Solving Ticket: {$ticket->subject}";
                 $agent->energy -= 2;
                 $agent->stress += 1;
                 
                 if ($ticket->progress >= 100) {
                     $ticket->status = 'resolved';
                     $ticket->resolved_at = now();
                     
                     if ($ticket->customer) {
                         $ticket->customer->improveSatisfaction(8);
                     }
                     
                     $this->awardXp($agent, 30); // XP for resolving ticket
                 }
                 $ticket->save();
             } else {
                 // Support agents reduce churn probability passively, but we show they are "Busy"
                 $unhappyCount = \App\Models\Customer::where('user_id', $user->id)
                    ->where('status', 'unhappy')
                    ->count();
                    
                 if ($unhappyCount > 0) {
                     $agent->current_task = 'Calming Unhappy Customers';
                     $agent->energy -= 1;
                     $agent->stress += 0.5;
                 } else {
                     $agent->current_task = 'Processing Routine Inquiries';
                 }
             }
             $agent->save();
        }

        // --- Security Engineers Logic ---
        $securityEngineers = $employees->where('type', 'security_engineer');
        foreach ($securityEngineers as $eng) {
            if ($eng->energy < 10) {
                $eng->current_task = 'Resting / Low Energy';
                $eng->save(); continue;
            }

            $activeBreaches = \App\Models\GameEvent::where('user_id', $user->id)
                ->where('type', \App\Enums\EventType::SECURITY_BREACH)
                ->whereIn('status', ['active', 'escalated'])
                ->exists();

            if ($activeBreaches) {
                $eng->current_task = 'Counter-Hacking Active Breach';
                $eng->energy -= 4;
                $eng->stress += 3;
            } else {
                $eng->current_task = 'Hardening Infrastructure';
                $eng->energy -= 1;
            }
            $eng->save();
        }

        // --- Compliance Officers Logic ---
        $complianceOfficers = $employees->where('type', 'compliance_officer');
        foreach ($complianceOfficers as $off) {
            if ($off->energy < 10) {
                $off->current_task = 'Resting / Low Energy';
                $off->save(); continue;
            }

            $activeAudits = \App\Models\ComplianceAudit::where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();

             if ($activeAudits) {
                 $off->current_task = 'Facilitating External Audit';
                 $off->energy -= 3;
                 $off->stress += 2;
             } else {
                 $off->current_task = 'Regulatory Compliance Review';
                 
                 // Perk: Ticket Slayer (20% faster generic tasks - reusing for Compliance here for simplicity)
                 if ($this->hasPerk($off, 'ticket_slayer')) {
                     // Compliance specifically doesn't have much "Speed" metric, 
                     // but we could reduce energy cost? Let's just leave it for now or implement properly later.
                 }
                 
                 $off->energy -= 1;
             }
             $off->save();
         }

        // --- Network Engineers Logic ---
        $networkEngineers = $employees->where('type', 'network_engineer');
        foreach ($networkEngineers as $net) {
            if ($net->energy < 10) {
                $net->current_task = 'Resting / Low Energy';
                $net->save(); continue;
            }

            $isSaturated = \App\Models\GameRoom::where('user_id', $user->id)
                ->with(['racks.servers.activeOrders'])
                ->get()
                ->contains(fn($r) => $r->isBandwidthSaturated());

            if ($isSaturated) {
                $net->current_task = 'Traffic Shaping / Congestion Control';
                $net->energy -= 3;
                $net->stress += 2;
            } else {
                $net->current_task = 'Optimizing Routing Tables';
                $net->energy -= 1;
            }
            $net->save();
        }
    }

    private function simulateEmployee(Employee $employee, int $activeEventsCount)
    {
        // Stress increase per incident
        // Normal level: 0.5 stress per minute per incident
        $stressIncrease = $activeEventsCount * 0.5;

        // Apply Manager bonus: Morale Booster (stress_reduction)
        $reduction = $this->getAggregatedBonus($employee->user, 'stress_reduction');
        if ($reduction > 0) {
            $stressIncrease *= (1.0 - min(0.8, $reduction));
        }
        
        // Efficiency impact on stress (experts handle it better)
        $stressIncrease /= max(0.5, $employee->efficiency);

        // FEATURE 73: Circadian Fatigue (Night-Shift Scaling)
        // Employees working between 22:00-06:00 game time gain stress 1.5x faster
        $economy = $employee->user->economy;
        $gameHour = ($economy->current_tick ?? 0) % 24;
        $isNightShift = ($gameHour >= 22 || $gameHour < 6);
        if ($isNightShift) {
            $stressIncrease *= 1.5;
        }

        $employee->stress = min(100, max(0, $employee->stress + $stressIncrease - 0.2)); // -0.2 natural recovery
        
        // Efficiency impact: stressed employees are slower
        if ($employee->stress > 70) {
             // 70-100 stress = 30% speed reduction linear
             $penalty = ($employee->stress - 70) / 100; // max 0.3
             $employee->efficiency = max(0.4, 1.0 - $penalty);
        } else {
             $employee->efficiency = 1.0;
        }

        // Apply Manager bonus: Strategic Vision (global_efficiency_bonus)
        $efficiencyBonus = $this->getAggregatedBonus($employee->user, 'global_efficiency_bonus');
        if ($efficiencyBonus > 0) {
            $employee->efficiency += $efficiencyBonus;
        }

        // Energy recovery if idle
        $idleTasks = [
            'Monitoring Infrastructure', 
            'Processing Routine Inquiries',
            'Hardening Infrastructure',
            'Regulatory Compliance Review',
            'Optimizing Routing Tables'
        ];

        if (in_array($employee->current_task, $idleTasks)) {
             $employee->energy = min(100, $employee->energy + 2);
             $employee->stress = max(0, $employee->stress - 1);
             
             // FEATURE 63: Corporate Academy
             $hasAcademy = \App\Models\GameRoom::where('user_id', $employee->user_id)
                           ->whereJsonContains('upgrades', 'academy')
                           ->exists();
                           
             if ($hasAcademy) {
                 $this->awardXp($employee, 5); // 5 XP per tick while idle with Academy
             }
        }

        // Perk: Script Kiddie (Energy Efficiency)
        if ($this->hasPerk($employee, 'script_kiddie')) {
             $employee->energy = min(100, $employee->energy + 0.5);
        }

        // Sudden burnout check (FEATURE 129: Employee Burnout)
        if ($employee->stress > 98 && rand(0, 100) < 5) {
             $employee->current_task = 'BURNED OUT / MEDICAL LEAVE';
             $employee->efficiency = 0.1;
             $employee->stress = 100;
             $employee->loyalty = max(0, $employee->loyalty - 5); // Burnout tanks loyalty
             
             $meta = $employee->metadata ?? [];
             $meta['burnout_until'] = now()->addHours(rand(24, 48))->toIso8601String();
             $employee->metadata = $meta;
             
             \App\Models\GameLog::log($employee->user, "🚨 {$employee->name} suffered a burnout and is on unannounced medical leave! (24-48h)", 'danger', 'hr');
        }

        // FEATURE 89: Employee Loyalty & Retention
        $loyalty = (float) ($employee->loyalty ?? 50);

        // Loyalty Recovery (slow natural gain)
        $loyalty += 0.05; // +0.05 per tick base gain (slow trust building)

        // Stress Impact: High stress erodes loyalty
        if ($employee->stress > 80) {
            $loyalty -= 0.15; // Stressed employees lose trust fast
        } elseif ($employee->stress < 30) {
            $loyalty += 0.03; // Happy employees gain trust
        }

        // Level Impact: Senior employees expect better treatment
        if ($employee->level >= 10 && $loyalty > 40) {
            // Senior staff are more loyal by default (they've invested in the company)
            $loyalty += 0.02;
        }

        // Attrition risk: Low loyalty employees might quit
        // FEATURE 128: Golden Handcuffs — employees with active retention bonus skip attrition
        $retentionUntil = $employee->metadata['retention_until'] ?? null;
        $hasRetentionBonus = $retentionUntil && now()->lt(\Carbon\Carbon::parse($retentionUntil));
        
        if (!$hasRetentionBonus) {
            if ($loyalty < 15 && rand(1, 100) <= 2) {
                // 2% chance per tick to resign when loyalty is critical
                \App\Models\GameLog::log($employee->user, "⚠️ {$employee->name} hat gekündigt! Loyalty war zu niedrig ({$loyalty}%).", 'danger', 'hr');
                $employee->delete();
                return; // EXIT early
            } elseif ($loyalty < 5 && rand(1, 100) <= 5) {
                // 5% chance for instant resignation at critically low loyalty
                \App\Models\GameLog::log($employee->user, "🚨 {$employee->name} hat fristlos gekündigt! ({$loyalty}% Loyalty)", 'danger', 'hr');
                $employee->delete();
                return; // EXIT early
            }
        }

        $employee->loyalty = max(0, min(100, $loyalty));
        
        $employee->save();
    }

    private function generateName(): string
    {
        $first = ['Alex', 'Sam', 'Jordan', 'Casey', 'Riley', 'Taylor', 'Morgan', 'Quinn', 'Skyler', 'Charlie'];
        $last = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
        
        return $first[array_rand($first)] . ' ' . $last[array_rand($last)];
    }

    /**
     * FEATURE 284: Send an employee on paid sabbatical
     * Requires: Level >= 5, Stress > 60
     * Duration: 2 real hours (= roughly 1 in-game month)
     * Cost: 1 month salary (employee.salary * 720 ticks, but simplified)
     */
    public function sendOnSabbatical(User $user, string $employeeId): array
    {
        $employee = Employee::where('user_id', $user->id)->find($employeeId);
        if (!$employee) return ['success' => false, 'error' => 'Mitarbeiter nicht gefunden.'];

        if ($employee->isOnSabbatical()) {
            return ['success' => false, 'error' => "{$employee->name} ist bereits im Sabbatical."];
        }

        if ($employee->level < 5) {
            return ['success' => false, 'error' => 'Sabbatical erfordert mindestens Level 5.'];
        }

        if ($employee->stress < 60) {
            return ['success' => false, 'error' => "{$employee->name} ist nicht gestresst genug für ein Sabbatical (min. 60%)."];
        }

        // Cost: 1 month salary bonus (additional to regular salary)
        $sabbaticalCost = $employee->salary * 30; // 30 hours worth as a bonus
        if (!$user->economy->canAfford($sabbaticalCost)) {
            return ['success' => false, 'error' => "Sabbatical-Bonus kostet \$" . number_format($sabbaticalCost, 2) . ". Nicht genug Guthaben."];
        }

        return DB::transaction(function () use ($user, $employee, $sabbaticalCost) {
            $user->economy->debit($sabbaticalCost, "Sabbatical-Bonus: {$employee->name}", 'hr', $employee);

            $employee->sabbatical_until = now()->addHours(2); // 2 real hours
            $employee->current_task = '🏖️ On Sabbatical';
            // FEATURE 89: Sabbatical massively boosts loyalty
            $employee->loyalty = min(100, ($employee->loyalty ?? 50) + 20);
            $employee->save();

            \App\Models\GameLog::log($user, "🏖️ {$employee->name} geht ins Sabbatical bis " . $employee->sabbatical_until->format('H:i') . ". Stress wird vollständig abgebaut. Loyalität steigt kräftig!", 'info', 'hr');

            return [
                'success' => true,
                'message' => "{$employee->name} geht ins Sabbatical. Rückkehr in 2 Stunden mit 0% Stress!",
                'returns_at' => $employee->sabbatical_until->toIso8601String(),
            ];
        });
    }

    /**
     * FEATURE 128: Golden Handcuffs (Retention Bonus)
     * Offer a lump-sum bonus to make critical staff immune to attrition for X hours.
     * Cost: 5x employee salary per hour of protection.
     */
    public function giveRetentionBonus(User $user, string $employeeId, int $hours = 24): array
    {
        $employee = Employee::where('user_id', $user->id)->find($employeeId);
        if (!$employee) return ['success' => false, 'error' => 'Employee not found.'];

        // Check if already has golden handcuffs
        $retentionUntil = $employee->metadata['retention_until'] ?? null;
        if ($retentionUntil && now()->lt(\Carbon\Carbon::parse($retentionUntil))) {
            return ['success' => false, 'error' => "{$employee->name} already has an active retention bonus."];
        }

        $hours = max(1, min(72, $hours)); // Min 1h, max 72h
        $cost = $employee->salary * 5 * $hours;

        if (!$user->economy->canAfford($cost)) {
            return ['success' => false, 'error' => "Insufficient funds. Retention bonus costs \$" . number_format($cost, 2) . "."];
        }

        return DB::transaction(function () use ($user, $employee, $cost, $hours) {
            $user->economy->debit($cost, "Golden Handcuffs: {$employee->name} ({$hours}h)", 'hr', $employee);

            $meta = $employee->metadata ?? [];
            $meta['retention_until'] = now()->addHours($hours)->toIso8601String();
            $employee->metadata = $meta;
            $employee->loyalty = min(100, ($employee->loyalty ?? 50) + 15);
            $employee->stress = max(0, $employee->stress - 10);
            $employee->save();

            \App\Models\GameLog::log($user, "🔒 GOLDEN HANDCUFFS: {$employee->name} received a retention bonus. Immune to attrition for {$hours}h.", 'success', 'hr');

            return [
                'success' => true,
                'message' => "{$employee->name} received retention bonus. Locked in for {$hours} hours.",
                'protected_until' => $meta['retention_until'],
            ];
        });
    }
}
