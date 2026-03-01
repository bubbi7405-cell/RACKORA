<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\EnergyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EnergyController extends Controller
{
    public function __construct(
        protected EnergyService $energyService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'spot_price' => $this->energyService->getSpotPrice(),
            'regional_prices' => Cache::get('energy_regional_prices', []),
            'price_history' => Cache::get('energy_price_history', []),
            'regional_history' => Cache::get('energy_regional_history', []),
            'regional_weather' => Cache::get('regional_weather', []),
            'solar_factors' => Cache::get('energy_regional_solar_factors', []),
            'offers' => $this->energyService->getContractOffers($user),
            'policies' => $this->energyService->getAvailablePolicies(),
            'current_contract' => [
                'type' => $user->economy->energy_contract_type,
                'price' => (float) $user->economy->energy_contract_price,
                'expires_at' => $user->economy->energy_contract_expires_at,
            ],
            'active_policies' => $user->economy->strategic_policies ?? [],
            'green_score' => $this->energyService->getGreenScore($user),
            'storage' => [
                'total_capacity' => (float) \App\Models\Server::where('type', 'battery')
                    ->whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
                    ->sum('battery_capacity_kwh'),
                'current_level' => (float) \App\Models\Server::where('type', 'battery')
                    ->whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
                    ->sum('battery_level_kwh'),
                'battery_count' => \App\Models\Server::where('type', 'battery')
                    ->whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
                    ->count(),
                'average_health' => (float) \App\Models\Server::where('type', 'battery')
                    ->whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
                    ->avg('health') ?? 100,
                'is_vpp_active' => in_array('vpp_mode', $user->economy->strategic_policies ?? []) && $this->energyService->getSpotPrice() > 0.45,
            ]
        ]);
    }

    /**
     * Set/Toggle an operational policy.
     */
    public function togglePolicy(Request $request): JsonResponse
    {
        $request->validate([
            'policy' => 'required|string'
        ]);

        $success = $this->energyService->togglePolicy($request->user(), $request->policy);

        if (!$success) {
            return response()->json([
                'success' => false,
                'error' => 'Policy activation failed.'
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Operational policy updated.'
        ]);
    }

    /**
     * Sign a new contract.
     */
    public function sign(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string'
        ]);

        $success = $this->energyService->signContract($request->user(), $request->type);

        if (!$success) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid contract type or offer expired.'
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contract signed successfully. Operational safety increased.'
        ]);
    }
    /**
     * Set corporate specialization (Level 10 required).
     */
    public function setSpecialization(Request $request): JsonResponse
    {
        $request->validate([
            'specialization' => 'required|string|in:eco_certified,high_performance,budget_mass'
        ]);

        try {
            $service = app(\App\Services\Game\SpecializationService::class);
            $service->setSpecialization($request->user(), $request->specialization);

            return response()->json([
                'success' => true,
                'message' => 'Corporate Doctrine established successfully.',
                'specialization' => $request->specialization
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 403);
        }
    }

    public function refill(Request $request): JsonResponse
    {
        $request->validate([
            'room_id' => 'required|uuid|exists:game_rooms,id',
            'liters' => 'required|integer|min:1'
        ]);

        $user = $request->user();
        $room = \App\Models\GameRoom::where('user_id', $user->id)
            ->where('id', $request->room_id)
            ->firstOrFail();

        $success = $this->energyService->refillDiesel($user, $room, $request->liters);

        if (!$success) {
            return response()->json([
                'success' => false,
                'error' => 'Refill failed. Check balance or capacity.'
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Diesel backup refilled.',
            'diesel' => [
                'fuel' => $room->fresh()->diesel_fuel_liters,
                'capacity' => $room->diesel_fuel_capacity
            ]
        ]);
    }
}
