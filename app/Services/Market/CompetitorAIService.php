<?php

namespace App\Services\Market;

use App\Models\Competitor;
use App\Models\GameConfig;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * CompetitorAIService — Intelligent NPC decision engine.
 *
 * Each competitor has a personality archetype that drives their decision-making:
 *   - aggressive_expander: Prioritizes growth, undercuts prices, risks overload
 *   - premium_stability: Maintains high uptime, charges premium, invests in infra
 *   - budget_volume: Lowest prices, thin margins, massive capacity
 *   - stealth_innovator: Invests in innovation, targets AI/enterprise, slow but strategic
 *   - regional_specialist: Dominates their HQ region, defends market share fiercely
 *
 * AI decisions are made every N ticks (configurable per competitor).
 * Competitors react to player actions and market conditions.
 */
class CompetitorAIService
{
    /**
     * Archetype behavior profiles.
     * Each key maps to a set of preference weights and thresholds.
     */
    private const ARCHETYPE_PROFILES = [
        'aggressive_expander' => [
            'price_aggression' => 0.8,
            'expansion_bias' => 0.9,
            'risk_tolerance' => 0.7,
            'marketing_weight' => 0.6,
            'innovation_weight' => 0.3,
            'defense_reaction' => 0.5,
            'capacity_target' => 1.3,
            'aggression_threshold' => 40, // Strikes at 40 enmity
            'enmity_modifier' => 1.2,     // Gains enmity 20% faster
        ],
        'premium_stability' => [
            'price_aggression' => 0.2,
            'expansion_bias' => 0.3,
            'risk_tolerance' => 0.2,
            'marketing_weight' => 0.5,
            'innovation_weight' => 0.4,
            'defense_reaction' => 0.3,
            'capacity_target' => 0.9,
            'aggression_threshold' => 80, // Very patient
            'enmity_modifier' => 0.7,
        ],
        'budget_volume' => [
            'price_aggression' => 0.95,
            'expansion_bias' => 0.7,
            'risk_tolerance' => 0.5,
            'marketing_weight' => 0.8,
            'innovation_weight' => 0.1,
            'defense_reaction' => 0.7,
            'capacity_target' => 1.5,
            'aggression_threshold' => 50,
            'enmity_modifier' => 1.0,
        ],
        'stealth_innovator' => [
            'price_aggression' => 0.3,
            'expansion_bias' => 0.4,
            'risk_tolerance' => 0.4,
            'marketing_weight' => 0.3,
            'innovation_weight' => 0.9,
            'defense_reaction' => 0.2,
            'capacity_target' => 1.0,
            'aggression_threshold' => 60,
            'enmity_modifier' => 0.9,
        ],
        'regional_specialist' => [
            'price_aggression' => 0.5,
            'expansion_bias' => 0.2,
            'risk_tolerance' => 0.3,
            'marketing_weight' => 0.7,
            'innovation_weight' => 0.3,
            'defense_reaction' => 0.9,
            'capacity_target' => 1.1,
            'aggression_threshold' => 55,
            'enmity_modifier' => 1.1,
        ],
    ];

    public function __construct(
        protected EconomicCycleEngine $cycleEngine,
        protected \App\Services\Game\NewsService $newsService,
    ) {}

    // ─── MAIN TICK ─────────────────────────────────────

    /**
     * Process AI decisions for all active competitors.
     */
    public function tick(int $currentTick): void
    {
        $competitors = Competitor::where('status', 'active')->get();
        $economicMods = $this->cycleEngine->getModifiers();
        $players = User::with('economy', 'network')->get();

        foreach ($competitors as $competitor) {
            // Step 1: Periodic Evolution (Leveling up)
            if ($currentTick % 50 === 0) {
                $this->processEvolution($competitor, $currentTick);
            }

            // Step 2: Decision making
            if ($currentTick - $competitor->last_decision_tick < $competitor->decision_cooldown) {
                continue;
            }

            $this->processCompetitorDecision($competitor, $currentTick, $economicMods, $players);
            $competitor->last_decision_tick = $currentTick;
            $competitor->save();
        }

        // Global Market update (Blueprint 1.1)
        if ($currentTick % 10 === 0) {
            $this->updateNetworkMarket($competitors);
        }
    }

