<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Services\Game\ResearchService;
use App\Services\Game\PlayerSkillService;
use App\Models\WorldEvent;
use Carbon\Carbon;
use App\Models\GameLog;
use Illuminate\Support\Str;

class CustomerOrderService
{
    // FEATURE 84: Procedural Startup Identities
    private const COMPANY_PREFIXES = [
        'Nebula', 'Vertex', 'Axiom', 'Cipher', 'Quanta', 'Helix', 'Stratos', 'Synapse', 
        'Zenith', 'Prism', 'Vortex', 'Nexus', 'Radiant', 'Flux', 'Orbit', 'Pulse', 
        'Marina', 'Cobalt', 'Verdant', 'Onyx', 'Cascade', 'Ember', 'Aurora', 'Titan',
        'GridPeak', 'DataForge', 'CloudSpire', 'NetBridge', 'ByteShift', 'CodeNest',
        'PlatformX', 'StackNode', 'LogiCore', 'InfraBase', 'PacketWave', 'CruxOps',
    ];
    private const COMPANY_SUFFIXES = [
        'Labs', 'Cloud', 'Systems', 'Analytics', 'Stream', 'Protocol', 'Logic', 'AI',
        'Networks', 'Digital', 'Platform', 'Dynamics', 'Ventures', 'Technologies', 'Studio',
        'Compute', 'Research', 'Ops', 'Intelligence', 'Solutions', 'Engine', 'Hub',
    ];
    private const STARTUP_FULLNAMES = [
        'Neon Wavelength', 'Infinite Ledger', 'Deep Orchid', 'Quantum Bloom', 
        'Carbon Theory', 'Mirror Lattice', 'Photon Gate', 'Zero Point Studio',
        'Abstract Canvas', 'Binary Horizon', 'Parallel Mind', 'Signal Drift',
        'North Latitude', 'Velocity Nine', 'Copper Atlas', 'Silver Thread',
        'Bright Fossil', 'Molten Core', 'Tidal Basin', 'Static Pulse',
    ];
    private const FIRST_NAMES = ['James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda', 'William', 'Elizabeth'];
    private const LAST_NAMES = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
    private const ENTERPRISE_NAMES = ['FlixNet', 'MarcoSoft', 'MacroHard', 'Pear Computers', 'Giggle', 'AmazeOn', 'BookFace', 'SpaceX', 'WeirderBank', 'GIGA-Corp', 'Omni-Consumer-Products'];
    private const GOV_NAMES = ['Global Intel Agency', 'Federal Data Vault', 'National Cyber Defense', 'Bureau of Algorithms', 'Treasury Sec-Ops', 'Central Archive Authority'];

    public function __construct(
        protected ResearchService $researchService,
        protected MarketService $marketService,
        protected PlayerSkillService $skillService,
        protected NetworkService $networkService,
        protected MarketingService $marketingService
    ) {}

    /**
     * Main tick function called by the game loop
     */
    public function tick(User $user): void
    {
        // Check for expired pending orders (deadlines not met)
        $this->expirePendingOrders($user);

        // Check for expired active contracts (renewal/termination)
        $this->processActiveExpirations($user);

        // Automation: Smart Provisioning
        if ($user->economy->isAutomationEnabled('auto_provisioning')) {
            $this->processAutoProvisioning($user);
        }

        // Chance to generate new order
        if ($this->shouldGenerateOrder($user)) {
            $this->generateNewOrder($user);
        }

        // Process Satisfaction / Accessibility
        $this->processActiveSatisfaction($user);
    }

    /**
     * Expire orders that have passed their patience deadline
     */
    private function expirePendingOrders(User $user): void
    {
        $expiredOrders = CustomerOrder::whereHas('customer', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'pending')
            ->where('patience_expires_at', '<=', now())
            ->get();

        foreach ($expiredOrders as $order) {
            $order->status = 'cancelled';
            $order->save();

            // Small reputation penalty for letting orders expire
            // Reduced to -0.25 and only applied if rep > 10 to prevent death spiral
            if (($user->economy->reputation ?? 0) > 10) {
                $user->economy->adjustReputation(-0.25);
            }
        }
    }

