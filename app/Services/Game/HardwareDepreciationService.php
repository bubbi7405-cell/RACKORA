<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\Server;
use App\Models\HardwareGeneration;
use App\Models\GameLog;

class HardwareDepreciationService
{
    /**
     * Process depreciation for all of a user's servers.
     * Called by GameLoopService on each tick.
     */
    public function tick(User $user): void
    {
        $this->processDepreciation($user);
        $this->checkObsolescence($user);
    }

    /**
     * Calculate and update resale value based on generation depreciation rate and age.
     */
    protected function processDepreciation(User $user): void
    {
        $servers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', '!=', 'decommissioned')
            ->get();

        foreach ($servers as $server) {
            $gen = $this->getGeneration($server->hardware_generation);
            if (!$gen) continue;

            $depRate = $gen->depreciation_rate;
            $purchaseCost = (float) $server->purchase_cost;

            // Calculate resale value:
            // Starts at 80% of purchase cost, then decreases by depreciation_rate per game "month"
            // Game months ≈ runtime / (30 * 24 * 3600) scaled to game time
            $runtimeHours = $server->total_runtime_seconds / 3600;
            $gameMonths = $runtimeHours / 720; // ~720 hours per month

            $resale = $purchaseCost * 0.80 * pow(1 - $depRate, $gameMonths);
            $resale = max($purchaseCost * 0.05, $resale); // Floor at 5% of purchase

            // Update only if changed meaningfully (avoid DB thrash)
            if (abs(($server->resale_value ?? 0) - $resale) > 1.0) {
                $server->resale_value = round($resale, 2);
                $server->save();
            }
        }
    }

    /**
     * Warn players about obsolete hardware (legacy gen servers with high runtime)
     */
    protected function checkObsolescence(User $user): void
    {
        $servers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('hardware_generation', 1) // Legacy
            ->where('status', 'online')
            ->get();

        foreach ($servers as $server) {
            $wearPct = $server->getLifespanUsage();
            
            // When legacy hardware passes 70% wear, warn player (rare chance per tick)
            if ($wearPct > 70 && rand(1, 500) === 1) {
                GameLog::log(
                    $user,
                    "⚠️ Hardware Alert: '{$server->model_name}' (Gen 1) is {$wearPct}% worn. Consider upgrading to newer hardware for better performance and lower power costs.",
                    'warning',
                    'hardware'
                );
            }
        }
    }

    /**
     * Get the resale value for selling a server back
     */
    public function getResaleValue(Server $server): float
    {
        $gen = $this->getGeneration($server->hardware_generation);
        $depRate = $gen ? $gen->depreciation_rate : 0.05;
        $purchaseCost = (float) $server->purchase_cost;
        
        $runtimeHours = $server->total_runtime_seconds / 3600;
        $gameMonths = $runtimeHours / 720;
        
        // Base resale starts at 80% and goes down with age
        $resale = $purchaseCost * 0.80 * pow(1 - $depRate, $gameMonths);
        
        // MARKET MODIFIER: Era-based demand
        // Legacy hardware has -30% demand, Next-gen has +20% demand
        $marketModifier = 1.0;
        if ($gen) {
            $marketModifier = match($gen->era) {
                'legacy' => 0.70,
                'current' => 1.00,
                'nextgen' => 1.20,
                default => 1.00
            };
        }
        $resale *= $marketModifier;

        // QUALITY MODIFIER: Health impact
        // Resale value drops sharply if health is low
        if ($server->status === \App\Enums\ServerStatus::EOL || $server->health <= 0) {
             return max($purchaseCost * 0.01, round($purchaseCost * 0.02, 2)); // 2% scrap value
        }

        $healthImpact = $server->health / 100;
        $resale *= (0.1 + ($healthImpact * 0.9)); // Drops toward 10% value if health is 0
        
        return max($purchaseCost * 0.02, round($resale, 2)); // absolute floor 2%
    }

