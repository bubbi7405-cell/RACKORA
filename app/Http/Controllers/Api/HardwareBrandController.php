<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameConfig;
use App\Models\HardwareBrandDeal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HardwareBrandController extends Controller
{
    /**
     * Get available brand deal options.
     */
    public function getOptions(Request $request): JsonResponse
    {
        $vendors = GameConfig::get('hardware_vendors', []);
        $user = $request->user();
        
        $currentDeal = HardwareBrandDeal::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        $data = [];
        foreach ($vendors as $name => $meta) {
            $data[] = [
                'name' => $name,
                'focus' => $meta['focus'],
                'reputation' => $meta['reputation'],
                'discount' => 10.00, // Fixed 10% for now
                'is_active' => $currentDeal ? ($currentDeal->brand_name === $name) : false,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'options' => $data,
                'current_deal' => $currentDeal
            ]
        ]);
    }

    /**
     * Sign a new exclusivity deal.
     */
    public function signDeal(Request $request): JsonResponse
    {
        $request->validate([
            'brand_name' => 'required|string',
        ]);

        $user = $request->user();
        $brandName = $request->input('brand_name');
        $vendors = GameConfig::get('hardware_vendors', []);

        if (!isset($vendors[$brandName])) {
            return response()->json(['success' => false, 'error' => 'Invalid brand'], 400);
        }

        // Check for existing active deal
        $existing = HardwareBrandDeal::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'error' => 'You already have an active exclusivity deal'], 400);
        }

        return DB::transaction(function () use ($user, $brandName) {
            $deal = HardwareBrandDeal::create([
                'user_id' => $user->id,
                'brand_name' => $brandName,
                'discount_percent' => 10.00,
                'signed_at' => now(),
                'expires_at' => now()->addDays(30), // 30 days expiry
                'status' => 'active'
            ]);

            \App\Models\GameLog::log($user, "Signed 30-day hardware exclusivity deal with {$brandName}. Benefit: 10% discount on all gear.", 'success', 'hardware');

            return response()->json([
                'success' => true,
                'message' => "Successfully signed exclusivity deal with {$brandName}",
                'data' => $deal
            ]);
        });
    }

    /**
     * Terminate an active deal (with penalty).
     */
    public function terminateDeal(Request $request): JsonResponse
    {
        $user = $request->user();
        $deal = HardwareBrandDeal::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$deal) {
            return response()->json(['success' => false, 'error' => 'No active deal to terminate'], 404);
        }

        $penalty = 2500; // Fixed penalty for breaking contract
        $economy = $user->economy;

        if ($economy->balance < $penalty) {
            return response()->json(['success' => false, 'error' => "Insufficient balance to pay termination penalty (\${$penalty})"], 400);
        }

        return DB::transaction(function () use ($user, $economy, $deal, $penalty) {
            $economy->balance -= $penalty;
            $economy->save();

            $deal->update(['status' => 'terminated']);

            \App\Models\PaymentTransaction::create([
                'user_id' => $user->id,
                'amount' => -$penalty,
                'type' => 'expense',
                'category' => 'hardware',
                'description' => "Contract termination penalty: {$deal->brand_name}",
                'balance_after' => $economy->balance
            ]);

            return response()->json([
                'success' => true,
                'message' => "Deal terminated. \${$penalty} penalty deducted.",
            ]);
        });
    }
}
