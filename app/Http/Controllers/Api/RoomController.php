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

        // Check if already owned
        $alreadyOwned = GameRoom::where('user_id', $user->id)
            ->where('type', $roomType->value)
            ->exists();

        if ($alreadyOwned) {
            return response()->json([
                'success' => false,
                'error' => 'You already own this room.',
            ], 400);
        }

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

        return DB::transaction(function () use ($user, $economy, $roomType, $cost) {
            // Create room
            $room = GameRoom::create([
                'user_id' => $user->id,
                'type' => $roomType,
                'name' => $roomType->label(),
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
            if (!$economy->debit($cost, "Room purchase: {$roomType->label()}", 'real_estate', $room)) {
                throw new \Exception("Insufficient funds transaction failed.");
            }

            // Award XP for room purchase
            $economy->addExperience(100);

            return response()->json([
                'success' => true,
                'data' => $room->toGameState(),
                'message' => "Welcome to your new {$roomType->label()}!",
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
            'upgrade_type' => 'required|string|in:power,cooling,bandwidth',
        ]);

        $user = $request->user();
        $room = GameRoom::where('user_id', $user->id)
            ->where('id', $request->room_id)
            ->firstOrFail();

        $upgradeType = $request->upgrade_type;
        $upgrades = $room->upgrades ?? [];
        $currentLevel = $upgrades[$upgradeType] ?? 0;

        if ($currentLevel >= 5) {
            return response()->json(['success' => false, 'error' => 'Upgrade at maximum level.'], 400);
        }

        // Calculate cost based on room type and current level
        $baseCost = $room->type->unlockCost() * 0.2; // 20% of room cost
        if ($baseCost < 500) $baseCost = 500; // Min cost
        
        $upgradeCost = $baseCost * pow(1.8, $currentLevel);

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
}
