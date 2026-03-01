<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\MarketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketController extends Controller
{
    public function __construct(
        protected MarketService $marketService,
        protected \App\Services\Market\MarketSimulationService $simulationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->simulationService->getMarketOverview($request->user())
        ]);
    }

    public function getDemandHistory(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->simulationService->getDemandHistory(50)
        ]);
    }

    public function getUsedListings(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->marketService->getUsedMarketListings($request->user())
        ]);
    }

    public function buyUsedItem(Request $request): JsonResponse
    {
        $request->validate([
            'listing_id' => 'required|uuid'
        ]);

        $user = $request->user();
        $listing = \App\Models\MarketListing::active()->where('id', $request->listing_id)->first();

        if (!$listing) {
            return response()->json([
                'success' => false, 
                'error' => 'Listing not found or already sold.'
            ], 404);
        }

        if (!$user->economy->canAfford($listing->price)) {
            return response()->json([
                'success' => false, 
                'error' => 'Insufficient funds.'
            ], 400);
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($user, $listing) {
                // 1. Process Payment
                $user->economy->debit($listing->price, "Used {$listing->item_type}: {$listing->item_key}", 'hardware');

                // 2. Mark Sold
                $listing->is_sold = true;
                $listing->save();

                // 3. Grant Item
                $this->grantItem($user, $listing);

                // 4. Log
                \App\Models\GameLog::log($user, "Purchased used {$listing->item_key} from {$listing->seller_name} for $" . number_format($listing->price), 'success', 'economy');
            });

            return response()->json(['success' => true, 'message' => 'Item purchased successfully!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function grantItem(\App\Models\User $user, \App\Models\MarketListing $listing): void
    {
        switch ($listing->item_type) {
            case 'component':
                \App\Models\UserComponent::create([
                    'user_id' => $user->id,
                    'component_type' => 'cpu', // Simplify for now or look up from config based on key
                    'component_key' => $listing->item_key,
                    'status' => 'inventory',
                    'health' => $listing->condition,
                    'purchased_at' => now(),
                    // If specs has generation/cores, use them
                ]);
                break;
            
            case 'server':
                 \App\Models\UserComponent::create([
                    'user_id' => $user->id,
                    'component_type' => 'server_unit', 
                    'component_key' => $listing->item_key,
                    'status' => 'inventory',
                    'health' => $listing->condition,
                    'purchased_at' => now(),
                ]);
                break;
            
            case 'rack':
                $room = $user->rooms()->whereRaw('(SELECT COUNT(*) FROM server_racks WHERE room_id = game_rooms.id) < max_racks')->first();
                if (!$room) {
                    throw new \Exception("No space in any room for a new rack!");
                }
                
                $occupiedSlots = $room->racks()->pluck('position')->map(function($p) {
                    $arr = is_string($p) ? json_decode($p, true) : $p;
                    return $arr['slot'] ?? 0;
                })->toArray();
                $slot = 0;
                while(in_array($slot, $occupiedSlots)) $slot++;

                $rackTypeEnum = \App\Enums\RackType::tryFrom($listing->item_key) ?? \App\Enums\RackType::RACK_42U;

                \App\Models\ServerRack::create([
                    'room_id' => $room->id,
                    'type' => $rackTypeEnum,
                    'name' => "Refurbished Rack #{$listing->id}",
                    'total_units' => $rackTypeEnum->totalUnits(),
                    'max_power_kw' => $rackTypeEnum->maxPowerKw(),
                    'position' => ['slot' => $slot],
                    'status' => 'operational',
                    'temperature' => 22.0,
                    'dust_level' => 0.0,
                    'purchase_cost' => $listing->price ?? 0,
                ]);
                break;
        }
    }
}
