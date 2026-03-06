<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\QA\Bots\BeginnerBot;
use App\QA\Bots\ChaosBot;
use App\QA\Bots\ExpansionBot;
use App\QA\Bots\AggressiveInvestorBot;
use App\QA\Bots\OptimizationBot;
use App\QA\Bots\SecurityExploitBot;
use App\QA\Exploits\ExploitDetector;
use App\QA\Reporting\QAReport;
use Illuminate\Support\Str;
use App\Models\PlayerEconomy;

class RunMegaQA extends Command
{
    protected $signature = 'qa:mega {--bots=10 : Number of bot players} {--ticks=100 : Number of simulation ticks} {--clean : Reset existing bots}';
    protected $description = 'Run a massive automated QA simulation with bot players.';

    protected array $activeBots = [];
    protected array $metricsHistory = [];

    public function handle()
    {
        $this->info('🚀 INITIALIZING MEGA QA SYSTEM...');
        
        $botCount = (int)$this->option('bots');
        $ticks = (int)$this->option('ticks');

        if ($this->option('clean')) {
            $this->warn('CLEANING PREVIOUS BOT POOL...');
            User::where('name', 'LIKE', 'QA_BOT_%')->delete();
        }

        // 1. Setup Bot Pool
        $this->info("Setting up pool of {$botCount} bots...");
        $this->setupBots($botCount);

        // 2. Main Simulation Loop
        $this->info("Simulating {$ticks} ticks of gameplay...");
        $bar = $this->output->createProgressBar($ticks);
        $bar->start();

        $exploitDetector = new ExploitDetector();

        for ($t = 1; $t <= $ticks; $t++) {
            foreach ($this->activeBots as $bot) {
                try {
                    $bot->tick($t);
                    $exploitDetector->check($bot->getUser());
                } catch (\Exception $e) {
                    $this->error("\nBot Error Exception: " . $e->getMessage());
                }
                
                // Debug actions
                foreach ($bot->getLogs() as $log) {
                    if (!$log['success']) {
                        $this->warn("\nAction Failed: " . $log['action'] . " - " . $log['message']);
                    }
                }
            }

            // Capture snapshots for reporting
            $this->captureMetrics($t);
            $bar->advance();
        }

        $bar->finish();
        $this->info("\n✅ SIMULATION COMPLETE.");

        // 3. Generate Report
        $report = new QAReport($this->activeBots, $this->metricsHistory, $exploitDetector->detectedExploits);
        $reportPath = $report->generate();

        $this->info("📈 REPORT GENERATED: {$reportPath}");
    }

    protected function setupBots(int $count)
    {
        $strategies = [
            BeginnerBot::class, 
            ExpansionBot::class, 
            ChaosBot::class,
            AggressiveInvestorBot::class,
            OptimizationBot::class,
            SecurityExploitBot::class
        ];

        for ($i = 1; $i <= $count; $i++) {
            $email = "qa_bot_{$i}@" . Str::random(10) . ".test";
            
            $user = User::firstOrCreate(['email' => $email], [
                'name' => "QA_BOT_{$i}",
                'company_name' => "QA_CORP_" . $i,
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);

            // Ensure economy exists
            if (!$user->economy) {
                PlayerEconomy::create([
                    'user_id' => $user->id,
                    'balance' => 200000,
                    'reputation' => 50,
                    'level' => 5,
                    'experience' => 0
                ]);
            }

            $strategyClass = $strategies[$i % count($strategies)];
            $this->activeBots[] = new $strategyClass($user);
        }
    }

    protected function captureMetrics(int $tick)
    {
        $totalBalance = 0;
        $avgRep = 0;
        $totalRacks = 0;
        $totalServers = 0;

        foreach ($this->activeBots as $bot) {
            $u = $bot->getUser();
            $totalBalance += $u->economy->balance;
            $avgRep += $u->economy->reputation;
            $totalRacks += $u->rooms->sum(fn ($r) => $r->racks->count());
            $totalServers += $u->servers()->count();
        }

        $this->metricsHistory[$tick] = [
            'total_wealth' => $totalBalance,
            'avg_balance' => $totalBalance / count($this->activeBots),
            'avg_reputation' => $avgRep / count($this->activeBots),
            'total_racks' => $totalRacks,
            'total_servers' => $totalServers
        ];
    }
}
