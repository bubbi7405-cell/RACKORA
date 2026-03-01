<?php

namespace App\Services\Game;

use App\Models\PlayerEconomy;
use App\Models\User;
use App\Models\GameLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StockMarketService
{
    public function getState(User $user): array
    {
        $economy = $user->economy;
        $metadata = $economy->metadata ?? [];
        
        return [
            'stockPrice' => $this->getCurrentStockPrice($user),
            'shortPositions' => $metadata['short_positions'] ?? [],
            'isFrozen' => (bool) ($metadata['sec_freeze'] ?? false),
            'freezeEndsAt' => $metadata['sec_freeze_expires_at'] ?? null,
        ];
    }

    public function getCurrentStockPrice(User $user): float
    {
        $economy = $user->economy;
        
        $base = 10.0;
        $repMultiplier = (float) $economy->reputation / 10.0;
        $revenueMultiplier = (float) $economy->hourly_income / 500.0;
        
        $price = $base + ($repMultiplier * 5) + ($revenueMultiplier * 10);
        
        // Market volatility based on current tick to keep it stable within a 15min window
        $seed = (int) ($economy->current_tick / 4); 
        srand($seed);
        $volatility = (rand(-200, 200) / 100);
        srand(); // Reset
        
        return max(0.5, round($price + $volatility, 2));
    }

    public function shortSell(User $user, int $shares): array
    {
        return DB::transaction(function () use ($user, $shares) {
            $economy = $user->economy;
            $metadata = $economy->metadata ?? [];

            if ($metadata['sec_freeze'] ?? false) {
                if (Carbon::parse($metadata['sec_freeze_expires_at'])->isFuture()) {
                    throw new \Exception("Your assets are currently frozen by the SEC. Trading disabled.");
                }
                // Unfreeze if expired
                $metadata['sec_freeze'] = false;
            }

            $currentPrice = $this->getCurrentStockPrice($user);
            $shorts = $metadata['short_positions'] ?? [];
            
            if (count($shorts) >= 5) {
                throw new \Exception("Maximum 5 active short positions allowed.");
            }

            $collateral = ($shares * $currentPrice) * 0.4; // 40% margin requirement
            if (!$economy->canAfford($collateral)) {
                throw new \Exception("Insufficient collateral. You need $" . number_format($collateral) . " (40% margin).");
            }

            $economy->debit($collateral, "Stock Short-Sell Collateral: {$shares} shares", 'investment');
            
            $shorts[] = [
                'id' => uniqid(),
                'shares' => $shares,
                'entry_price' => $currentPrice,
                'collateral' => (float) $collateral,
                'opened_at' => now()->toIso8601String(),
            ];
            
            $metadata['short_positions'] = $shorts;
            $economy->metadata = $metadata;
            $economy->save();
            
            GameLog::log($user, "SHORT-SELL EXECUTED: Borrowed {$shares} shares at \${$currentPrice}.", 'warning', 'investment');
            
            // SEC Audit Chance (Feature 256)
            $this->checkForSecAudit($user);

            return [
                'success' => true,
                'price' => $currentPrice,
                'collateral' => $collateral
            ];
        });
    }

    public function closeShort(User $user, string $positionId): array
    {
        return DB::transaction(function () use ($user, $positionId) {
            $economy = $user->economy;
            $metadata = $economy->metadata ?? [];

            if ($metadata['sec_freeze'] ?? false) {
                if (Carbon::parse($metadata['sec_freeze_expires_at'])->isFuture()) {
                    throw new \Exception("Your assets are currently frozen by the SEC. Trading disabled.");
                }
            }

            $currentPrice = $this->getCurrentStockPrice($user);
            $shorts = $metadata['short_positions'] ?? [];
            
            $posIndex = -1;
            foreach ($shorts as $index => $pos) {
                if ($pos['id'] === $positionId) {
                    $posIndex = $index;
                    break;
                }
            }
            
            if ($posIndex === -1) {
                throw new \Exception("Position not found.");
            }
            
            $pos = $shorts[$posIndex];
            $shares = $pos['shares'];
            $entryPrice = $pos['entry_price'];
            $collateral = $pos['collateral'];
            
            $profitPerShare = $entryPrice - $currentPrice;
            $totalProfit = $profitPerShare * $shares;
            
            $payout = $collateral + $totalProfit;
            
            if ($payout > 0) {
                $economy->credit($payout, "Close Short Position: {$shares} shares", 'investment');
            }
            
            unset($shorts[$posIndex]);
            $metadata['short_positions'] = array_values($shorts);
            $economy->metadata = $metadata;
            $economy->save();
            
            $status = $totalProfit >= 0 ? 'success' : 'danger';
            $msg = $totalProfit >= 0 ? "PROFIT" : "LOSS";
            
            GameLog::log($user, "SHORT CLOSED: {$msg} of \$" . number_format(abs($totalProfit)) . " realized.", $status, 'investment');
            
            return [
                'success' => true,
                'profit' => $totalProfit,
                'payout' => $payout
            ];
        });
    }

    private function checkForSecAudit(User $user): void
    {
        // 30% chance according to Roadmap
        if (rand(1, 100) <= 30) {
            $economy = $user->economy;
            $metadata = $economy->metadata ?? [];
            
            $metadata['sec_freeze'] = true;
            $metadata['sec_freeze_expires_at'] = now()->addHours(2)->toIso8601String(); // 2 hour freeze
            
            $economy->federal_heat = min(100, $economy->federal_heat + 25);
            $economy->metadata = $metadata;
            $economy->save();
            
            GameLog::log($user, "SEC INVESTIGATION: Suspicious trading activity! Your brokerage account has been FROZEN for 2 hours.", 'danger', 'compliance');
        }
    }
}
