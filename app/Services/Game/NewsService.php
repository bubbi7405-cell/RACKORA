<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\GameEvent;
use App\Models\Competitor;
use App\Models\WorldEvent;
use Illuminate\Support\Facades\Log;
use App\Events\NewsBroadcasted;

/**
 * NewsService - Generates and broadcasts the "Live News Engine" (Blueprint 4.1)
 */
class NewsService
{
    /**
     * Flavor news templates for random injects.
     */
    private const FLAVOR_NEWS = [
        ['title' => 'NVIDIA releases next-gen Blackwell chips', 'type' => 'info'],
        ['title' => 'Digital Nomad population hits record high', 'type' => 'info'],
        ['title' => 'Cybercrime on the rise: CISO reports suggest AI-usage', 'type' => 'warning'],
        ['title' => 'Crypto Market Volatility: Hardware prices fluctuating', 'type' => 'info'],
        ['title' => 'Silicon Shortage: Delivery times for servers increasing', 'type' => 'warning'],
        ['title' => 'Green Data Centers: EU introduces new carbon tax', 'type' => 'warning'],
        ['title' => 'SpaceX Starlink achieves 1Gbps globally', 'type' => 'info'],
        ['title' => 'Zero-Trust Architecture: The new standard for VPC security', 'type' => 'info'],
        ['title' => 'Major Breach: Competitor reported 10TB data leak', 'type' => 'error'],
    ];

    /**
     * Generate news based on current game state and broadcast it to the player.
     */
    public function generateNews(User $user): void
    {
        $news = [];

        // 0. Macro-Economic News
        $econState = \App\Models\GameConfig::get('market.economic_state', 'growth');
        $lastTransition = (int) \App\Models\GameConfig::get('market.last_transition_tick', 0);
        $globalTick = (int) \App\Models\GameConfig::get('global_tick_count', 0);
        
        if ($globalTick - $lastTransition < 15) {
            $news[] = [
                'headline' => "BREAKING: Global economy transitions into " . strtoupper($econState) . " phase.",
                'type' => 'breaking',
                'category' => 'ECONOMY'
            ];
        }

        // 1. Economic News
        $economy = $user->economy;
        if ($economy) {
            // Check for significant changes or states
            if ($economy->balance < 1000) {
                $news[] = [
                    'headline' => "CFO ALERT: {$user->company_name} liquidity critical!",
                    'type' => 'error'
                ];
            }
        }

        // 2. Network / Market News
        $network = $user->network;
        if ($network) {
            if ($network->ipv4_used / max(1, $network->ipv4_total) > 0.9) {
                $news[] = [
                    'headline' => "MARKET ALERT: Global IPv4 scarcity index rising. Prices surging!",
                    'type' => 'warning'
                ];
            }
        }

        // 2b. Private Network / Security News
        $privateNetworks = \App\Models\PrivateNetwork::where('user_id', $user->id)->get();
        foreach ($privateNetworks as $net) {
            if ($net->traffic_denied_count > 50) {
                $news[] = [
                    'headline' => "SECURITY: Massive packet drops detected on '{$net->name}'. Unusual external traffic activity.",
                    'type' => 'warning',
                    'category' => 'FIREWALL'
                ];
            }
        }

        // 3. Competitor News
        $competitors = Competitor::where('status', 'active')->inRandomOrder()->limit(3)->get();
        foreach ($competitors as $competitor) {
            if ($competitor->expansion_streak > 2) {
                $news[] = [
                    'headline' => "MARKET WATCH: {$competitor->name} aggressively acquiring market share!",
                    'type' => 'info'
                ];
            }
            if ($competitor->market_share > 30) {
                 $news[] = [
                    'headline' => "DOMINANCE: {$competitor->name} now controls " . round($competitor->market_share) . "% of the market.",
                    'type' => 'warning'
                ];
            }
        }

        // 4. Flavor inject (if no critical news)
        if (empty($news) || rand(1, 100) < 20) {
            $flavor = self::FLAVOR_NEWS[array_rand(self::FLAVOR_NEWS)];
            $news[] = [
                'headline' => $flavor['title'],
                'type' => $flavor['type']
            ];
        }

        // Broadcast a random piece from the generated news
        if (!empty($news)) {
            $item = $news[array_rand($news)];
            $this->broadcast($user, $item['headline'], $item['type'], $item['category'] ?? 'ALERT');
        }
    }

    /**
     * Get initial headlines for the player dashboard.
     */
    public function getHeadlines(User $user): array
    {
        // Return a mix of recent world events and flavor news
        $worldEvents = \App\Models\WorldEvent::where('is_active', true)->limit(2)->get();
        $headlines = [];

        foreach ($worldEvents as $event) {
            $headlines[] = [
                'headline' => $event->name,
                'category' => 'WORLD',
                'type' => 'breaking',
                'content' => $event->description
            ];
        }

        // Add 3 random flavor news
        $flavorKeys = array_rand(self::FLAVOR_NEWS, 3);
        foreach ($flavorKeys as $key) {
            $item = self::FLAVOR_NEWS[$key];
            $headlines[] = [
                'headline' => $item['title'],
                'category' => 'MARKET',
                'type' => $item['type'] === 'error' ? 'breaking' : $item['type'],
            ];
        }

        return $headlines;
    }

    /**
     * Broadcast a specific news item to the user.
     */
    public function broadcast(User $user, string $headline, string $type = 'info', string $category = 'ALERT'): void
    {
        event(new NewsBroadcasted($user, [
            'id' => uniqid(),
            'headline' => $headline,
            'category' => $category,
            'type' => $type,
            'timestamp' => now()->toIso8601String(),
        ]));
    }

    /**
     * Broadcast a news item to ALL users.
     */
    public function broadcastGlobal(string $headline, string $type = 'info', string $category = 'WORLD'): void
    {
        $users = User::all(); // In V2, might filter by active session
        foreach ($users as $user) {
            $this->broadcast($user, $headline, $type, $category);
        }
    }

    /**
     * Helper to broadcast specific competitor actions.
     */
    public function broadcastCompetitorAction(Competitor $competitor, string $action, string $detail): void
    {
        $headlines = [
            'adjust_pricing' => "MARKET: {$competitor->name} adjusted their pricing strategy.",
            'expand_capacity' => "EXPANSION: {$competitor->name} is aggressively increasing infrastructure capacity.",
            'boost_marketing' => "BRAND: {$competitor->name} launched a massive global marketing campaign!",
            'invest_innovation' => "TECH: Breakthrough reported at {$competitor->name} R&D labs.",
        ];

        $headline = $headlines[$action] ?? "NEWS: {$competitor->name} made a strategic move: {$detail}";
        $this->broadcastGlobal($headline, 'info', 'MARKET');
    }
}
