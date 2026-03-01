<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competitor;
use App\Models\PeeringAgreement;
use App\Services\Game\NetworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PeeringController extends Controller
{
    public function __construct(
        protected NetworkService $networkService
    ) {}

    /**
     * Get list of potential peering partners (NPCs)
     */
    public function getPotentialPartners(Request $request): JsonResponse
    {
        $user = Auth::user();
        $network = $user->network;
        $asnRequired = !$network || !$network->asn;

        $competitors = Competitor::where('status', 'active')
            ->get()
            ->map(function ($c) use ($network, $user, $asnRequired) {
                // If no ASN, always ineligible
                if ($asnRequired) {
                    return [
                        'id' => $c->id,
                        'name' => $c->name,
                        'color' => $c->color_primary,
                        'personality' => $c->personality,
                        'archetype' => $c->archetype,
                        'isEligible' => false,
                        'asnRequired' => true,
                        'minPeeringScore' => $this->getMinScoreForPartner($c),
                        'baseLatency' => (float)$c->latency_score,
                        'baseCapacity' => (int)($c->capacity_score / 10),
                    ];
                }

                // Check if already peering
                $alreadyPeering = PeeringAgreement::where('user_id', $user->id)
                    ->where('competitor_id', $c->id)
                    ->where('status', 'active')
                    ->exists();

                // Check compatibility
                $minScore = $this->getMinScoreForPartner($c);
                $isEligible = $network->peering_score >= $minScore && !$alreadyPeering;

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'color' => $c->color_primary,
                    'personality' => $c->personality,
                    'archetype' => $c->archetype,
                    'isEligible' => $isEligible,
                    'isAlreadyPeering' => $alreadyPeering,
                    'minPeeringScore' => $minScore,
                    'baseLatency' => (float)$c->latency_score,
                    'baseCapacity' => (int)($c->capacity_score / 10),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $competitors,
            'playerScore' => $network ? (float)$network->peering_score : 0,
            'asnRequired' => $asnRequired
        ]);
    }

    /**
     * Propose a peering agreement to an NPC
     */
    public function proposePeering(Request $request): JsonResponse
    {
        $request->validate([
            'competitor_id' => 'required|exists:competitors,id',
            'capacity_gbps' => 'required|integer|min:10|max:1000',
            'monthly_cost' => 'required|numeric|min:0',
        ]);

        $user = Auth::user();
        $network = $user->network;
        $competitor = Competitor::find($request->competitor_id);

        if ($network->peering_score < $this->getMinScoreForPartner($competitor)) {
            return response()->json(['success' => false, 'error' => 'Peering score too low for this partner.'], 403);
        }

        // Acceptance Logic
        $acceptance = $this->calculateAcceptance($competitor, $request->capacity_gbps, $request->monthly_cost, $network->peering_score);

        if (rand(1, 100) > ($acceptance['probability'] * 100)) {
            return response()->json([
                'success' => false,
                'message' => $acceptance['counter_message'],
                'probability' => $acceptance['probability']
            ], 200); // We return 200 but success: false for "rejected"
        }

        // Logic to create agreement
        $latencyBonus = 1.0 - (100 - $competitor->latency_score) / 200; // e.g. 0.95 = 5% reduction
        $hopsReduction = rand(1, 3); // BGP Hops optimization

        $agreement = PeeringAgreement::create([
            'user_id' => $user->id,
            'competitor_id' => $competitor->id,
            'provider_name' => $competitor->name,
            'tier' => 'regional',
            'monthly_cost' => $request->monthly_cost,
            'capacity_gbps' => $request->capacity_gbps,
            'latency_bonus' => $latencyBonus,
            'hops_reduction' => $hopsReduction,
            'status' => 'converging', // BGP Convergence Phase
            'signed_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        \App\Models\GameLog::log($user, "Signed peering agreement with {$competitor->name}: {$request->capacity_gbps}Gbps", 'success', 'network');

        return response()->json([
            'success' => true,
            'data' => $agreement,
            'message' => "Peering established with {$competitor->name}!"
        ]);
    }

    private function getMinScoreForPartner(Competitor $c): int
    {
        return match($c->archetype) {
            'aggressive_expander' => 30,
            'premium_stability' => 60,
            'budget_volume' => 20,
            'stealth_innovator' => 50,
            'regional_specialist' => 40,
            default => 40
        };
    }

    private function calculateAcceptance(Competitor $c, int $capacity, float $cost, float $playerScore): array
    {
        // NPC Target: $100 per 10 Gbps (base) adjusted by NPC price_modifier
        $baseTarget = ($capacity / 10) * 100;
        $targetPrice = $baseTarget * $c->price_modifier;

        // Relationship factor could be added later
        $probability = ($cost / max(1, $targetPrice)) * ($playerScore / 100);
        $probability = min(1.0, max(0.01, $probability));

        $messages = [
            "Your offer is insulting. We value our backbone more than that.",
            "Not quite what we were looking for. Perhaps a higher commitment?",
            "We're interested, but the numbers don't add up for our board.",
        ];

        return [
            'probability' => $probability,
            'counter_message' => $messages[array_rand($messages)]
        ];
    }
}
