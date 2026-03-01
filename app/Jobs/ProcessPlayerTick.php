<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPlayerTick implements ShouldQueue
{
    use \Illuminate\Queue\SerializesModels;
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public \App\Models\User $user)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(\App\Services\Game\GameLoopService $gameLoop): void
    {
        try {
            $gameLoop->processUserTick($this->user);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Async Tick Error (User {$this->user->id}): " . $e->getMessage());
        }
    }
}
