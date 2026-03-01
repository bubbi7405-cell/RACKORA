<?php

namespace App\Services\Game;

use App\Models\PrivateNetwork;
use App\Models\PrivateNetworkFirewallRule;
use App\Models\Server;
use App\Models\User;
use App\Models\GameLog;

class PrivateNetworkService
{
    public function getUserNetworks(User $user): array
    {
        return $user->privateNetworks()
            ->withCount('servers')
            ->with('firewallRules')
            ->get()
            ->map(fn($n) => $n->toGameState())
            ->toArray();
    }

    public function createNetwork(User $user, string $name, string $cidr = '10.0.0.0/24', ?int $vlanTag = null): PrivateNetwork
    {
        if ($user->privateNetworks()->count() >= 5) {
             throw new \Exception("Maximum private networks limit reached.");
        }
        
        return PrivateNetwork::create([
            'user_id' => $user->id,
            'name' => $name,
            'cidr' => $cidr,
            'vlan_tag' => $vlanTag ?? rand(100, 4000),
        ]);
    }

    public function deleteNetwork(User $user, string $networkId): void
    {
        $network = PrivateNetwork::where('user_id', $user->id)->where('id', $networkId)->firstOrFail();
        
        // Detach all servers
        foreach ($network->servers as $server) {
            $server->private_network_id = null;
            $server->private_ip_address = null;
            $server->save();
        }
        
        $network->delete();
    }

    public function attachServer(User $user, string $networkId, string $serverId): void
    {
        $network = PrivateNetwork::where('user_id', $user->id)->where('id', $networkId)->firstOrFail();
        $server = Server::where(function($q) use ($user) {
            $q->whereHas('rack.room', fn($inner) => $inner->where('user_id', $user->id))
              ->orWhere('tenant_id', $user->id);
        })->where('id', $serverId)->firstOrFail();

        if ($server->private_network_id) {
            throw new \Exception("Server is already attached to a private network.");
        }

        $server->private_network_id = $network->id;
        $server->private_ip_address = $this->allocateIp($network);
        $server->save();
    }
    
    public function detachServer(User $user, string $serverId): void
    {
        $server = Server::where(function($q) use ($user) {
            $q->whereHas('rack.room', fn($inner) => $inner->where('user_id', $user->id))
              ->orWhere('tenant_id', $user->id);
        })->where('id', $serverId)->firstOrFail();
             
        $server->private_network_id = null;
        $server->private_ip_address = null;
        $server->save();
    }

    private function allocateIp(PrivateNetwork $network): string
    {
         $parts = explode('/', $network->cidr);
         $base = $parts[0] ?? '10.0.0.0';
         $prefix = substr($base, 0, strrpos($base, '.'));
         
         $usedIps = $network->servers()->pluck('private_ip_address')->toArray();
         
         for ($i = 2; $i < 255; $i++) {
             $ip = "{$prefix}.{$i}";
             if (!in_array($ip, $usedIps)) {
                 return $ip;
             }
         }
         
         throw new \Exception("Subnet exhausted.");
    }

    public function addFirewallRule(User $user, string $networkId, array $data): PrivateNetworkFirewallRule
    {
        $network = PrivateNetwork::where('user_id', $user->id)->where('id', $networkId)->firstOrFail();
        
        return $network->firewallRules()->create([
            'type' => $data['type'],
            'protocol' => $data['protocol'],
            'port_range' => $data['port_range'] ?? null,
            'source_cidr' => $data['source_cidr'] ?? '0.0.0.0/0',
            'priority' => $data['priority'] ?? 100,
            'description' => $data['description'] ?? null,
        ]);
    }

