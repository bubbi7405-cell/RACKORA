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

    protected ?bool $cachedStrikeStatus = null;

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
            ],
            'hardware_safety' => [
                'name' => 'Hardware Safety',
                'description' => 'Reduces Hardware Failure probability by 30%',
                'cost' => 2,
                'level_req' => 15,
                'prerequisite' => 'server_whisperer',
                'effect' => ['hardware_failure_prevention' => 0.3]
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
            ],
            'incident_master' => [
                'name' => 'Incident Master',
                'description' => 'Global incident probability -10%',
                'cost' => 2,
                'level_req' => 15,
                'prerequisite' => 'black_hat_past',
                'effect' => ['global_incident_prevention' => 0.1]
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
        ],
        'compliance_officer' => [
            'legal_eagle' => [
                'name' => 'Legal Eagle',
                'description' => 'Audit fines reduced by 30%',
                'cost' => 1,
                'level_req' => 2,
                'effect' => ['audit_fine_reduction' => 0.3]
            ],
            'scam_shield' => [
                'name' => 'Scam Shield',
                'description' => 'Reduces Data Leak probability by 40%',
                'cost' => 1,
                'level_req' => 5,
                'prerequisite' => 'legal_eagle',
                'effect' => ['data_leak_prevention' => 0.4]
            ],
            'tax_wizard' => [
                'name' => 'Tax Wizard',
                'description' => 'Reduces regional taxes by additional 15%',
                'cost' => 2,
                'level_req' => 10,
                'prerequisite' => 'scam_shield',
                'effect' => ['tax_reduction_flat' => 0.15]
            ]
        ],
        'network_engineer' => [
            'topology_guru' => [
                'name' => 'Topology Guru',
                'description' => 'Reduces global latency by 15ms',
                'cost' => 1,
                'level_req' => 2,
                'effect' => ['latency_reduction_flat' => 15]
            ],
            'redundancy_genius' => [
                'name' => 'Redundancy Genius',
                'description' => 'Reduces Network Failure probability by 50%',
                'cost' => 1,
                'level_req' => 5,
                'prerequisite' => 'topology_guru',
                'effect' => ['network_failure_prevention' => 0.5]
            ],
            'peering_partner' => [
                'name' => 'Peering Partner',
                'description' => 'Reduces bandwidth costs by 20%',
                'cost' => 2,
                'level_req' => 10,
                'prerequisite' => 'redundancy_genius',
                'effect' => ['bandwidth_cost_reduction' => 0.2]
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
            $this->employeeCache[$user->id] = Employee::where('user_id', $user->id)->get()
                ->map(fn($e) => $this->formatEmployee($e));
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
            // ── HANDLE MERCENARY (FEATURE 168) ──────────────────
            if ($type === 'mercenary') {
                $affected = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
                    ->where(function($q) {
                        $q->where('health', '<', 100)
                          ->orWhereIn('status', [ServerStatus::DAMAGED, ServerStatus::HARDWARE_FAULT, ServerStatus::DEGRADED]);
                    })->update([
                        'health' => 100,
                        'status' => ServerStatus::ONLINE,
                        'current_fault' => null,
                        'is_diagnosed' => false,
                        'last_maintenance_at' => now(),
                    ]);

                $user->economy->debit($hiringCost, "Mercenary Recovery Team deployed", 'maintenance');
                $user->economy->addExperience(50); // High XP rewards for saving the company

                \App\Models\GameLog::log($user, "🏗️ MERCENARY RECOVERY: {$affected} servers were instantly restored to 100% health by professional technicians.", 'success', 'maintenance');

                return [
                    'success' => true,
                    'message' => "Mercenary Team processed {$affected} units and left the facility.",
                    'mercenary_deployed' => true
                ];
            }

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

        // FEATURE 233: Realistic Severance Pay
        // Base: 10% of salary * (Level + 1) * tenure-factor (simulated by level)
        $severance = round($employee->salary * (5 + $employee->level), 2);
        
        if (!$user->economy->debit($severance, "Abfindung für {$employee->name} (Level {$employee->level})", 'hr', $employee)) {
             return ['success' => false, 'error' => "Abfindung (\$" . number_format($severance, 2) . ") kann nicht bezahlt werden."];
        }

        \App\Models\GameLog::log($user, "🚫 KÜNDIGUNG: {$employee->name} wurde entlassen. Abfindung: \${$severance} ausgezahlt.", 'warning', 'hr');

        $employee->delete();
        
        return ['success' => true, 'message' => "Employee terminated. Paid \$" . number_format($severance, 2) . " severance."];
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

    /**
     * FEATURE 161: Send employee to an off-site seminar for permanent efficiency boost.
     * Duration: 12 game hours (actually 12 real world hours for immersion, but we can scale it).
     */
    public function sendToSeminar(User $user, string $employeeId): array
    {
        $employee = Employee::where('user_id', $user->id)->find($employeeId);
        if (!$employee) return ['success' => false, 'error' => 'Mitarbeiter nicht gefunden.'];

        // Availability check
        if ($employee->isOnSabbatical()) return ['success' => false, 'error' => 'Mitarbeiter ist im Sabbatical.'];
        if ($employee->isOnSeminar()) return ['success' => false, 'error' => 'Bereits in einem Seminar.'];
        
        $meta = $employee->metadata ?? [];
        if (isset($meta['burnout_until'])) return ['success' => false, 'error' => 'Mitarbeiter ist wegen Burnout krankgeschrieben.'];
        if ($this->isStriking($user)) return ['success' => false, 'error' => 'Streik läuft. Keine Entsendung möglich.'];

        $cost = 7500; // Fixed high-tier cost for permanent boost
        if (!$user->economy->canAfford($cost)) {
            return ['success' => false, 'error' => "Seminar kostet \${$cost}. Nicht genug Guthaben."];
        }

        return DB::transaction(function () use ($user, $employee, $cost, $meta) {
            $user->economy->debit($cost, "Seminar: {$employee->name}", 'hr', $employee);

            $meta['seminar_until'] = now()->addHours(12)->toIso8601String();
            $employee->metadata = $meta;
            $employee->current_task = '🎓 At Off-site Seminar';
            $employee->save();

            \App\Models\GameLog::log($user, "🎓 SEMINAR: {$employee->name} wurde zu einer Fortbildung geschickt (12h).", 'info', 'hr');

            return ['success' => true, 'message' => "{$employee->name} besucht nun ein externes Seminar."];
        });
    }

    public function assignToRoom(User $user, string $employeeId, ?string $roomId): array
    {
        $employee = Employee::where('user_id', $user->id)->find($employeeId);
        if (!$employee) return ['success' => false, 'error' => 'Employee not found'];

        if ($roomId) {
            $room = \App\Models\GameRoom::where('user_id', $user->id)->find($roomId);
            if (!$room) return ['success' => false, 'error' => 'Room not found'];
            $employee->room_id = $room->id;
            $msg = "{$employee->name} assigned to {$room->name}.";
        } else {
            $employee->room_id = null;
            $msg = "{$employee->name} unassigned from site duty.";
        }

        $employee->save();
        return ['success' => true, 'message' => $msg];
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
                'effects' => ['breach_avoidance' => 0.25, 'wear_reduction' => 0.15]
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
                'effects' => ['audit_fine_reduction' => 0.40]
            ];
        }

        // 5. Mentorship (Senior Lvl 10+ and Junior Lvl < 5)
        $hasSenior = $employees->contains(fn($e) => $e->level >= 10);
        $hasJunior = $employees->contains(fn($e) => $e->level < 5);
        if ($hasSenior && $hasJunior) {
            $synergies[] = [
                'name' => 'Mentorship',
                'effects' => ['trainee_xp_boost' => 0.60, 'trainee_efficiency' => 0.20]
            ];
        }

        // 6. Support + Compliance: "Consumer Protection"
        if (in_array('support_agent', $types) && in_array('compliance_officer', $types)) {
            $synergies[] = [
                'name' => 'Consumer Protection',
                'effects' => ['satisfaction_retention' => 0.10]
            ];
        }

        // 7. Security + Network: "Zero Trust Layer"
        if (in_array('security_engineer', $types) && in_array('network_engineer', $types)) {
            $synergies[] = [
                'name' => 'Zero Trust Layer',
                'effects' => ['defense_bonus' => 0.30]
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

    /**
     * FEATURE 57: Labor Union Strikes
     * Calculates the probability (0-100) of a strike starting.
     */
    public function calculateStrikeRisk(User $user): float
    {
        $employees = Employee::where('user_id', $user->id)->get();
        if ($employees->isEmpty()) return 0;

        $avgStress = $employees->avg('stress') ?? 0;
        $burnoutCount = 0;
        foreach ($employees as $emp) {
            if (isset($emp->metadata['burnout_until'])) $burnoutCount++;
        }

        // Base risk from stress
        // 0 risk at < 30 stress, linear up to 50 risk at 100 stress
        $risk = max(0, ($avgStress - 30) * 0.71); 

        // Multiplier from burnouts
        // Each burned out employee adds 15% relative risk
        $risk *= (1 + ($burnoutCount * 0.15));

        // Policy modifiers
        $economy = $user->economy;
        $crunchTime = $economy->getPolicy('work_ethic', 'standard') === 'crunch';
        if ($crunchTime) {
            $risk += 20; // Massive flat increase for crunching
        }

        // Reputation factor (higher reputation = better public image = harder to justify strike?)
        // Or reverse: High reputation = more to lose?
        // Let's say high reputation slightly scales it down if you're a "good boss"
        $reputation = $economy->reputation ?? 50;
        if ($reputation > 80) $risk -= 10;
        if ($reputation < 20) $risk += 15;

        return min(100, max(0, $risk));
    }

    public function isStriking(User $user): bool
    {
        if ($this->cachedStrikeStatus !== null) return $this->cachedStrikeStatus;

        $this->cachedStrikeStatus = \App\Models\GameEvent::where('user_id', $user->id)
            ->where('type', \App\Enums\EventType::UNION_STRIKE)
            ->whereIn('status', [\App\Enums\EventStatus::ACTIVE, \App\Enums\EventStatus::ESCALATED])
            ->exists();

        return $this->cachedStrikeStatus;
    }

    public function processAutomation(User $user)
    {
        $employees = $this->getEmployees($user);
        $isStriking = $this->isStriking($user);
        
        $activeEventsCount = \App\Models\GameEvent::where('user_id', $user->id)
            ->whereIn('status', ['active', 'escalated'])
            ->count();

        foreach ($employees as $employee) {
            // HALT ON STRIKE (Except stress reduction while idle)
            if ($isStriking) {
                $employee->current_task = '🪧 On Strike';
                $employee->stress = max(0, $employee->stress - 0.1); // Small cool-off
                $employee->energy = min(100, $employee->energy + 2);
                $employee->save();
                continue;
            }
            // FEATURE 284: Handle sabbatical employees
            if ($employee->isOnSabbatical()) {
                $employee->current_task = '🏖️ On Sabbatical';
                $employee->stress = max(0, $employee->stress - 5);
                $employee->energy = min(100, $employee->energy + 10);
                
                // FEATURE 89: Passive loyalty gain while on paid leave
                $employee->loyalty = min(100, ($employee->loyalty ?? 50) + 0.1);
                
                $employee->save();
                continue;
            }

            // Check if sabbatical just ended
            if ($employee->sabbatical_until && !$employee->isOnSabbatical()) {
                $employee->sabbatical_until = null;
                $employee->stress = 0;
                $employee->energy = 100;
                $employee->efficiency = 1.0;
                $employee->loyalty = min(100, ($employee->loyalty ?? 50) + 10); // Big jump
                $employee->current_task = 'Returned from Sabbatical';
                $employee->save();

                \App\Models\GameLog::log($user, "🏖️ {$employee->name} ist aus dem Sabbatical zurückgekehrt. Stress: 0, Energie: 100%, Loyalität signifikant gestiegen!", 'success', 'hr');
            }

            // FEATURE 243: PASSIVE WELLNESS & SICK LEAVE BONUS
            $wellnessMultiplier = 1.0;
            if ($employee->room_id) {
                $room = \App\Models\GameRoom::find($employee->room_id);
                if ($room && in_array('wellness_facility', $room->upgrades ?? [])) {
                    $wellnessMultiplier = 2.0; // Cooldown for stress/energy
                }
            }

            // FEATURE 129: Skip burned out employees (Medical Leave)
            $meta = $employee->metadata ?? [];
            if (isset($meta['burnout_until'])) {
                if (now()->lt(\Carbon\Carbon::parse($meta['burnout_until']))) {
                    $employee->current_task = '⚕️ Medical Leave (Burnout)';
                    
                    // Recover stress/energy (boosted if in a wellness room)
                    $recoveryMod = $wellnessMultiplier;
                    $employee->stress = max(0, $employee->stress - (1.5 * $recoveryMod));
                    $employee->energy = min(100, $employee->energy + (2.5 * $recoveryMod));
                    $employee->save();
                    continue;
                } else {
                    unset($meta['burnout_until']);
                    $employee->metadata = $meta;
                    $employee->stress = 5; 
                    $employee->energy = 80;
                    $employee->efficiency = 0.8; 
                    $employee->current_task = 'Returned from Medical Leave';
                    $employee->save();
                    \App\Models\GameLog::log($user, "⚕️ {$employee->name} returned from medical leave. Slowly easing back into work.", 'success', 'hr');
                }
            }

            // FEATURE 161: Handle Seminar completion
            if (isset($meta['seminar_until'])) {
                if ($employee->isOnSeminar()) {
                    $employee->current_task = '🎓 At Off-site Seminar';
                    $employee->save();
                    continue;
                } else {
                    unset($meta['seminar_until']);
                    $employee->metadata = $meta;
                    // Apply permanent +10% efficiency bonus (stacks)
                    $employee->efficiency = ($employee->efficiency ?? 1.0) + 0.10;
                    $employee->current_task = 'Returned from Seminar';
                    $employee->save();
                    \App\Models\GameLog::log($user, "🎓 {$employee->name} ist vom Seminar zurückgekehrt. Effizienz permanent um 10% gesteigert!", 'success', 'hr');
                }
            }

            $this->simulateEmployee($employee, $activeEventsCount, $wellnessMultiplier);
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

    private function simulateEmployee(Employee $employee, int $activeEventsCount, float $wellnessMultiplier = 1.0)
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

        $stressRecovery = 0.2 * $wellnessMultiplier;
        if ($wellnessMultiplier > 1.0) {
            $stressRecovery += 1.5; // Passive wellness floor bonus
        }

        $employee->stress = min(100, max(0, $employee->stress + $stressIncrease - $stressRecovery)); 
        
        // Efficiency impact: stressed employees are slower
        if ($employee->stress > 70) {
             // 70-100 stress = 30% speed reduction linear
             $penalty = ($employee->stress - 70) / 100; // max 0.3
             $employee->efficiency = max(0.4, 1.0 - $penalty);
             
             // FEATURE 176: Advanced Burnout Risk
             if ($employee->stress > 95 && rand(1, 400) === 1) { // ~0.25% chance per tick at critical stress
                 $burnoutHours = rand(24, 72);
                 $meta = $employee->metadata ?? [];
                 $meta['burnout_until'] = now()->addHours($burnoutHours)->toIso8601String();
                 $employee->metadata = $meta;
                 $employee->current_task = '⚕️ Sudden Burnout Victim';
                 $employee->save();
                 
                 \App\Models\GameLog::log($employee->user, "🚑 ZUSAMMENBRUCH: {$employee->name} hat einen stressbedingten Burnout erlitten und fällt für {$burnoutHours}h aus!", 'danger', 'hr');
             }
        } else {
             $employee->efficiency = 1.0;
        }

        // Apply Manager bonus: Strategic Vision (global_efficiency_bonus)
        $efficiencyBonus = $this->getAggregatedBonus($employee->user, 'global_efficiency_bonus');
        if ($efficiencyBonus > 0) {
            $employee->efficiency += $efficiencyBonus;
        }

        // FEATURE 199: Trainee efficiency bonus from Mentorship synergy
        if ($employee->level < 5) {
            $traineeBonus = $this->getAggregatedBonus($employee->user, 'trainee_efficiency');
            if ($traineeBonus > 0) {
                $employee->efficiency += $traineeBonus;
            }
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
             $employee->energy = min(100, $employee->energy + (2 * $wellnessMultiplier));
             $employee->stress = max(0, $employee->stress - (1 * $wellnessMultiplier));
             
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
        
        $meta = $employee->metadata ?? [];
        $isResigning = isset($meta['resignation_at']);

        if (!$hasRetentionBonus && !$isResigning) {
            if ($loyalty < 15 && rand(1, 100) <= 2) {
                // FEATURE 232: Resignation Notice (2h window to react)
                $noticeHours = 2;
                $meta['resignation_at'] = now()->addHours($noticeHours)->toIso8601String();
                $employee->metadata = $meta;
                $employee->save();

                \App\Models\GameLog::log($employee->user, "⚠️ KÜNDIGUNG EINGEGANGEN: {$employee->name} hat gekündigt! Verlässt das Unternehmen in {$noticeHours}h. (Loyalty: {$loyalty}%).", 'danger', 'hr');
                return;
            } elseif ($loyalty < 5 && rand(1, 100) <= 5) {
                // Tiny chance for instant resignation at critically low loyalty
                \App\Models\GameLog::log($employee->user, "🚨 FRISTLOSE KÜNDIGUNG: {$employee->name} hat das Gebäude sofort verlassen! ({$loyalty}% Loyalty)", 'danger', 'hr');
                $employee->delete();
                return; // EXIT early
            }
        }

        // Handle active resignation period
        if ($isResigning) {
            $deadline = \Carbon\Carbon::parse($meta['resignation_at']);
            if (now()->gt($deadline)) {
                \App\Models\GameLog::log($employee->user, "💼 ABGANG: {$employee->name} hat das Unternehmen nach Ablauf der Kündigungsfrist verlassen.", 'danger', 'hr');
                $employee->delete();
                return;
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
    public function formatEmployee(Employee $employee): array
    {
        return array_merge($employee->toArray(), [
            'is_on_sabbatical' => $employee->isOnSabbatical(),
            'is_on_seminar' => $employee->isOnSeminar(),
            'sabbatical_time_remaining' => $employee->getSabbaticalTimeRemaining(),
            'is_resigning' => isset($employee->metadata['resignation_at']),
            'resignation_deadline' => $employee->metadata['resignation_at'] ?? null,
        ]);
    }

    /**
     * FEATURE 232: Persuade an employee that has submitted a resignation to stay.
     * Costs a lump-sum "Loyalty Bonus" and requires a mandatory raise.
     */
    public function persuadeToStay(User $user, string $employeeId): array
    {
        $employee = Employee::where('user_id', $user->id)->find($employeeId);
        if (!$employee) return ['success' => false, 'error' => 'Employee not found.'];

        $meta = $employee->metadata ?? [];
        if (!isset($meta['resignation_at'])) {
            return ['success' => false, 'error' => "{$employee->name} kündigt derzeit nicht."];
        }

        // Cost: 25x current salary (Retention Bonus)
        $cost = (float) $employee->salary * 25;
        if (!$user->economy->canAfford($cost)) {
            return ['success' => false, 'error' => "Nicht genug Geld. Bonus kostet \$" . number_format($cost, 2) . "."];
        }

        return DB::transaction(function () use ($user, $employee, $meta, $cost) {
            $user->economy->debit($cost, "Halte-Prämie: {$employee->name}", 'hr', $employee);

            // Mandatory 15% raise
            $employee->salary = round($employee->salary * 1.15, 2);
            $employee->loyalty = 60; // Reset loyalty to a stable baseline
            
            unset($meta['resignation_at']);
            $employee->metadata = $meta;
            $employee->save();

            \App\Models\GameLog::log($user, "🤝 VERHANDLUNGSERFOLG: {$employee->name} hat die Kündigung zurückgezogen! Gehalt erhöht auf \${$employee->salary} (+15%).", 'success', 'hr');

            return [
                'success' => true,
                'message' => "{$employee->name} agreed to stay.",
                'new_salary' => $employee->salary,
            ];
        });
    }
}
