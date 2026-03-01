<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\HardwareAuction;
use App\Models\GameLog;
use App\Models\Competitor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuctionService
{
    public function __construct(
        protected RackManagementService $rackService
    ) {}

    /**
     * Get active auctions for the user.
     */
    public function getActiveAuctions(): array
    {
        return HardwareAuction::where('is_processed', false)
            ->where('ends_at', '>', now())
            ->orderBy('ends_at', 'asc')
            ->get()
            ->map(function ($auction) {
                $data = $auction->toArray();
                $data['min_next_bid'] = $auction->getMinNextBid();
                $data['is_active'] = $auction->isActive();
                $data['time_remaining_seconds'] = now()->diffInSeconds($auction->ends_at, false);
                return $data;
            })
            ->toArray();
    }

    /**
     * Place a bid on an auction.
     */
    public function placeBid(User $user, string $auctionId, float $amount): array
    {
        return DB::transaction(function () use ($user, $auctionId, $amount) {
            $auction = HardwareAuction::lockForUpdate()->find($auctionId);

            if (!$auction || $auction->is_processed || $auction->ends_at <= now()) {
                return ['success' => false, 'error' => 'Auction is no longer active.'];
            }

            if ($auction->starts_at > now()) {
                return ['success' => false, 'error' => 'Auction has not started yet.'];
            }

            $minBid = $auction->getMinNextBid();
            if ($amount < $minBid) {
                return ['success' => false, 'error' => "Minimum bid required: $" . number_format($minBid, 2)];
            }

            if ($user->economy->balance < $amount) {
                return ['success' => false, 'error' => 'Insufficient funds.'];
            }

            // Refund previous bidder if exists
            // Actually, in a high-stakes local game, we might wait until the end to deduct.
            // But for real-time feel, let's deduct now and refund if outbid.
            
            if ($auction->highest_bidder_id) {
                $prevBidder = User::find($auction->highest_bidder_id);
                if ($prevBidder) {
                    $prevBidder->economy->credit($auction->current_bid, "Auction outbid refund: {$auction->item_key}", 'other');
                    GameLog::log($prevBidder, "You have been outbid on '{$auction->item_key}'! Refund of $" . number_format($auction->current_bid, 2) . " processed.", 'warning', 'economy');
                }
            }

            // Deduct from new bidder
            $user->economy->debit($amount, "Auction Bid: {$auction->item_key}", 'capex');

            // Update auction
            $auction->current_bid = $amount;
            $auction->highest_bidder_id = $user->id;
            
            // "Anti-Snipe" Rule: Extend auction by 30 seconds if bid placed in last 30 seconds
            if (now()->diffInSeconds($auction->ends_at) < 30) {
                $auction->ends_at = $auction->ends_at->addSeconds(30);
            }

            $auction->save();

            GameLog::log($user, "Bid of $" . number_format($amount, 2) . " placed on '{$auction->item_key}'. Ends at " . $auction->ends_at->toDateTimeString(), 'success', 'economy');

            return ['success' => true, 'auction' => $auction];
        });
    }

    /**
     * Tick for Auction system.
     */
    public function tick(): void
    {
        // 1. Process expired auctions
        $expired = HardwareAuction::where('is_processed', false)
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($expired as $auction) {
            $this->finalizeAuction($auction);
        }

        // 2. Chance to generate a new liquidation auction
        $isDownturn = \App\Models\WorldEvent::where('type', 'downturn')->where('is_active', true)->exists();
        $chance = $isDownturn ? 25 : 5; // 25% during downturn, 5% normally

        if (rand(1, 100) <= $chance) {
            $this->generateLiquidationAuction();
        }
    }

    private function finalizeAuction(HardwareAuction $auction): void
    {
        DB::transaction(function () use ($auction) {
            $auction->is_processed = true;
            $auction->save();

            if (!$auction->highest_bidder_id) {
                Log::info("Auction {$auction->id} ended with no bids.");
                return;
            }

            $winner = User::find($auction->highest_bidder_id);
            if (!$winner) return;

            // Grant item to winner
            $this->grantAuctionItem($winner, $auction);

            GameLog::log($winner, "🏆 CONGRATULATIONS! You won the auction for '{$auction->item_key}' with a bid of $" . number_format($auction->current_bid, 2) . ".", 'success', 'infrastructure');
            
            Log::info("User {$winner->id} won auction {$auction->id}");
        });
    }

    private function grantAuctionItem(User $user, HardwareAuction $auction): void
    {
        // Use the logic from MarketController or shared service
        // For now, I'll implement it here to be safe
       
        switch ($auction->item_type) {
            case 'server':
                \App\Models\UserComponent::create([
                    'user_id' => $user->id,
                    'component_type' => 'server_unit', 
                    'component_key' => $auction->item_key,
                    'status' => 'inventory',
                    'health' => $auction->condition,
                    'purchased_at' => now(),
                ]);
                break;
            
            case 'rack':
                $room = $user->rooms()->whereRaw('(SELECT COUNT(*) FROM server_racks WHERE room_id = game_rooms.id) < max_racks')->first();
                if (!$room) {
                    // If no room, we store it as a "Pending Delivery" or just refund?
                    // Better to put it in a "Logistics" tab if it existed.
                    // For now, we try to find ONE rack and if it fails, we log an error.
                    \App\Models\GameLog::log($user, "ERROR: No space in any room for your won rack. Contact support (Logic error).", 'danger', 'infrastructure');
                    return;
                }
                
                $occupiedSlots = $room->racks()->pluck('position')->map(function($p) {
                    $arr = is_string($p) ? json_decode($p, true) : $p;
                    return $arr['slot'] ?? 0;
                })->toArray();
                $slot = 0;
                while(in_array($slot, $occupiedSlots)) $slot++;

                $rackTypeEnum = \App\Enums\RackType::tryFrom($auction->item_key) ?? \App\Enums\RackType::RACK_42U;

                \App\Models\ServerRack::create([
                    'room_id' => $room->id,
                    'type' => $rackTypeEnum,
                    'name' => "Auction Rack #" . substr($auction->id, 0, 4),
                    'total_units' => $rackTypeEnum->totalUnits(),
                    'max_power_kw' => $rackTypeEnum->maxPowerKw(),
                    'position' => ['slot' => $slot],
                    'status' => 'operational',
                    'temperature' => 22.0,
                    'dust_level' => 0.0,
                    'purchase_cost' => $auction->current_bid ?? 0,
                ]);
                break;

            case 'component':
                \App\Models\UserComponent::create([
                    'user_id' => $user->id,
                    'component_type' => $this->inferComponentType($auction->item_key),
                    'component_key' => $auction->item_key,
                    'status' => 'inventory',
                    'health' => $auction->condition,
                    'purchased_at' => now(),
                ]);
                break;
        }
    }

    public function generateLiquidationAuction(): void
    {
        $settings = \App\Models\GameConfig::get('auction_settings', [
            'probability_server' => 60,
            'probability_rack' => 30,
            'probability_component' => 10,
            'condition_min' => 15,
            'condition_max' => 80,
            'price_multiplier_min' => 0.2,
            'price_multiplier_max' => 0.4,
            'duration_min' => 5,
            'duration_max' => 15
        ]);

        // Types: server, rack, component
        $rand = rand(1, 100);
        $type = 'server';
        if ($rand > $settings['probability_server']) {
            if ($rand > ($settings['probability_server'] + $settings['probability_rack'])) {
                $type = 'component';
            } else {
                $type = 'rack';
            }
        }

        $seller = $this->generateAuctionSeller();
        $condition = rand($settings['condition_min'], $settings['condition_max']); // Auctions are usually lower condition
        
        $itemData = null;
        $key = null;
        $basePrice = 0;

        if ($type === 'server') {
            $catalog = app(RackManagementService::class)->getServerCatalog();
            $flatCatalog = [];
            foreach ($catalog as $category => $models) {
                foreach ($models as $k => $v) { $flatCatalog[$k] = $v; }
            }
            if (empty($flatCatalog)) return;
            $key = array_rand($flatCatalog);
            $itemData = $flatCatalog[$key];
            $basePrice = $itemData['purchaseCost'] ?? 1000;
        } elseif ($type === 'rack') {
            $cases = \App\Enums\RackType::cases();
            $randomCase = $cases[array_rand($cases)];
            $key = $randomCase->value;
            $basePrice = $randomCase->purchaseCost();
            $itemData = ['name' => $randomCase->label()];
        } else {
             // Random component from GameConfig
             $components = \App\Models\GameConfig::get('server_components', []);
             $compType = array_rand($components);
             $key = array_rand($components[$compType]);
             $itemData = $components[$compType][$key];
             $basePrice = $itemData['price'] ?? 500;
        }
 
        // Starting price is low: 20-40% of base
        $isDownturn = \App\Models\WorldEvent::where('type', 'downturn')->where('is_active', true)->exists();
        $mult = rand($settings['price_multiplier_min'] * 100, $settings['price_multiplier_max'] * 100) / 100;
        
        if ($isDownturn) {
            $mult *= 0.6; // Even cheaper during downturn
        }

        $startingPrice = $basePrice * $mult;
 
        HardwareAuction::create([
            'item_type' => $type,
            'item_key' => $key,
            'item_specs' => $itemData,
            'seller_name' => $seller,
            'starting_price' => round($startingPrice, 2),
            'condition' => $condition,
            'defect_chance' => (100 - $condition) * 0.4,
            'starts_at' => now(),
            'ends_at' => now()->addMinutes(rand($settings['duration_min'], $settings['duration_max'])), // Fast auctions
            'is_processed' => false,
        ]);

        // Global Alert via NewsService
        app(NewsService::class)->broadcastGlobal(
            "⚡ AUCTION ALERT: Liquidated assets from '{$seller}' are now open for bidding! Type: {$type} ({$key})",
            'warning',
            'MARKET'
        );
    }

    private function generateAuctionSeller(): string
    {
        $prefixes = ['Bankrupt', 'Foreclosed', 'Seized', 'Obsolete', 'Abandoned'];
        $nouns = ['Data Center', 'Tech Stack', 'Mining Rig', 'Hosting Co', 'Network'];
        
        $competitors = Competitor::where('status', 'active')->inRandomOrder()->first();
        if ($competitors && rand(0, 1)) {
            return $competitors->name . " Liquidation";
        }

        return $prefixes[array_rand($prefixes)] . ' ' . $nouns[array_rand($nouns)];
    }

    private function inferComponentType(string $key): string
    {
        $components = \App\Models\GameConfig::get('server_components', []);
        foreach ($components as $type => $items) {
            if (isset($items[$key])) return $type;
        }
        return 'misc';
    }
}
