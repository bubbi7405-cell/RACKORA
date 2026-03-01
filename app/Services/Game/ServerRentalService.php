<?php

namespace App\Services\Game;

use App\Models\Server;
use App\Models\ServerRental;
use App\Models\User;
use Carbon\Carbon;
use App\Events\RentalEventOccurred;
use Illuminate\Support\Facades\DB;

class ServerRentalService
{
    /**
     * List a server for rent in the marketplace.
     */
    public function listForRent(Server $server, float $pricePerHour): ServerRental
    {
        // Validation: Server must not have active orders
        if ($server->activeOrders()->count() > 0) {
            throw new \Exception("Cannot rent out a server with active orders.");
        }

        // Validation: Server must not be already rented or listed
        if (ServerRental::where('server_id', $server->id)->whereIn('status', ['available', 'rented'])->exists()) {
             throw new \Exception("Server is already listed or rented.");
        }

        return ServerRental::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'provider_id' => $server->rack->room->user_id,
            'server_id' => $server->id,
            'price_per_hour' => $pricePerHour,
            'status' => 'available',
        ]);
    }

    /**
     * Tenant rents an available server.
     */
    public function rentServer(User $tenant, string $rentalId): ServerRental
    {
        return DB::transaction(function () use ($tenant, $rentalId) {
            $rental = ServerRental::with(['provider', 'server'])->where('id', $rentalId)->where('status', 'available')->lockForUpdate()->firstOrFail();

            if ($rental->provider_id === $tenant->id) {
                throw new \Exception("You cannot rent your own server.");
            }

            $rental->status = 'rented';
            $rental->tenant_id = $tenant->id;
            $rental->rented_at = Carbon::now();
            $rental->save();

            // Update server tenant_id
            $server = $rental->server;
            $server->tenant_id = $tenant->id;
            $server->save();

            // Notify Provider
            if ($rental->provider) {
                \App\Models\GameLog::log(
                    $rental->provider,
                    "Your server '{$server->model_name}' has been rented by {$tenant->name}.",
                    'info',
                    'rental'
                );
                
                event(new RentalEventOccurred(
                    $rental->provider,
                    "Server Rented",
                    "A tenant has signed a contract for '{$server->model_name}'.",
                    'success'
                ));
            }

            // Notify Tenant
            event(new RentalEventOccurred(
                $tenant,
                "Rental Contract Signed",
                "You now have access to '{$server->model_name}'.",
                'success'
            ));

            return $rental;
        });
    }

    /**
     * Terminate a rental agreement.
     */
    public function terminateRental(string $rentalId, User $user, bool $applyPenalty = true): bool
    {
        return DB::transaction(function () use ($rentalId, $user, $applyPenalty) {
            $rental = ServerRental::where('id', $rentalId)->where('status', 'rented')->lockForUpdate()->firstOrFail();

            // Only provider or tenant can terminate
            if ($rental->provider_id !== $user->id && $rental->tenant_id !== $user->id) {
                throw new \Exception("Unauthorized to terminate this rental.");
            }

            $rental->status = 'terminated';
            $rental->expires_at = Carbon::now();
            $rental->save();

            $server = $rental->server;
            
            // Cancel any active orders on this server
            $activeOrders = $server->orders()->whereIn('status', ['active', 'provisioning'])->get();
            
            foreach ($activeOrders as $order) {
                // Cancel the order first (registers incident)
                $order->cancel();
                
                // Notify the tenant about the specific order failure
                if ($rental->tenant) {
                    \App\Models\GameLog::log(
                        $rental->tenant,
                        "Contract #{$order->id} failed: Server rental terminated.",
                        'error',
                        'contracts'
                    );
                }
            }

            // General notification
            if ($rental->tenant) {
                $statusMsg = $user->id === $rental->tenant_id ? "You terminated the rental." : "Provider terminated the rental.";
                \App\Models\GameLog::log(
                    $rental->tenant,
                    "Rental terminated for server '{$server->model_name}'. $statusMsg",
                    'warning', 
                    'rental'
                );

                event(new RentalEventOccurred(
                    $rental->tenant,
                    "Rental Terminated",
                    "Your lease for '{$server->model_name}' has ended.",
                    'warning'
                ));
            }

            if ($rental->provider) {
                $statusMsg = $user->id === $rental->provider_id ? "You terminated the rental." : "Tenant terminated the rental.";
                \App\Models\GameLog::log(
                    $rental->provider,
                    "Rental ended for server '{$server->model_name}'. $statusMsg",
                    'info',
                    'rental'
                );

                event(new RentalEventOccurred(
                    $rental->provider,
                    "Rental Terminated",
                    "The rental for server '{$server->model_name}' has ended.",
                    'info'
                ));

                // Apply reputation penalty to provider if they terminate early (and not due to eviction)
                if ($user->id === $rental->provider_id && $applyPenalty) {
                    if ($rental->provider->economy) {
                        $rental->provider->economy->adjustSpecializedReputation('rental_reliability', -15.0);
                        
                        \App\Models\GameLog::log(
                            $rental->provider,
                            "Reputation penalty (-15) applied for early rental termination.",
                            'warning',
                            'reputation'
                        );
                    }
                }
            }

            $server->tenant_id = null;
            $server->save();

            return true;
        });
    }

    /**
     * Process hourly payments for all active rentals.
     */
    public function processRentalPayments(): void
    {
        $activeRentals = ServerRental::where('status', 'rented')->get();

        foreach ($activeRentals as $rental) {
            $pricePerHour = (float) $rental->price_per_hour;
            // 1 tick = 15 minutes of game time = 0.25 hours
            $pricePerTick = $pricePerHour * 0.25;
            
            // Debit Tenant
            $tenantEconomy = $rental->tenant->economy;
            if ($tenantEconomy) {
                $success = $tenantEconomy->debit($pricePerTick, "Server rental payment for {$rental->server->model_name}", 'rental_expense', $rental);
                
                if (!$success) {
                    // Evict tenant for non-payment
                    try {
                        // We use the provider as the "actor" for termination to pass authorization checks
                        // But pass false for applyPenalty so provider isn't punished for evicting non-paying tenant
                        $this->terminateRental($rental->id, $rental->provider, false);
                        
                        \App\Models\GameLog::log(
                            $rental->tenant,
                            "Rental terminated due to insufficient funds: {$rental->server->model_name}",
                            'error',
                            'rental'
                        );

                        event(new RentalEventOccurred(
                            $rental->tenant,
                            "Eviction: Insufficient Funds",
                            "Contract for '{$rental->server->model_name}' terminated - payment failed.",
                            'error'
                        ));
                        
                        \App\Models\GameLog::log(
                            $rental->provider,
                            "Rental terminated due to tenant non-payment: {$rental->server->model_name}",
                            'warning',
                            'rental'
                        );

                        event(new RentalEventOccurred(
                            $rental->provider,
                            "Rental Payment Failed",
                            "The tenant for '{$rental->server->model_name}' could not pay. Server has been reclaimed.",
                            'warning'
                        ));
                    } catch (\Exception $e) {
                        // Log internal error but don't crash loop
                        report($e);
                    }
                    
                    continue; // Skip credit to provider
                }
            }

            // Credit Provider
            $providerEconomy = $rental->provider->economy;
            if ($providerEconomy) {
                $providerEconomy->credit($pricePerTick, "Server rental income for {$rental->server->model_name}", 'rental_income', $rental);
            }
        }
    }

    /**
     * Simulate NPCs renting player servers from the marketplace.
     */
    public function npcRentAvailableServers(): void
    {
        $availableRentals = ServerRental::with(['server', 'provider.economy'])
            ->where('status', 'available')
            ->get();

        $companyPool = [
            'Cyberdyne Systems', 'Tyrell Corp', 'Weyland-Yutani', 'Omni Consumer Products',
            'Blue Sun Corp', 'Soylent Corp', 'Initech', 'Hooli', 'Pied Piper',
            'E Corp', 'Allsafe Security', 'Rekall Inc.', 'Tessier-Ashpool',
            'Nakatomi Trading', 'Stark Industries', 'Wayne Enterprises'
        ];

        foreach ($availableRentals as $rental) {
            $server = $rental->server;
            if (!$server) continue;

            // Simple heuristic value of server per hour based on specs
            // Cores: $0.5/ea, RAM: $0.1/GB, Storage: $1.0/TB
            $estimatedValue = ($server->cpu_cores * 0.5) + ($server->ram_gb * 0.1) + ($server->storage_tb * 1.5);
            
            // Adjust based on provider reputation
            $providerRep = $rental->provider->economy ? $rental->provider->economy->getSpecializedReputation('rental_reliability') : 50;
            $repModifier = max(0.4, $providerRep / 60.0); // 1.0 at 60 rep

            $adjustedValue = $estimatedValue * $repModifier;
            $price = (float) $rental->price_per_hour;

            if ($price <= 0) continue;

            $ratio = $adjustedValue / $price; // > 1 is a good deal

            // Base chance 2% per tick, boosted by "deal quality"
            // If price = value, chance is 2%. If price = 1/2 value, chance is 4%
            $chance = 2 * min(10, $ratio);

            if (rand(1, 1000) <= ($chance * 10)) {
                $companyName = $companyPool[array_rand($companyPool)];
                $email = strtolower(str_replace(' ', '.', $companyName)) . '@npc.market';

                $npc = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $companyName . " Agent",
                        'company_name' => $companyName,
                        'password' => bcrypt(\Illuminate\Support\Str::random(16)),
                    ]
                );

                try {
                    $this->rentServer($npc, $rental->id);
                } catch (\Exception $e) {
                    report($e);
                }
            }
        }
    }
}