    /**
     * Periodically check active orders for satisfaction and accessibility
     */
    private function processActiveSatisfaction(User $user): void
    {
        $activeOrders = CustomerOrder::whereHas('customer', fn($q) => $q->where('user_id', $user->id))
            ->where('status', 'active')
            ->with(['customer', 'server.privateNetwork'])
            ->get();

        foreach ($activeOrders as $order) {
            $satisfactionPenalty = 0;
            $customer = $order->customer;

            // 1. Accessibility Check (Firewall)
            $requiredPorts = $order->requirements['ports'] ?? [];
            if (!empty($requiredPorts) && $order->server && $order->server->privateNetwork) {
                $blockedPorts = [];
                $vnet = $order->server->privateNetwork;
                $vnetService = app(PrivateNetworkService::class);

                foreach ($requiredPorts as $port) {
                    // Check if public traffic (0.0.0.0) can reach this port on the VPC
                    if (!$vnetService->isTrafficAllowed($vnet, 'TCP', $port, '0.0.0.0')) {
                        $blockedPorts[] = $port;
                    }
                }

                if (!empty($blockedPorts)) {
                    $satisfactionPenalty += 2.0; // Moderate penalty per tick for downtime
                    $customer->registerIncident("Unreachable ports: " . implode(', ', $blockedPorts));
                    
                    if (rand(1, 20) === 1) {
                        GameLog::log($user, "Customer {$customer->company_name} is complaining about firewall blocking port(s): " . implode(', ', $blockedPorts), 'warning', 'network');
                    }
                }
            }

            // 1.b Region Latency Check
            $targetRegion = $customer->preferences['target_region'] ?? null;
            $maxLatency = $order->requirements['max_latency_ms'] ?? 150;

            if ($order->server && $order->server->rack && $order->server->rack->room) {
                $room = $order->server->rack->room;
                $serverRegion = $room->region;
                
                // Old region check (fallback if latency isn't configured)
                if ($targetRegion && $serverRegion !== 'unknown' && $serverRegion !== $targetRegion) {
                    $satisfactionPenalty += 0.5; // Slight constant penalty 
                }

                // Dynamic Latency Check
                $currentLatency = $room->latency_ms ?? 100;
                if ($currentLatency > $maxLatency) {
                    $excess = $currentLatency - $maxLatency;
                    $latencyPenalty = min(3.0, $excess / 50.0); // 1 point per 50ms over max
                    $satisfactionPenalty += $latencyPenalty;
                    
                    if (rand(1, 40) === 1) {
                        GameLog::log($user, "Customer {$customer->company_name} is experiencing severe latency ({$currentLatency}ms vs max allowed {$maxLatency}ms).", 'warning', 'network');
                    }
                }
            }

            // 2. Health Check
            if ($order->server && $order->server->health < 40) {
                $satisfactionPenalty += 1.5;
            }

            // Apply penalty if found
            if ($satisfactionPenalty > 0) {
                $customer->satisfaction = max(0, $customer->satisfaction - $satisfactionPenalty);
                $customer->save();

                // Generate Support Ticket on major penalties
                if (rand(1, 15) === 1 || $satisfactionPenalty > 2.0) {
                    app(SupportService::class)->generateTicket($user, $customer, $satisfactionPenalty);
                }

                // FEATURE: Negative Review Generation (5% chance when unhappy per tick)
                if (rand(1, 100) <= 5) {
                    $customer->generateReview('dissatisfied');
                }
            } else {
                // Slow satisfaction recovery if all is well
                if ($customer->satisfaction < 100) {
                    $baseRecovery = 0.1;

                    // Specialization V2: Retention Specialist (Satisfaction Bonus)
                    $empService = app(EmployeeService::class);
                    $satBonus = $empService->getAggregatedBonus($user, 'satisfaction_bonus');
                    if ($satBonus > 0) {
                         $baseRecovery *= $satBonus;
                    }

                    $customer->satisfaction = min(100, $customer->satisfaction + $baseRecovery);
                    $customer->save();
                    
                    // FEATURE: Occasional Positive Review (0.2% chance for very happy ones)
                    if ($customer->satisfaction > 90 && rand(1, 500) === 1) {
                        $customer->generateReview('periodic');
                    }
                }
            }
        }
    }

