<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoomType;
use App\Http\Controllers\Controller;
use App\Models\GameRoom;
use App\Models\Server;
use App\Enums\ServerStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomController extends Controller
{
    /**
     * Get all rooms for the player (including locked ones as previews)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $ownedRooms = GameRoom::where('user_id', $user->id)->get();
        $ownedTypes = $ownedRooms->pluck('type')->map(fn($t) => $t->value)->toArray();

        $rooms = [];

        foreach (RoomType::cases() as $type) {
            if (in_array($type->value, $ownedTypes)) {
                $room = $ownedRooms->first(fn($r) => $r->type->value === $type->value);
                $rooms[] = array_merge($room->toGameState(), [
                    'owned' => true,
                    'purchaseCost' => $type->unlockCost(),
                    'requiredLevel' => $type->requiredLevel(),
                ]);
            } else {
                $rooms[] = [
                    'type' => $type->value,
                    'name' => $type->label(),
                    'owned' => false,
                    'isUnlocked' => false,
                    'purchaseCost' => $type->unlockCost(),
                    'requiredLevel' => $type->requiredLevel(),
                    'specs' => [
                        'maxRacks' => $type->maxRacks(),
                        'maxPowerKw' => $type->maxPowerKw(),
                        'maxCoolingKw' => $type->maxCoolingKw(),
                        'bandwidthGbps' => $type->bandwidthGbps(),
                        'rentPerHour' => $type->rentPerHour(),
                    ],
                    'canAfford' => $user->economy->canAfford($type->unlockCost()),
                    'meetsLevel' => $user->economy->level >= $type->requiredLevel(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $rooms,
            'regions' => \App\Models\GameConfig::get('regions', []),
        ]);
    }

    /**
     * Purchase a new room
     */
    public function purchase(Request $request): JsonResponse
    {
        $request->validate([
            'room_type' => 'required|string',
        ]);

        $user = $request->user();
        $economy = $user->economy;

        // Validate room type
        $roomType = RoomType::tryFrom($request->room_type);
        if (!$roomType) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid room type.',
            ], 400);
        }

        // For Multi-Region Expansion, we allow purchasing the same room type in different regions.
        // We will move the duplicate check INSIDE the DB::transaction once we know the region.

        // Check level requirement
        if ($economy->level < $roomType->requiredLevel()) {
            return response()->json([
                'success' => false,
                'error' => "You need to be Level {$roomType->requiredLevel()} to unlock this room. You are Level {$economy->level}.",
            ], 400);
        }

        // Check funds
        $cost = $roomType->unlockCost();
        if (!$economy->canAfford($cost)) {
            return response()->json([
                'success' => false,
                'error' => "Insufficient funds. You need \${$cost} but only have \${$economy->balance}.",
            ], 400);
        }

        return DB::transaction(function () use ($user, $economy, $roomType, $cost, $request) {
            // Region Handling
            $regionKey = $request->input('region', 'us_east');
            $regions = \App\Models\GameConfig::get('regions', []);
            $regionConfig = $regions[$regionKey] ?? null;

            if (!$regionConfig) {
                // Fallback or error? Let's default to US East if invalid, or error out
                 throw new \Exception("Invalid region selected.");
            }

            // Check Region Level Requirement
            if ($economy->level < ($regionConfig['level_required'] ?? 1)) {
                 throw new \Exception("Region requires Level " . ($regionConfig['level_required'] ?? 1));
            }

            // Check if already owned in this specific region
            $alreadyOwnedInRegion = GameRoom::where('user_id', $user->id)
                ->where('type', $roomType->value)
                ->where('region', $regionKey)
                ->exists();

            if ($alreadyOwnedInRegion) {
                 throw new \Exception("You already own a {$roomType->label()} in {$regionConfig['name']}. Try expanding to another region.");
            }

            // Create room
            $room = GameRoom::create([
                'user_id' => $user->id,
                'type' => $roomType,
                'name' => $roomType->label() . ' (' . ($regionConfig['name'] ?? $regionKey) . ')',
                'region' => $regionKey,
                'power_cost_kwh' => $regionConfig['base_power_cost'] ?? 0.12,
                'latency_ms' => 50.0 * ($regionConfig['latency_modifier'] ?? 1.0),
                'level' => 1,
                'max_racks' => $roomType->maxRacks(),
                'max_power_kw' => $roomType->maxPowerKw(),
                'max_cooling_kw' => $roomType->maxCoolingKw(),
                'bandwidth_gbps' => $roomType->bandwidthGbps(),
                'rent_per_hour' => $roomType->rentPerHour(),
                'is_unlocked' => true,
                'unlocked_at' => now(),
                'position' => ['x' => 0, 'y' => 0],
            ]);

            // Debit
            if (!$economy->debit($cost, "Room purchase: {$roomType->label()} in " . ($regionConfig['name'] ?? $regionKey), 'real_estate', $room)) {
                throw new \Exception("Insufficient funds transaction failed.");
            }

            // Award XP for room purchase
            $economy->addExperience(100);

            // --- FEATURE 35: AI REACTION ---
            app(\App\Services\Market\CompetitorAIService::class)->reactToPlayerAction($user, 'region_entry', ['region' => $regionKey, 'room' => $room]);
            
            if ($roomType->value === RoomType::DATA_CENTER) {
                app(\App\Services\Market\CompetitorAIService::class)->reactToPlayerAction($user, 'massive_expansion', ['room' => $room]);
            }

            return response()->json([
                'success' => true,
                'data' => $room->toGameState(),
                'message' => "Welcome to your new {$roomType->label()} in {$regionConfig['name']}!",
            ]);
        });
    }

    /**
     * Upgrade room specifications
     */
    public function upgrade(Request $request): JsonResponse
    {
        $request->validate([
            'room_id' => 'required|uuid|exists:game_rooms,id',
            'upgrade_type' => 'required|string|in:power,cooling,bandwidth,repair_cooling,airflow,network_tier,redundancy,diesel_backup,solar_panels,heat_recovery,academy,wellness_facility',
            'airflow_type' => 'nullable|string|in:hot_aisle,cold_aisle_containment',
        ]);

        $user = $request->user();
        $room = GameRoom::where('user_id', $user->id)
            ->where('id', $request->room_id)
            ->firstOrFail();

        $upgradeType = $request->upgrade_type;
        $upgrades = $room->upgrades ?? [];

        // Handle Special Upgrades (Repair / Airflow)
        if ($upgradeType === 'repair_cooling') {
            if ($room->cooling_health >= 100) {
                return response()->json(['success' => false, 'error' => 'Cooling system is fully operational.'], 400);
            }
            $repairCost = 500 * (1 - ($room->cooling_health / 100)); // Cost scales with damage
            $repairCost = max(50, $repairCost); // Minimum $50

            if (!$user->economy->canAfford($repairCost)) {
                return response()->json(['success' => false, 'error' => 'Insufficient funds for repair.'], 400);
            }

            $user->economy->debit($repairCost, "Cooling System Repair", 'maintenance');
            $room->cooling_health = 100;
            $room->save();

            return response()->json([
                'success' => true,
                'data' => $room->toGameState(),
                'message' => "Cooling system repaired to 100% health."
            ]);
        }

        if ($upgradeType === 'airflow') {
             $targetType = $request->airflow_type;
             if (!$targetType) {
                 return response()->json(['success' => false, 'error' => 'Airflow type required.'], 400);
             }
             
             // Cost Table
             $airflowCosts = [
                 'hot_aisle' => 5000,
                 'cold_aisle_containment' => 15000
             ];

             $cost = $airflowCosts[$targetType] ?? 999999;
             $label = str_replace('_', ' ', ucfirst($targetType));

             if ($room->airflow_type === $targetType) {
                 return response()->json(['success' => false, 'error' => 'Optimization already installed.'], 400);
             }

             if (!$user->economy->canAfford($cost)) {
                 return response()->json(['success' => false, 'error' => 'Insufficient funds for optimization.'], 400);
             }

             $user->economy->debit($cost, "Airflow Optimization: {$label}", 'real_estate');
             $room->airflow_type = $targetType;
             $room->save();

             return response()->json([
                 'success' => true,
                 'data' => $room->toGameState(),
                 'message' => "Room upgraded with {$label}!"
             ]);
        }

        if ($upgradeType === 'redundancy') {
            $currentLevel = $room->redundancy_level;
            if ($currentLevel >= 4) {
                return response()->json(['success' => false, 'error' => 'Redundancy is already at maximum (Tier 4).'], 400);
            }

            // High cost for redundancy upgrades
            $redundancyCosts = [
                1 => 25000,   // Tier 2 (N+1)
                2 => 100000,  // Tier 3 (2N)
                3 => 500000,  // Tier 4 (2(N+1))
            ];
            $cost = $redundancyCosts[$currentLevel] ?? 999999;

            if (!$user->economy->canAfford($cost)) {
                 return response()->json(['success' => false, 'error' => 'Insufficient funds for redundancy upgrade.'], 400);
            }

            return DB::transaction(function () use ($user, $room, $currentLevel, $cost) {
                $room->redundancy_level = $currentLevel + 1;
                $room->save();

                $economy = $user->economy;
                $economy->debit($cost, "Redundancy Upgrade: Tier " . ($currentLevel + 1), 'infrastructure', $room);
                $economy->addExperience(500); // Massive XP for big infrastructure

                return response()->json([
                    'success' => true,
                    'data' => $room->toGameState(),
                    'message' => "Infrastructure upgraded to " . $room->getRedundancyLabel() . "!"
                ]);
            });
        }

        if ($upgradeType === 'diesel_backup') {
            if ($room->has_diesel_backup) {
                return response()->json(['success' => false, 'error' => 'Diesel backup already installed.'], 400);
            }
            $cost = 50000; // Expensive
            if (!$user->economy->canAfford($cost)) {
                return response()->json(['success' => false, 'error' => 'Insufficient funds for diesel backup.'], 400);
            }
            $user->economy->debit($cost, "Diesel Backup Installation", 'infrastructure');
            $room->has_diesel_backup = true;
            $room->diesel_fuel_liters = $room->diesel_fuel_capacity; // Fill it up the first time
            $room->save();
            return response()->json([
                'success' => true,
                'data' => $room->toGameState(),
                'message' => "Diesel backup system installed in {$room->name}!"
            ]);
        }

        if ($upgradeType === 'solar_panels') {
            $currentLevel = $upgrades['solar_panels'] ?? 0;
            if ($currentLevel >= 5) {
                return response()->json(['success' => false, 'error' => 'Rooftop is full of solar panels.'], 400);
            }

            // High CAPEX for solar
            $cost = 15000 * pow(2.5, $currentLevel);

            if (!$user->economy->canAfford($cost)) {
                 return response()->json(['success' => false, 'error' => 'Insufficient funds for solar panels.'], 400);
            }

            return DB::transaction(function () use ($user, $room, $currentLevel, $cost, $upgrades) {
                $upgrades['solar_panels'] = $currentLevel + 1;
                $room->upgrades = $upgrades;
                $room->solar_capacity_kw += 15.0; // Each level adds 15kW capacity
                $room->save();

                $economy = $user->economy;
                $economy->debit($cost, "Solar Panel Installation: Level " . ($currentLevel + 1), 'infrastructure', $room);
                $economy->addExperience(300);

                return response()->json([
                    'success' => true,
                    'data' => $room->toGameState(),
                    'message' => "Solar panels (Level " . ($currentLevel + 1) . ") installed! Free energy incoming."
                ]);
            });
        }

        // Standard Upgrades logic continues...
        // FEATURE 268: Heat Recovery System
        if ($upgradeType === 'heat_recovery') {
            $currentUpgrades = $room->upgrades ?? [];
            if (in_array('heat_recovery', $currentUpgrades)) {
                return response()->json(['success' => false, 'error' => 'Heat Recovery is already installed.'], 400);
            }
            $cost = 20000;
            if (!$user->economy->canAfford($cost)) {
                return response()->json(['success' => false, 'error' => 'Insufficient funds for Heat Recovery System.'], 400);
            }

            return DB::transaction(function () use ($user, $room, $cost, $currentUpgrades) {
                $currentUpgrades[] = 'heat_recovery';
                $room->upgrades = $currentUpgrades;
                $room->save();

                $economy = $user->economy;
                $economy->debit($cost, "Heat Recovery System: {$room->name}", 'infrastructure', $room);
                $economy->addExperience(200);
                $economy->adjustSpecializedReputation('green', 5.0);

                \App\Models\GameLog::log($user, "Heat Recovery System installed in {$room->name}. Carbon Tax reduced by 40%.", 'success', 'infrastructure');

                return response()->json([
                    'success' => true,
                    'data' => $room->toGameState(),
                    'message' => 'Heat Recovery System installed! Carbon Tax reduced by 40%.'
                ]);
            });
        }

        // FEATURE 243: Employee Wellness Facility
        if ($upgradeType === 'wellness_facility') {
            $currentUpgrades = $room->upgrades ?? [];
            if (in_array('wellness_facility', $currentUpgrades)) {
                return response()->json(['success' => false, 'error' => 'Wellness Facility is already installed.'], 400);
            }
            $cost = 25000;
            if (!$user->economy->canAfford($cost)) {
                return response()->json(['success' => false, 'error' => 'Insufficient funds for Wellness Facility.'], 400);
            }

            return DB::transaction(function () use ($user, $room, $cost, $currentUpgrades) {
                $currentUpgrades[] = 'wellness_facility';
                $room->upgrades = $currentUpgrades;
                $room->save();

                $economy = $user->economy;
                $economy->debit($cost, "Wellness Facility: {$room->name}", 'infrastructure', $room);
                $economy->addExperience(250);

                \App\Models\GameLog::log($user, "Wellness Facility (Gym/Sauna) built in {$room->name}. Assigned staff recover stress 2x faster!", 'success', 'hr');

                return response()->json([
                    'success' => true,
                    'data' => $room->toGameState(),
                    'message' => 'Wellness Facility installed! Staff well-being improved.'
                ]);
            });
        }

        // FEATURE 63: Corporate Academy
        if ($upgradeType === 'academy') {
            $currentUpgrades = $room->upgrades ?? [];
            if (in_array('academy', $currentUpgrades)) {
                return response()->json(['success' => false, 'error' => 'Corporate Academy is already installed.'], 400);
            }
            $cost = 35000;
            if (!$user->economy->canAfford($cost)) {
                return response()->json(['success' => false, 'error' => 'Insufficient funds for Corporate Academy.'], 400);
            }

            return DB::transaction(function () use ($user, $room, $cost, $currentUpgrades) {
                $currentUpgrades[] = 'academy';
                $room->upgrades = $currentUpgrades;
                $room->save();

                $economy = $user->economy;
                $economy->debit($cost, "Corporate Academy Installation: {$room->name}", 'infrastructure', $room);
                $economy->addExperience(300);

                \App\Models\GameLog::log($user, "Corporate Academy built in {$room->name}. Idle staff gain +5 XP per tick automatically.", 'success', 'infrastructure');

                return response()->json([
                    'success' => true,
                    'data' => $room->toGameState(),
                    'message' => 'Corporate Academy installed! Employees learn faster.'
                ]);
            });
        }

        $currentLevel = $upgrades[$upgradeType] ?? 0;

        if ($currentLevel >= 5) {
            return response()->json(['success' => false, 'error' => 'Upgrade at maximum level.'], 400);
        }

        // Special handling for network_tier (max 3)
        if ($upgradeType === 'network_tier' && $currentLevel >= 3) {
            return response()->json(['success' => false, 'error' => 'Network is already at Global Anycast tier.'], 400);
        }

        // Calculate cost based on room type and current level
        $baseCost = $room->type->unlockCost() * 0.2; // 20% of room cost
        if ($baseCost < 500) $baseCost = 500; // Min cost
        
        // Network Tier is much more expensive
        if ($upgradeType === 'network_tier') {
            $baseCost = 10000;
            $upgradeCost = $baseCost * pow(3, $currentLevel);
        } else {
            $upgradeCost = $baseCost * pow(1.8, $currentLevel);
        }

        if (!$user->economy->canAfford($upgradeCost)) {
            return response()->json([
                'success' => false, 
                'error' => "Upgrade costs \$" . number_format($upgradeCost, 2) . ". Insufficient funds."
            ], 400);
        }

        return DB::transaction(function () use ($user, $room, $upgradeType, $upgrades, $currentLevel, $upgradeCost) {
            $upgrades[$upgradeType] = $currentLevel + 1;
            $room->upgrades = $upgrades;

            // Apply spec increases
            if ($upgradeType === 'power') {
                $room->max_power_kw *= 1.25; // +25%
            } elseif ($upgradeType === 'cooling') {
                $room->max_cooling_kw *= 1.25; // +25%
            } elseif ($upgradeType === 'bandwidth') {
                $room->bandwidth_gbps *= 2.0; // +100%
            }

            $room->save();
            $user->economy->debit($upgradeCost, "Room Upgrade ({$upgradeType}): {$room->name} Lvl " . ($currentLevel + 1), 'real_estate', $room);

            return response()->json([
                'success' => true,
                'data' => $room->toGameState(),
                'message' => ucfirst($upgradeType) . " upgraded to Level " . ($currentLevel + 1) . "!"
            ]);
        });
    }

    /**
     * Customize room appearance
     */
    public function customize(Request $request): JsonResponse
    {
        $request->validate([
            'room_id' => 'required',
            'wallpaper_id' => 'nullable|string|max:50',
            'theme' => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        $room = GameRoom::where('user_id', $user->id)
            ->where('id', $request->room_id)
            ->firstOrFail();

        if ($request->has('wallpaper_id')) $room->wallpaper_id = $request->wallpaper_id;
        if ($request->has('theme')) $room->theme = $request->theme;
        
        $room->save();

        return response()->json([
            'success' => true,
            'data' => $room->toGameState(),
            'message' => 'Room style updated!',
        ]);
    }

    /**
     * FEATURE 83: Reset Circuit Breaker
     */
    public function resetCircuitBreaker(Request $request): JsonResponse
    {
        $request->validate([
            'room_id' => 'required|uuid|exists:game_rooms,id',
        ]);

        $user = $request->user();
        $room = GameRoom::where('user_id', $user->id)
            ->where('id', $request->room_id)
            ->firstOrFail();

        if (!$room->has_circuit_breaker_tripped) {
            return response()->json([
                'success' => false,
                'error' => 'The circuit breaker is not tripped in this room.'
            ], 400);
        }

        return DB::transaction(function () use ($user, $room) {
            // Un-trip the breaker
            $room->has_circuit_breaker_tripped = false;
            $room->save();

            // Attempt to bring servers back online, assuming they were shut down by the breaker
            foreach ($room->racks as $rack) {
                foreach ($rack->servers as $server) {
                    if ($server->status === ServerStatus::OFFLINE && $server->current_fault === 'Circuit Breaker Tripped') {
                        $server->status = ServerStatus::ONLINE;
                        $server->current_fault = null;
                        $server->save();
                    }
                }
                $rack->recalculatePowerAndHeat();
            }

            \App\Models\GameLog::log($user, "Circuit breaker in {$room->name} has been manually reset. Power restored.", 'success', 'infrastructure');

            return response()->json([
                'success' => true,
                'data' => $room->toGameState(),
                'message' => 'Circuit breaker reset successfully. Power restored.',
            ]);
        });
    }

    /**
     * FEATURE 297: Data Center Tourism & PR Tours
     */
    public function hostPrTour(Request $request): JsonResponse
    {
        $request->validate([
            'room_id' => 'required|uuid|exists:game_rooms,id',
        ]);

        $user = $request->user();
        $room = GameRoom::where('user_id', $user->id)
            ->where('id', $request->room_id)
            ->firstOrFail();

        // Check level requirement (Phase 5)
        if ($user->economy->level < 15) {
            return response()->json(['success' => false, 'error' => 'PR Tours require Company Level 15.'], 400);
        }

        // Check if a tour was recently hosted (cooldown of 4 real-hours or 1 in-game month)
        if ($room->last_pr_tour_at && \Carbon\Carbon::parse($room->last_pr_tour_at)->addHours(4)->isFuture()) {
            return response()->json(['success' => false, 'error' => 'You can only host one PR Tour per room every 4 hours.'], 400);
        }

        $cost = 5000;
        if (!$user->economy->canAfford($cost)) {
            return response()->json(['success' => false, 'error' => 'Hosting a PR Tour costs $5,000 for marketing.'], 400);
        }

        // Calculate Room Score
        $racks = $room->racks;
        if ($racks->isEmpty()) {
            return response()->json(['success' => false, 'error' => 'The room is completely empty. Investors would be disappointed.'], 400);
        }

        // 1. Cleanliness (Dust level)
        $avgDust = $racks->avg('dust_level') ?? 0;
        $cleanlinessScore = max(0, 100 - $avgDust);

        // 2. Hardware Fill Rate & Server Health
        $totalSlots = $racks->sum('total_units');
        $usedSlots = $racks->sum('used_units');
        $fillRate = $totalSlots > 0 ? ($usedSlots / $totalSlots) * 100 : 0;
        
        $onlineServers = 0;
        $totalHealth = 0;
        foreach ($racks as $rack) {
            foreach ($rack->servers as $server) {
                if ($server->status === ServerStatus::ONLINE) {
                    $onlineServers++;
                }
                $totalHealth += $server->health;
            }
        }
        $serverCount = $racks->sum(fn($r) => $r->servers->count());
        $avgHealth = $serverCount > 0 ? ($totalHealth / $serverCount) : 0;

        // 3. Overall aesthetic (cable_management doesn't exist directly on rack yet, so we use RGB + Theme)
        $aestheticScore = 50; 
        if ($room->theme !== 'classic') $aestheticScore += 20;
        if ($room->wallpaper_id !== 'default') $aestheticScore += 30;

        // Final Score (0 - 100)
        $tourScore = ($cleanlinessScore * 0.4) + ($fillRate * 0.2) + ($avgHealth * 0.2) + ($aestheticScore * 0.2);

        // Outcomes
        $repGain = 0;
        $cashGain = 0;
        $message = "";

        if ($tourScore >= 85) { // Excellent
            $repGain = 120;
            $cashGain = 15000; // Ticket sales + investor backing
            $message = "The PR Tour was an overwhelming success! Investors were blown away by your immaculate facility.";
        } elseif ($tourScore >= 60) { // Good
            $repGain = 40;
            $cashGain = 2000;
            $message = "The PR Tour went well. Visitors appreciated the setup.";
        } elseif ($tourScore >= 30) { // Poor
            $repGain = -10;
            $message = "The PR Tour was disappointing. The racks look somewhat neglected and dusty.";
        } else { // Catastrophic
            $repGain = -50;
            $message = "DISASTER! Investors saw a dirty, empty, and failing data center. Reputation plummeted.";
        }

        return DB::transaction(function () use ($user, $room, $cost, $repGain, $cashGain, $message) {
            $user->economy->debit($cost, "PR Tour Marketing: {$room->name}", 'marketing');
            
            if ($cashGain > 0) {
                $user->economy->credit($cashGain, "PR Tour Investor Backing & Tickets", 'marketing');
            }
            
            if ($repGain !== 0) {
                $user->economy->adjustReputation($repGain);
            }

            $room->last_pr_tour_at = now();
            $room->save();

            \App\Models\GameLog::log($user, $message, $repGain > 0 ? 'success' : 'danger', 'marketing');

            return response()->json([
                'success' => true,
                'data' => [
                    'room' => $room->toGameState(),
                    'score' => round($tourScore, 1),
                    'repChange' => $repGain,
                    'cashChange' => $cashGain - $cost,
                    'message' => $message,
                ]
            ]);
        });
    }
}
