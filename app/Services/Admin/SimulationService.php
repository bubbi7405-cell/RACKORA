<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Models\Server;
use App\Models\CustomerOrder;
use App\Models\GameRoom;
use App\Models\PlayerEconomy;
use Illuminate\Support\Facades\Log;

class SimulationService
{
    /**
     * Run a 24-hour revenue and risk projection.
     */
    public function project24h(User $user = null, string $protocol = 'economy_24h', float $intensity = 0.5): array
    {
        // If no user provided, use a random active one or a placeholder aggregate
        if (!$user) {
            $user = User::whereHas('economy')->first();
        }

        if (!$user || !$user->economy) {
            return [
                'expected_revenue' => 0,
                'risk_level' => 'unknown',
                'projected_churn' => 0,
                'bottlenecks' => ['No active users found'],
                'confidence' => 0
            ];
        }

        $economy = $user->economy;
        $hourlyIncome = $economy->hourly_income;
        $hourlyExpenses = $economy->hourly_expenses;
        
        // Base multipliers
        $revenueMultiplier = 1.0;
        $expenseMultiplier = 1.0;
        $churnMultiplier = 1.0;
        $confidenceBonus = 0;

        // Apply Protocol Logic
        switch ($protocol) {
            case 'market_crash':
                // Energy prices spike, customers tighten belts
                $expenseMultiplier = 1.0 + ($intensity * 2.5); // Up to 3.5x expenses
                $revenueMultiplier = 0.9 - ($intensity * 0.2); // Revenue drops slightly
                $confidenceBonus = -15;
                break;
            case 'network_outage':
                // Latency spikes, packet loss, massive churn
                $revenueMultiplier = 0.5 - ($intensity * 0.4); // Revenue drops up to 90%
                $churnMultiplier = 5.0 + ($intensity * 10); // Massive churn spike
                $confidenceBonus = -30;
                break;
            case 'stress_cascade':
                // Mixed chaos
                $expenseMultiplier = 1.5;
                $revenueMultiplier = 0.7;
                $churnMultiplier = 3.0;
                $confidenceBonus = -10;
                break;
            case 'spike':
                // Sudden demand spike
                $revenueMultiplier = 1.0 + ($intensity * 1.5);
                $expenseMultiplier = 1.0 + ($intensity * 0.5);
                $confidenceBonus = -5;
                break;
        }

        // 24h projection with slight stochastic variance
        $baseRevenue = (($hourlyIncome * $revenueMultiplier) - ($hourlyExpenses * $expenseMultiplier)) * 24;
        
        // Analyze bottlenecks
        $bottlenecks = [];
        
        // 1. Power Bottleneck
        $rooms = GameRoom::where('user_id', $user->id)->get();
        foreach ($rooms as $room) {
            $utilization = ($room->racks()->sum('current_power_kw')) / max(1, $room->max_power_kw);
            if ($utilization > (0.85 / ($expenseMultiplier > 1 ? 1.2 : 1))) {
                $bottlenecks[] = "Power Lattice Instability ({$room->name})";
            }
        }

        // 2. Hardware Aging Bottleneck
        $oldServers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('health', '<', 40 + ($intensity * 20))
            ->count();
        if ($oldServers > 5) {
            $bottlenecks[] = "Predicted Hardware Failure ({$oldServers} units)";
        }

        // 3. Customer Satisfaction Risk
        $unhappyCount = \App\Models\Customer::where('user_id', $user->id)
            ->where('status', 'unhappy')
            ->count();
            
        if ($protocol === 'network_outage') $unhappyCount += 15 * $intensity;
        if ($protocol === 'market_crash') $unhappyCount += 5 * $intensity;

        if ($unhappyCount > 2) {
            $bottlenecks[] = "Customer Attrition Warning";
        }

        // Risk Level
        $risk = 'low';
        if (count($bottlenecks) > 2 || $protocol === 'network_outage') $risk = 'critical';
        elseif (count($bottlenecks) > 0 || $protocol === 'market_crash') $risk = 'elevated';

        return [
            'expected_revenue' => round($baseRevenue, 2),
            'risk_level' => $risk,
            'projected_churn' => round(($unhappyCount * 1.5) * $churnMultiplier, 1),
            'bottlenecks' => array_values(array_unique($bottlenecks)),
            'confidence' => max(10, min(99, (88 + rand(1, 10) + $confidenceBonus))),
        ];
    }
}
