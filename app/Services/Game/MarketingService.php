<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\MarketingCampaign;
use App\Models\Customer;
use App\Services\Game\CustomerOrderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MarketingService
{
    public function __construct() {}

    public const CAMPAIGN_TYPES = [
        'email_blast' => [
            'name' => 'Email Blast',
            'cost' => 500,
            'duration' => 60, // 1 hour
            'effectiveness' => 1.5, // 1.5x customer generation chance
            'reputation_gain' => 0.5,
            'min_reputation' => 0
        ],
        'social_media' => [
            'name' => 'Social Media Ads',
            'cost' => 2500,
            'duration' => 360, // 6 hours
            'effectiveness' => 3.0,
            'reputation_gain' => 2.0,
            'min_reputation' => 10
        ],
        'influencer' => [
            'name' => 'Tech Influencer Review',
            'cost' => 8000,
            'duration' => 1440, // 24 hours
            'effectiveness' => 5.0,
            'reputation_gain' => 8.0,
            'min_reputation' => 30
        ],
        'billboard' => [
            'name' => 'City Billboard',
            'cost' => 15000,
            'duration' => 10080, // 7 days (in minutes) - simplified to 1 week game time
            'effectiveness' => 2.0,
            'reputation_gain' => 12.0,
            'min_reputation' => 50
        ],
        'tv_spot' => [
            'name' => 'Super Bowl TV Spot',
            'cost' => 100000,
            'duration' => 60, // 1 hour (intense burst)
            'effectiveness' => 20.0,
            'reputation_gain' => 25.0,
            'min_reputation' => 80
        ],
        'b2b_outreach' => [
            'name' => 'B2B Enterprise Outreach',
            'cost' => 250000,
            'duration' => 2880, // 48 hours
            'effectiveness' => 50.0,
            'reputation_gain' => 50.0,
            'min_reputation' => 90,
            'effects' => [
                'enterprise_probability' => 45,
                'whale_probability' => 20
            ]
        ],
        // FEATURE 90: PR Agency Campaigns
        'pr_crisis_response' => [
            'name' => 'PR Crisis Response Team',
            'cost' => 25000,
            'duration' => 120, // 2 hours (fast emergency recovery)
            'effectiveness' => 1.2, // Slight order boost
            'reputation_gain' => 30.0,
            'min_reputation' => 0, // Available at ANY reputation
            'max_reputation' => 60, // Only when reputation is LOW
            'description' => 'Deploy a PR agency to do damage control. Rapidly recovers reputation after outages or scandals.',
            'effects' => [
                'reputation_recovery_speed' => 3.0, // 3x faster passive rep recovery
            ]
        ],
        'pr_brand_ambassador' => [
            'name' => 'PR Brand Ambassador Program',
            'cost' => 50000,
            'duration' => 480, // 8 hours (sustained campaign)
            'effectiveness' => 2.5,
            'reputation_gain' => 50.0,
            'min_reputation' => 30,
            'description' => 'Hire industry ambassadors to rebuild trust and attract premium clients.',
            'effects' => [
                'reputation_recovery_speed' => 2.0,
                'enterprise_probability' => 20,
            ]
        ],
    ];

    /**
     * Start a new marketing campaign.
     */
    public function startCampaign(User $user, string $type): MarketingCampaign
    {
        $campaignTypes = \App\Models\GameConfig::get('marketing_campaigns', self::CAMPAIGN_TYPES);

        if (!isset($campaignTypes[$type])) {
            throw new \Exception("Invalid campaign type.");
        }

        $config = $campaignTypes[$type];
        $economy = $user->economy;

        if (!$economy->canAfford($config['cost'])) {
            throw new \Exception("Insufficient funds.");
        }

        if ($economy->reputation < $config['min_reputation']) {
            throw new \Exception("Reputation too low for this campaign.");
        }

        // FEATURE 90: PR campaigns may have max reputation (only available during crisis)
        if (isset($config['max_reputation']) && $economy->reputation > $config['max_reputation']) {
            throw new \Exception("Your reputation is too high for this crisis-response campaign. It's only available when reputation is below {$config['max_reputation']}.");
        }

        $economy->debit($config['cost'], "Marketing: {$config['name']}", 'marketing');

        $campaign = MarketingCampaign::create([
            'user_id' => $user->id,
            'type' => $type,
            'name' => $config['name'],
            'cost' => $config['cost'],
            'duration_minutes' => $config['duration'],
            'started_at' => now(),
            'ends_at' => now()->addMinutes($config['duration']),
            'status' => 'active',
            'results' => [
                'customers_gained' => 0,
                'reputation_gained' => 0,
            ]
        ]);

        Log::info("User {$user->id} started marketing campaign: {$type}");

        return $campaign;
    }

    /**
     * Process active campaigns (tick).
     * This is called by the GameLoopService.
     */
    public function tick(User $user): void
    {
        $activeCampaigns = MarketingCampaign::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->get();

        foreach ($activeCampaigns as $campaign) {
            $campaignTypes = \App\Models\GameConfig::get('marketing_campaigns', self::CAMPAIGN_TYPES);
            $config = $campaignTypes[$campaign->type] ?? null;

            if (!$config) continue;
            
            // 1. Reputation Gain (trickle over time)
            // Total gain / duration * 1 tick (1 min)
            $repGainPerTick = $config['reputation_gain'] / $config['duration'];
            
            // Research Bonus: Brand Identity Kit
            $researchService = app(\App\Services\Game\ResearchService::class);
            $repBonus = $researchService->getBonus($user, 'rep_gain_multiplier');
            $repGainPerTick *= (1 + $repBonus);

            $user->economy->adjustReputation($repGainPerTick);

            $results = $campaign->results ?? ['customers_gained' => 0, 'reputation_gained' => 0];
            $results['reputation_gained'] += $repGainPerTick;
            
            // 2. Customer Acquisition Boost
            // Base chance is handled in OrderService, but we can trigger EXTRA customers here directly
            // dependent on effectiveness.
            // Example: Effectiveness 5.0 => 5% chance per tick to get an extra lead immediately.
            
            $chance = $config['effectiveness']; 
            if (rand(0, 1000) < ($chance * 10)) { // 10.0 effectiveness = 100/1000 = 10%
                $this->generateLead($user, $campaign);
                $results['customers_gained']++;
            }

            $campaign->results = $results;
            $campaign->save();
        }

        // Complete finished campaigns
        MarketingCampaign::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('ends_at', '<=', now())
            ->update(['status' => 'completed']);
    }

    private function generateLead(User $user, MarketingCampaign $campaign): void
    {
        // Use existing customer logic but force a new customer creation
        // Only if we don't have too many pending orders (spam prevention)
        
        // This is a simplified direct hook. Ideally we call CustomerOrderService.
        // For now, we'll let the campaign boost the `OrderService` probability indirectly via a getter,
        // OR we can directly inject a customer here. Let's do direct injection for immediate feedback.
        
        // Create a temporary "lead" or just trigger the standard customer generation logic forcefully
        // We'll trust that the GameLoop calls OrderService, which we will modify to check for active campaigns.
        
        // Actually, let's keep it clean: We just track stats here. 
        // The ACTUAL generation boost should happen in the CustomerOrderService by checking active campaigns.
        // BUT, to make campaigns feel "powerful", let's force a generation here occasionally.
        
        // Let's rely on the random chance above to call a helper in CustomerOrderService?
        // Since we can't easily modify the service dependency loop right now, we'll duplicate a tiny bit of logic
        // or just let the OrderService handle it if we expose a "boost" method.
        
        // Plan: We will update the OrderService to look for active campaigns. 
        // The logic above in `tick` is just for tracking stats/reputation.
    }

    /**
     * Get total effectiveness multiplier from all active campaigns.
     */
    public function getActiveCampaignMultiplier(User $user): float
    {
        $activeCampaigns = MarketingCampaign::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->get();

        $multiplier = 1.0;
        foreach ($activeCampaigns as $campaign) {
            $campaignTypes = \App\Models\GameConfig::get('marketing_campaigns', self::CAMPAIGN_TYPES);
            $config = $campaignTypes[$campaign->type] ?? null;
            if ($config) {
                // Additive or Multiplicative? Let's go additive for simplicity then add to base 1.
                // If effectiveness is "3.0" (3x), we add 2.0 to the multiplier.
                // So 1 campaign of 3x -> 3x total.
                // 2 campaigns of 3x -> 5x total.
                $multiplier += ($config['effectiveness'] - 1.0);
            }
        }

        return max(1.0, $multiplier);
    }

    /**
     * FEATURE 90: Get rep recovery speed multiplier from active PR campaigns
     */
    public function getReputationRecoverySpeed(User $user): float
    {
        $activeCampaigns = MarketingCampaign::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->whereIn('type', ['pr_crisis_response', 'pr_brand_ambassador'])
            ->get();

        $multiplier = 1.0;
        $campaignTypes = \App\Models\GameConfig::get('marketing_campaigns', self::CAMPAIGN_TYPES);

        foreach ($activeCampaigns as $campaign) {
            $config = $campaignTypes[$campaign->type] ?? null;
            if ($config && isset($config['effects']['reputation_recovery_speed'])) {
                $multiplier *= $config['effects']['reputation_recovery_speed'];
            }
        }

        return $multiplier;
    }

    public function hasEnterpriseCampaign(User $user): bool
    {
        return MarketingCampaign::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->where('type', 'b2b_outreach')
            ->exists();
    }

    public function getSlaTierBonus(User $user): int
    {
        $activeCampaigns = MarketingCampaign::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->get();

        $totalBonus = 0;
        $campaignTypes = \App\Models\GameConfig::get('marketing_campaigns', self::CAMPAIGN_TYPES);

        foreach ($activeCampaigns as $campaign) {
            $config = $campaignTypes[$campaign->type] ?? null;
            if ($config) {
                // Check if effect is specifically for enterprise/whale
                $totalBonus += ($config['effects']['enterprise_probability'] ?? 0);
                $totalBonus += ($config['effects']['whale_probability'] ?? 0);
            }
        }

        return $totalBonus;
    }

    /**
     * Get regional demand and volatility predictions for the next cycles.
     */
    public function getMarketPredictions(User $user): array
    {
        // Requires high marketing level or specific research
        $level = $user->economy->level;
        if ($level < 15) {
            return [
                'success' => false, 
                'error' => 'Level 15 required for Market Volatility Predictions.',
                'predictions' => []
            ];
        }

        $regions = array_keys(\App\Models\GameConfig::get('regions', ['us_east' => []]));
        $predictions = [];

        foreach ($regions as $r) {
            // Seed randomness by user/region/day for consistency
            $seedStr = $user->id . $r . now()->format('Y-m-H'); // Shifts every hour
            mt_srand(crc32($seedStr));
            
            $predictionTypes = [
                'demand_spike' => [
                    'label' => 'Demand Surge',
                    'desc' => 'Regional gaming demand is expected to surge! +40% lead generation soon.',
                    'impact' => 'positive'
                ],
                'hardware_crunch' => [
                    'label' => 'Supply Chain Strain',
                    'desc' => 'Global chip shortage detected. Component delivery times may double.',
                    'impact' => 'negative'
                ],
                'regulatory_headwind' => [
                    'label' => 'Compliance Update',
                    'desc' => 'Upcoming privacy laws will increase customer retention risk.',
                    'impact' => 'neutral'
                ],
                'hyper_growth' => [
                    'label' => 'Venture Boom',
                    'desc' => 'Massive startup funding in ' . strtoupper($r) . ' will trigger enterprise-class leads.',
                    'impact' => 'premium'
                ]
            ];
            
            $keys = array_keys($predictionTypes);
            $typeKey = $keys[mt_rand(0, count($keys) - 1)];
            $pred = $predictionTypes[$typeKey];
            
            $predictions[] = [
                'region' => $r,
                'type' => $typeKey,
                'label' => $pred['label'],
                'description' => $pred['desc'],
                'confidence' => mt_rand(65, 98),
                'impact_type' => $pred['impact']
            ];
        }
        
        mt_srand(); // Reset

        return [
            'success' => true,
            'predictions' => $predictions
        ];
    }
}
