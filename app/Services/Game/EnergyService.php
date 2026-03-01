<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\Server;
use App\Models\PlayerEconomy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EnergyService
{
    /**
     * FEATURE 310: Green Hosting Scores
     * Returns a float from 0.0 to 1.0 based on renewable adoption.
     */
    public function getGreenScore(User $user): float
    {
        $rooms = \App\Models\GameRoom::where('user_id', $user->id)->get();
        if ($rooms->isEmpty()) return 0.0;

        $totalItPower = 0.0;
        $totalSolar = 0.0;
        
        // Count online battery units as storage presence
        $hasBatteries = Server::where('type', 'battery')
            ->whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', \App\Enums\ServerStatus::ONLINE)
            ->exists();

        foreach ($rooms as $room) {
            $totalItPower += $room->getCurrentPowerUsage();
            $totalSolar += (float) $room->solar_capacity_kw;
        }

        if ($totalItPower <= 0) $totalItPower = 1.0; 

        // Score: up to 70% from solar coverage, plus 30% for battery presence
        $solarCoveragePercent = min(1.0, $totalSolar / $totalItPower);
        $solarScore = $solarCoveragePercent * 0.7;
        $batteryBonus = $hasBatteries ? 0.3 : 0.0;

        return min(1.0, $solarScore + $batteryBonus);
    }
    private function getSettings(): array
    {
        return \App\Models\GameConfig::get('energy_market_settings', [
            'base_price' => 0.12,
            'volatility' => 0.02,
            'min_price' => 0.04,
            'max_price' => 0.65, // Erhöht für stärkere Peaks
            'reversion_speed' => 0.05
        ]);
    }

    /**
     * Get the current energy spot price.
     * If region is provided, returns that region's price.
     * If null, returns the global average index.
     */
    public function getSpotPrice(?string $region = null): float
    {
        $prices = Cache::get('energy_regional_prices', []);

        if ($region && isset($prices[$region])) {
            return (float) $prices[$region];
        }

        // Return global average if no region specified
        if (isset($prices['global_avg'])) {
            return (float) $prices['global_avg'];
        }

        // Fallback
        $settings = $this->getSettings();
        return (float) Cache::get('global_energy_spot_price', $settings['base_price']);
    }

    /**
     * Simulate price fluctuation for all regions with parity logic.
     */
    public function tickMarket(): void
    {
        $settings = $this->getSettings();
        $engine = \App\Models\GameConfig::get('engine_constants', []);
        $regions = \App\Models\GameConfig::get('regions', []);
        
        // Get active world event modifiers
        // We'll resolve per-region below instead of globally
        $globalModifiers = \App\Models\WorldEvent::getActiveModifiers();

        $marketVolMult = $engine['market_volatility'] ?? 1.0;
        $globalSurcharge = $globalModifiers['power_cost'] ?? 1.0;
        
        // 1. Update Global Market Factor (Trend)
        $globalFactor = Cache::get('energy_global_factor', 1.0);
        
        $vol = $settings['volatility'] * $marketVolMult; 
        $variation = (rand(-100, 100) / 10000) * $vol * 100;
        
        $newFactor = $globalFactor * (1 + $variation);
        
        // Mean reversion for global factor to 1.0
        $reversion = (1.0 - $newFactor) * $settings['reversion_speed'];
        $newFactor += $reversion;
        
        // Clamp Global Factor
        $newFactor = max(0.5, min(3.0, $newFactor));
        Cache::put('energy_global_factor', $newFactor, 3600);

        // 2. Calculate Regional Prices
        $prices = [];
        $solarFactors = [];
        $totalPrice = 0;
        $count = 0;

        $currentTick = \App\Models\GameConfig::get('global_tick_count', 0);
        
        // Regional UTC Offsets (in minutes from base)
        $offsets = [
            'us_east' => 0,       // UTC-5 (Base)
            'us_west' => -180,    // UTC-8
            'eu_central' => 360,  // UTC+1
            'asia_east' => 840,   // UTC+9
            'nordics' => 360,     // UTC+1 (same as EU)
            'asia_south' => 480,  // UTC+8 (Singapore)
            'south_america' => 120, // UTC-3
        ];

        // Pre-load weather data for grid stability
        $currentWeather = Cache::get('regional_weather', []);

        foreach ($regions as $key => $data) {
            $offset = $offsets[$key] ?? 0;
            $localTime = ($currentTick + $offset) % 1440;
            if ($localTime < 0) $localTime += 1440;

            // --- FEATURE 193: PLANETARY ROTATION (SOLAR SYNC) ---
            // Solar Curve: Peak at noon (720), Zero at night
            $solarFactors[$key] = max(0.0, sin(($localTime - 360) * M_PI / 720));
            
            // Price Peaks at morning (480-600) and evening (1080-1200) local time
            $peakFactor = 1.0;
            if (($localTime >= 480 && $localTime <= 600) || ($localTime >= 1080 && $localTime <= 1200)) {
                $peakFactor = 1.8; // 80% spike
            }

            $baseCost = $data['base_power_cost'] ?? $data['energyPrice'] ?? 0.10;
            $name = $data['name'] ?? "Region {$key}";
            $slug = \Illuminate\Support\Str::slug($name);
            
            // Local Variance (momentary noise)
            $localNoise = (rand(-50, 50) / 10000); 
            
            // Check for REGIONAL world event surcharge (e.g. "Hitzewelle in Europa" only affects eu_central)
            $regionalModifiers = \App\Models\WorldEvent::getActiveModifiersForRegion($key);
            $regionalPowerCost = $regionalModifiers['power_cost'] ?? $globalSurcharge;
            
            // FEATURE 51: Grid Instability affects price
            $stability = (float) ($currentWeather[$key]['modifiers']['grid_stability'] ?? 1.0);
            $stabilitySurcharge = 1.0 + (max(0, 1.0 - $stability) * 2.0); // 0.7 stability -> +60% price

            // Parity Logic — use regional power_cost modifier instead of global
            $price = $baseCost * $newFactor * $peakFactor * $regionalPowerCost * $stabilitySurcharge * (1 + $localNoise);
            
            // Clamp price
            $price = max($settings['min_price'], min($settings['max_price'], round($price, 4)));
            
            $prices[$key] = $price;
            $totalPrice += $price;
            $count++;
        }
        
        Cache::put('energy_regional_solar_factors', $solarFactors, 3600);
        // Fallback for global display (US-East average)
        Cache::put('energy_solar_factor', $solarFactors['us_east'] ?? 0.0, 3600);

        // 3. Store Results
        $avg = $count > 0 ? $totalPrice / $count : ($settings['base_price'] * $newFactor);
        $prices['global_avg'] = $avg;
        
        Cache::put('energy_regional_prices', $prices, 3600);
        Cache::put('global_energy_spot_price', $avg, 3600);

        // 3.5 Broadcast Price Spikes
        if ($avg >= 0.30) {
            $newsService = app(NewsService::class);
            $newsService->broadcastGlobal(
                "MARKET ALERT: Global energy index surged to \${$avg}/kWh! Critical grid stress reported.", 
                'breaking', 
                'ENERGY'
            );
        }

        // 4. Update History (Global Index)
        $history = Cache::get('energy_price_history', []);
        $history[] = [
            'price' => $avg,
            'time' => now()->toIso8601String()
        ];
        if (count($history) > 60) array_shift($history);
        Cache::put('energy_price_history', $history, 3600);

        // 5. Update Regional History
        $regionalHistory = Cache::get('energy_regional_history', []);
        foreach ($prices as $region => $p) {
            if ($region === 'global_avg') continue;
            if (!isset($regionalHistory[$region])) $regionalHistory[$region] = [];
            
            $regionalHistory[$region][] = [
                'price' => $p,
                'time' => now()->toIso8601String()
            ];
            
            if (count($regionalHistory[$region]) > 60) array_shift($regionalHistory[$region]);
        }
        Cache::put('energy_regional_history', $regionalHistory, 3600);

        // 6. FEATURE 203: Dynamic Weather-Logic Engine
        foreach ($regions as $key => $data) {
            $existing = $currentWeather[$key] ?? null;
            $changeProbability = 5; // 5% chance to change per tick
            
            if (!$existing || rand(1, 100) <= $changeProbability) {
                // Bias weather by region
                $weights = match($key) {
                    'asia_east' => ['clear' => 30, 'cloudy' => 25, 'heatwave' => 25, 'storm' => 15, 'blizzard' => 5],
                    'eu_central' => ['clear' => 25, 'cloudy' => 30, 'heatwave' => 10, 'storm' => 20, 'blizzard' => 15],
                    'us_east' => ['clear' => 30, 'cloudy' => 25, 'heatwave' => 15, 'storm' => 20, 'blizzard' => 10],
                    'us_west' => ['clear' => 40, 'cloudy' => 20, 'heatwave' => 25, 'storm' => 10, 'blizzard' => 5],
                    'nordics' => ['clear' => 20, 'cloudy' => 20, 'heatwave' => 5, 'storm' => 30, 'blizzard' => 25],
                    'asia_south' => ['clear' => 30, 'cloudy' => 15, 'heatwave' => 30, 'storm' => 20, 'blizzard' => 5],
                    'south_america' => ['clear' => 25, 'cloudy' => 15, 'heatwave' => 35, 'storm' => 15, 'blizzard' => 10],
                    default => ['clear' => 30, 'cloudy' => 25, 'heatwave' => 15, 'storm' => 20, 'blizzard' => 10],
                };
                
                $roll = rand(1, 100);
                $cumulative = 0;
                $newWeather = 'clear';
                foreach ($weights as $type => $weight) {
                    $cumulative += $weight;
                    if ($roll <= $cumulative) {
                        $newWeather = $type;
                        break;
                    }
                }
                
                $currentWeather[$key] = [
                    'type' => $newWeather,
                    'since' => now()->toIso8601String(),
                    'modifiers' => match($newWeather) {
                        'clear' => ['solar_mod' => 1.0, 'pue_mod' => 1.0, 'grid_stability' => 1.0],
                        'cloudy' => ['solar_mod' => 0.4, 'pue_mod' => 0.95, 'grid_stability' => 1.0],
                        'heatwave' => ['solar_mod' => 1.2, 'pue_mod' => 1.35, 'grid_stability' => 0.9],
                        'storm' => ['solar_mod' => 0.1, 'pue_mod' => 1.1, 'grid_stability' => 0.7],
                        'blizzard' => ['solar_mod' => 0.05, 'pue_mod' => 0.8, 'grid_stability' => 0.6],
                        default => ['solar_mod' => 1.0, 'pue_mod' => 1.0, 'grid_stability' => 1.0],
                    },
                ];
            }
        }
        Cache::put('regional_weather', $currentWeather, 3600);
    }

    /**
     * Get available fixed contracts for a user.
     */
    public function getContractOffers(User $user): array
    {
        $spot = $this->getSpotPrice();
        
        return [
            [
                'type' => 'fixed_short',
                'name' => 'Stability Plus (Short)',
                'duration_ticks' => 120, // 2 hours
                'price' => round($spot * 1.15, 4), // 15% premium for stability
                'description' => 'Lock in prices for the next 2 operational hours.'
            ],
            [
                'type' => 'fixed_long',
                'name' => 'Enterprise Grid Shield',
                'duration_ticks' => 480, // 8 hours
                'price' => round($spot * 1.30, 4), // 30% premium
                'description' => 'Extreme protection against volatility. Best for high-capacity datacenters.'
            ]
        ];
    }

    /**
     * Player signs a contract.
     */
    /**
     * Get available energy policies and their modifiers.
     */
    public function getAvailablePolicies(): array
    {
        return [
            'eco_mode' => [
                'name' => 'Eco-Certified Hosting',
                'description' => 'Prioritize green energy sources. +10% Reputation, but +15% power cost surcharge.',
                'power_cost_mod' => 1.15,
                'reputation_mod' => 0.05, // Monthly/Tick bonus? Let's treat it as a passive boost
                'icon' => '🌱',
            ],
            'performance_mode' => [
                'name' => 'High-Performance Link',
                'description' => 'Boost server throughput by 10%, but +25% power draw and -5% reputation due to noise/heat.',
                'power_draw_mod' => 1.25,
                'performance_boost' => 0.10,
                'reputation_mod' => -0.05,
                'icon' => '⚡',
            ],
            'grid_saver' => [
                'name' => 'Grid Stabilization',
                'description' => 'Reduce power draw during peak hours. -15% power cost, but -5% customer satisfaction due to latency spikes.',
                'power_cost_mod' => 0.85,
                'satisfaction_mod' => -0.05,
                'icon' => '🔋',
            ],
            'data_mining' => [
                'name' => 'Dubious Data Mining',
                'description' => 'Extract and sell anonymized user metadata. +$0.50/hour per customer. Risk: Privacy Leaks.',
                'income_per_customer' => 0.50,
                'reputation_mod' => -0.10,
                'icon' => '🕵️',
            ],
            'battery_arbitrage' => [
                'name' => 'Grid Arbitrage AI',
                'description' => 'Charge batteries at low rates and discharge during peak prices. Increases battery wear (+50%).',
                'icon' => '📈',
                'category' => 'battery'
            ],
            'battery_reserve' => [
                'name' => 'Deep Cycle Reserve',
                'description' => 'Reserves 30% of battery capacity for grid outages. Disables price-based discharge below the limit.',
                'icon' => '🛡️',
                'category' => 'battery'
            ],
            'vpp_mode' => [
                'name' => 'Virtual Power Plant (VPP)',
                'description' => 'Sell surplus battery energy to the grid during extreme price spikes (>$0.45). Generates direct credits, but drains batteries rapidly.',
                'icon' => '🛰️',
                'category' => 'battery'
            ]
        ];
    }

    /**
     * Toggle a policy for the user.
     */
    public function togglePolicy(User $user, string $policyKey): bool
    {
        $policies = $this->getAvailablePolicies();
        if (!isset($policies[$policyKey])) return false;

        $economy = $user->economy;
        $active = $economy->strategic_policies ?? [];

        if (collect($active)->contains($policyKey)) {
            // Remove it
            $active = collect($active)->reject(fn($v) => $v === $policyKey)->values()->toArray();
            \App\Models\GameLog::log($user, "Deactivated policy: {$policies[$policyKey]['name']}", 'info', 'energy');
        } else {
            // Add it 
            $active[] = $policyKey;
            \App\Models\GameLog::log($user, "Activated policy: {$policies[$policyKey]['name']}", 'info', 'energy');
        }

        $economy->strategic_policies = $active;
        $economy->save();

        return true;
    }

    public function signContract(User $user, string $contractType): bool
    {
        $economy = $user->economy;
        $offers = $this->getContractOffers($user);
        
        $offer = collect($offers)->firstWhere('type', $contractType);
        if (!$offer) return false;

        // Admin fee
        if (!$economy->debit(100.00, "Energy Contract Application Fee", "energy")) {
            return false;
        }

        $economy->energy_contract_type = 'fixed';
        $economy->energy_contract_price = $offer['price'];
        $economy->energy_contract_expires_at = now()->addMinutes($offer['duration_ticks']);
        $economy->save();

        Log::info("User {$user->id} signed {$offer['name']} at \${$offer['price']}/kWh");
        \App\Models\GameLog::log($user, "Signed energy contract: {$offer['name']} at \${$offer['price']}/kWh", 'success', 'energy');
        
        return true;
    }
    public function getSolarProduction(User $user, ?string $region = null): float
    {
        $roomsQuery = $user->rooms()->where('solar_capacity_kw', '>', 0);
        if ($region) {
            $roomsQuery->where('region', $region);
        }
        $rooms = $roomsQuery->get();

        $factors = Cache::get('energy_regional_solar_factors', []);
        $weather = Cache::get('regional_weather', []);
        $totalProd = 0;

        foreach ($rooms as $room) {
            $factor = (float) ($factors[$room->region] ?? 0);
            
            // F203: Weather modifies solar output
            $weatherMod = (float) ($weather[$room->region]['modifiers']['solar_mod'] ?? 1.0);
            
            $totalProd += ($room->solar_capacity_kw * $factor * $weatherMod);
        }

        return $totalProd;
    }

    public function processStorage(User $user, float &$remainingUsage, $rooms = null): void
    {
        $spotPrice = $this->getSpotPrice();
        
        // Find all batteries for the user
        $batteries = Server::where('type', 'battery')
            ->whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('health', '>', 5)
            ->with(['rack.room'])
            ->get();

        if ($batteries->isEmpty()) {
            return;
        }

        // Detect Outages per room
        $outageRoomIds = [];
        if ($rooms) {
            foreach ($rooms as $room) {
                $hasOutage = \App\Models\GameEvent::where('affected_room_id', $room->id)
                    ->where('type', \App\Enums\EventType::POWER_OUTAGE)
                    ->whereIn('status', [\App\Enums\EventStatus::ACTIVE, \App\Enums\EventStatus::ESCALATED])
                    ->exists();
                if ($hasOutage) $outageRoomIds[] = $room->id;
            }
        }

        $activePolicies = $user->economy->strategic_policies ?? [];
        $hasArbitrage = in_array('battery_arbitrage', $activePolicies);
        $hasReserve = in_array('battery_reserve', $activePolicies);

        // Dynamic Market Thresholds
        $dischargePriceThreshold = $hasArbitrage ? 0.18 : 0.25;
        $chargePriceThreshold = $hasArbitrage ? 0.12 : 0.08;

        foreach ($batteries as $battery) {
            $capacity = (float) $battery->battery_capacity_kwh;
            $currentLevel = (float) $battery->battery_level_kwh;
            $roomId = $battery->rack->room_id;
            $isRoomInOutage = in_array($roomId, $outageRoomIds);
            
            if ($capacity <= 0) continue;

            $maxDischargeRateKw = $capacity * 2.0; 
            $maxChargeRateKw = $capacity * 1.0;    
            $tickFraction = 1 / 60;

            // Policy Impact: Reserve (keep 30% for outages)
            $availableForMarketDischarge = $currentLevel;
            if ($hasReserve && !$isRoomInOutage) {
                $availableForMarketDischarge = max(0, $currentLevel - ($capacity * 0.30));
            }

            if ($isRoomInOutage || $spotPrice > $dischargePriceThreshold) {
                // Determine source: If outage, we use total energy. If market, we respect reserve.
                $dischargePool = $isRoomInOutage ? $currentLevel : $availableForMarketDischarge;

                if ($dischargePool > 0 && $remainingUsage > 0) {
                    $possibleDischarge = min($remainingUsage, $maxDischargeRateKw, $dischargePool / $tickFraction);
                    $remainingUsage -= $possibleDischarge;
                    $battery->battery_level_kwh -= ($possibleDischarge * $tickFraction);
                    
                    $wearMod = $hasArbitrage ? 1.5 : 1.0;
                    $battery->health = max(0, $battery->health - (0.0008 * $wearMod)); // Discharge wear
                }

                // VPP: Sell excess to grid if price is EXTREME
                if (in_array('vpp_mode', $activePolicies) && $spotPrice > 0.45 && $currentLevel > ($capacity * 0.15)) {
                    // Maximum sell rate (up to 200% of capacity, very aggressive)
                    $sellRateKw = $maxDischargeRateKw * 1.5; 
                    $toSell = min($sellRateKw, ($currentLevel - ($capacity * 0.10)) / $tickFraction);
                    
                    if ($toSell > 0) {
                        $payout = $toSell * $tickFraction * $spotPrice;
                        $user->economy->credit($payout, 'Grid Stabilization: VPP Event', 'energy');
                        
                        $battery->battery_level_kwh -= ($toSell * $tickFraction);
                        $battery->health = max(0, $battery->health - 0.005); // High wear from VPP
                    }
                }
            } elseif ($spotPrice < $chargePriceThreshold || $remainingUsage < 0) {
                // Charge
                $neededChargeKw = ($remainingUsage < 0) ? abs($remainingUsage) : $maxChargeRateKw;
                $roomForChargeKwh = $capacity - $currentLevel;
                
                if ($roomForChargeKwh > 0) {
                    $possibleCharge = min($neededChargeKw, $maxChargeRateKw, $roomForChargeKwh / $tickFraction);
                    $remainingUsage += $possibleCharge;
                    
                    $battery->battery_level_kwh += ($possibleCharge * $tickFraction);
                    
                    $wearMod = $hasArbitrage ? 1.5 : 1.0;
                    $battery->health = max(0, $battery->health - (0.0005 * $wearMod)); // Charging wear
                }
            }

            // Passive health degradation
            $battery->health = max(0, $battery->health - 0.0001);
            
            // Clamping
            $battery->battery_level_kwh = max(0, min($capacity, $battery->battery_level_kwh));
            $battery->save();
        }
    }

    public function refillDiesel(User $user, \App\Models\GameRoom $room, int $liters): bool
    {
        if (!$room->has_diesel_backup) return false;
        
        $costPerLiter = 1.85; // $1.85 per liter
        $totalCost = $liters * $costPerLiter;
        
        if ($user->economy->balance < $totalCost) return false;
        
        $availableSpace = $room->diesel_fuel_capacity - (float)$room->diesel_fuel_liters;
        $litersToAdd = min((float)$liters, $availableSpace);
        $finalCost = $litersToAdd * $costPerLiter;
        
        if ($litersToAdd <= 0) return false;
        
        if (!$user->economy->debit($finalCost, "Refilled " . round($litersToAdd, 1) . "L Diesel for {$room->name}", 'energy')) {
            return false;
        }
        
        $room->diesel_fuel_liters += $litersToAdd;
        $room->save();
        
        \App\Models\GameLog::log($user, "Refilled " . round($litersToAdd, 1) . "L Diesel in {$room->name}", 'info', 'energy');
        
        return true;
    }
}
