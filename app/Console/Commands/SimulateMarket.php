<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Competitor;
use App\Models\GameConfig;
use App\Services\Game\GameLoopService;
use App\Services\Game\MarketService;
use App\Services\Market\MarketSimulationService;
use Illuminate\Console\Command;

class SimulateMarket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:simulate-market {ticks=100} {--user=1} {--interval=10} {--fake-capacity=0} {--crisis} {--price-war}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate multiple game ticks to test market balancing and NPC behavior';

    private array $initialState = [];

    /**
     * Execute the console command.
     */
    public function handle(GameLoopService $gameLoop, MarketSimulationService $marketSim, MarketService $marketService): void
    {
        $ticks = (int) $this->argument('ticks');
        $userId = (int) $this->option('user');
        $interval = (int) $this->option('interval');
        $fakeCapacity = (int) $this->option('fake-capacity');
        
        $user = User::find($userId) ?: User::first();

        if (!$user) {
            $this->error('No user found for simulation.');
            return;
        }

        $this->info("🚀 Starting simulation for {$ticks} ticks...");
        $this->info("👤 Target User: {$user->name} (ID: {$user->id})");
        
        if ($this->option('crisis')) {
            $this->warn("📉 FORCING ECONOMIC CRISIS!");
            GameConfig::set('market.economic_state', 'crisis', 'market');
            GameConfig::set('market.economic_cycle_tick', 0, 'market');
        }

        if ($this->option('price-war')) {
            $this->warn("⚔️ TRIGGERING GLOBAL PRICE WAR!");
            foreach (Competitor::where('status', 'active')->get() as $c) {
                \Illuminate\Support\Facades\Cache::put("npc_event_{$c->id}_price_war", true, 3600);
            }
        }

        // Store initial state for trend analysis
        $this->captureInitialState($user, $marketService);
        
        $this->info("📈 Initial Market State:");
        $this->outputState($user, $marketService);

        for ($i = 1; $i <= $ticks; $i++) {
            // 1. Global Simulation Tick
            $globalTick = (int) GameConfig::get('global_tick_count', 0) + 1;
            GameConfig::set('global_tick_count', $globalTick);
            $marketSim->globalTick($globalTick);

            // 2. Player Tick
            $gameLoop->processUserTick($user);

            // 3. Periodic Reporting
            if ($i % $interval === 0 || $i === $ticks) {
                $this->info("\n>>> Tick {$i} / {$ticks} <<<");
                $this->outputState($user, $marketService);
            }
        }

        $this->info("\n✅ Simulation complete.");
    }

    private function captureInitialState(User $user, MarketService $marketService): void
    {
        $this->initialState = [
            'player' => $marketService->getPlayerMarketShare($user),
            'competitors' => Competitor::where('status', 'active')->pluck('market_share', 'id')->toArray(),
        ];
    }

    /**
     * Output the current market state in a table.
     */
    private function outputState(User $user, MarketService $marketService): void
    {
        $competitors = Competitor::where('status', 'active')->orderBy('market_share', 'desc')->get();
        $playerShare = $marketService->getPlayerMarketShare($user);
        $economy = $user->economy;

        $headers = ['Participant', 'Archetype', 'Share (%)', 'Trend', 'Reputation', 'Assets/Balance', 'Expansion'];
        $rows = [];

        // Trend calculation
        $playerTrend = $playerShare - ($this->initialState['player'] ?? 0);
        $trendStr = $playerTrend >= 0 ? "<fg=green>+" . number_format($playerTrend, 2) . "</>" : "<fg=red>" . number_format($playerTrend, 2) . "</>";

        // Player Row
        $rows[] = [
            '<fg=green>YOU (Player)</>',
            'User',
            number_format($playerShare, 2),
            $trendStr,
            number_format($economy->reputation, 1),
            number_format($economy->balance, 0) . ' $',
            'N/A'
        ];

        // Competitor Rows
        foreach ($competitors as $c) {
            $cInitial = $this->initialState['competitors'][$c->id] ?? $c->market_share;
            $cTrend = $c->market_share - $cInitial;
            $cTrendStr = $cTrend >= 0 ? "<fg=green>+" . number_format($cTrend, 2) . "</>" : "<fg=red>" . number_format($cTrend, 2) . "</>";

            $rows[] = [
                $c->name,
                $c->archetype,
                number_format($c->market_share, 2),
                $cTrendStr,
                number_format($c->reputation, 1),
                number_format($c->assets_value, 0) . ' $',
                $c->expansion_streak . ' ↗'
            ];
        }

        $this->table($headers, $rows);
        
        // Output Global Economic State
        $state = GameConfig::get('market.economic_state', 'unknown');
        $gdp = GameConfig::get('market.gdp_growth_rate', 0);
        $servedRatio = GameConfig::get('market.demand_served_ratio', 0);
        $totalDemand = GameConfig::get('market.total_demand_generated', 0);

        $this->comment("Economic Context: [" . strtoupper($state) . "] | GDP Growth: " . round($gdp * 100, 2) . "%");
        $this->comment("Market Saturation: " . round($servedRatio * 100, 1) . "% Served | Unmet Demand: " . number_format($totalDemand * (1 - $servedRatio), 0) . " units");
    }
}