    /**
     * Slowly evolves NPC stats over time to keep matching player progression.
     */
    private function processEvolution(Competitor $c, int $currentTick): void
    {
        $profile = self::ARCHETYPE_PROFILES[$c->archetype] ?? self::ARCHETYPE_PROFILES['premium_stability'];
        
        // Base growth based on luck and existing market share
        $growthMod = 1.0 + ($c->market_share / 20.0); // Successful ones grow faster
        
        // 1. Capacity Growth
        $c->capacity_score += (5 * $profile['expansion_bias'] * $growthMod);
        
        // 2. Innovation Growth
        $c->innovation_index = min(100, $c->innovation_index + (0.5 * $profile['innovation_weight'] * $growthMod));
        
        // 3. Archetype specific scaling
        switch ($c->archetype) {
            case 'stealth_innovator':
                $c->intelligence = min(100, $c->intelligence + 1);
                $c->latency_score = max(2, $c->latency_score - 0.2);
                break;
            case 'aggressive_expander':
                $c->aggression = min(100, $c->aggression + 1);
                $c->capacity_score += 10; // Extra expansion
                break;
            case 'premium_stability':
                $c->reputation = min(100, $c->reputation + 0.5);
                $c->uptime_score = min(99.999, $c->uptime_score + 0.001);
                break;
        }

        // 4. Financial "Infusion" (Simulated VC or organic profit)
        $c->assets_value += (10000 * $growthMod);

        Log::debug("[CompetitorAI] {$c->name} evolved at tick {$currentTick}");
        $c->save();
    }

    private function updateNetworkMarket($competitors): void
    {
        $market = \App\Models\NetworkMarket::getMarket();
        
        // Total "NPC Usage" simulation
        $npcTotalCapacity = $competitors->sum('capacity_score');
        $utilizationFactor = $npcTotalCapacity / 10000; // Assume 10k is very tight global pool
        
        $newScarcity = 30 + ($utilizationFactor * 60) + (rand(-5, 5));
        $market->ipv4_scarcity_index = max(1, min(100, $newScarcity));
        
        // Demand factor based on economic cycle
        $econMods = $this->cycleEngine->getModifiers();
        $market->global_demand_factor = $econMods['customer_demand'] ?? 1.0;
        
        $market->save();
    }

    // ─── DECISION ENGINE ───────────────────────────────

    private function processCompetitorDecision(
        Competitor $competitor,
        int $currentTick,
        array $economicMods,
        $players,
    ): void {
        $profile = self::ARCHETYPE_PROFILES[$competitor->archetype] ?? self::ARCHETYPE_PROFILES['premium_stability'];
        $state = $this->assessCompetitorState($competitor);
        $marketPressure = $this->assessMarketPressure($competitor, $players);

        // Generate decision scores for each possible action
        $decisions = [
            'adjust_pricing' => $this->scorePricingDecision($competitor, $profile, $state, $economicMods),
            'expand_capacity' => $this->scoreExpansionDecision($competitor, $profile, $state, $economicMods),
            'boost_marketing' => $this->scoreMarketingDecision($competitor, $profile, $state, $marketPressure),
            'invest_innovation' => $this->scoreInnovationDecision($competitor, $profile, $state, $economicMods),
            'stabilize' => $this->scoreStabilizeDecision($competitor, $profile, $state),
            'defend_region' => $this->scoreDefendDecision($competitor, $profile, $state, $marketPressure),
            'hostile_action' => $this->scoreHostileDecision($competitor, $profile, $state, $marketPressure),
        ];

        // Sort decisions by score, take the top 1-2 actions
        arsort($decisions);
        $topDecisions = array_slice($decisions, 0, 2, true);

        foreach ($topDecisions as $action => $score) {
            if ($score < 10) continue; // Skip low-priority actions

            $this->executeDecision($competitor, $action, $score, $profile, $state, $economicMods);
            
            // Random chance to broadcast news about significant decisions
            if ($score > 30 && rand(1, 100) < 40) {
                $this->newsService->broadcastCompetitorAction($competitor, $action, $action);
            }
        }

        // Apply natural drift (uptime/latency noise)
        $this->applyNaturalDrift($competitor, $economicMods);

        // --- FEATURE 35: ENMITY DRIFT ---
        $this->updateEnmityDrift($competitor, $players);
    }

