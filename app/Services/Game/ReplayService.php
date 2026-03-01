<?php

namespace App\Services\Game;

use App\Models\GameLog;
use App\Models\GameStatistic;
use App\Models\User;
use Illuminate\Support\Collection;

class ReplayService
{
    /**
     * Get replay data (stats + logs) for timeline visualization.
     */
    public function getReplayData(User $user, int $limit = 100): array
    {
        // Fetch stats (Timeline points)
        $stats = GameStatistic::where('user_id', $user->id)
            ->orderBy('created_at', 'desc') // Get newest first
            ->limit($limit)
            ->get()
            ->reverse() // Reorder chronological
            ->values();

        if ($stats->isEmpty()) {
            return [
                'stats' => [],
                'logs' => [],
                'range' => ['start' => null, 'end' => null]
            ];
        }

        // Determine time range from stats
        $startTime = $stats->first()->created_at;
        $endTime = $stats->last()->created_at;

        // Fetch logs within that window (plus some buffer)
        $logs = GameLog::where('user_id', $user->id)
            ->where('created_at', '>=', $startTime)
            ->where('created_at', '<=', $endTime)
            ->orderBy('created_at', 'asc')
            ->get();

        return [
            'stats' => $stats,
            'logs' => $logs,
            'range' => [
                'start' => $startTime->toIso8601String(),
                'end' => $endTime->toIso8601String()
            ]
        ];
    }
}
