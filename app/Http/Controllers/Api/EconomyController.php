<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EconomyController extends Controller
{
    /**
     * Get transaction history for the user
     */
    public function history(Request $request): JsonResponse
    {
        $transactions = PaymentTransaction::where('user_id', $request->user()->id)
            ->with(['related']) // Eager load related
            ->latest()
            ->paginate(50); // Pagination

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Get financial transaction log with filters and summary
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = PaymentTransaction::where('user_id', $user->id);

        // Filter by type (income / expense)
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Get last N hours
        if ($request->has('hours')) {
            $query->where('created_at', '>=', now()->subHours((int) $request->hours));
        }

        $transactions = $query->latest()->paginate(30);

        // Summary stats
        $baseQuery = PaymentTransaction::where('user_id', $user->id);
        if ($request->has('hours')) {
            $baseQuery->where('created_at', '>=', now()->subHours((int) $request->hours));
        }

        $totalIncome = (clone $baseQuery)->where('type', 'income')->sum('amount');
        $totalExpenses = (clone $baseQuery)->where('type', 'expense')->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions,
                'summary' => [
                    'totalIncome' => (float) abs($totalIncome),
                    'totalExpenses' => (float) abs($totalExpenses),
                    'netProfit' => (float) ($totalIncome + $totalExpenses),
                ],
            ],
        ]);
    }
}