    private function updateEnmityDrift(Competitor $c, $players): void
    {
        // For now, only handle the primary player (or first one found)
        $player = $players->first();
        if (!$player) return;

        $profile = self::ARCHETYPE_PROFILES[$c->archetype] ?? self::ARCHETYPE_PROFILES['premium_stability'];
        $enmityMod = $profile['enmity_modifier'] ?? 1.0;

        if (!$player->economy) return;
        
        $playerShare = (float) ($player->economy->global_market_share ?? 0);
        $playerRep = (float) ($player->economy->reputation ?? 50);

        // Increase enmity if player is dominating
        if ($playerShare > $c->market_share) {
            $c->player_enmity = min(100, $c->player_enmity + (0.8 * $enmityMod));
        }

        // Price War trigger: If player has chosen budget (aggressive) pricing
        $marketFocus = $player->economy->getPolicy('market_focus', 'balanced');
        if ($marketFocus === 'budget') {
            $c->player_enmity = min(100, $c->player_enmity + (1.2 * $enmityMod));
        }

        // Increase enmity if player is an "Elite" rival
        if ($playerRep > 80 && $c->archetype === 'premium_stability') {
            $c->player_enmity = min(100, $c->player_enmity + (0.4 * $enmityMod));
        }

        // Natural decay if player is not a threat
        if ($playerShare < 1) {
            $c->player_enmity = max(0, $c->player_enmity - 0.2);
        }
    }

    // ─── STATE ASSESSMENT ──────────────────────────────

    private function assessCompetitorState(Competitor $competitor): array
    {
        $marketShare = (float) $competitor->market_share;
        $prevShare = $marketShare; // Would track historical, for now use streaks
        $isGrowing = $competitor->expansion_streak > 0;
        $isShrinking = $competitor->contraction_streak > 0;

        return [
            'market_share' => $marketShare,
            'is_growing' => $isGrowing,
            'is_shrinking' => $isShrinking,
            'profit_margin' => (float) $competitor->profit_margin,
            'capacity_util' => $competitor->capacity_score > 0
                ? min(1.0, $marketShare * 10 / max(1, $competitor->capacity_score))
                : 0.5,
            'reputation' => (float) $competitor->reputation,
            'uptime' => (float) $competitor->uptime_score,
            'innovation' => (float) $competitor->innovation_index,
        ];
    }

    private function assessMarketPressure(Competitor $competitor, $players): array
    {
        $threats = [];

        foreach ($players as $player) {
            if (!$player->economy) continue;
            $playerShare = (float) ($player->economy->global_market_share ?? 0);
            $playerReputation = (float) ($player->economy->reputation ?? 50);

            if ($playerShare > 5) {
                $threats[] = [
                    'user_id' => $player->id,
                    'share' => $playerShare,
                    'reputation' => $playerReputation,
                    'threat_level' => $playerShare / max(1, (float) $competitor->market_share) * 100,
                ];
            }
        }

        // Check if competitors are encroaching on our HQ region
        $hqRegionThreat = false;
        $otherCompetitors = Competitor::where('status', 'active')
            ->where('id', '!=', $competitor->id)
            ->get();

        foreach ($otherCompetitors as $other) {
            $otherRegionalShare = ($other->regional_shares[$competitor->headquarters_region] ?? 0);
            if ($otherRegionalShare > 15) {
                $hqRegionThreat = true;
                break;
            }
        }

        return [
            'player_threats' => $threats,
            'max_player_threat' => !empty($threats) ? max(array_column($threats, 'threat_level')) : 0,
            'hq_region_threatened' => $hqRegionThreat,
            'competitive_density' => $otherCompetitors->avg('market_share') ?? 0,
        ];
    }

