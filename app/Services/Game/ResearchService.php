<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\Research;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResearchService
{
    // Research Tree Definition
    // Organized by 'id'
    public const TECH_TREE = [
        // ─── INFRASTRUCTURE ───
        'cooling_v1' => [
            'name' => 'Server Efficiency I',
            'description' => 'Optimizes server cooling and internal airflow. Reduces power consumption and heat output.',
            'cost' => 500,
            'duration' => 60, // 1 min
            'category' => 'infrastructure',
            'effect' => ['type' => 'power_efficiency', 'value' => 0.05], // +5% eff
            'prerequisites' => [],
        ],
        'cooling_v2' => [
            'name' => 'Liquid Cooling Prototyping',
            'description' => 'Experiment with closed-loop liquid cooling. significantly reduces heat output.',
            'cost' => 2500,
            'duration' => 300, // 5 min
            'category' => 'infrastructure',
            'effect' => ['type' => 'power_efficiency', 'value' => 0.10], // +10% eff
            'prerequisites' => ['cooling_v1'],
        ],
        'rack_hv' => [
            'name' => 'High-Voltage Racks',
            'description' => 'Unlocks access to High-Voltage Racks which support more power-hungry servers.',
            'cost' => 5000,
            'duration' => 600,
            'category' => 'infrastructure',
            'effect' => ['type' => 'unlock', 'value' => 'rack_hv'],
            'prerequisites' => ['cooling_v1'],
        ],
        'cold_aisle' => [
            'name' => 'Cold Aisle Containment',
            'description' => 'Physically separates cold air intake from hot exhaust. Reduces cooling power usage by 25% and improves cooling efficiency.',
            'cost' => 8000,
            'duration' => 900,
            'category' => 'infrastructure',
            'effect' => ['type' => 'unlock', 'value' => 'cold_aisle_containment'],
            'prerequisites' => ['cooling_v2'],
        ],
        'e_waste_recycling' => [
            'name' => 'E-Waste Recycling Protocols',
            'description' => 'Implements environmentally friendly disposal methods. Reduces or removes Hazardous Waste Disposal Fees when liquidating broken hardware.',
            'cost' => 4500,
            'duration' => 600,
            'category' => 'infrastructure',
            'effect' => ['type' => 'waste_cost_reduction', 'value' => 1.0], // 100% reduction
            'prerequisites' => ['cooling_v1'],
        ],

        // ─── SOFTWARE & OPS ───
        'auto_provisioning' => [
            'name' => 'Automated Provisioning Script',
            'description' => 'Reduces the time it takes to setup new servers by 25%.',
            'cost' => 1000,
            'duration' => 120,
            'category' => 'software',
            'effect' => ['type' => 'provisioning_speed', 'value' => 0.25],
            'unlocks' => ['auto_provisioning'],
            'prerequisites' => [],
        ],
        'hypervisor_v1' => [
            'name' => 'Rack-Level Hypervisor',
            'description' => 'Install hypervisors to run multiple VMs per physical node. Doubles vServer capacity but increases shared failure risk.',
            'cost' => 12000,
            'duration' => 900,
            'category' => 'software',
            'effect' => ['type' => 'vserver_multiplier', 'value' => 2.0],
            'prerequisites' => ['auto_provisioning'],
        ],
        'monitoring_v1' => [
            'name' => 'Advanced Monitoring Agent',
            'description' => 'Detects faults earlier. Increases hardware lifespan by optimizing load.',
            'cost' => 2000,
            'duration' => 300,
            'category' => 'software',
            'effect' => ['type' => 'lifespan_bonus', 'value' => 0.10], // +10% lifespan
            'prerequisites' => ['auto_provisioning'],
        ],

        // ─── MARKETING ───
        'brand_awareness' => [
            'name' => 'Brand Identity Kit',
            'description' => 'Establishes a professional brand. Increases base customer rep gain from orders.',
            'cost' => 1500,
            'duration' => 180,
            'category' => 'marketing',
            'effect' => ['type' => 'rep_gain_multiplier', 'value' => 0.1], // +10% rep gain
            'prerequisites' => [],
        ],
        'corporate_sales' => [
            'name' => 'B2B Sales Training',
            'description' => 'Unlock Enterprise Customers. Only useful if you have high-end hardware.',
            'cost' => 8000,
            'duration' => 1200,
            'category' => 'marketing',
            'effect' => ['type' => 'unlock_customer_tier', 'value' => 'enterprise'],
            'prerequisites' => ['brand_awareness'],
        ],
        'financial_engineering' => [
            'name' => 'Financial Engineering',
            'description' => 'Unlocks access to Energy Futures and Derivatives. Hedge against price spikes and speculate on market trends.',
            'cost' => 15000,
            'duration' => 900,
            'category' => 'marketing',
            'effect' => ['type' => 'unlock', 'value' => 'energy_futures'],
            'prerequisites' => ['brand_awareness'],
        ],

        // ─── SECURITY ───
        'security_shield' => [
            'name' => 'Security Shield',
            'description' => 'Implements advanced intrusion detection. Reduces chance of security breach events by 40%.',
            'cost' => 3000,
            'duration' => 300,
            'category' => 'security',
            'effect' => ['type' => 'security_defense', 'value' => 0.40],
            'prerequisites' => ['monitoring_v1'],
        ],
        'cryogenic_cooling' => [
            'name' => 'Cryogenic Cooling Solutions',
            'description' => 'Unlocks the ability to use Liquid Nitrogen Overclocking. Extreme performance boosts at extreme risks.',
            'cost' => 25000,
            'duration' => 1200,
            'category' => 'infrastructure',
            'effect' => ['type' => 'unlock', 'value' => 'ln2_overclocking'],
            'prerequisites' => ['cooling_v2'],
        ],

        // ─── RESILIENCE ───
        'auto_recovery' => [
            'name' => 'Auto-Recovery System',
            'description' => 'Servers automatically attempt to restart after failures. 60% chance to self-heal without intervention.',
            'cost' => 6000,
            'duration' => 600,
            'category' => 'software',
            'effect' => ['type' => 'auto_recovery_chance', 'value' => 0.60],
            'unlocks' => ['auto_reboot'],
            'prerequisites' => ['monitoring_v1', 'auto_provisioning'],
        ],

        // ─── ENERGY ───
        'energy_optimizer' => [
            'name' => 'Energy Optimizer AI',
            'description' => 'AI-driven power management distributes load efficiently. Reduces total power cost by 15%.',
            'cost' => 4000,
            'duration' => 480,
            'category' => 'infrastructure',
            'effect' => ['type' => 'power_cost_reduction', 'value' => 0.15],
            'prerequisites' => ['cooling_v2'],
        ],
        'auto_cleanup' => [
            'name' => 'Garbage Collector Script',
            'description' => 'Unlocks the Garbage Collector automation module. Automatically terminates expired or non-compliant service contracts.',
            'cost' => 3500,
            'duration' => 300,
            'category' => 'software',
            'effect' => ['type' => 'unlock', 'value' => 'auto_cleanup'],
            'prerequisites' => ['auto_provisioning'],
        ],
        'cooling_automation' => [
            'name' => 'Adaptive Thermal Governor',
            'description' => 'Unlocks the Cooling Automation module. Dynamically adjusts room cooling intensity to maintain optimal temperatures with minimal power draw.',
            'cost' => 5500,
            'duration' => 600,
            'category' => 'infrastructure',
            'effect' => ['type' => 'unlock', 'value' => 'cooling_automation'],
            'prerequisites' => ['energy_optimizer'],
        ],
        // FEATURE 54: Heat Recovery System (District Heating)
        'thermal_grid_integration' => [
            'name' => 'Thermal Grid Integration',
            'description' => 'Sells waste heat to the local city district heating grid. Offsets power costs and provides a steady boost to Green Reputation.',
            'cost' => 15000,
            'duration' => 900,
            'category' => 'infrastructure',
            'effect' => ['type' => 'unlock', 'value' => 'heat_recovery'],
            'prerequisites' => ['cooling_automation'],
        ],
        'solar_panels' => [
            'name' => 'Solar Power Arrays',
            'description' => 'Unlocks the ability to install solar panels on facility roofs. Provides free green energy during daylight hours.',
            'cost' => 12000,
            'duration' => 600,
            'category' => 'infrastructure',
            'effect' => ['type' => 'unlock', 'value' => 'solar_panels'],
            'prerequisites' => ['energy_optimizer'],
        ],
        'battery_storage' => [
            'name' => 'Graphene Battery Modules',
            'description' => 'High-capacity energy storage. Allows storing surplus solar energy or cheap grid power for use during peak times.',
            'cost' => 18000,
            'duration' => 900,
            'category' => 'infrastructure',
            'effect' => ['type' => 'unlock', 'value' => 'battery_modules'],
            'prerequisites' => ['solar_panels'],
        ],

        // ─── EXPERIMENTAL TECH (PHASE 3) ───
        'fusion_pdu' => [
            'name' => 'Helium-3 Fusion PDU',
            'description' => 'Prototypes of fusion-based power distribution. Reduces net energy costs by 40% but introduces a 0.5% chance per tick of a localized power surge.',
            'cost' => 15000,
            'duration' => 900,
            'category' => 'experimental',
            'effect' => ['type' => 'power_cost_reduction', 'value' => 0.40],
            'prerequisites' => ['energy_optimizer'],
        ],
        'cryo_cooling' => [
            'name' => 'Sub-Zero Liquid Nitrogen Cooling',
            'description' => 'Extreme cooling phase. Unlocks "Cryo Racks" that can handle 2x heat output, but increases hardware failure rate by 20% due to condensation.',
            'cost' => 20000,
            'duration' => 1200,
            'category' => 'experimental',
            'effect' => ['type' => 'unlock', 'value' => 'cryo_racks'],
            'prerequisites' => ['cooling_v2'],
        ],
        'experimental_hardware' => [
            'name' => 'Experimental Silicon Foundry',
            'description' => 'Unlocks powerful but unstable server prototypes. These servers are extremely efficient but can suffer from "Quantum Drift" (sudden health drops).',
            'cost' => 30000,
            'duration' => 1800,
            'category' => 'experimental',
            'effect' => ['type' => 'unlock', 'value' => 'experimental_hardware'],
            'prerequisites' => ['monitoring_v1', 'cooling_v2'],
        ],

        // ─── NETWORKING ───
        'anycast_routing' => [
            'name' => 'Anycast Global Routing',
            'description' => 'Implements Anycast methodology. Reduces average network latency by 15% across all regions.',
            'cost' => 12000,
            'duration' => 600,
            'category' => 'networking',
            'effect' => ['type' => 'latency_reduction', 'value' => 0.15],
            'prerequisites' => ['monitoring_v1'],
        ],
        'ipv6_transition' => [
            'name' => 'IPv6 Dual-Stack Transition',
            'description' => 'Modernizes network stack for IPv6. Reduces IPv4 maintenance costs by 50% and doubles IPv6 allocation.',
            'cost' => 8000,
            'duration' => 480,
            'category' => 'networking',
            'effect' => ['type' => 'ipv4_cost_reduction', 'value' => 0.50],
            'prerequisites' => ['anycast_routing'],
        ],
        'flow_scrubbing' => [
            'name' => 'Next-Gen Flow Scrubbing',
            'description' => 'Advanced AI traffic analysis. Increases DDoS resilience and reduces reputation penalty during attacks.',
            'cost' => 15000,
            'duration' => 900,
            'category' => 'networking',
            'effect' => ['type' => 'ddos_resilience', 'value' => 0.40],
            'prerequisites' => ['anycast_routing', 'security_shield'],
        ],
        'redundant_backbone' => [
            'name' => 'Redundant Fiber Backbone',
            'description' => 'Establishes multiple geographical exit points for data. Halves the impact of global network failures.',
            'cost' => 20000,
            'duration' => 1200,
            'category' => 'networking',
            'effect' => ['type' => 'crisis_mitigation', 'value' => 'fiber_cut'],
            'prerequisites' => ['anycast_routing'],
        ],
        'tier1_peering' => [
            'name' => 'Tier-1 Peering Agreements',
            'description' => 'Establishes direct links with internet backbones. Increases total bandwidth capacity by 20% without adding new links.',
            'cost' => 25000,
            'duration' => 1200,
            'category' => 'networking',
            'effect' => ['type' => 'bandwidth_capacity_bonus', 'value' => 0.20],
            'prerequisites' => ['ipv6_transition'],
        ],
        'orbital_redundancy' => [
            'name' => 'Orbital Redundancy (Star-Link)',
            'description' => 'Unlocks an emergency satellite uplink. Prevents total connectivity loss during Fiber Cuts or BGP Hijacks at a high cost-per-GB.',
            'cost' => 35000,
            'duration' => 1800,
            'category' => 'networking',
            'effect' => ['type' => 'unlock', 'value' => 'orbital_redundancy'],
            'prerequisites' => ['redundant_backbone'],
        ],
        'cloud_bursting' => [
            'name' => 'Hybrid Cloud Bursting',
            'description' => 'Rent temporary external capacity from Global Cloud Providers. Prevents SLA breach when racks are full, but costs 5x normal OPEX.',
            'cost' => 25000,
            'duration' => 1500,
            'category' => 'software',
            'effect' => ['type' => 'unlock', 'value' => 'cloud_bursting'],
            'prerequisites' => ['orbital_redundancy'],
        ],

        // ─── SPECIALIZED DOCTRINE TECHS (LEVEL 20+) ───
        'emp_hardening' => [
            'name' => 'EMP Hardening',
            'description' => 'Shields critical components against electromagnetic pulses. Halves damage taken during Solar Superstorms.',
            'cost' => 12000,
            'duration' => 900,
            'category' => 'resilience',
            'effect' => ['type' => 'crisis_mitigation', 'value' => 'solar_flare'],
            'prerequisites' => ['monitoring_v1'],
        ],
        'market_hedging' => [
            'name' => 'Automated Market Hedging',
            'description' => 'AI protocols that automatically sell off risky assets during volatility. Reduces income loss during Market Crashes by 15%.',
            'cost' => 18000,
            'duration' => 900,
            'category' => 'marketing',
            'effect' => ['type' => 'crisis_mitigation', 'value' => 'market_crash'],
            'prerequisites' => ['brand_awareness'],
        ],
        'tax_evasion_ai' => [
            'name' => 'Automated Tax Optimization',
            'description' => 'Advanced AI loops that route revenue through off-shore digital zones. Reduces tax rate by 50%.',
            'cost' => 150000,
            'duration' => 3600,
            'category' => 'experimental',
            'effect' => ['type' => 'tax_reduction', 'value' => 0.50],
            'prerequisites' => ['corporate_sales'],
            'specialization' => 'budget_mass',
        ],
        'quantum_buffer' => [
            'name' => 'Quantum Entanglement Buffer',
            'description' => 'Utilizes quantum states for instant packet filtering. 80% DDoS protection and 0ms latency processing.',
            'cost' => 250000,
            'duration' => 5400,
            'category' => 'networking',
            'effect' => ['type' => 'ddos_resilience', 'value' => 0.80],
            'prerequisites' => ['flow_scrubbing'],
            'specialization' => 'high_performance',
        ],
        'advanced_encryption' => [
            'name' => 'Next-Gen Decryption Protocols',
            'description' => 'Advanced algorithmic tools for countering encryption-based attacks. Increases success chance of backup restores during Ransomware by 25%.',
            'cost' => 15000,
            'duration' => 600,
            'category' => 'security',
            'effect' => ['type' => 'crisis_mitigation', 'value' => 'crypto_ransom'],
            'prerequisites' => ['security_shield'],
        ],
        'neural_backplane' => [
            'name' => 'Neural Liquid Backplane',
            'description' => 'A biological-synthetic hybrid rack backplane. Doubles the density of ML orders without extra heat.',
            'cost' => 300000,
            'duration' => 7200,
            'category' => 'experimental',
            'effect' => ['type' => 'ml_density_bonus', 'value' => 1.0],
            'prerequisites' => ['experimental_hardware'],
            'specialization' => 'hpc_specialist',
        ],
        'geothermal_link' => [
            'name' => 'Geothermal Direct Link',
            'description' => 'Taps directly into regional thermal vents. Reduces power costs by 60% in suitable regions.',
            'cost' => 120000,
            'duration' => 3000,
            'category' => 'infrastructure',
            'effect' => ['type' => 'power_cost_reduction', 'value' => 0.60],
            'prerequisites' => ['cooling_automation'],
            'specialization' => 'eco_certified',
        ],
        'darknet_peering' => [
            'name' => 'Darknet Shadow Peering',
            'description' => 'Routes traffic through unlisted autonomous systems. Completely hides your true IP from BGP hijacks.',
            'cost' => 180000,
            'duration' => 4200,
            'category' => 'networking',
            'effect' => ['type' => 'bgp_immunity', 'value' => 1.0],
            'prerequisites' => ['ipv6_transition'],
            'specialization' => 'crypto_vault',
        ],

        // --- FEATURE 205: PROPRIETARY OS ---
        'os_kernel_dev' => [
            'name' => 'OS Kernel Development',
            'description' => 'Begin development of your own proprietary OS kernel. Reduces dependency on external licenses.',
            'cost' => 50000,
            'duration' => 7200, // 2 hours
            'category' => 'experimental',
            'prerequisites' => ['hypervisor_v1'],
            'effect' => ['type' => 'unlock', 'value' => 'os_dev'],
        ],
        'modernization_protocols' => [
            'name' => 'Legacy Modernization Protocols',
            'description' => 'Unlocks Modernization Missions. Revitalize aged racks and servers by assigning a specialized team. Resets technical debt and efficiency penalties.',
            'cost' => 40000,
            'duration' => 2400,
            'category' => 'software',
            'effect' => ['type' => 'unlock', 'value' => 'modernization_missions'],
            'prerequisites' => ['hypervisor_v1'],
        ],
        'proprietary_os_v1' => [
            'name' => 'PonyOS v1.0 (Alpha)',
            'description' => 'Your first stable proprietary OS build. Zero licensing costs and higher customer retention.',
            'cost' => 150000,
            'duration' => 14400, // 4 hours
            'category' => 'experimental',
            'prerequisites' => ['os_kernel_dev'],
            'effect' => ['type' => 'customer_retention', 'value' => 0.15],
            'unlocks' => ['pony_os_v1'],
        ],
    ];

    /**
     * Get available research projects with current status.
     * Status: locked, available, researching, completed
     */
    /**
     * Get available research projects with current status.
     * Status: locked, available, researching, completed
     */
    public function getResearchState(User $user): array
    {
        $techTree = \App\Models\GameConfig::get('research_tree', self::TECH_TREE);
        $userResearch = Research::where('user_id', $user->id)->get()->keyBy('tech_id');
        
        $result = [];
        
        foreach ($techTree as $id => $tech) {
            $record = $userResearch->get($id);
            $status = 'locked';
            $progress = 0;
            
            if ($record) {
                $status = $record->status;
                $progress = $record->progress;
            } else {
                // Check prerequisites
                $prereqsMet = true;
                foreach ($tech['prerequisites'] ?? [] as $prereqId) {
                    if (!$userResearch->has($prereqId) || $userResearch->get($prereqId)->status !== 'completed') {
                        $prereqsMet = false;
                        break;
                    }
                }
                
                if ($prereqsMet) {
                    $status = 'available';
                }

                // Check Specialization
                if (isset($tech['specialization']) && $tech['specialization'] !== $user->economy->corporate_specialization) {
                    $status = 'locked';
                    // Optional: hide it completely? or show with lock?
                    // User requested Level 20+ specialized techs, showing them as locked by specialization is better for "desire"
                }
            }
            
            $isBusy = Research::where('user_id', $user->id)->where('status', 'researching')->exists();
            
            $remainingSeconds = null;
            if ($status === 'researching' && $tech['duration'] > 0) {
                $remainingSeconds = max(0, round($tech['duration'] * (1 - ($progress / 100))));
            }
            
            $result[] = [
                'id' => $id,
                'name' => $tech['name'],
                'description' => $tech['description'],
                'category' => $tech['category'],
                'cost' => $tech['cost'],
                'duration' => $tech['duration'],
                'prerequisites' => $tech['prerequisites'] ?? [],
                'effect' => $tech['effect'] ?? null,
                'status' => $status,
                'progress' => $progress,
                'remaining_seconds' => $remainingSeconds,
                'is_busy' => $isBusy && $status === 'available'
            ];
        }
        
        return $result;
    }

    public function startResearch(User $user, string $techId): Research
    {
        $techTree = \App\Models\GameConfig::get('research_tree', self::TECH_TREE);

        if (!isset($techTree[$techId])) {
            throw new \Exception("Invalid research ID.");
        }
        
        $tech = $techTree[$techId];
        
        // 1. Check if already researching anything
        if (Research::where('user_id', $user->id)->where('status', 'researching')->exists()) {
             throw new \Exception("Research lab is busy.");
        }
        
        // 2. Check if already researched
        $existing = Research::where('user_id', $user->id)->where('tech_id', $techId)->first();
        if ($existing && $existing->status === 'completed') {
            throw new \Exception("Already researched.");
        }
        
        // 3. Check Prereqs
        foreach ($tech['prerequisites'] ?? [] as $prereqId) {
             $prereq = Research::where('user_id', $user->id)
                ->where('tech_id', $prereqId)
                ->where('status', 'completed')
                ->first();
             if (!$prereq) {
                 // Try to get name safely
                 $prereqName = $techTree[$prereqId]['name'] ?? 'Unknown Tech';
                 throw new \Exception("Missing prerequisite: " . $prereqName);
             }
        }
        
        // 4. Check Funds
        if (!$user->economy->canAfford($tech['cost'])) {
            throw new \Exception("Insufficient funds.");
        }
        
        return DB::transaction(function() use ($user, $techId, $tech, $existing) {
            $user->economy->debit($tech['cost'], "Research: {$tech['name']}", 'research');
            
            if ($existing) {
                // Restarting a paused/failed research?
                $existing->status = 'researching';
                $existing->started_at = now();
                $existing->save();
                return $existing;
            }
            
            \App\Models\GameLog::log($user, "RESEARCH_STARTED: {$tech['name']}", 'info', 'research');

            return Research::create([
                'user_id' => $user->id,
                'tech_id' => $techId,
                'status' => 'researching',
                'progress' => 0,
                'started_at' => now(),
            ]);
        });
    }

    public function tick(User $user): void
    {
        $active = Research::where('user_id', $user->id)
            ->where('status', 'researching')
            ->first();
            
        if (!$active) return;
        
        $techTree = \App\Models\GameConfig::get('research_tree', self::TECH_TREE);
        $tech = $techTree[$active->tech_id] ?? null;

        if (!$tech) {
            // Edge case: Tech ID removed from config while running? just pause/cancel
            // For now, assume consistent config
            return; 
        }

        // Calculated progress per minute (assuming tick is 60s)
        // If duration is 60s -> 100% per tick
        // If duration is 300s -> 20% per tick
        $duration = $tech['duration'] > 0 ? $tech['duration'] : 60;
        $progressPerTick = (60 / $duration) * 100; 
        
        // --- FEATURE 136: Diversity & Innovation Bonus ---
        // Diverse staff composition accelerates research progress by 10%.
        $uniqueRolesCount = \App\Models\Employee::where('user_id', $user->id)->distinct('type')->count('type');
        if ($uniqueRolesCount >= 4) { // At least 4 different roles (e.g. SysAdmin, Support, Marketing, Hardware)
            $progressPerTick *= 1.10; // 10% Bonus
        }
        
        $active->progress += $progressPerTick;
        
        if ($active->progress >= 100) {
            $active->progress = 100;
            $active->status = 'completed';
            $active->completed_at = now();
            
            Log::info("User {$user->id} completed research: {$tech['name']}");
            
            // Log to database
            \App\Models\GameLog::log($user, "Research Complete: {$tech['name']}", 'success', 'research');

            // Broadcast event
            broadcast(new \App\Events\ResearchCompleted($user, $active, $tech));

            // Award XP
            $user->economy->addExperience(100);

            // Award Specialized Reputation
            switch ($tech['category'] ?? 'general') {
                case 'security':
                    $user->economy->adjustSpecializedReputation('premium', 5.0);
                    break;
                case 'energy':
                case 'infrastructure':
                    $user->economy->adjustSpecializedReputation('green', 3.0);
                    $user->economy->adjustSpecializedReputation('budget', 2.0);
                    break;
                case 'experimental':
                    $user->economy->adjustSpecializedReputation('hpc', 10.0);
                    break;
                case 'software':
                    $user->economy->adjustSpecializedReputation('budget', 3.0);
                    break;
            }
            $user->economy->save();
        }
        
        $active->save();
    }
    
    protected array $completedResearchCache = [];

    /**
     * Cache completed research queries per request to prevent N+1 queries.
     */
    public function getCompletedResearch(User $user)
    {
        if (!isset($this->completedResearchCache[$user->id])) {
            $this->completedResearchCache[$user->id] = Research::where('user_id', $user->id)
                ->where('status', 'completed')
                ->get();
        }
        return $this->completedResearchCache[$user->id];
    }

    /**
     * Get cumulative bonus for a specific effect type.
     */
    public function getBonus(User $user, string $effectType): float
    {
        $techTree = \App\Models\GameConfig::get('research_tree', self::TECH_TREE);
        $completed = $this->getCompletedResearch($user);
            
        $bonus = 0.0;
        
        foreach ($completed as $r) {
            $tech = $techTree[$r->tech_id] ?? null;
            if ($tech && ($tech['effect']['type'] ?? '') === $effectType) {
                if (isset($tech['effect']['value']) && is_numeric($tech['effect']['value'])) {
                    $bonus += $tech['effect']['value'];
                }
            }
        }
        
        return $bonus;
    }
    
    /**
     * Check if a specific effect type and value is unlocked/active.
     */
    public function hasEffect(User $user, string $type, string $value): bool
    {
        $techTree = \App\Models\GameConfig::get('research_tree', self::TECH_TREE);
        $completed = $this->getCompletedResearch($user);
            
        foreach ($completed as $r) {
            $tech = $techTree[$r->tech_id] ?? null;
            if ($tech && ($tech['effect']['type'] ?? '') === $type && ($tech['effect']['value'] ?? '') === $value) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if a specific unlock value is present.
     */
    public function isUnlocked(User $user, string $value): bool
    {
        $techTree = \App\Models\GameConfig::get('research_tree', self::TECH_TREE);
        $completed = $this->getCompletedResearch($user);
            
        foreach ($completed as $r) {
            $tech = $techTree[$r->tech_id] ?? null;
            if (!$tech) continue;

            // Check effect field
            if (isset($tech['effect']) && ($tech['effect']['type'] ?? '') === 'unlock' && ($tech['effect']['value'] ?? '') === $value) {
                return true;
            }

            // Check unlocks array
            if (isset($tech['unlocks']) && is_array($tech['unlocks']) && in_array($value, $tech['unlocks'])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get all currently active research bonuses for a user.
     */
    public function getAllActiveBonuses(User $user): array
    {
        $techTree = \App\Models\GameConfig::get('research_tree', self::TECH_TREE);
        $completed = Research::where('user_id', $user->id)
            ->where('status', 'completed')
            ->get();
            
        $bonuses = [];
        
        foreach ($completed as $r) {
            $tech = $techTree[$r->tech_id] ?? null;
            if ($tech && isset($tech['effect']['type'])) {
                $type = $tech['effect']['type'];
                $value = $tech['effect']['value'] ?? 0;
                
                if (is_numeric($value)) {
                    $bonuses[$type] = ($bonuses[$type] ?? 0) + $value;
                } else {
                    // For non-numeric (unlocks/mitigations), keep as array/tag
                    if (!isset($bonuses[$type])) $bonuses[$type] = [];
                    if (is_array($bonuses[$type])) {
                        $bonuses[$type][] = $value;
                    }
                }
            }
        }
        
        return $bonuses;
    }

    /**
     * Check if a specific technology is completed.
     */
    public function hasResearch(User $user, string $techId): bool
    {
        return Research::where('user_id', $user->id)
            ->where('tech_id', $techId)
            ->where('status', 'completed')
            ->exists();
    }
}
