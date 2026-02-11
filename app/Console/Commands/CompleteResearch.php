<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserResearch;
use Illuminate\Console\Command;

class CompleteResearch extends Command
{
    protected $signature = 'game:complete-research {user_id} {key}';
    protected $description = 'Instantly complete a research project for a user';

    public function handle()
    {
        $userId = $this->argument('user_id');
        $key = $this->argument('key');

        $user = User::findOrFail($userId);

        // Check if exists
        $research = UserResearch::where('user_id', $user->id)
            ->where('research_key', $key)
            ->where('status', 'active')
            ->first();

        if (!$research) {
            // Create completed entry if not exists?
            // Or force complete if pending?
            $this->error("No active research found for key: $key. Start it first via API.");
            return;
        }

        $research->status = 'completed';
        $research->progress = 100;
        $research->completed_at = now();
        $research->save();

        $this->info("Completed research $key for user {$user->name}");
    }
}