    // ─── DECISION SCORING ──────────────────────────────

    private function scorePricingDecision(Competitor $c, array $profile, array $state, array $econMods): float
    {
        $score = 0;

        // If market share is shrinking, pricing becomes important
        if ($state['is_shrinking']) {
            $score += 30 * $profile['price_aggression'];
        }

        // During recession/crisis, price wars are more common
        if (in_array($econMods['state'], ['recession', 'crisis'])) {
            $score += 20 * $profile['price_aggression'];
        }

        // Low profit margin → less likely to cut prices
        if ($state['profit_margin'] < 0.05) {
            $score -= 20;
        }

        // High profit margin → room to cut
        if ($state['profit_margin'] > 0.20) {
            $score += 15 * $profile['price_aggression'];
        }

        return max(0, $score);
    }

    private function scoreExpansionDecision(Competitor $c, array $profile, array $state, array $econMods): float
    {
        $score = 0;

        // High capacity utilization → expansion needed
        if ($state['capacity_util'] > 0.8) {
            $score += 40 * $profile['expansion_bias'];
        }

        // Growing market share → riding the wave
        if ($state['is_growing']) {
            $score += 25 * $profile['expansion_bias'];
        }

        // During expansion/peak, expand aggressively
        if (in_array($econMods['state'], ['expansion', 'peak'])) {
            $score += 20 * $profile['expansion_bias'];
        }

        // During crisis, only risk-tolerant expand
        if ($econMods['state'] === 'crisis') {
            $score -= 30 * (1 - $profile['risk_tolerance']);
        }

        // Healthy profit margin enables expansion
        if ($state['profit_margin'] > 0.10) {
            $score += 15;
        }

        return max(0, $score);
    }

    private function scoreMarketingDecision(Competitor $c, array $profile, array $state, array $pressure): float
    {
        $score = 0;

        // Player is threatening market share
        if ($pressure['max_player_threat'] > 50) {
            $score += 30 * $profile['marketing_weight'];
        }

        // Reputation is below average
        if ($state['reputation'] < 60) {
            $score += 25 * $profile['marketing_weight'];
        }

        // Growing → market to accelerate
        if ($state['is_growing']) {
            $score += 15 * $profile['marketing_weight'];
        }

        return max(0, $score);
    }

    private function scoreInnovationDecision(Competitor $c, array $profile, array $state, array $econMods): float
    {
        $score = 0;

        // Innovation-weighted archetypes always value this
        $score += 20 * $profile['innovation_weight'];

        // Low innovation index → need to catch up
        if ($state['innovation'] < 40) {
            $score += 20 * $profile['innovation_weight'];
        }

        // During growth/expansion, invest in future
        if (in_array($econMods['state'], ['growth', 'expansion'])) {
            $score += 15 * $profile['innovation_weight'];
        }

        return max(0, $score);
    }

    private function scoreStabilizeDecision(Competitor $c, array $profile, array $state): float
    {
        $score = 0;

        // Uptime below threshold → must stabilize
        if ($state['uptime'] < 99.0) {
            $score += 50 * (1 - $profile['risk_tolerance']);
        }

        // High capacity utilization → risk of overload
        if ($state['capacity_util'] > 0.9) {
            $score += 30 * (1 - $profile['risk_tolerance']);
        }

        // Low profit margin → stop spending, stabilize
        if ($state['profit_margin'] < 0.03) {
            $score += 25;
        }

        return max(0, $score);
    }

