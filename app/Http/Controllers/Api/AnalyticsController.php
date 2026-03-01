<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Competitor;
use App\Models\PaymentTransaction;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $economy = $user->economy;

        // Fetch competitors
        $competitors = Competitor::where('status', 'active')
            ->orderByDesc('market_share')
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'archetype' => $c->archetype,
                    'marketShare' => round($c->market_share, 2),
                    'reputation' => round($c->reputation, 1),
                    'uptime' => round($c->uptime_score, 2),
                    'enmity' => round($c->player_enmity, 1),
                    'isHostile' => $c->player_enmity > 50,
                    'capacity' => $c->capacity_score,
                ];
            });

        // Add player to the market share pie
        $marketData = [
            'player' => [
                'name' => $user->company_name ?? 'Your Company',
                'marketShare' => round($economy->global_market_share ?? 0, 2),
                'reputation' => round($economy->reputation ?? 50, 1),
            ],
            'competitors' => $competitors,
        ];

        // Fetch recent financial history for revenue chart
        $transactions = PaymentTransaction::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes(60)) // Last hour of ticks
            ->get();
            
        // Group transactions into buckets (e.g. 5 min intervals) for charts
        $financials = $transactions->groupBy(function($date) {
            return Carbon::parse($date->created_at)->format('i'); // Group by minute
        });
        
        $chartData = [];
        $incomeTotal = 0;
        $expenseTotal = 0;
        
        foreach ($financials as $minute => $transGroup) {
            $income = $transGroup->where('type', 'income')->sum('amount');
            $expense = abs($transGroup->where('type', 'expense')->sum('amount'));
            $incomeTotal += $income;
            $expenseTotal += $expense;
            
            $chartData[] = [
                'time' => $minute,
                'income' => $income,
                'expense' => $expense,
                'profit' => $income - $expense
            ];
        }

        return response()->json([
            'success' => true,
            'market' => $marketData,
            'financials' => [
                'history' => $chartData,
                'summary' => [
                    'income' => $incomeTotal,
                    'expense' => $expenseTotal,
                    'profit' => $incomeTotal - $expenseTotal
                ]
            ],
            'playerStats' => [
                'customers' => $user->customers()->count(),
                'activeServers' => $user->servers()->where('status', 'online')->count(),
                'utilization' => $economy->utilization ?? 0,
            ]
        ]);
    }
}
