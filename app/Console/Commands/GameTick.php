<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Game\GameLoopService;

class GameTick extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:tick';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process a game tick (economy, orders, server status)';

    /**
     * Execute the console command.
     */
    public function handle(GameLoopService $service): void
    {
        $this->info('Starting game tick...');
        
        try {
            $service->processTick();
            $this->info('Game tick executed successfully.');
        } catch (\Exception $e) {
            $this->error('Game tick failed: ' . $e->getMessage());
        }
    }
}
