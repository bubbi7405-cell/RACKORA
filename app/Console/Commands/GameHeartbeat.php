<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GameHeartbeat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:heartbeat {--interval=10 : The interval in seconds between ticks}';

    protected $description = 'V2 High-Frequency Game Engine Heartbeat';

    public function handle(\App\Services\Game\GameLoopService $service)
    {
        $this->info("Rackora V2 Heartbeat started. Monitoring Engine Constants...");
        $this->warn("Simulating server-authoritative ticks for all users...");

        while (true) {
            $start = microtime(true);
            
            // Re-fetch interval each iteration for real-time architect responsiveness
            $engine = \App\Models\GameConfig::get('engine_constants', []);
            $interval = max(1, (int) ($engine['tick_rate_seconds'] ?? 15));

            try {
                // In V2, we dispatch async for maximum throughput
                $service->processTick();
                
                $executionTime = round((microtime(true) - $start) * 1000, 2);
                $this->line("[" . now()->format('H:i:s') . "] Heartbeat dispatched (Wait: {$interval}s). Latency: {$executionTime}ms");
                
            } catch (\Exception $e) {
                $this->error("Heartbeat Failure: " . $e->getMessage());
            }

            sleep($interval);
        }
    }
}
