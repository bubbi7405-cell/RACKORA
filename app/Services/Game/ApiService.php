<?php

namespace App\Services\Game;

use App\Models\ApiEndpoint;
use App\Models\User;
use App\Models\Server;
use Illuminate\Support\Collection;

class ApiService
{
    /**
     * Process virtual API traffic for all users
     */
    public function tick(User $user): void
    {
        $endpoints = ApiEndpoint::where('user_id', $user->id)->get();
        $totalRevenue = 0;

        foreach ($endpoints as $endpoint) {
            $this->simulateTraffic($endpoint, $totalRevenue);
        }

        if ($totalRevenue > 0) {
            $user->economy->credit($totalRevenue, "API Traffic Revenue", 'income');
        }
    }

    /**
     * Simulate traffic and performance for a single endpoint
     */
    private function simulateTraffic(ApiEndpoint $endpoint, float &$totalRevenue): void
    {
        $server = $endpoint->server;
        if (!$server || $server->status !== 'online') {
            $endpoint->status = 'offline';
            $endpoint->uptime = max(0, $endpoint->uptime - 5);
            $endpoint->rpm = 0;
            $endpoint->save();
            return;
        }

        // Base traffic generation (randomized based on max_rpm)
        $targetRpm = rand((int)($endpoint->max_rpm * 0.7), $endpoint->max_rpm);
        $endpoint->rpm = $targetRpm;

        // Performance impact: CPU/RAM usage on server
        // Every 100 RPM costs 5% CPU/RAM
        $loadFactor = ($endpoint->rpm / 100) * 5;
        // In a real simulation we'd add this load to the server model
        // For now, let's just affect the endpoint metrics

        // Server health and overload affects latency
        $baseLatency = $endpoint->latency_ms;
        if ($server->cpu_usage > 90 || $server->ram_usage > 90) {
            $endpoint->latency_ms = $baseLatency * 2.5;
            $endpoint->status = 'rate_limited';
            $endpoint->uptime = max(0, $endpoint->uptime - 1);
        } else {
            $endpoint->latency_ms = max(5, $baseLatency * 0.95 + 2); // Stabilize
            $endpoint->status = 'online';
            $endpoint->uptime = min(100, $endpoint->uptime + 0.5);
        }

        // Calculate Revenue: (RPM * revenue_per_1k / 1000) * uptime_factor
        $uptimeFactor = $endpoint->uptime / 100;
        $earned = ($endpoint->rpm * ($endpoint->revenue_per_1k_req / 1000)) * $uptimeFactor;
        
        $totalRevenue += $earned;
        $endpoint->save();
    }

    /**
     * Create a new API endpoint
     */
    public function createEndpoint(User $user, array $data): ApiEndpoint
    {
        return ApiEndpoint::create([
            'user_id' => $user->id,
            'server_id' => $data['server_id'],
            'path' => $data['path'],
            'method' => $data['method'] ?? 'GET',
            'max_rpm' => $data['max_rpm'] ?? 100,
            'revenue_per_1k_req' => $this->calculateInitialRevenue($data),
            'config' => [
                'complexity' => $data['complexity'] ?? 'low',
                'security' => $data['security'] ?? 'standard',
            ]
        ]);
    }

    private function calculateInitialRevenue(array $data): float
    {
        $base = 0.05; // $0.05 per 1k requests
        $complexity = $data['complexity'] ?? 'low';
        
        if ($complexity === 'medium') $base *= 2;
        if ($complexity === 'high') $base *= 5;
        
        return $base;
    }
}
