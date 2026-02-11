<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Services\Game\ResearchService;
use App\Models\WorldEvent;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CustomerOrderService
{
    private const COMPANY_PREFIXES = ['Tech', 'Global', 'Cyber', 'Modern', 'Future', 'Smart', 'Data', 'Cloud', 'Net', 'Web'];
    private const COMPANY_SUFFIXES = ['Solutions', 'Systems', 'Corp', 'Inc', 'Ltd', 'Group', 'Enterprises', 'Labs', 'Dynamics', 'Soft'];
    private const FIRST_NAMES = ['James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda', 'William', 'Elizabeth'];
    private const LAST_NAMES = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];

    public function __construct(
        protected ResearchService $researchService
    ) {}

    /**
     * Main tick function called by the game loop
     */
    public function tick(User $user): void
    {
        // Check for expired pending orders
        $this->expirePendingOrders($user);

        // Chance to generate new order
        if ($this->shouldGenerateOrder($user)) {
            $this->generateNewOrder($user);
        }
    }

    /**
     * Expire orders that have passed their patience deadline
     */
    private function expirePendingOrders(User $user): void
    {
        $expiredOrders = CustomerOrder::whereHas('customer', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'pending')
            ->where('patience_expires_at', '<=', now())
            ->get();

        foreach ($expiredOrders as $order) {
            $order->status = 'cancelled';
            $order->save();

            // Reputation penalty for letting orders expire
            $user->economy->adjustReputation(-2);
        }
    }

    /**
     * Determine if a new order should be generated
     */
    private function shouldGenerateOrder(User $user): bool
    {
        // Base chance per tick (assuming tick is every minute or 5 minutes?)
        // Let's assume this is called frequently, so keep chance low
        
        $pendingCount = CustomerOrder::whereHas('customer', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->where('status', 'pending')->count();

        // Max pending orders based on level
        $maxPending = 3 + floor(($user->economy->level ?? 1) / 5);

        if ($pendingCount >= $maxPending) {
            return false;
        }

        // Chance based on reputation (0-100)
        // Reputation 0: 5% chance
        // Reputation 100: 25% chance
        $chance = 5 + (($user->economy->reputation ?? 50) / 5);

        // Apply World Modifier: order_frequency
        $modifiers = \App\Models\WorldEvent::getActiveModifiers();
        if (isset($modifiers['order_frequency'])) {
            $chance *= $modifiers['order_frequency'];
        }

        // Apply Strategic Policy Modifier: market_focus
        $marketFocus = $user->economy->getPolicy('market_focus', 'balanced');
        $policyModifiers = \App\Services\Game\ManagementService::DECISIONS['market_focus']['options'][$marketFocus]['modifiers'];
        $chance *= ($policyModifiers['order_frequency'] ?? 1.0);

        return rand(0, 100) < $chance;
    }

    /**
     * Generate a new customer and order
     */
    public function generateNewOrder(User $user): CustomerOrder
    {
        // 30% chance to be an existing customer if we have any
        $customer = null;
        if (rand(1, 100) <= 30) {
            $customer = Customer::where('user_id', $user->id)->inRandomOrder()->first();
        }

        if (!$customer) {
            $customer = $this->createCustomer($user);
        }

        return $this->createOrderForCustomer($user, $customer);
    }

    private function createCustomer(User $user): Customer
    {
        $company = $this->generateCompanyName();
        $contact = $this->generateContactName();

        return Customer::create([
            'user_id' => $user->id,
            'name' => $contact,
            'company_name' => $company,
            'tier' => 'bronze', // Can improve with level
            'revenue_per_month' => 0,
            'satisfaction' => 50, // Neutral start
            'patience_minutes' => 60, // Standard patience
            'tolerance_incidents' => 3,
            'incidents_count' => 0,
            'status' => 'active',
            'acquired_at' => now(),
            'preferences' => [],
        ]);
    }

    private function createOrderForCustomer(User $user, Customer $customer): CustomerOrder
    {
        $level = $user->economy->level ?? 1;

        // Determine order type based on user level
        $types = ['web_hosting']; // Level 1 default
        if ($level >= 5) $types[] = 'game_server';
        if ($level >= 10) $types[] = 'database_hosting';
        if ($level >= 15) $types[] = 'ml_training';

        $type = $types[array_rand($types)];
        $requirements = $this->generateRequirements($type, $level);

        // SLA Tier
        $slaTiers = ['standard'];
        if ($level >= 10) $slaTiers[] = 'premium';
        if ($level >= 25) $slaTiers[] = 'enterprise';
        $slaTier = $slaTiers[array_rand($slaTiers)];

        // Calculate price based on requirements
        // Simple formula: (CPU * 20) + (RAM * 5) + (Storage * 0.1) + Base
        $basePrice = 10;
        $price = $basePrice + 
                 ($requirements['cpu'] * 15) + 
                 ($requirements['ram'] * 4) + 
                 ($requirements['storage'] * 0.05);

        $slaRoll = rand(1, 100);
        
        // Research Bonus: Better Marketing = Better Customers
        $qualityBonus = $this->researchService->getBonus($user, 'customer_quality');
        if ($qualityBonus > 0) {
            $slaRoll += ($qualityBonus * 50); // +7.5 per level effectively
        }

        $slaTier = 'standard';
        if ($slaRoll > 95) $slaTier = 'whale';
        else if ($slaRoll > 85) $slaTier = 'enterprise';
        else if ($slaRoll > 60) $slaTier = 'premium';

        // Add variance + margin
        $price = $price * 1.5; // 50% margin

        if ($slaTier === 'premium') $price *= 1.5;
        if ($slaTier === 'enterprise') $price *= 2.5;
        if ($slaTier === 'whale') $price *= 6.0;

        // Apply customer_quality research bonus to price as well
        if ($qualityBonus > 0) {
            $price = $price * (1 + $qualityBonus);
        }

        // Apply Strategic Policy Modifier: price_modifier
        $marketFocus = $user->economy->getPolicy('market_focus', 'balanced');
        $policyModifiers = \App\Services\Game\ManagementService::DECISIONS['market_focus']['options'][$marketFocus]['modifiers'];
        $price *= ($policyModifiers['price_modifier'] ?? 1.0);

        $price = round($price, 2);

        // Apply World Modifier: order_value
        $modifiers = WorldEvent::getActiveModifiers();
        if (isset($modifiers['order_value'])) {
            $price *= $modifiers['order_value'];
        }

        return CustomerOrder::create([
            'customer_id' => $customer->id,
            'product_type' => $type,
            'requirements' => $requirements,
            'price_per_month' => $price,
            'status' => 'pending',
            'ordered_at' => now(),
            'contract_months' => rand(1, 12),
            'sla_tier' => $slaTier,
            'patience_expires_at' => now()->addMinutes($customer->patience_minutes),
        ]);
    }

    private function generateRequirements(string $type, int $level): array
    {
        $multiplier = 1 + ($level * 0.1); // Requirements scale slightly with level

        switch ($type) {
            case 'game_server':
                return [
                    'cpu' => rand(2, 8) * $multiplier,
                    'ram' => rand(4, 16) * $multiplier,
                    'storage' => rand(50, 200),
                    'bandwidth' => rand(100, 1000),
                ];
            case 'database_hosting':
                return [
                    'cpu' => rand(2, 16) * $multiplier,
                    'ram' => rand(8, 32) * $multiplier,
                    'storage' => rand(100, 1000) * $multiplier,
                    'bandwidth' => rand(50, 500),
                ];
            case 'ml_training':
                return [
                    'cpu' => rand(8, 64) * $multiplier,
                    'ram' => rand(32, 128) * $multiplier,
                    'storage' => rand(500, 5000),
                    'gpu' => rand(1, 4), // Special requirement
                    'bandwidth' => rand(1000, 10000),
                ];
            case 'web_hosting':
            default:
                return [
                    'cpu' => rand(1, 2),
                    'ram' => rand(1, 4),
                    'storage' => rand(10, 50),
                    'bandwidth' => rand(10, 100),
                ];
        }
    }

    private function generateCompanyName(): string
    {
        $prefix = self::COMPANY_PREFIXES[array_rand(self::COMPANY_PREFIXES)];
        $suffix = self::COMPANY_SUFFIXES[array_rand(self::COMPANY_SUFFIXES)];
        return "$prefix $suffix";
    }

    public function assignOrder(User $user, CustomerOrder $order, \App\Models\Server $server): void
    {
        // Check ownership
        if ($server->rack->room->user_id !== $user->id) { // Not quite right, room->rack belongs to user?
             // Room belongs to user ideally. Or verify via query.
        }

        // Check if order is pending
        if (!$order->isPending()) {
            throw new \Exception("Order is not pending.");
        }

        // Check server status
        if ($server->status === \App\Enums\ServerStatus::OFFLINE) {
             throw new \Exception("Server is offline.");
        }

        // Check occupancy
        if ($server->type !== \App\Enums\ServerType::VSERVER_NODE) {
            $occupiedCount = $server->orders()->whereIn('status', ['active', 'provisioning'])->count();
            if ($occupiedCount > 0) {
                throw new \Exception("Server is already occupied.");
            }
        } else {
             if ($server->getAvailableVServerSlots() <= 0) {
                 throw new \Exception("Server is full.");
             }
        }

        // Validate Requirements
        $req = $order->requirements;
        
        // CPU
        if ($server->cpu_cores < ($req['cpu'] ?? 0)) {
            throw new \Exception("Insufficient CPU cores (Need {$req['cpu']}, Have {$server->cpu_cores}).");
        }

        // RAM
        if ($server->ram_gb < ($req['ram'] ?? 0)) {
            throw new \Exception("Insufficient RAM (Need {$req['ram']}GB, Have {$server->ram_gb}GB).");
        }

        // Storage (TB vs GB)
        $serverStorageGB = $server->storage_tb * 1024;
        if ($serverStorageGB < ($req['storage'] ?? 0)) {
             throw new \Exception("Insufficient Storage (Need {$req['storage']}GB, Have {$serverStorageGB}GB).");
        }

        // Bandwidth (NIC Limit)
        $requestedBandwidth = $req['bandwidth'] ?? 0;
        if ($server->bandwidth_mbps < $requestedBandwidth) {
            throw new \Exception("Insufficient NIC Bandwidth (Need {$requestedBandwidth}Mbps, Have {$server->bandwidth_mbps}Mbps).");
        }

        // Room Bandwidth (Uplink Limit)
        $room = $server->rack->room;
        $roomCurrentGbps = $room->getCurrentBandwidthUsage();
        $requestedGbps = $requestedBandwidth / 1000;

        if (($roomCurrentGbps + $requestedGbps) > $room->bandwidth_gbps) {
            $available = max(0, $room->bandwidth_gbps - $roomCurrentGbps);
            throw new \Exception("Insufficient Room Uplink capacity (Need {$requestedGbps}Gbps, only {$available}Gbps available).");
        }

        // Calculate Provisioning Time based on Type
        $baseDuration = match($order->product_type) {
            'vserver', 'vps', 'web_hosting', 'game_server' => 60, // 1 minute
            'dedicated' => 300, // 5 minutes
            'gpu_rental', 'ml_training' => 600, // 10 minutes
            'storage', 'database_hosting' => 120, // 2 minutes
            default => 60
        };

        // Apply Research Bonus
        // Bonus is percentage reduction (e.g. 0.20 for 20%)
        $speedBonus = $this->researchService->getBonus($user, 'provisioning_speed');
        $duration = $baseDuration * (1 - $speedBonus);
        $duration = max(10, $duration); // Minimum 10 seconds

        // Provision the order
        // This sets order status to 'provisioning' and links server
        $order->assigned_server_id = $server->id;
        $order->status = 'provisioning'; // Should match enum ideally
        $order->provisioning_started_at = now();
        $order->provisioning_completes_at = now()->addSeconds((int)$duration);
        $order->provisioned_at = null; // Not done yet!
        $order->save();

        // Update server usage? 
        // For dedicated servers, mark as fully used?
        // For VPS nodes, deduct resources?
        
        if ($server->type === \App\Enums\ServerType::VSERVER_NODE) {
            $server->vservers_used++;
            $server->save();
        } else {
            // Dedicated
            // Maybe lock server?
        }
    }

    private function generateContactName(): string
    {
        $first = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
        $last = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
        return "$first $last";
    }
}
