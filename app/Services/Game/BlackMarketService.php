<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\UserComponent;
use App\Models\PlayerEconomy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BlackMarketService
{
    /**
     * Get available hardware deals for the Night Shop.
     * Only available between 00:00 and 04:00 in-game time.
     */
    public function getNightDeals(User $user): array
    {
        $economy = $user->economy;
        $currentTick = $economy->current_tick;
        $minuteOfDay = $currentTick % 1440;

        $isNight = $minuteOfDay >= 0 && $minuteOfDay <= 240; // 00:00 - 04:00

        if (!$isNight) {
            return [
                'available' => false,
                'nextOpening' => 1440 - $minuteOfDay, // Ticks until midnight
                'deals' => []
            ];
        }

        // Generate deterministic deals based on day and user ID
        $day = (int) floor($currentTick / 1440);
        $seed = $day + $user->id;
        srand($seed);

        $deals = [];
        $componentTypes = ['cpu', 'ram', 'storage', 'motherboard'];
        
        // Generate 3-5 random hardware deals
        $dealCount = rand(3, 5);
        for ($i = 0; $i < $dealCount; $i++) {
            $type = $componentTypes[array_rand($componentTypes)];
            $config = $this->getRandomComponentConfig($type);
            
            // Black market deals are 50% off standard price
            $standardPrice = $config['price'] ?? 500;
            $dealPrice = round($standardPrice * 0.5);

            $deals[] = [
                'id' => "bm_deal_{$i}_{$day}",
                'type' => $type,
                'name' => "Recycled " . $config['name'],
                'key' => $config['key'],
                'price' => $dealPrice,
                'originalPrice' => $standardPrice,
                'condition' => rand(60, 95), // Slightly worn
                'vendor' => 'Unknown Agent',
                'description' => 'Unmarked hardware from a liquidated startup. No receipts, no warranty.',
                'backdoorRisk' => 0.15, // 15% hidden chance
            ];
        }

        srand(); // Reset seed

        return [
            'available' => true,
            'closingIn' => 240 - $minuteOfDay,
            'deals' => $deals
        ];
    }

    /**
     * Purchase an item from the black market.
     */
    public function purchaseDeal(User $user, string $dealId): array
    {
        $dealsData = $this->getNightDeals($user);
        if (!$dealsData['available']) {
            return ['success' => false, 'error' => 'The Night Shop is currently closed. Come back at midnight.'];
        }

        $deal = collect($dealsData['deals'])->firstWhere('id', $dealId);
        if (!$deal) {
            return ['success' => false, 'error' => 'Deal no longer available.'];
        }

        $economy = $user->economy;
        if (!$economy->debit($deal['price'], "Black Market Purchase: {$deal['name']}", 'hardware')) {
            return ['success' => false, 'error' => 'Insufficient funds.'];
        }

        // Roll for backdoor
        $hasBackdoor = (rand(1, 100) <= 15);

        // Create component in inventory
        $component = UserComponent::create([
            'user_id' => $user->id,
            'component_type' => $deal['type'],
            'component_key' => $deal['key'],
            'status' => 'inventory',
            'health' => $deal['condition'],
            'purchased_at' => now(),
            'meta' => [
                'is_black_market' => true,
                'has_backdoor' => $hasBackdoor,
                'vendor_alias' => 'Shadow Dealer'
            ]
        ]);

        if ($hasBackdoor) {
            Log::warning("User {$user->id} purchased tainted hardware! Component {$component->id} has a backdoor.");
            // In a future step, we might trigger a Sabotage event or security leak logic
        }

        \App\Models\GameLog::log($user, "Acquired hardware via underground channels: {$deal['name']}", 'warning', 'economy');

        return [
            'success' => true,
            'message' => "Item purchased and delivered to inventory.",
            'component' => $component
        ];
    }

    private function getRandomComponentConfig(string $type): array
    {
        $allComponents = \App\Models\GameConfig::get('server_components', []);
        $catalog = $allComponents[$type] ?? [];
        
        if (empty($catalog)) {
            return ['name' => 'Generic Component', 'key' => 'generic', 'price' => 200];
        }
        
        $keys = array_keys($catalog);
        $key = $keys[array_rand($keys)];
        $config = $catalog[$key];
        $config['key'] = $key;
        
        return $config;
    }
}