    private function scoreDefendDecision(Competitor $c, array $profile, array $state, array $pressure): float
    {
        $score = 0;

        // HQ region is being threatened
        if ($pressure['hq_region_threatened']) {
            $score += 50 * $profile['defense_reaction'];
        }

        // Shrinking market share + high defense reaction
        if ($state['is_shrinking']) {
            $score += 30 * $profile['defense_reaction'];
        }

        // Player is specifically targeting this competitor's sector
        if ($pressure['max_player_threat'] > 70) {
            $score += 40 * $profile['defense_reaction'];
        }

        return max(0, $score);
    }

    private function scoreHostileDecision(Competitor $c, array $profile, array $state, array $pressure): float
    {
        $score = 0;
        $threshold = $profile['aggression_threshold'] ?? 50;

        // Base score comes from Enmity relative to threshold
        if ($c->player_enmity > $threshold) {
             $score += ($c->player_enmity - $threshold) * 2;
        }

        // High threat activates hostility even earlier
        if ($pressure['max_player_threat'] > 80) {
            $score += 30;
        }

        // Recession/Crisis → desperate times
        $econMods = $this->cycleEngine->getModifiers();
        if ($econMods['state'] === 'crisis') {
            $score += 15;
        }

        return max(0, $score);
    }

    // ─── DECISION EXECUTION ────────────────────────────

    private function executeDecision(
        Competitor $competitor,
        string $action,
        float $score,
        array $profile,
        array $state,
        array $econMods,
    ): void {
        switch ($action) {
            case 'adjust_pricing':
                $this->executePricing($competitor, $profile, $state);
                break;
            case 'expand_capacity':
                $this->executeExpansion($competitor, $profile, $state);
                break;
            case 'boost_marketing':
                $this->executeMarketing($competitor, $profile, $state);
                break;
            case 'invest_innovation':
                $this->executeInnovation($competitor, $profile, $state);
                break;
            case 'stabilize':
                $this->executeStabilize($competitor, $profile, $state);
                break;
            case 'defend_region':
                $this->executeDefend($competitor, $profile, $state);
                break;
            case 'hostile_action':
                $this->executeHostile($competitor, $profile, $state);
                break;
        }

        Log::debug("[CompetitorAI] {$competitor->name} ({$competitor->archetype}) → {$action} (score: {$score})");
    }

    private function executePricing(Competitor $c, array $profile, array $state): void
    {
        $direction = $state['is_shrinking'] ? -1 : ($state['profit_margin'] > 0.20 ? -1 : 1);
        $magnitude = $profile['price_aggression'] * 0.05 * $direction;

        $c->price_modifier = max(0.40, min(2.50, $c->price_modifier + $magnitude));

        // Profit margin adjusts inversely to price changes
        if ($direction < 0) {
            $c->profit_margin = max(-0.10, $c->profit_margin - 0.02);
        } else {
            $c->profit_margin = min(0.50, $c->profit_margin + 0.01);
        }
    }

    private function executeExpansion(Competitor $c, array $profile, array $state): void
    {
        $expansionAmount = $profile['expansion_bias'] * 50 * (1 + $profile['risk_tolerance']);
        $c->capacity_score = min(5000, $c->capacity_score + $expansionAmount);

        // Expansion costs money → reduce assets
        $investmentCost = $expansionAmount * 1000;
        $c->assets_value = max(0, $c->assets_value - $investmentCost);
        $c->profit_margin = max(-0.10, $c->profit_margin - 0.01);

        $c->expansion_streak++;
        $c->contraction_streak = 0;
    }