    public function deleteFirewallRule(User $user, string $ruleId): void
    {
        $rule = PrivateNetworkFirewallRule::whereHas('network', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('id', $ruleId)->firstOrFail();
        
        $rule->delete();
    }

    /**
     * Check if traffic is allowed through a private network's firewall.
     */
    public function isTrafficAllowed(PrivateNetwork $network, string $protocol, ?int $port = null, string $sourceIp = '0.0.0.0'): bool
    {
        // Default policy: ALLOW ALL if no rules exist
        $rules = $network->firewallRules()->orderBy('priority', 'asc')->get();
        if ($rules->isEmpty()) {
            return true;
        }

        foreach ($rules as $rule) {
            if ($this->ruleMatches($rule, $protocol, $port, $sourceIp)) {
                return $rule->type === 'ALLOW';
            }
        }

        // Default if rules exist but none match
        return false;
    }

    private function ruleMatches(PrivateNetworkFirewallRule $rule, string $protocol, ?int $port, string $sourceIp): bool
    {
        // Protocol match
        if ($rule->protocol !== 'ANY' && $rule->protocol !== $protocol) {
            return false;
        }

        // Port match
        if ($rule->port_range && $port !== null) {
            if (!$this->portMatches($rule->port_range, $port)) {
                return false;
            }
        }

        // Source CIDR match
        if ($rule->source_cidr && $rule->source_cidr !== '0.0.0.0/0') {
            if (!$this->ipInCidr($sourceIp, $rule->source_cidr)) {
                return false;
            }
        }

        return true;
    }

    private function portMatches(string $range, int $port): bool
    {
        if (str_contains($range, '-')) {
            $parts = explode('-', $range);
            if (count($parts) === 2) {
                return $port >= (int)$parts[0] && $port <= (int)$parts[1];
            }
        }
        return (int)$range === $port;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        if ($cidr === '0.0.0.0/0') return true;
        
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        
        [$subnet, $mask] = explode('/', $cidr);
        
        // Simple octet matching for simulation
        $ipParts = explode('.', $ip);
        $subnetParts = explode('.', $subnet);
        
        $mask = (int)$mask;
        $octets = floor($mask / 8);
        
        for ($i = 0; $i < $octets; $i++) {
            if (($ipParts[$i] ?? null) !== ($subnetParts[$i] ?? null)) return false;
        }
        
        return true;
    }

    /**
     * Simulation tick for private networks.
     * Simulates background traffic between servers and enforces firewall rules.
     */
    public function tick(User $user): void
    {
        $networks = PrivateNetwork::where('user_id', $user->id)->with('servers')->get();
        
        foreach ($networks as $network) {
            $servers = $network->servers;
            if ($servers->count() < 1) continue;

            $allowed = 0;
            $denied = 0;

            // Simulate 5-10 "background packets" per network per tick
            $packetCount = rand(5, 10);
            for ($i = 0; $i < $packetCount; $i++) {
                $protocol = ['TCP', 'UDP', 'ICMP'][rand(0, 2)];
                $port = $protocol !== 'ICMP' ? [80, 443, 22, 3306, 5432, rand(1024, 65535)][rand(0, 5)] : null;
                
                // Random source IP from the same subnet (simulating another server or authorized peer)
                $parts = explode('.', explode('/', $network->cidr)[0]);
                $sourceIp = "{$parts[0]}.{$parts[1]}.{$parts[2]}." . rand(2, 254);

                if ($this->isTrafficAllowed($network, $protocol, $port, $sourceIp)) {
                    $allowed++;
                } else {
                    $denied++;
                }
            }

            $network->traffic_allowed_count = $allowed;
            $network->traffic_denied_count = $denied;
            $network->save();
        }
    }

    /**
     * Calculate a cumulative DDoS mitigation bonus based on firewall thoroughness.
     * Bonus: 0.05 per network with active rules (Max 0.25)
     */
    public function getFirewallMitigationBonus(User $user): float
    {
        $networks = PrivateNetwork::where('user_id', $user->id)->get();
        if ($networks->isEmpty()) return 0.0;
        
        $bonus = 0.0;
        foreach ($networks as $net) {
            // If rules exist, it means user is proactively managing traffic
            if ($net->firewallRules()->exists()) {
                $bonus += 0.05;
            }
        }
        
        return min(0.25, round($bonus, 2));
    }
}
