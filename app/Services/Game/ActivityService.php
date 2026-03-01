<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\PlayerEconomy;
use App\Models\Customer;
use App\Models\GameEvent;
use Carbon\Carbon;

class ActivityService
{
    /**
     * Generate a summary of what happened since last_summary_at
     */
    public function generateLoginSummary(User $user): ?array
    {
        $lastSummary = $user->last_summary_at;
        if (!$lastSummary) {
            $user->last_summary_at = now();
            $user->save();
            return null;
        }

        $now = now();
        $minutesAway = $lastSummary->diffInMinutes($now);

        // Don't show summary if away for less than 10 minutes
        if ($minutesAway < 10) {
            $user->last_summary_at = $now;
            $user->save();
            return null;
        }

        $economy = $user->economy;
        
        // Calculate estimated earnings (Hourly Income / 60 * minutes)
        // This is a rough estimation since income fluctuates
        $estimatedIncome = ($economy->hourly_income / 60) * $minutesAway;
        $estimatedExpenses = ($economy->hourly_expenses / 60) * $minutesAway;
        $netProfit = $estimatedIncome - $estimatedExpenses;

        // Events that happened
        $newEvents = GameEvent::where('user_id', $user->id)
            ->where('created_at', '>', $lastSummary)
            ->count();

        $resolvedEvents = GameEvent::where('user_id', $user->id)
            ->where('resolved_at', '>', $lastSummary)
            ->where('status', 'resolved')
            ->count();

        $failedEvents = GameEvent::where('user_id', $user->id)
            ->where('resolved_at', '>', $lastSummary)
            ->where('status', 'failed')
            ->count();

        // Churn
        $churnedCount = Customer::where('user_id', $user->id)
            ->where('status', 'churned')
            ->where('churn_at', '>', $lastSummary)
            ->count();

        // Update last summary
        $user->last_summary_at = $now;
        $user->save();

        return [
            'minutesAway' => $minutesAway,
            'timeRange' => [
                'start' => $lastSummary->toIso8601String(),
                'end' => $now->toIso8601String(),
            ],
            'finances' => [
                'income' => round($estimatedIncome, 2),
                'expenses' => round($estimatedExpenses, 2),
                'net' => round($netProfit, 2),
            ],
            'incidents' => [
                'new' => $newEvents,
                'resolved' => $resolvedEvents,
                'failed' => $failedEvents,
            ],
            'customers' => [
                'churned' => $churnedCount,
            ]
        ];
    }

    /**
     * Mark user as active
     */
    public function updateLastActive(User $user): void
    {
        $user->last_active_at = now();
        $user->save();
    }
}