    private function executeMarketing(Competitor $c, array $profile, array $state): void
    {
        $boost = $profile['marketing_weight'] * 5000;
        $c->marketing_budget += $boost;

        // Marketing improves reputation over time
        $repGain = min(3.0, $c->marketing_budget / 50000);
        $c->reputation = min(100, $c->reputation + $repGain);

        // Costs money
        $c->assets_value = max(0, $c->assets_value - $boost);
        $c->profit_margin = max(-0.10, $c->profit_margin - 0.005);
    }

    private function executeInnovation(Competitor $c, array $profile, array $state): void
    {
        $innovationGain = $profile['innovation_weight'] * 3;
        $c->innovation_index = min(100, $c->innovation_index + $innovationGain);

        // Innovation improves uptime and latency over time
        $c->uptime_score = min(99.99, $c->uptime_score + 0.01);
        $c->latency_score = max(5, $c->latency_score - 0.5);

        // Costs money
        $investmentCost = $innovationGain * 2000;
        $c->assets_value = max(0, $c->assets_value - $investmentCost);
    }

    private function executeStabilize(Competitor $c, array $profile, array $state): void
    {
        // Focus on uptime improvement and capacity management
        $c->uptime_score = min(99.99, $c->uptime_score + 0.05);
        $c->latency_score = max(5, $c->latency_score - 1);

        // Reduce marketing spend to conserve resources
        $c->marketing_budget = max(0, $c->marketing_budget - 2000);

        // Improve profit margin through efficiency
        $c->profit_margin = min(0.50, $c->profit_margin + 0.01);

        $c->contraction_streak++;
        $c->expansion_streak = 0;
    }

    private function executeDefend(Competitor $c, array $profile, array $state): void
    {
        // Invest heavily in HQ region
        $hqRegion = $c->headquarters_region;
        if ($hqRegion) {
            $shares = $c->regional_shares ?? [];
            $currentShare = ($shares[$hqRegion] ?? 10) + 2;
            $shares[$hqRegion] = min(60, $currentShare);
            $c->regional_shares = $shares;
        }

        // Boost marketing in response
        $c->marketing_budget += 3000;
        $c->reputation = min(100, $c->reputation + 1);

        // Slight price cut to defend
        $c->price_modifier = max(0.50, $c->price_modifier - 0.02);
    }

    private function executeHostile(Competitor $c, array $profile, array $state): void
    {
        // 1. Pick a target (the dominant player usually)
        $target = User::whereHas('economy', fn($q) => $q->where('global_market_share', '>', 5))
            ->inRandomOrder()
            ->first() ?? User::first();

        if (!$target) return;

        // 2. Cooldown check
        if ($c->last_attack_at && $c->last_attack_at->diffInMinutes(now()) < 20) return;

        // 3. Selection weighted by archetype
        $action = match($c->archetype) {
            'aggressive_expander' => collect(['ddos', 'sabotage', 'price_war', 'patent_suit'])->random(),
            'stealth_innovator' => collect(['data_leak', 'slander', 'tech_leak'])->random(),
            'budget_volume' => collect(['price_war', 'hiring_raid', 'sabotage'])->random(),
            'premium_stability' => collect(['patent_suit', 'slander'])->random(),
            default => collect(['price_war', 'slander'])->random(),
        };

        $this->triggerHostileEffect($c, $target, $action);
        
        $c->last_attack_at = now();
        $c->player_enmity = max(0, $c->player_enmity - 25); // Large drop after striking
    }

