<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\GameStatistic;
use App\Models\Customer;
use App\Models\Server;
use App\Enums\ServerStatus;

class StatsService
{
    /**
     * Record a snapshot of the user's current game state.
     * Should be called periodically (e.g. every game tick/minute).
     */
    public function recordSnapshot(User $user, int $tick = 0): void
    {
        $economy = $user->economy;
        if (!$economy) return;

        // Calculate aggregates
        $activeCustomers = Customer::where('user_id', $user->id)
            ->where('status', 'active')
            ->count();
            
        // Use query for average satisfaction to avoid loading all models
        $avgSatisfaction = Customer::where('user_id', $user->id)
            ->whereIn('status', ['active', 'unhappy'])
            ->avg('satisfaction') ?? 0;
            
        $activeServers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', ServerStatus::ONLINE)
            ->count();

        GameStatistic::create([
            'user_id' => $user->id,
            'tick' => $tick ?: time(), // Use timestamp if no tick provided
            'revenue' => $economy->hourly_income,
            'expenses' => $economy->hourly_expenses,
            'balance' => $economy->balance,
            'reputation' => $economy->reputation,
            'active_customers' => $activeCustomers,
            'active_servers' => $activeServers,
            'avg_satisfaction' => $avgSatisfaction,
        ]);
    }
    
    /**
     * Retrieve historical statistics for charts.
     */
    public function getHistory(User $user, int $limit = 50)
    {
        return GameStatistic::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse() // Reverse to get chronological order (oldest -> newest)
            ->values();
    }
}
