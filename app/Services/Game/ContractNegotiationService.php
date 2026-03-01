<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\CustomerOrder;
use App\Enums\EventStatus;
use Illuminate\Support\Facades\DB;

class ContractNegotiationService
{
    public function __construct(
        protected CustomerOrderService $orderService
    ) {}
    /**
     * Submit a bid for a negotiable contract
     */
    public function submitBid(User $user, CustomerOrder $order, float $bidPrice, string $bidSla, int $bidMonths): array
    {
        if (!$order->is_negotiable) {
            throw new \Exception("This contract is not negotiable.");
        }

        if ($order->status !== 'pending') {
            throw new \Exception("This contract is already closed or cancelled.");
        }

        if ($order->negotiation_attempts >= 3) {
            throw new \Exception("Maximum negotiation attempts reached. The customer is no longer interested.");
        }

        $probability = $this->calculateAcceptanceProbability($user, $order, $bidPrice, $bidSla, $bidMonths);
        $roll = rand(0, 100);
        $success = $roll <= ($probability * 100);

        return DB::transaction(function () use ($user, $order, $bidPrice, $bidSla, $bidMonths, $success, $probability) {
            $order->negotiation_attempts++;
            
            if ($success) {
                $order->price_per_month = $bidPrice;
                $order->sla_tier = $bidSla;
                $order->contract_months = $bidMonths;
                $order->is_negotiable = false; // Finalized

                // Rescale requirements based on new SLA tier
                $order->requirements = $this->rescaleRequirements($order->requirements, $bidSla);
                
                $order->save();

                // Bonus XP for successful high-value negotiation
                $user->economy->addExperience(250);
                
                return [
                    'success' => true,
                    'message' => 'The customer has accepted your proposal!',
                    'order' => $order->toGameState()
                ];
            } else {
                // If failed, check if they walk away immediately
                // Higher attempts = higher chance to walk away
                $walkAwayChance = 10 + (($order->negotiation_attempts - 1) * 20);
                if (rand(0, 100) < $walkAwayChance) {
                    $order->status = 'cancelled';
                    $order->save();
                    
                    return [
                        'success' => false,
                        'message' => 'The customer was offended by your offer and has walked away.',
                        'order' => $order->toGameState(),
                        'walked_away' => true
                    ];
                }

                $order->save();
                return [
                    'success' => false,
                    'message' => 'The customer has rejected your offer, but is willing to continue talking.',
                    'order' => $order->toGameState(),
                    'probability' => $probability,
                    'walked_away' => false
                ];
            }
        });
    }

    /**
     * Logic for acceptance probability
     */
    public function calculateAcceptanceProbability(User $user, CustomerOrder $order, float $bidPrice, string $bidSla, int $bidMonths): float
    {
        // Rescale requirements for the proposed SLA tier to find the 'fair' price
        $proposedRequirements = $this->rescaleRequirements($order->requirements, $bidSla);
        $reqMod = $order->requirements['_reqMod'] ?? 1.0;
        
        $fairBasePrice = (float) $this->orderService->calculateIdealPrice($proposedRequirements, $bidSla, $user, $reqMod);
        
        // 1. Price Factor
        // If bid is below fair price, bonus. If above, penalty.
        $priceRatio = $bidPrice / $fairBasePrice;
        $priceScore = 0.8; // Neutral (80% chance if price matches 'fair' price)

        if ($priceRatio <= 1.0) {
            // Cheaper than fair: up to 1.0 probability
            $priceScore = 0.8 + (1.0 - $priceRatio) * 0.4;
        } else {
            // More expensive than fair
            // 1.5x price = 0.3 probability
            $priceScore = 0.8 / pow($priceRatio, 3);
        }

        // 2. SLA Factor
        $slaTiers = ['standard' => 1, 'premium' => 2, 'enterprise' => 3, 'whale' => 4];
        $originalSla = $order->sla_tier;
        $slaDiff = ($slaTiers[$bidSla] ?? 1) - ($slaTiers[$originalSla] ?? 1);
        
        $slaBonus = 1.0 + ($slaDiff * 0.15); // +15% per tier improvement

        // 3. Contract Length Factor (Customers like stability)
        // Average 6 months is neutral
        $lengthBonus = 1.0 + (($bidMonths - 6) * 0.02); // +/- 2% per month

        // 4. Reputation / Level Factor
        $rep = $user->economy->reputation ?? 50;
        $repBonus = 1.0 + (($rep - 50) / 250); // +/- 20% max bonus from reputation

        // 5. Fatigue Factor (Negotiation Attempts)
        $fatigue = 1.0 - ($order->negotiation_attempts * 0.2); // -20% chance per previous rejection

        // 6. FEATURE 310: Green Hosting Factor
        $energyService = app(EnergyService::class);
        $greenScore = $energyService->getGreenScore($user);
        
        $activePolicies = $user->economy->strategic_policies ?? [];
        $hasEcoCertified = in_array('eco_mode', $activePolicies);
        $hasGreenPref = $order->customer->preferences['green_preference'] ?? false;
        
        $greenBonus = 1.0;
        if ($greenScore > 0.5 || $hasEcoCertified) {
            // Base bonus for eco-conscious setup
            $greenBonus += ($greenScore * 0.1); 
            
            // Doubled if customer explicitly prefers green hosting
            if ($hasGreenPref) {
                $greenBonus += 0.25; 
            }
            
            if ($hasEcoCertified) {
                $greenBonus += 0.10;
            }
        }

        $finalProb = $priceScore * $slaBonus * $lengthBonus * $repBonus * $fatigue * $greenBonus;

        return (float) max(0.01, min(0.99, $finalProb));
    }
    public function rescaleRequirements(array $requirements, string $slaTier): array
    {
        if (!isset($requirements['_base'])) {
            return $requirements; // Cannot rescale without base data
        }

        $base = $requirements['_base'];
        $reqMod = $requirements['_reqMod'] ?? 1.0;

        $slaReqMod = match($slaTier) {
            'premium' => 1.5,
            'enterprise' => 5.0,
            'whale' => 15.0,
            default => 1.0,
        };

        $newRequirements = [];
        foreach ($base as $k => $v) {
            if (in_array($k, ['cpu', 'ram', 'storage', 'bandwidth'])) {
                 $newRequirements[$k] = ceil($v * $reqMod * $slaReqMod);
            } elseif (is_numeric($v)) {
                 $newRequirements[$k] = ceil($v * $reqMod);
            } else {
                 $newRequirements[$k] = $v;
            }
        }

        // Keep the base metadata
        $newRequirements['_base'] = $base;
        $newRequirements['_reqMod'] = $reqMod;

        return $newRequirements;
    }
}