    private function triggerHostileEffect(Competitor $c, User $user, string $action): void
    {
        $eventService = app(\App\Services\Game\GameEventService::class);
        
        // Broadcast for dramatic UI response
        broadcast(new \App\Events\CompetitorAttackStarted($user, $c, $action));

        switch ($action) {
            case 'ddos':
                $eventService->createDdosAttack($user);
                \App\Models\GameLog::log($user, "⚠️ SABOTAGE: Massive traffic spike detected! Intel suggests {$c->name} is testing your infrastructure.", 'danger', 'security');
                break;
            case 'sabotage':
                $eventService->createSecurityBreach($user);
                \App\Models\GameLog::log($user, "⚠️ SABOTAGE: Physical intrusion detected. {$c->name} agents spotted near your racks.", 'danger', 'security');
                break;
            case 'price_war':
                \App\Models\GameLog::log($user, "⚔️ PRICE WAR: {$c->name} has started an aggressive campaign to undercut your services!", 'warning', 'economy');
                \Illuminate\Support\Facades\Cache::put("npc_event_{$c->id}_price_war", true, now()->addMinutes(15));
                break;
            case 'hiring_raid':
                $eventService->createHiringRaid($user, $c);
                \App\Models\GameLog::log($user, "🤝 HIRING RAID: {$c->name} is headhunting your engineers with massive bonuses!", 'warning', 'employees');
                break;
            case 'slander':
                 \App\Models\GameLog::log($user, "📢 PR ATTACK: {$c->name} is running a 'reliability' smear campaign targeting your brand.", 'warning', 'security');
                 $user->economy->adjustReputation(-5.0);
                 break;
            case 'data_leak':
                $eventService->createSecurityBreach($user);
                \App\Models\GameLog::log($user, "🛑 DATA LEAK: Customer records leaked! Anonymous sources link the leak to {$c->name}.", 'danger', 'security');
                break;
            case 'patent_suit':
                $eventService->createPatentLawsuit($user, $c);
                \App\Models\GameLog::log($user, "⚖️ LEGAL ACTION: {$c->name} has filed a patent lawsuit against your infrastructure!", 'danger', 'economy');
                break;
            case 'tech_leak':
                $xpLoss = min($user->economy->experience_points * 0.1, 2000);
                $user->economy->experience_points = max(0, $user->economy->experience_points - $xpLoss);
                $user->economy->save();
                \App\Models\GameLog::log($user, "🔬 R&D LEAK: Sensitive tech schematics stolen by {$c->name}! Research progress compromised.", 'danger', 'innovation');
                break;
        }
    }

    // ─── NATURAL DRIFT ─────────────────────────────────

    private function applyNaturalDrift(Competitor $c, array $econMods): void
    {
        // Small random fluctuations to uptime and latency
        $c->uptime_score = max(90, min(99.99,
            $c->uptime_score + (mt_rand(-30, 20) / 1000)
        ));

        $c->latency_score = max(5, min(200,
            $c->latency_score + (mt_rand(-50, 50) / 100)
        ));

        // Reputation natural decay
    $c->reputation = max(10, min(100,
        $c->reputation + (mt_rand(-10, 5) / 100)
    ));

    // FEATURE 261: Mole Sabotage over time
    if ($c->has_mole ?? false) {
        $c->uptime_score = max(50, $c->uptime_score - (mt_rand(10, 50) / 100)); // Substantial daily damage
        $c->latency_score = min(500, $c->latency_score + mt_rand(5, 20)); // Spiking latency
        $c->reputation = max(0, $c->reputation - (mt_rand(5, 15) / 100)); // Rep damage
        
        // Very small chance the mole is discovered and removed naturally
        if (rand(1, 100) <= 2) {
            $c->has_mole = false;
        }
    }

    // Marketing budget natural decay (spending)
        $c->marketing_budget = max(0, $c->marketing_budget - mt_rand(100, 500));

        // Assets value natural growth from revenue
        $revenueGrowth = $c->market_share * 1000 * $c->price_modifier * $econMods['customer_spend'];
        $c->assets_value += max(0, $revenueGrowth);

        // Profit margin mean reversion toward 15%
        $c->profit_margin += (0.15 - $c->profit_margin) * 0.02;

        // Track market share direction
        $prevShare = (float) $c->getOriginal('market_share');
        if ($c->market_share > $prevShare) {
            $c->expansion_streak++;
            $c->contraction_streak = 0;
        } elseif ($c->market_share < $prevShare) {
            $c->contraction_streak++;
            $c->expansion_streak = 0;
        }
    }