    /**
     * Sell a server — Remove it and credit the player.
     */
    public function sellServer(User $user, Server $server): array
    {
        // Ensure server belongs to user
        $room = $server->rack?->room;
        if (!$room || $room->user_id !== $user->id) {
            return ['success' => false, 'message' => 'You do not own this server.'];
        }

        // Ensure no active orders
        $activeOrders = $server->orders()->whereIn('status', ['active', 'provisioning'])->count();
        if ($activeOrders > 0) {
            return ['success' => false, 'message' => 'Cannot sell a server with active orders. Migrate or cancel orders first.'];
        }

        // Ensure not rented out
        if ($server->tenant_id || \App\Models\ServerRental::where('server_id', $server->id)->where('status', 'rented')->exists()) {
            return ['success' => false, 'message' => 'Cannot sell a server that is currently rented out to another player.'];
        }

        $resaleValue = $this->getResaleValue($server);
        $gen = $this->getGeneration($server->hardware_generation);
        $genLabel = $gen ? $gen->name : 'Gen ' . $server->hardware_generation;

        // Credit player
        $user->economy->credit($resaleValue, "Sold '{$server->model_name}' ({$genLabel}) for resale value", 'hardware_sale');

        // Remove server from rack
        $server->delete();

        GameLog::log($user, "Sold server '{$server->model_name}' for $" . number_format($resaleValue, 2), 'info', 'hardware');

        return [
            'success' => true,
            'message' => "Sold '{$server->model_name}' for $" . number_format($resaleValue, 2),
            'resaleValue' => $resaleValue,
        ];
    }

    /**
     * Shred a server — Purely for security certification. No resale value, but increments shred_count.
     */
    public function shredServer(User $user, Server $server): array
    {
        // Ensure server belongs to user
        $room = $server->rack?->room;
        if (!$room || $room->user_id !== $user->id) {
            return ['success' => false, 'message' => 'You do not own this server.'];
        }

        // Ensure no active orders
        $activeOrders = $server->orders()->whereIn('status', ['active', 'provisioning'])->count();
        if ($activeOrders > 0) {
            return ['success' => false, 'message' => 'Cannot shred a server with active orders.'];
        }

        // Ensure not rented out
        if ($server->tenant_id || \App\Models\ServerRental::where('server_id', $server->id)->where('status', 'rented')->exists()) {
            return ['success' => false, 'message' => 'Cannot shred a server that is currently rented out.'];
        }

        // Increment shred_count in economy
        $economy = $user->economy;
        $economy->shred_count = ($economy->shred_count ?? 0) + 1;
        $economy->save();

        // Log transaction (small destruction fee instead of income)
        $destructionFee = 50.00;
        $economy->debit($destructionFee, "Secure Destruction Fee for '{$server->model_name}'", 'compliance_shredding');

        // Remove server
        $server->delete();

        GameLog::log($user, "Securely shredded server '{$server->model_name}'. Compliance +1.", 'success', 'compliance');

        return [
            'success' => true,
            'message' => "Successfully shredded '{$server->model_name}'. Security protocols updated.",
            'shredCount' => $economy->shred_count,
        ];
    }

    /**
     * Get the generation model for a given gen number
     */
    protected function getGeneration(int $gen): ?HardwareGeneration
    {
        return HardwareGeneration::where('generation', $gen)->first();
    }

    /**
     * Get all available generations with their catalog impact (for the shop UI)
     */
    public function getAvailableGenerations(): array
    {
        $gens = HardwareGeneration::available();

        return $gens->map(function ($gen) {
            return [
                'id' => $gen->id,
                'generation' => $gen->generation,
                'name' => $gen->name,
                'era' => $gen->era,
                'efficiency' => (float) $gen->efficiency_multiplier,
                'power' => (float) $gen->power_multiplier,
                'price' => (float) $gen->price_multiplier,
                'depreciationRate' => (float) $gen->depreciation_rate,
                'bonuses' => $gen->bonuses ?? [],
                'isLegacy' => $gen->isLegacy(),
            ];
        })->toArray();
    }

    /**
     * Get comparison data between generations
     */
    public function getGenerationComparison(): array
    {
        $gens = HardwareGeneration::orderBy('generation')->get();
        $comparison = [];

        foreach ($gens as $gen) {
            $comparison[] = [
                'gen' => $gen->generation,
                'name' => $gen->name,
                'benefits' => [
                    'efficiency' => (int) (($gen->efficiency_multiplier - 1.0) * 100),
                    'power_save' => (int) ((1.0 - $gen->power_multiplier) * 100),
                    'depreciation' => (int) ($gen->depreciation_rate * 100),
                ],
                'price_factor' => (float) $gen->price_multiplier,
            ];
        }

        return $comparison;
    }
}