    /**
     * Handle active contracts (renewal or termination)
     */
    public function processActiveExpirations(User $user): void
    {
        $expired = CustomerOrder::whereHas('customer', fn($q) => $q->where('user_id', $user->id))
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $order) {
            // 60% base chance + Satisfaction bonus
            $satisfaction = $order->customer->satisfaction; // 0-100
            $renewalChance = 30 + ($satisfaction * 0.5); // Max 80%
            
            if (rand(1, 100) <= $renewalChance) {
                // RENEW
                $order->expires_at = now()->addMonths($order->contract_months);
                $order->save();
                
                \Log::info("Order {$order->id} renewed for {$order->contract_months} months.");
            } else {
                // TERMINATE
                $order->status = 'completed';
                $order->save();
                \Log::info("Order {$order->id} terminated naturally (contract ended).");
                
                // Good performance reward
                $user->economy->adjustReputation(3.0);
                $user->economy->addExperience(50);
            }
        }
    }

    /**
     * Determine if a new order should be generated
     */
    private function shouldGenerateOrder(User $user): bool
    {
        $pendingCount = CustomerOrder::whereHas('customer', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->where('status', 'pending')->count();

        // Max pending orders based on level (minimum 3, scales with level)
        $maxPending = 3 + floor(($user->economy->level ?? 1) / 3);

        // Guard: Don't generate if pending pool is full
        if ($pendingCount >= $maxPending) {
            return false;
        }

        // ─── BASE CHANCE ───
        // Reputation 0: 15% base | Reputation 50: 25% | Reputation 100: 35%
        $reputation = $user->economy->reputation ?? 50;
        $chance = 15 + ($reputation / 5);

        // Comeback bonus: When reputation is very low, give extra chance
        // so players can recover from the death spiral
        if ($reputation < 25) {
            // Up to +10% bonus at 0 reputation, linearly decreasing to 0% at 25 rep
            $comebackBonus = (25 - $reputation) / 25 * 10;
            $chance += $comebackBonus;
        }

        // Level bonus: Higher level players attract more inquiries
        $level = $user->economy->level ?? 1;
        $chance += min(10, $level * 0.5); // Up to +10% from level (at level 20+)
        
        // HQ Prestige Bonus
        if ($user->hq) {
             $chance *= (1.0 + ($user->hq->prestige_score / 2000.0)); // e.g. 500 prestige = +25% frequency
        }

        // ─── MULTIPLIERS ───
        // Apply World Modifier: order_frequency
        // Use regional modifiers if the player has rooms in a specific region
        $playerRegion = $this->getPlayerPrimaryRegion($user);
        $modifiers = $playerRegion 
            ? \App\Models\WorldEvent::getActiveModifiersForRegion($playerRegion)
            : \App\Models\WorldEvent::getActiveModifiers();
        
        // Feature 65: Also incorporate Global Crisis modifiers
        $crisisService = app(\App\Services\Game\GlobalCrisisService::class);
        $crisisModifiers = $crisisService->getActiveModifiers($user);
        $modifiers = array_merge($modifiers, $crisisModifiers);

        if (isset($modifiers['order_frequency'])) {
            $chance *= $modifiers['order_frequency'];
        }

        // Apply Marketing Campaign Multiplier
        $marketingService = app(\App\Services\Game\MarketingService::class);
        $marketingMultiplier = $marketingService->getActiveCampaignMultiplier($user);
        $chance *= $marketingMultiplier;

        // Apply NPC Competition Penalty
        $competitiveModifier = $this->marketService->getCompetitiveModifier($user);
        $chance *= $competitiveModifier;

        // Apply Strategic Policy Modifier: market_focus
        $marketFocus = $user->economy->getPolicy('market_focus', 'balanced');
        $policyModifiers = \App\Services\Game\ManagementService::DECISIONS['market_focus']['options'][$marketFocus]['modifiers'] ?? [];
        $chance *= ($policyModifiers['order_frequency'] ?? 1.0);

        // Loyalty Bonus: Happy customers generate more orders
        // Note: avg() returns null when no rows match, must handle separately
        $avgSatisfaction = Customer::where('user_id', $user->id)
            ->where('status', 'active')
            ->avg('satisfaction');
        $avgSatisfaction = $avgSatisfaction ?? 50; // Default if no active customers
        
        if ($avgSatisfaction > 70) {
            // Up to +30% more orders at 100 satisfaction
            $loyaltyBonus = 1.0 + (($avgSatisfaction - 70) / 100);
            $chance *= $loyaltyBonus;
        }
        
        // Check IP Pool Availability (Rackora Network Logic)
        $network = $user->network;
        if ($network && $network->ipv4_used >= $network->ipv4_total) {
            // Pool is full — block order generation but don't penalize reputation
            // (Reputation penalty here was causing death spiral via per-tick drain)
            return false;
        }

        // ─── FLOOR: Absolute minimum chance after all multipliers ───
        // This is the critical anti-death-spiral mechanism.
        // Even in the worst case, there's always SOME chance of an order.
        $chance = max(8, $chance);

        return rand(0, 100) < $chance;
    }

    /**
     * Generate a new customer and order.
     * Prioritizes existing happy customers to simulate loyalty and word-of-mouth.
     */
    public function generateNewOrder(User $user): CustomerOrder
    {
        $customer = null;
        $roll = rand(1, 100);

        // 1. Calculate Average Loyalty Chance based on existing customer satisfaction
        $avgSatisfaction = Customer::where('user_id', $user->id)
            ->where('status', 'active')
            ->avg('satisfaction') ?? 50;

        // Base distribution:
        // High satisfaction (>80) -> 85% Returning / 15% New
        // Neutral satisfaction (50) -> 70% Returning / 30% New
        // Low satisfaction (<30) -> 40% Returning / 60% New
        $returningChance = 40 + ($avgSatisfaction * 0.45); 

        if ($roll <= $returningChance) {
            // RETURNING CUSTOMER PATH
            $subRoll = rand(1, 100);
            
            if ($subRoll <= 60) {
                // 60% of returning: IDLE customers (Clean reactivation)
                $customer = Customer::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->whereDoesntHave('orders', function ($q) {
                        $q->whereIn('status', ['active', 'provisioning', 'pending']);
                    })
                    ->inRandomOrder()
                    ->first();
            }

            if (!$customer) {
                // FALLBACK or 40% of returning: UP-SELLING to existing active customers
                // Only happy customers (satisfaction >= 60) consider taking an additional service
                $customer = Customer::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('satisfaction', '>=', 60)
                    ->inRandomOrder()
                    ->first();
            }
        }

        // 2. BRAND NEW CUSTOMER PATH (If above failed or roll was high)
        if (!$customer) {
            // Predict SLA Tier here to pass to customer creation
            $level = $user->economy->level ?? 1;
            $slaTiers = ['standard'];
            if ($this->researchService->hasEffect($user, 'unlock_customer_tier', 'premium') || $level >= 10) $slaTiers[] = 'premium';
            if ($this->researchService->hasEffect($user, 'unlock_customer_tier', 'enterprise') || $level >= 25) $slaTiers[] = 'enterprise';
            
            $marketingService = app(\App\Services\Game\MarketingService::class);
            $hasEnterpriseBoost = $marketingService->hasEnterpriseCampaign($user);

            // Enterprise Marketing Boost: Much higher chance to get Enterprise clients!
            if ($hasEnterpriseBoost && rand(1, 100) <= 60 && in_array('enterprise', $slaTiers)) {
                $predictedSla = 'enterprise';
            } else {
                // To keep arrays balanced if we add 'enterprise' multiple times, just pick one.
                $predictedSla = $slaTiers[array_rand($slaTiers)];
            }
            
            $customer = $this->createCustomer($user, ($predictedSla === 'enterprise' || $predictedSla === 'whale'), ($predictedSla === 'whale'));
            return $this->createOrderForCustomer($user, $customer, $predictedSla);
        }

        return $this->createOrderForCustomer($user, $customer);
    }
    private function createCustomer(User $user, bool $isEnterprise = false, bool $isHighCompliance = false): Customer
    {
        $company = $this->generateCompanyName($isEnterprise, $isHighCompliance);
        $contact = $this->generateContactName();
        
        // ─── Smart Region Selection ───
        // Only select regions unlocked by the player's level
        $playerLevel = $user->economy->level ?? 1;
        $regionsConfig = \App\Models\GameConfig::get('regions', ['us_east' => []]);
        
        $availableRegions = [];
        foreach ($regionsConfig as $key => $data) {
            if ($playerLevel >= ($data['level_required'] ?? 1)) {
                // Weight by demand_weight for more realistic distribution
                $weight = (int)(($data['demand_weight'] ?? 1.0) * 100);
                for ($i = 0; $i < $weight; $i++) {
                    $availableRegions[] = $key;
                }
            }
        }
        
        if (empty($availableRegions)) {
            $availableRegions = ['us_east'];
        }
        
        $targetRegion = $availableRegions[array_rand($availableRegions)];
        $targetRegionData = $regionsConfig[$targetRegion] ?? [];
        $regionPrefs = $targetRegionData['preferences'] ?? [];

        // ─── Regional Enterprise Bias ───
        // Some regions naturally attract more enterprise clients
        $enterpriseRatio = $regionPrefs['enterprise_ratio'] ?? 0.20;
        if (!$isEnterprise && rand(1, 100) <= ($enterpriseRatio * 100)) {
            $isEnterprise = true;
        }

        // ─── Regional Compliance Bias ───
        // EU and Nordic regions push compliance-heavy customers
        $complianceWeight = $regionPrefs['compliance_weight'] ?? 0.3;
        if (!$isHighCompliance && rand(1, 100) <= ($complianceWeight * 100)) {
            $isHighCompliance = true; 
        }

        // ─── Performance Focus (Asia, Nordics) ───
        $isPerformanceFocused = (bool)($regionPrefs['is_performance_focused'] ?? false);
        if (!$isPerformanceFocused && rand(1, 100) <= 15) {
            $isPerformanceFocused = true; // Small global random chance
        }

        // ─── Eco Focus ───
        $isEcoFocused = (bool)($regionPrefs['is_eco_focused'] ?? false);
        if (!$isEcoFocused && rand(1, 100) <= 10) {
            $isEcoFocused = true;
        }

        // ─── Tier Determination ───
        // Diamond = Compliance-heavy Enterprise (rarest)
        // Enterprise = High-value corporate clients
        // Silver = Mid-range returning customers  
        // Bronze = Starter / budget customers
        $tier = 'bronze';
        if ($isHighCompliance && $isEnterprise) {
            $tier = 'diamond';
        } elseif ($isEnterprise) {
            $tier = 'enterprise';
        } elseif (rand(1, 100) <= 40) {
            $tier = 'silver';
        }

        // ─── Procedural Branding ───
        $colors = ['#fbbf24', '#10b981', '#06b6d4', '#3b82f6', '#8b5cf6', '#ec4899', '#f43f5e', '#14b8a6', '#f97316', '#6366f1'];
        $icons = ['activity', 'box', 'cloud', 'database', 'globe', 'hash', 'layers', 'package', 'server', 'zap', 'shield', 'cpu'];
        $shapes = ['circle', 'square', 'rounded'];

        // ─── Patience Scaling ───
        // Higher-tier customers have more patience but zero tolerance for incidents
        $patience = match($tier) {
            'diamond' => rand(200, 300),
            'enterprise' => rand(100, 180),
            'silver' => rand(50, 90),
            default => rand(30, 70),
        };
        
        $toleranceIncidents = match($tier) {
            'diamond' => 0,
            'enterprise' => 1,
            'silver' => 2,
            default => 3,
        };

        return Customer::create([
            'user_id' => $user->id,
            'name' => $contact,
            'company_name' => $company,
            'tier' => $tier,
            'revenue_per_month' => 0,
            'satisfaction' => rand(45, 65), // Slightly varied initial satisfaction
            'patience_minutes' => $patience,
            'tolerance_incidents' => $toleranceIncidents,
            'incidents_count' => 0,
            'status' => 'active',
            'acquired_at' => now(),
            'preferences' => [
                'target_region' => $targetRegion,
                'is_compliance_heavy' => $isHighCompliance,
                'is_performance_focused' => $isPerformanceFocused,
                'is_eco_focused' => $isEcoFocused,
                'brand_color' => $colors[array_rand($colors)],
                'brand_icon' => $icons[array_rand($icons)],
                'brand_shape' => $shapes[array_rand($shapes)],
            ],
        ]);
    }


    private function createOrderForCustomer(User $user, Customer $customer, ?string $preSelectedSla = null): CustomerOrder
    {
        $level = $user->economy->level ?? 1;
        $products = $this->getProductConfig();

        // Determine available order types based on config min_level
        $types = [];
        foreach ($products as $key => $conf) {
             if ($level >= ($conf['min_level'] ?? 1)) {
                 $types[] = $key;
             }
        }
        
        if (empty($types)) $types = ['web_hosting']; // Safe fallback

        // SPECIALIZATION: Weighted Random for Order Type
        $specService = app(\App\Services\Game\SpecializationService::class);
        $specMods = $specService->getActiveModifiers($user);
        $typeWeights = $specMods['order_types'] ?? [];

        // SPECIALIZED REPUTATION: Add dynamic weight based on history
        $economy = $user->economy;
        $repBudget = $economy->getSpecializedReputation('budget');
        $repPremium = $economy->getSpecializedReputation('premium');
        $repHpc = $economy->getSpecializedReputation('hpc');

        $weightedTypes = [];
        foreach ($types as $t) {
            $baseWeight = $typeWeights[$t] ?? 1.0;
            
            // Add reputation bonus to weights
            $repBonus = 1.0;
            if ($t === 'web_hosting') {
                $repBonus += ($repBudget / 50.0);
            } elseif ($t === 'dedicated' || $t === 'database_hosting' || $t === 'game_server') {
                $repBonus += ($repPremium / 50.0);
            } elseif ($t === 'ml_training') {
                $repBonus += ($repHpc / 25.0); // HPC is rarer, so scale it more
            }

            $weight = $baseWeight * $repBonus;

            // Simple expansion for weighted random
            $count = max(1, (int)($weight * 10)); 
            for ($i = 0; $i < $count; $i++) {
                $weightedTypes[] = $t;
            }
        }
        
        $type = $weightedTypes[array_rand($weightedTypes)];
        $targetRegion = $customer->preferences['target_region'] ?? 'us_east';
        $baseRequirements = $this->generateRequirements($type, $level, $targetRegion);
        $requirements = $baseRequirements;

        // SLA Tier
        if ($preSelectedSla) {
            $slaTier = $preSelectedSla;
        } else {
            $slaTiers = ['standard'];
            if ($this->researchService->hasEffect($user, 'unlock_customer_tier', 'premium') || $level >= 10) $slaTiers[] = 'premium';
            if ($this->researchService->hasEffect($user, 'unlock_customer_tier', 'enterprise') || $level >= 25) $slaTiers[] = 'enterprise';
            $slaTier = $slaTiers[array_rand($slaTiers)];
        }
        
        // SPECIALIZATION
        // Apply specialization modifiers
        $specService = app(\App\Services\Game\SpecializationService::class); // Lazy resolve
        $specMods = $specService->getActiveModifiers($user);
        
        $priceMod = $specMods['price_modifier'] ?? 1.0;
        $reqMod = $specMods['requirement_modifier'] ?? 1.0;
        
        // Apply spec size modifiers based on SLA Tier
        $slaReqMod = match($slaTier) {
            'premium' => 1.5,
            'enterprise' => 5.0,  // Enterprise needs 5x the resources
            'whale' => 15.0,      // Whales need 15x
            default => 1.0,
        };
        
        // Apply req modifier to requirements
        foreach ($requirements as $k => $v) {
            if (in_array($k, ['cpu', 'ram', 'storage', 'bandwidth'])) {
                $requirements[$k] = ceil($v * $reqMod * $slaReqMod);
            } elseif (is_numeric($v)) {
                $requirements[$k] = ceil($v * $reqMod);
            }
        }
        
        // Store base requirements for later rescaling during negotiation
        $requirements['_base'] = $baseRequirements;
        $requirements['_reqMod'] = $reqMod;

        // Calculate price based on requirements
        // Simple formula: (CPU * 20) + (RAM * 5) + (Storage * 0.1) + Base
        $basePrice = 10;
        $price = $basePrice + 
                 ($requirements['cpu'] * 15) + 
                 ($requirements['ram'] * 4) + 
                 ($requirements['storage'] * 0.05);

        $slaRoll = rand(1, 100);
        
        $qualityBonus = $this->researchService->getBonus($user, 'customer_quality');
        if ($qualityBonus > 0) {
            $slaRoll += ($qualityBonus * 50); // +7.5 per level effectively
        }

        // Apply Marketing Campaign SLA Bonus
        $marketingBonus = $this->marketingService->getSlaTierBonus($user);
        if ($marketingBonus > 0) {
            $slaRoll += $marketingBonus;
        }

        // HQ Whale Attraction
        if ($user->hq && $user->hq->prestige_score >= 1000) {
             $slaRoll += ($user->hq->prestige_score / 100); // e.g. 1000 prestige = +10 to slaRoll
        }

        $regions = \App\Models\GameConfig::get('regions', []);
        $prefs = $regions[$targetRegion]['preferences'] ?? [];

        // Apply Regional SLA Preference
        if (isset($prefs['sla_premium'])) {
            $slaRoll *= $prefs['sla_premium'];
        }

        // Compliance Gating
        $userCerts = $user->certifications()->pluck('key')->toArray();
        $hasSecurity = in_array('iso_27001', $userCerts) || in_array('soc2_type1', $userCerts);
        $hasPrivacy = in_array('gdpr_compliance', $userCerts);

        if ($preSelectedSla) {
            $slaTier = $preSelectedSla;
        } else {
            $slaTier = 'standard';
            if ($slaRoll > 95) {
                // Whale orders require both Security and Privacy certs
                $slaTier = ($hasSecurity && $hasPrivacy) ? 'whale' : ($hasSecurity || $hasPrivacy ? 'enterprise' : 'premium');
            } else if ($slaRoll > 85) {
                // Enterprise orders require at least one major certification
                $slaTier = ($hasSecurity || $hasPrivacy) ? 'enterprise' : 'premium';
            } else if ($slaRoll > 60) {
                $slaTier = 'premium';
            }
        }

        $price = $this->calculateIdealPrice($requirements, $slaTier, $user, $priceMod, $customer);
        $price = round($price, 2);

        // FEATURE 49: CONTRACT NEGOTIATION
        // Enterprise and Whale orders are negotiable by default
        // Standard/Premium have a small chance (15%) to be negotiable
        $isNegotiable = in_array($slaTier, ['enterprise', 'whale']) || rand(1, 100) <= 15;
        $basePriceRequested = null;
        
        if ($isNegotiable) {
            $basePriceRequested = round($price, 2);
            // In negotiation, the "initial" price shown to user is the customer's request
            // But if it's negotiable, we mark it as such.
        }

        return CustomerOrder::create([
            'customer_id' => $customer->id,
            'product_type' => $type,
            'requirements' => $requirements,
            'price_per_month' => $price,
            'status' => 'pending',
            'ordered_at' => now(),
            'contract_months' => rand(1, 12),
            'sla_tier' => $slaTier,
            'is_negotiable' => $isNegotiable,
            'base_price_requested' => $basePriceRequested,
            'patience_expires_at' => now()->addMinutes($customer->patience_minutes),
        ]);
    }

    private function getProductConfig(): array
    {
        return \App\Models\GameConfig::get('product_definitions', [
             'web_hosting' => [
                 'requirements' => ['cpu' => [1, 2], 'ram' => [1, 4], 'storage' => [10, 50], 'bandwidth' => [10, 100], 'ipv4' => 1, 'ports' => [80, 443]],
                 'min_level' => 1
             ],
             'vserver' => [
                 'requirements' => ['cpu' => [2, 8], 'ram' => [4, 16], 'storage' => [40, 200], 'bandwidth' => [100, 500], 'ipv4' => 1, 'ports' => [22, 80, 443]],
                 'min_level' => 3
             ],
             'dedicated' => [
                 'requirements' => ['cpu' => [8, 32], 'ram' => [32, 128], 'storage' => [500, 4000], 'bandwidth' => [1000, 10000], 'ipv4' => 1, 'dedicated_ip' => true, 'ports' => [22, 80, 443]],
                 'min_level' => 10
             ],
             'database_hosting' => [
                 'requirements' => ['cpu' => [4, 16], 'ram' => [16, 64], 'storage' => [100, 1000], 'bandwidth' => [100, 1000], 'ipv4' => 1, 'ports' => [3306, 5432]],
                 'min_level' => 8
             ],
             'game_server' => [
                 'requirements' => ['cpu' => [4, 12], 'ram' => [8, 32], 'storage' => [50, 200], 'bandwidth' => [500, 2000], 'ipv4' => 1, 'ports' => [27015, 7777]],
                 'min_level' => 5
             ],
             'reseller' => [ // FEATURE 149: White-Label Reseller Program
                 'requirements' => ['cpu' => [16, 64], 'ram' => [64, 256], 'storage' => [2000, 10000], 'bandwidth' => [5000, 20000], 'ipv4' => 16],
                 'min_level' => 15
             ]
        ]);
    }

    private function generateRequirements(string $type, int $level, string $regionKey = 'us_east'): array
    {
        $products = $this->getProductConfig();
        $regions = \App\Models\GameConfig::get('regions', []);
        $region = $regions[$regionKey] ?? null;
        $prefs = $region['preferences'] ?? [];

        $def = $products[$type] ?? null;

        if (!$def) {
             // Fallback
             return ['cpu' => 1, 'ram' => 1, 'storage' => 10, 'bandwidth' => 10];
        }

        $multiplier = 1 + ($level * 0.1); 
        $reqs = $def['requirements'];

        $result = [];
        foreach ($reqs as $key => $range) {
             if (is_array($range) && count($range) === 2) {
                 $val = rand($range[0], $range[1]);
                 
                 // Apply level multiplier
                 if ($key !== 'gpu') {
                     $val = ceil($val * $multiplier);
                 }

                 // Apply Regional Preference Multipliers
                 if ($key === 'cpu' && isset($prefs['cpu_focus'])) {
                     $val = ceil($val * $prefs['cpu_focus']);
                 }
                 if ($key === 'ram' && isset($prefs['ram_focus'])) {
                     $val = ceil($val * $prefs['ram_focus']);
                 }
                 if ($key === 'storage' && isset($prefs['storage_focus'])) {
                     $val = ceil($val * $prefs['storage_focus']);
                 }

                 $result[$key] = $val;
             } else {
                 $result[$key] = $range;
             }
        }
        
        // Generate OS Requirement
        $result['os'] = $this->selectRequiredOs($type, $level);

        // Generate Latency Requirement
        $baseLatency = match($type) {
            'game_server' => rand(30, 60),
            'database_hosting' => rand(40, 80),
            'ml_training' => rand(80, 150),
            default => rand(80, 200),
        };

        if (isset($prefs['max_latency_ms'])) {
            $baseLatency = min($baseLatency, $prefs['max_latency_ms'] * (rand(80, 120) / 100)); // fuzzy match
        }
        
        $result['max_latency_ms'] = floor($baseLatency);

        return $result;
    }

    private function selectRequiredOs(string $type, int $level): string
    {
        // Weighted random selection based on use-case
        return match ($type) {
            'web_hosting', 'vserver' => $this->pickWeighted([
                'ubuntu_lts' => 80, 
                'debian_stable' => 20
            ]),
            'game_server' => $this->pickWeighted([
                'gaming_optimized' => 50, 
                'windows_server_std' => 30, 
                'ubuntu_lts' => 20
            ]),
            'database_hosting' => $this->pickWeighted([
                'rhel_enterprise' => 40,
                'suse_enterprise' => 20,
                'ubuntu_lts' => 30,
                'windows_server_std' => 10
            ]),
            'ml_training', 'gpu_rental' => $this->pickWeighted([
                'ai_compute_os' => 70,
                'ubuntu_lts' => 30
            ]),
            'storage' => $this->pickWeighted([
                'storage_optimized' => 70,
                'debian_stable' => 30
            ]),
            'dedicated' => $this->pickWeighted([
                'ubuntu_lts' => 40,
                'rhel_enterprise' => 20,
                'windows_server_dc' => 10,
                'windows_server_std' => 20,
                'debian_stable' => 10
            ]),
            default => 'ubuntu_lts'
        };
    }

    private function pickWeighted(array $weights): string
    {
        $rand = rand(1, array_sum($weights));
        $current = 0;
        foreach ($weights as $key => $weight) {
            $current += $weight;
            if ($rand <= $current) return $key;
        }
        return array_key_first($weights);
    }

    private function generateCompanyName(bool $isEnterprise = false, bool $isHighCompliance = false): string
    {
        if ($isHighCompliance) {
            return self::GOV_NAMES[array_rand(self::GOV_NAMES)];
        }
        if ($isEnterprise) {
            return self::ENTERPRISE_NAMES[array_rand(self::ENTERPRISE_NAMES)];
        }

        // FEATURE 84: Procedural Startup Identities – 3 name formats
        $roll = rand(1, 100);

        if ($roll <= 30) {
            // Full startup name: "Quantum Bloom"
            return self::STARTUP_FULLNAMES[array_rand(self::STARTUP_FULLNAMES)];
        } elseif ($roll <= 70) {
            // Classic combo: "Vertex Analytics"
            $prefix = self::COMPANY_PREFIXES[array_rand(self::COMPANY_PREFIXES)];
            $suffix = self::COMPANY_SUFFIXES[array_rand(self::COMPANY_SUFFIXES)];
            return "{$prefix} {$suffix}";
        } else {
            // Modern style with dot-notation or abbreviation: "CruxOps.io" or "Helix AI"
            $prefix = self::COMPANY_PREFIXES[array_rand(self::COMPANY_PREFIXES)];
            $styles = ['.io', '.ai', '.dev', '.cloud', ' Inc.', ' GmbH', ' Co.'];
            $style = $styles[array_rand($styles)];
            return "{$prefix}{$style}";
        }
    }

    public function assignOrder(User $user, CustomerOrder $order, \App\Models\Server $server): void
    {
        // Check ownership
        if ($server->rack->room->user_id !== $user->id) { // Not quite right, room->rack belongs to user?
             // Room belongs to user ideally. Or verify via query.
        }

        // Check if order is pending
        if (!$order->isPending()) {
            throw new \Exception("Order is not pending.");
        }

        // Check server status
        if ($server->status === \App\Enums\ServerStatus::OFFLINE) {
             throw new \Exception("Server is offline.");
        }
        
        // Check OS Compatibility
        $requiredOs = $order->requirements['os'] ?? null;
        if ($requiredOs && $server->installed_os_type !== $requiredOs) {
            // Friendly name lookup?
            $osName = $requiredOs; // Simplification
            throw new \Exception("OS Mismatch: Customer requires {$osName}. Server has {$server->installed_os_type}.");
        }
        
        // Check Security Level for Premium/Enterprise/Whale
        $requiredSecurity = match($order->sla_tier) {
            'whale' => 95,
            'enterprise' => 90,
            'premium' => 80,
            default => 0
        };
        
        if ($server->security_patch_level < $requiredSecurity) {
            throw new \Exception("Security too low: Customer requires {$requiredSecurity}% patch level. Server has {$server->security_patch_level}%. Update OS first.");
        }

        // Check occupancy
        if ($server->type === \App\Enums\ServerType::SHARED_NODE && !in_array($order->product_type, ['web_hosting', 'database_hosting'])) {
            throw new \Exception("Shared Hosting Nodes can only accept Web or DB hosting contracts.");
        }
        
        if ($server->type === \App\Enums\ServerType::VSERVER_NODE || $server->type === \App\Enums\ServerType::SHARED_NODE) {
            if ($server->getAvailableVServerSlots() <= 0) {
                 throw new \Exception("Server is full. No available slots.");
            }
        } else {
            $occupiedCount = $server->orders()->whereIn('status', ['active', 'provisioning'])->count();
            if ($occupiedCount > 0) {
                throw new \Exception("Server is already occupied.");
            }
        }

        // Validate Requirements
        $req = $order->requirements;
        
        // HPC Specialization: Neural Liquid Backplane density bonus
        $mlDensityBonus = $this->researchService->getBonus($user, 'ml_density_bonus');
        $resourceMultiplier = 1.0;
        if ($order->product_type === 'ml_training' && $mlDensityBonus > 0) {
            $resourceMultiplier = 0.5; // Double the density = 50% resource footprint
        }

        // CPU
        $cpuCapacityBonus = $this->skillService->getBonus($user, 'cpu_capacity');
        // Include OS modifier via getEffectiveCpuCores
        $effectiveCores = $server->getEffectiveCpuCores() * (1.0 + $cpuCapacityBonus);
        
        $requestedCpu = ($req['cpu'] ?? 0) * $resourceMultiplier;
        if ($effectiveCores < $requestedCpu) {
            throw new \Exception("Insufficient CPU capacity (Need " . round($requestedCpu, 1) . ", Have " . round($effectiveCores, 1) . ").");
        }

        // RAM
        $requestedRam = ($req['ram'] ?? 0) * $resourceMultiplier;
        if ($server->ram_gb < $requestedRam) {
            throw new \Exception("Insufficient RAM (Need " . round($requestedRam, 1) . "GB, Have {$server->ram_gb}GB).");
        }

        // Storage (TB vs GB)
        $serverStorageGB = $server->storage_tb * 1024;
        if ($serverStorageGB < ($req['storage'] ?? 0)) {
             throw new \Exception("Insufficient Storage (Need {$req['storage']}GB, Have {$serverStorageGB}GB).");
        }

        // Bandwidth (NIC Limit)
        $requestedBandwidth = $req['bandwidth'] ?? 0;
        if ($server->bandwidth_mbps < $requestedBandwidth) {
            throw new \Exception("Insufficient NIC Bandwidth (Need {$requestedBandwidth}Mbps, Have {$server->bandwidth_mbps}Mbps).");
        }

        // Room Bandwidth (Uplink Limit)
        $room = $server->rack->room;
        $roomCurrentGbps = $room->getCurrentBandwidthUsage();
        $requestedGbps = $requestedBandwidth / 1000;

        if (($roomCurrentGbps + $requestedGbps) > $room->bandwidth_gbps) {
            $available = max(0, $room->bandwidth_gbps - $roomCurrentGbps);
            throw new \Exception("Insufficient Room Uplink capacity (Need {$requestedGbps}Gbps, only {$available}Gbps available).");
        }

        // Calculate Provisioning Time based on Type
        $baseDuration = match($order->product_type) {
            'vserver', 'vps', 'web_hosting', 'game_server' => 60, // 1 minute
            'dedicated' => 300, // 5 minutes
            'gpu_rental', 'ml_training' => 600, // 10 minutes
            'storage', 'database_hosting' => 120, // 2 minutes
            default => 60
        };

        // Apply Research Bonus
        // Bonus is percentage reduction (e.g. 0.20 for 20%)
        $speedBonus = $this->researchService->getBonus($user, 'provisioning_speed');

        // Apply Performance Mode Policy Bonus
        if (in_array('performance_mode', $user->economy->strategic_policies ?? [])) {
            $speedBonus += 0.10; // Bonus reduction (shorter duration)
        }

        $duration = $baseDuration * (1 - $speedBonus);
        $duration = max(10, $duration); // Minimum 10 seconds

        // Provision the order
        // This sets order status to 'provisioning' and links server
        $order->assigned_server_id = $server->id;
        $order->status = 'provisioning'; // Should match enum ideally
        $order->provisioning_started_at = now();
        $order->provisioning_completes_at = now()->addSeconds((int)$duration);
        $order->provisioned_at = null; // Not done yet!
        $order->save();

        // Provision IPs
        $this->networkService->provisionIPs($order);

        // Update server usage? 
        // For dedicated servers, mark as fully used?
        // For VPS nodes, deduct resources?
        
        if ($server->type === \App\Enums\ServerType::VSERVER_NODE || $server->type === \App\Enums\ServerType::SHARED_NODE) {
            $server->vservers_used++;
            $server->save();
        } else {
            // Dedicated
            // Maybe lock server?
        }
    }

    private function generateContactName(): string
    {
        $first = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
        $last = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
        return "$first $last";
    }

    /**
     * Process Auto-Provisioning Automation
     */
    private function processAutoProvisioning(User $user): void
    {
        // 1. Get Pending Orders
        $orders = CustomerOrder::whereHas('customer', fn($q) => $q->where('user_id', $user->id))
            ->where('status', 'pending')
            ->orderBy('created_at') // Oldest first
            ->limit(3) // Process max 3 per tick to save performance
            ->get();

        if ($orders->isEmpty()) return;

        // 2. Get Available Servers (Eager load orders to avoid N+1 queries)
        $candidates = \App\Models\Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', \App\Enums\ServerStatus::ONLINE)
            ->with(['orders', 'rack.room'])
            ->get();
        
        // Filter candidates in memory and prioritize VPS Nodes AND matching region
        $candidates = $candidates->filter(function ($server) {
            if ($server->type !== \App\Enums\ServerType::VSERVER_NODE) {
                // Dedicated: Must have 0 active/provisioning orders
                return $server->orders->whereIn('status', ['active', 'provisioning'])->isEmpty();
            }
            return true; // Let assignOrder handle VPS capacity check
        });

        foreach ($orders as $order) {
            $targetRegion = $order->customer->preferences['target_region'] ?? null;
            
            // Sort candidates for this specific order
            $sortedCandidates = $candidates->sortBy(function($server) use ($targetRegion) {
                $score = 0;
                // Prioritize filling VPS nodes first (0) to save dedicated servers (1) for big orders
                $score += $server->type === \App\Enums\ServerType::VSERVER_NODE ? 0 : 10;
                
                // Prioritize matching region (lower score is better)
                if ($targetRegion && $server->rack && $server->rack->room) {
                    $serverRegion = $server->rack->room->region;
                    if ($serverRegion === $targetRegion) {
                        $score -= 5;
                    }
                }
                
                return $score;
            });

            foreach ($sortedCandidates as $server) {
                try {
                    // Try to assign (Validation throws exception if resources/capacity insufficient)
                    $this->assignOrder($user, $order, $server);
                    
                    // If successful:
                    // For dedicated servers, remove from candidates pool for this tick
                    if ($server->type !== \App\Enums\ServerType::VSERVER_NODE) {
                        $candidates = $candidates->reject(fn($s) => $s->id === $server->id);
                    }
                    break; // Order handled, move to next order
                } catch (\Exception $e) {
                    // Criteria mismatch (CPU/RAM etc), try next server
                    continue;
                }
            }
        }
    }

    public function calculateIdealPrice(array $requirements, string $slaTier, User $user, float $priceMod = 1.0, ?Customer $customer = null): float
    {
        $baseResourcePrice = 10 + 
                 (($requirements['cpu'] ?? 0) * 15) + 
                 (($requirements['ram'] ?? 0) * 4) + 
                 (($requirements['storage'] ?? 0) * 0.05);

        $price = $baseResourcePrice * 1.5; // 50% base margin

        if ($slaTier === 'premium') $price *= 1.5;
        if ($slaTier === 'enterprise') $price *= 2.5;
        if ($slaTier === 'whale') $price *= 6.0;

        // Apply research bonus
        $qualityBonus = $this->researchService->getBonus($user, 'customer_quality');
        if ($qualityBonus > 0) {
            $price = $price * (1 + $qualityBonus);
        }

        // Apply market focus policy
        $marketFocus = $user->economy->getPolicy('market_focus', 'balanced');
        $policyModifiers = \App\Services\Game\ManagementService::DECISIONS['market_focus']['options'][$marketFocus]['modifiers'];
        $price *= ($policyModifiers['price_modifier'] ?? 1.0);

        // World event modifiers — regional if customer has a region
        $customerRegion = $customer?->getRegion();
        $modifiers = $customerRegion
            ? \App\Models\WorldEvent::getActiveModifiersForRegion($customerRegion)
            : \App\Models\WorldEvent::getActiveModifiers();

        if (isset($modifiers['order_value'])) {
             $price *= $modifiers['order_value'];
        }

        // Compliance/Performance premium from customer regional profile
        if ($customer) {
            $price *= $customer->getRegionalRevenueMultiplier();
        }

        // Specialization modifier
        $price *= $priceMod;

        return $price;
    }

    /**
     * Determine the player's primary region based on their rooms.
     */
    private function getPlayerPrimaryRegion(User $user): ?string
    {
        $room = \App\Models\GameRoom::where('user_id', $user->id)->first();
        return $room?->region;
    }
}
