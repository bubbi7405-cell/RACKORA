<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Enums\ServerStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServerController extends Controller
{
    /**
     * Get detailed logs and performance metrics for a specific server (simulated)
     */
    public function details(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('id', $id)
            ->firstOrFail();

        // Generate simulated CPU/RAM history
        $metrics = [];
        $now = now();
        for ($i = 20; $i >= 0; $i--) {
            $time = $now->copy()->subMinutes($i);
            
            // Usage depends on server load (active orders) AND time of day
            $hour = (int)$time->format('H');
            $hourFactor = 0.5 + (0.5 * sin((($hour - 10) / 24) * 2 * pi())); // Sine wave peaking around 16:00, low at 04:00
            
            $baseCpu = $server->activeOrders()->count() * 15 * $hourFactor;
            $baseRam = $server->activeOrders()->count() * 10 * $hourFactor;
            
            $metrics[] = [
                'time' => $time->toIso8601String(),
                'cpu' => min(100, max(2, $baseCpu + rand(-5, 5))),
                'ram' => min(100, max(5, $baseRam + rand(-2, 2))),
                'bandwidth' => rand(10, 100),
            ];
        }

        // Generate simulated logs
        $logs = [];
        $logTemplates = [
            'INFO: System heartbeat check OK',
            'INFO: Process scheduler optimized (PID ' . rand(1000, 9999) . ')',
            'DEBUG: Bandwidth interface eth0 polling...',
            'INFO: SSL Handshake successful for customer node',
        ];
        
        if ($server->status === ServerStatus::DEGRADED || $server->status === ServerStatus::DAMAGED) {
            $logTemplates[] = 'WARNING: Unhandled exception in kernel module';
            $logTemplates[] = 'ERROR: I/O Wait exceeded threshold (800ms)';
            $logTemplates[] = 'CRITICAL: Hardware interrupt fault detected';
        }

        for ($i = 0; $i < 15; $i++) {
            $logs[] = [
                'timestamp' => now()->subMinutes(rand(0, 60))->toIso8601String(),
                'message' => $logTemplates[array_rand($logTemplates)],
                'level' => Str::contains($logTemplates[array_rand($logTemplates)], 'ERROR') ? 'error' : (Str::contains($logTemplates[array_rand($logTemplates)], 'WARNING') ? 'warn' : 'info')
            ];
        }
        
        // Sort logs by time
        usort($logs, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return response()->json([
            'success' => true,
            'data' => [
                'server' => $server->toGameState(),
                'metrics' => $metrics,
                'logs' => $logs,
            ]
        ]);
    }

    /**
     * Run diagnostics on a server to identify hidden faults
     */
    public function diagnose(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('id', $id)
            ->firstOrFail();

        if ($server->is_diagnosed) {
             return response()->json([
                'success' => true,
                'message' => 'Server already diagnosed.',
                'fault' => $server->current_fault ?: 'No hardware faults detected.'
            ]);
        }

        // Apply diagnosis
        $server->is_diagnosed = true;
        
        // If server is damaged but has no fault, assign one
        if (!$server->current_fault && ($server->status === ServerStatus::DAMAGED || $server->status === ServerStatus::DEGRADED)) {
            $faults = ['Faulty PSU', 'SSD Bit Rot', 'Memory Bank Failure', 'Thermal Paste Decay', 'Fragmented File System'];
            $server->current_fault = $faults[array_rand($faults)];
        }
        
        $server->save();

        return response()->json([
            'success' => true,
            'message' => 'Diagnostics complete.',
            'fault' => $server->current_fault ?: 'No hardware faults detected.',
            'server' => $server->toGameState(),
        ]);
    }

    /**
     * Repair a server (maintenance action) - Moved from RoomController
     */
    public function repair(Request $request): JsonResponse
    {
        $request->validate([
            'server_id' => 'required|exists:servers,id',
        ]);

        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('id', $request->server_id)
            ->firstOrFail();

        if (!in_array($server->status, [ServerStatus::DAMAGED, ServerStatus::DEGRADED])) {
            return response()->json(['success' => false, 'error' => 'This server does not need repair.'], 400);
        }

        // Base Repair cost: 20% of purchase cost
        $repairCost = $server->purchase_cost * 0.2;
        
        // 50% discount if diagnosed
        if ($server->is_diagnosed) {
            $repairCost *= 0.5;
        }

        if (!$user->economy->canAfford($repairCost)) {
            return response()->json([
                'success' => false,
                'error' => "Repair costs \$" . number_format($repairCost, 2) . ". Insufficient funds.",
            ], 400);
        }

        return DB::transaction(function () use ($user, $server, $repairCost) {
            $user->economy->debit($repairCost, "Server repair: {$server->model_name}", 'maintenance', $server);

            $server->health = 100;
            $server->status = ServerStatus::ONLINE;
            $server->current_fault = null;
            $server->is_diagnosed = false;
            $server->save();

            // Award XP
            $user->economy->addExperience(10);

            return response()->json([
                'success' => true,
                'data' => $server->fresh()->toGameState(),
                'message' => "Server repaired! Total cost: \$" . number_format($repairCost, 2) . ($server->is_diagnosed ? " (Diagnosed Discount Applied)" : ""),
            ]);
        });
    }

    /**
     * Start a scheduled maintenance window for an operational server
     */
    public function scheduleMaintenance(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('id', $id)
            ->firstOrFail();

        if ($server->status === ServerStatus::MAINTENANCE) {
            return response()->json(['success' => false, 'error' => 'Server is already in maintenance.'], 400);
        }

        if (!$server->status->isOperational()) {
             return response()->json(['success' => false, 'error' => 'Preemptive maintenance can only be started for operational servers.'], 400);
        }

        // Proactive maintenance is cheaper (5% of purchase cost)
        $maintenanceCost = $server->purchase_cost * 0.05;
        
        if (!$user->economy->canAfford($maintenanceCost)) {
             return response()->json(['success' => false, 'error' => "Maintenance starts at \$" . number_format($maintenanceCost, 2) . ". Insufficient funds."], 400);
        }
        
        return DB::transaction(function () use ($user, $server, $maintenanceCost) {
            $user->economy->debit($maintenanceCost, "Planned maintenance: {$server->model_name}", 'maintenance', $server);
            
            $server->status = ServerStatus::MAINTENANCE;
            $server->save();
            
            return response()->json([
                'success' => true,
                'data' => $server->toGameState(),
                'message' => 'Maintenance window started. Health will restore over time.'
            ]);
        });
    }
}
