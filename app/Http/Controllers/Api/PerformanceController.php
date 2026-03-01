<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameEvent;
use App\Models\GameStatistic;
use App\Models\Customer;
use App\Models\Server;
use App\Models\CustomerOrder;
use App\Enums\ServerStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    /**
     * Get comprehensive company performance report
     */
    public function getReport(Request $request): JsonResponse
    {
        $user = $request->user();
        $economy = $user->economy;

        if (!$economy) {
            return response()->json(['success' => false, 'error' => 'Player not initialized']);
        }

        // --- 1. Crisis Management Score ---
        $resolvedEvents = GameEvent::where('user_id', $user->id)
            ->where('status', 'resolved')
            ->orderBy('resolved_at', 'desc')
            ->limit(50)
            ->get();

        $failedEvents = GameEvent::where('user_id', $user->id)
            ->where('status', 'failed')
            ->count();

        $gradeDistribution = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];
        $totalScore = 0;
        $gradeCount = 0;

        foreach ($resolvedEvents as $event) {
            $grade = $event->management_grade ?? 'F';
            if (isset($gradeDistribution[$grade])) {
                $gradeDistribution[$grade]++;
            }
            $totalScore += $event->management_score ?? 0;
            $gradeCount++;
        }

        // Add failures as F grades
        $gradeDistribution['F'] += $failedEvents;
        $totalEvents = $gradeCount + $failedEvents;

        $avgCrisisScore = $totalEvents > 0 ? round($totalScore / max(1, $gradeCount), 1) : null;

        // --- 2. Financial Health ---
        $recentStats = GameStatistic::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();

        $revenueHistory = $recentStats->pluck('revenue')->toArray();
        $expenseHistory = $recentStats->pluck('expenses')->toArray();
        $balanceHistory = $recentStats->pluck('balance')->toArray();
        $powerHistory = $recentStats->pluck('power_usage')->toArray();
        $bandwidthHistory = $recentStats->pluck('bandwidth_usage')->toArray();

        // Revenue trend (comparing last 10 vs previous 10)
        $revenueTrend = 0;
        if (count($revenueHistory) >= 20) {
            $recent = array_sum(array_slice($revenueHistory, -10));
            $previous = array_sum(array_slice($revenueHistory, -20, 10));
            $revenueTrend = $previous > 0 ? round((($recent - $previous) / $previous) * 100, 1) : 0;
        }

        // Profit margin
        $totalRevenue = array_sum($revenueHistory);
        $totalExpenses = array_sum($expenseHistory);
        $profitMargin = $totalRevenue > 0 ? round((($totalRevenue - $totalExpenses) / $totalRevenue) * 100, 1) : 0;

        // Cash runway (hours until bankrupt at current burn rate)
        $netPerTick = $economy->hourly_income - $economy->hourly_expenses;
        $cashRunway = $netPerTick < 0 ? round($economy->balance / abs($netPerTick), 1) : null; // null = profitable

        // --- 3. Customer Satisfaction ---
        $customers = Customer::where('user_id', $user->id)
            ->whereIn('status', ['active', 'unhappy'])
            ->get();

        $totalCustomers = $customers->count();
        $avgSatisfaction = $totalCustomers > 0 ? round($customers->avg('satisfaction'), 1) : 0;
        $unhappyCustomers = $customers->where('status', 'unhappy')->count();
        $churnedLast30 = Customer::where('user_id', $user->id)
            ->where('status', 'churned')
            ->where('updated_at', '>=', now()->subMinutes(30))
            ->count();

        $satisfactionRate = $totalCustomers > 0 
            ? round(($totalCustomers - $unhappyCustomers) / $totalCustomers * 100, 1) 
            : 100;

        // --- 4. Infrastructure Health ---
        $servers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->get();
        $totalServers = $servers->count();
        $onlineServers = $servers->where('status', ServerStatus::ONLINE)->count();
        $degradedServers = $servers->where('status', ServerStatus::DEGRADED)->count();
        $avgHealth = $totalServers > 0 ? round($servers->avg('health'), 1) : 100;
        $uptimePercent = $totalServers > 0 ? round(($onlineServers / $totalServers) * 100, 1) : 100;

        // --- 5. SLA Compliance ---
        $activeOrders = CustomerOrder::where('status', 'active')
            ->whereHas('customer', fn($q) => $q->where('user_id', $user->id))
            ->get();

        $slaBreaches = 0; // simplified: count degraded/offline servers serving orders
        foreach ($activeOrders as $order) {
            $server = $order->server;
            if ($server && $server->status !== ServerStatus::ONLINE) {
                $slaBreaches++;
            }
        }

        $slaComplianceRate = $activeOrders->count() > 0 
            ? round(($activeOrders->count() - $slaBreaches) / $activeOrders->count() * 100, 1) 
            : 100;

        // --- CALCULATE OVERALL COMPANY RATING ---
        $rating = $this->calculateCompanyRating(
            $avgCrisisScore,
            $profitMargin,
            $satisfactionRate,
            $uptimePercent,
            $slaComplianceRate,
            $economy->reputation,
            $cashRunway,
            $totalEvents
        );

        return response()->json([
            'success' => true,
            'data' => [
                'companyRating' => $rating,
                'crisisManagement' => [
                    'avgScore' => $avgCrisisScore,
                    'totalEvents' => $totalEvents,
                    'resolved' => $gradeCount,
                    'failed' => $failedEvents,
                    'gradeDistribution' => $gradeDistribution,
                ],
                'financial' => [
                    'balance' => round($economy->balance, 2),
                    'hourlyIncome' => round($economy->hourly_income, 2),
                    'hourlyExpenses' => round($economy->hourly_expenses, 2),
                    'profitMargin' => $profitMargin,
                    'revenueTrend' => $revenueTrend,
                    'cashRunway' => $cashRunway,
                    'revenueHistory' => array_map(fn($v) => round($v, 2), $revenueHistory),
                    'balanceHistory' => array_map(fn($v) => round($v, 2), $balanceHistory),
                ],
                'resources' => [
                    'currentPower' => (float) ($economy->total_power_kw ?? 0),
                    'currentBandwidth' => (float) ($economy->total_bandwidth_gbps ?? 0),
                    'powerHistory' => array_map(fn($v) => round($v, 2), $powerHistory),
                    'bandwidthHistory' => array_map(fn($v) => round($v, 2), $bandwidthHistory),
                ],
                'customerHealth' => [
                    'total' => $totalCustomers,
                    'avgSatisfaction' => $avgSatisfaction,
                    'satisfactionRate' => $satisfactionRate,
                    'unhappy' => $unhappyCustomers,
                    'recentChurn' => $churnedLast30,
                ],
                'infrastructure' => [
                    'totalServers' => $totalServers,
                    'online' => $onlineServers,
                    'degraded' => $degradedServers,
                    'avgHealth' => $avgHealth,
                    'uptime' => $uptimePercent,
                ],
                'sla' => [
                    'complianceRate' => $slaComplianceRate,
                    'activeContracts' => $activeOrders->count(),
                    'breaches' => $slaBreaches,
                ],
                'reputation' => round($economy->reputation, 1),
                'level' => $economy->level,
            ]
        ]);
    }

    /**
     * Calculate overall company rating (AAA to D)
     */
    private function calculateCompanyRating(
        ?float $avgCrisisScore,
        float $profitMargin,
        float $satisfactionRate,
        float $uptimePercent,
        float $slaComplianceRate,
        float $reputation,
        ?float $cashRunway,
        int $totalEvents
    ): array {
        $score = 0;
        $maxScore = 0;

        // Crisis Management (25 points max)
        if ($avgCrisisScore !== null && $totalEvents > 0) {
            $score += ($avgCrisisScore / 100) * 25;
        } else {
            $score += 15; // Neutral if no events yet
        }
        $maxScore += 25;

        // Financial Health (25 points max)
        $financialScore = 0;
        if ($profitMargin > 30) $financialScore = 25;
        elseif ($profitMargin > 15) $financialScore = 20;
        elseif ($profitMargin > 5) $financialScore = 15;
        elseif ($profitMargin > 0) $financialScore = 10;
        elseif ($profitMargin > -10) $financialScore = 5;
        else $financialScore = 0;

        // Cash runway penalty
        if ($cashRunway !== null && $cashRunway < 5) {
            $financialScore = max(0, $financialScore - 10);
        }

        $score += $financialScore;
        $maxScore += 25;

        // Customer Satisfaction (20 points)
        $score += ($satisfactionRate / 100) * 20;
        $maxScore += 20;

        // Infrastructure Uptime (15 points)
        $score += ($uptimePercent / 100) * 15;
        $maxScore += 15;

        // SLA Compliance (10 points)
        $score += ($slaComplianceRate / 100) * 10;
        $maxScore += 10;

        // Reputation Bonus (5 points)
        $score += min(5, ($reputation / 100) * 5);
        $maxScore += 5;

        $percent = $maxScore > 0 ? ($score / $maxScore) * 100 : 0;

        // Determine letter rating
        $letter = 'D';
        $outlook = 'negative';

        if ($percent >= 95) { $letter = 'AAA'; $outlook = 'stable'; }
        elseif ($percent >= 88) { $letter = 'AA'; $outlook = 'stable'; }
        elseif ($percent >= 80) { $letter = 'A'; $outlook = 'stable'; }
        elseif ($percent >= 70) { $letter = 'BBB'; $outlook = 'positive'; }
        elseif ($percent >= 60) { $letter = 'BB'; $outlook = 'stable'; }
        elseif ($percent >= 50) { $letter = 'B'; $outlook = 'negative'; }
        elseif ($percent >= 40) { $letter = 'CCC'; $outlook = 'negative'; }
        elseif ($percent >= 30) { $letter = 'CC'; $outlook = 'negative'; }
        elseif ($percent >= 20) { $letter = 'C'; $outlook = 'negative'; }

        return [
            'letter' => $letter,
            'score' => round($percent, 1),
            'outlook' => $outlook,
        ];
    }
}