    // ─── STRATEGIC REACTIONS ───────────────────────────

    /**
     * React to a specific player action.
     * Called from other services when the player makes significant moves.
     */
    public function reactToPlayerAction(User $player, string $actionType, array $context = []): void
    {
        $competitors = Competitor::where('status', 'active')->get();

        foreach ($competitors as $competitor) {
            $profile = self::ARCHETYPE_PROFILES[$competitor->archetype] ?? self::ARCHETYPE_PROFILES['premium_stability'];

            match ($actionType) {
                'price_cut' => $this->reactToPriceCut($competitor, $profile, $context),
                'region_entry' => $this->reactToRegionEntry($competitor, $profile, $context),
                'reputation_gain' => $this->reactToReputationGain($competitor, $profile, $context),
                'massive_expansion' => $this->reactToMassiveExpansion($competitor, $profile, $context),
                'accept_whale' => $this->reactToMajorClient($competitor, $profile, $context, 'whale', $player),
                'accept_enterprise' => $this->reactToMajorClient($competitor, $profile, $context, 'enterprise', $player),
                'marketing_campaign' => $this->reactToMarketing($competitor, $profile, $context),
                default => null,
            };

            $competitor->save();
        }
    }

    private function reactToPriceCut(Competitor $c, array $profile, array $context): void
    {
        // Only aggressive/budget competitors counter price cuts
        if ($profile['price_aggression'] > 0.5 && mt_rand(1, 100) < 60) {
            $c->price_modifier = max(0.40, $c->price_modifier - 0.03);
            $c->profit_margin = max(-0.10, $c->profit_margin - 0.01);
            Log::info("[CompetitorAI] {$c->name} counters player price cut");
        }
    }

    private function reactToRegionEntry(Competitor $c, array $profile, array $context): void
    {
        $region = $context['region'] ?? null;
        if (!$region) return;

        // Regional specialists defend their territory
        if ($c->headquarters_region === $region && $profile['defense_reaction'] > 0.5) {
            $c->marketing_budget += 5000;
            $c->price_modifier = max(0.40, $c->price_modifier - 0.05);
            Log::info("[CompetitorAI] {$c->name} defends region {$region} against player entry");
        }
    }

    private function reactToReputationGain(Competitor $c, array $profile, array $context): void
    {
        if ($profile['marketing_weight'] > 0.5 && mt_rand(1, 100) < 40) {
            $c->marketing_budget += 3000;
            Log::info("[CompetitorAI] {$c->name} boosts marketing in response to player reputation gain");
        }
    }

    private function reactToMassiveExpansion(Competitor $c, array $profile, array $context): void
    {
        if ($profile['expansion_bias'] > 0.6 && mt_rand(1, 100) < 50) {
            $c->capacity_score += 100;
            $c->assets_value -= 100000;
            Log::info("[CompetitorAI] {$c->name} expands capacity in response to player expansion");
        }
    }

    private function reactToMajorClient(Competitor $c, array $profile, array $context, string $type, User $player): void
    {
        $enmityGain = $type === 'whale' ? 10 : 5;
        $c->player_enmity = min(100, $c->player_enmity + $enmityGain);

        // High aggression NPCs might strike immediately
        if ($c->player_enmity > 60 && mt_rand(1, 100) < 30) {
            $this->triggerHostileEffect($c, $player, 'ddos');
        }
    }

    private function reactToMarketing(Competitor $c, array $profile, array $context): void
    {
        $c->player_enmity = min(100, $c->player_enmity + 2);
        
        if ($profile['marketing_weight'] > 0.6 && mt_rand(1, 100) < 40) {
            $c->marketing_budget += 2000;
            Log::info("[CompetitorAI] {$c->name} boosts marketing to counter player campaign");
        }
    }
}
