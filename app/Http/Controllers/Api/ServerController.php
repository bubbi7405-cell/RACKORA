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
        $server = Server::where(function($q) use ($user) {
            $q->whereHas('rack.room', fn($inner) => $inner->where('user_id', $user->id))
              ->orWhere('tenant_id', $user->id);
        })->where('id', $id)->firstOrFail();
        
        // JIT Sync states before returning details
        $server->syncTaskStates();

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

        $components = [];
        if ($server->specs['is_custom'] ?? false) {
            $componentIds = array_merge(
                [$server->specs['motherboard_id'] ?? null],
                $server->specs['cpu_ids'] ?? [],
                $server->specs['ram_ids'] ?? [],
                $server->specs['storage_ids'] ?? []
            );
            
            $components = \App\Models\UserComponent::whereIn('id', array_filter($componentIds))
                ->get()
                ->map(fn($c) => $c->toGameState());
        }

        return response()->json([
            'success' => true,
            'data' => [
                'server' => $server->toGameState(),
                'metrics' => $metrics,
                'logs' => $logs,
                'components' => $components,
            ]
        ]);
    }

    /**
     * Run diagnostics on a server to identify hidden faults
     */
    public function diagnose(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $server = Server::where(function($q) use ($user) {
            $q->whereHas('rack.room', fn($inner) => $inner->where('user_id', $user->id))
              ->orWhere('tenant_id', $user->id);
        })->where('id', $id)->firstOrFail();

        // If server is damaged but has no fault, assign one
        if (!$server->current_fault && ($server->status === ServerStatus::DAMAGED || $server->status === ServerStatus::DEGRADED)) {
            $faults = [
                'Faulty PSU' => 'probe',
                'SSD Bit Rot' => 'signal',
                'Memory Bank Failure' => 'probe',
                'Thermal Paste Decay' => 'probe',
                'Fragmented File System' => 'logs',
                'Kernel Panic Loop' => 'logs',
                'I/O Bus Congestion' => 'signal',
                'RGB Controller Short' => 'probe'
            ];
            $faultName = array_rand($faults);
            $server->current_fault = $faultName;
        }

        // Map fault to interaction type
        $interactionMap = [
            'Faulty PSU' => 'probe',
            'SSD Bit Rot' => 'signal',
            'Memory Bank Failure' => 'probe',
            'Thermal Paste Decay' => 'probe',
            'Fragmented File System' => 'logs',
            'Kernel Panic Loop' => 'logs',
            'I/O Bus Congestion' => 'signal',
            'RGB Controller Short' => 'probe'
        ];
        
        $type = $interactionMap[$server->current_fault] ?? 'signal';

        if ($server->is_diagnosed) {
             return response()->json([
                'success' => true,
                'message' => 'Server already diagnosed.',
                'fault' => $server->current_fault ?: 'No hardware faults detected.',
                'interaction_type' => $type
            ]);
        }

        // We return the task info. The frontend must complete it to set is_diagnosed=true
        // Actually, the current flow sets is_diagnosed=true in the AFTER the puzzle.
        // So here we just provide the info needed for the puzzle.

        // FEATURE 197: Silent Outage Detection via Deep-Scan
        $silentOutageDetected = false;
        $specs = $server->specs ?? [];
        if ($specs['silent_outage'] ?? false) {
            $silentOutageDetected = true;
            unset($specs['silent_outage']);
            unset($specs['silent_outage_started_at']);
            $server->specs = $specs;
            $server->save();
            
            \App\Models\GameLog::log($user, "🔍 DEEP-SCAN: Silent software regression detected and patched on {$server->model_name}! Revenue leak stopped.", 'success', 'server');
        }

        return response()->json([
            'success' => true,
            'message' => $silentOutageDetected 
                ? 'SILENT OUTAGE DETECTED AND FIXED! Revenue leak has been stopped.'
                : 'Diagnostics initiated.',
            'interaction_type' => $type,
            'fault_hint' => $server->current_fault ?: 'Healthy',
            'silent_outage_fixed' => $silentOutageDetected,
            'server' => $server->toGameState(),
        ]);
    }

    /**
     * Confirm diagnostic success (callback from frontend puzzle)
     */
    public function completeDiagnosis(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $server = Server::where(function($q) use ($user) {
            $q->whereHas('rack.room', fn($inner) => $inner->where('user_id', $user->id))
              ->orWhere('tenant_id', $user->id);
        })->where('id', $id)->firstOrFail();

        if ($server->is_diagnosed) {
            return response()->json(['success' => true]);
        }

        $server->is_diagnosed = true;
        $server->save();

        $server->addMaintenanceLogEntry('diagnose', 'System diagnostics completed. Isolation of "' . ($server->current_fault ?: 'No faults') . '" successful.');

        return response()->json([
            'success' => true,
            'message' => 'Diagnosis confirmed.',
            'server' => $server->toGameState()
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
        $server = Server::where(function($q) use ($user) {
            $q->whereHas('rack.room', fn($inner) => $inner->where('user_id', $user->id))
              ->orWhere('tenant_id', $user->id);
        })
            ->where('id', $request->server_id)
            ->firstOrFail();

        if (!in_array($server->status, [ServerStatus::DAMAGED, ServerStatus::DEGRADED])) {
            return response()->json(['success' => false, 'error' => 'Dieser Server benötigt keine Reparatur.'], 400);
        }

        // Base Repair cost: 20% of purchase cost
        $repairCost = $server->purchase_cost * 0.2;
        
        // 50% discount if diagnosed
        if ($server->is_diagnosed) {
            $repairCost *= 0.5;
        }

        // Apply SysAdmin specialization bonus: "repair_cost_reduction" (Hardware Pro)
        $empService = app(\App\Services\Game\EmployeeService::class);
        $costBonus = $empService->getAggregatedBonus($user, 'repair_cost_reduction');
        if ($costBonus > 0) {
            $repairCost *= (1.0 - min(0.8, $costBonus));
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
            $server->last_maintenance_at = now();
            $server->save();

            $server->addMaintenanceLogEntry('repair', "Full hardware restoration: {$server->model_name}", $repairCost);

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
        $request->validate([
            'delay_minutes' => 'nullable|integer|min:0|max:60',
        ]);

        $user = $request->user();
        $server = Server::where(function($q) use ($user) {
            $q->whereHas('rack.room', fn($inner) => $inner->where('user_id', $user->id))
              ->orWhere('tenant_id', $user->id);
        })
            ->where('id', $id)
            ->firstOrFail();

        if ($server->status === ServerStatus::MAINTENANCE) {
            return response()->json(['success' => false, 'error' => 'Server befindet sich bereits in Wartung.'], 400);
        }

        if (!$server->status->isOperational()) {
             return response()->json(['success' => false, 'error' => 'Wartung kann nur für betriebsbereite Server geplant werden.'], 400);
        }

        $delay = $request->input('delay_minutes', 0);
        $scheduledAt = $delay > 0 ? now()->addMinutes($delay) : null;

        // Proactive maintenance is cheaper (5% of purchase cost)
        $maintenanceCost = $server->purchase_cost * 0.05;
        
        if (!$user->economy->canAfford($maintenanceCost)) {
             return response()->json(['success' => false, 'error' => "Maintenance starts at \$" . number_format($maintenanceCost, 2) . ". Insufficient funds."], 400);
        }
        
        return DB::transaction(function () use ($user, $server, $maintenanceCost, $delay, $scheduledAt) {
            $user->economy->debit($maintenanceCost, "Scheduled maintenance: {$server->model_name}", 'maintenance', $server);
            
            if ($delay === 0) {
                $server->status = ServerStatus::MAINTENANCE;
                $server->maintenance_scheduled_at = null;
                $server->last_maintenance_at = now();
                $server->addMaintenanceLogEntry('maintenance', "Proactive maintenance window started immediately", $maintenanceCost);
            } else {
                $server->maintenance_scheduled_at = $scheduledAt;
                $server->addMaintenanceLogEntry('maintenance', "Maintenance window scheduled for " . $scheduledAt->toDateTimeString(), $maintenanceCost);
            }
            
            $server->save();
            
            return response()->json([
                'success' => true,
                'data' => $server->toGameState(),
                'message' => $delay === 0 
                    ? 'Maintenance window started. Health will restore over time.' 
                    : "Maintenance window scheduled in {$delay} minutes."
            ]);
        });
    }

    /**
     * Update the backup plan for a server
     */
    public function updateBackupPlan(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'plan' => 'required|string|in:none,daily,hourly,tape,offsite',
        ]);

        $user = $request->user();
        $server = Server::where(function($q) use ($user) {
            $q->whereHas('rack.room', fn($inner) => $inner->where('user_id', $user->id))
              ->orWhere('tenant_id', $user->id);
        })
            ->where('id', $id)
            ->firstOrFail();

        $server->backup_plan = \App\Enums\BackupPlan::from($request->plan);
        $server->save();

        // Log the change
        \App\Models\GameLog::log($user, "Backup plan for {$server->model_name} updated to " . strtoupper($request->plan), 'info', 'infrastructure');

        return response()->json([
            'success' => true,
            'message' => 'Backup plan updated.',
            'server' => $server->toGameState()
        ]);
    }

    /**
     * FEATURE 275: Disaster Snapshot Rollbacks (Temporal Ops)
     */
    public function rollbackBackup(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->findOrFail($id);

        if ($server->status !== \App\Enums\ServerStatus::DAMAGED && $server->status !== \App\Enums\ServerStatus::OFFLINE) {
            return response()->json(['success' => false, 'error' => 'Rollbacks können nur auf zerstörten oder offline Servern ausgeführt werden.'], 400);
        }

        if ($server->backup_plan === \App\Enums\BackupPlan::NONE || !$server->last_backup_at) {
            return response()->json(['success' => false, 'error' => 'Kein gültiges Backup für diesen Server vorhanden.'], 400);
        }

        $repCost = 50;

        if ($user->economy->reputation < $repCost) {
            return response()->json(['success' => false, 'error' => "Nicht genug Reputation für dieses wundersame Ereignis (Benötigt: {$repCost})."], 400);
        }
        
        return DB::transaction(function () use ($user, $server, $repCost) {
            $user->economy->addReputation(-$repCost);
            $user->economy->save();

            // Hardware "Miracle" Restoration
            $server->health = 100;
            $server->status = \App\Enums\ServerStatus::ONLINE;
            $server->current_fault = null;
            $server->is_diagnosed = false;
            $server->last_maintenance_at = now();
            
            // Backup integrity takes a hit from the recovery process
            $specs = $server->specs ?? [];
            if (isset($specs['backup_health'])) {
                $specs['backup_health'] = max(0, $specs['backup_health'] - 20);
            }
            $server->specs = $specs;
            
            $server->save();

            $server->addMaintenanceLogEntry('maintenance', "TEMPORAL OPS: System rollbacked to last snapshot. Data loss averted.", 0);

            \App\Models\GameLog::log($user, "⏳ TEMPORAL OPS: Server {$server->model_name} wurde durch ein Snapshot-Rollback vor dem Totalausfall gerettet. (-50 Rep)", 'warning', 'infrastructure');

            return response()->json([
                'success' => true,
                'message' => "Temporal Ops erfolgreich! System aus Snapshot wiederhergestellt. (-50 Reputation)",
                'server' => $server->toGameState()
            ]);
        });
    }

    /**
     * Update the appearance (LED color, RGB) of a server
     */
    public function updateAppearance(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'led_color' => 'nullable|string|max:20',
            'custom_rgb' => 'nullable|array',
            'nickname' => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        $server = Server::where(function($q) use ($user) {
            $q->whereHas('rack.room', fn($inner) => $inner->where('user_id', $user->id))
              ->orWhere('tenant_id', $user->id);
        })
            ->where('id', $id)
            ->firstOrFail();

        if ($request->has('led_color')) {
            $server->led_color = $request->led_color;
        }

        if ($request->has('nickname')) {
            $server->nickname = $request->nickname;
        }
        
        if ($request->has('custom_rgb')) {
            $server->custom_rgb = $request->custom_rgb;
        }
        
        $server->save();

        return response()->json([
            'success' => true,
            'message' => 'Server appearance updated.',
            'server' => $server->toGameState()
        ]);
    }
    /**
     * Get available OS Catalog
     */
    public function getOsCatalog(Request $request): JsonResponse
    {
        $user = $request->user();
        $service = app(\App\Services\Game\OsService::class);
        $researchService = app(\App\Services\Game\ResearchService::class);
        
        $catalog = $service->getDefinitions();
        $filteredCatalog = [];
        
        foreach ($catalog as $id => $def) {
            if (isset($def['research_req'])) {
                if (!$researchService->hasResearch($user, $def['research_req'])) {
                    continue;
                }
            }
            $filteredCatalog[$id] = $def;
        }

        return response()->json([
            'success' => true,
            'catalog' => $filteredCatalog
        ]);
    }

    /**
     * Get available Insurance Plans
     */
    public function getInsurancePlans(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'plans' => \App\Services\Game\InsuranceService::PLANS
        ]);
    }

    /**
     * Install an Operating System on a server
     */
    public function installOs(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'os_type' => 'required|string',
        ]);

        $user = $request->user();
        $server = Server::where(function($q) use ($user) {
            $q->whereHas('rack.room', fn($inner) => $inner->where('user_id', $user->id))
              ->orWhere('tenant_id', $user->id);
        })->where('id', $id)->firstOrFail();

        $service = app(\App\Services\Game\OsService::class);
        
        try {
            $service->install($server, $request->os_type);
            
            // Log for activity
            \App\Models\GameLog::log($user, "Initiated OS install: " . $request->os_type, 'info', 'server');

            return response()->json([
                'success' => true,
                'message' => 'OS installation started.',
                'server' => $server->refresh()->toGameState()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * FEATURE 194: Liquid Nitrogen Overclocking
     * Extreme performance boost for a short duration. High risk of hardware failure.
     */
    public function overclock(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->find($id);

        if (!$server) {
            return response()->json(['success' => false, 'error' => 'Server nicht gefunden'], 404);
        }

        if ($server->status !== ServerStatus::ONLINE) {
            return response()->json(['success' => false, 'error' => 'Server muss online sein für Overclocking'], 400);
        }

        $researchService = app(\App\Services\Game\ResearchService::class);
        if (!$researchService->isUnlocked($user, 'ln2_overclocking')) {
            return response()->json(['success' => false, 'error' => 'Forschung "Kryogene Kühlungslösungen" erforderlich'], 403);
        }

        $specs = $server->specs ?? [];
        if ($specs['overclocked_until'] ?? null) {
            if (\Carbon\Carbon::now()->lt(\Carbon\Carbon::parse($specs['overclocked_until']))) {
                return response()->json(['success' => false, 'error' => 'Server läuft bereits auf maximaler LN2-Kapazität'], 400);
            }
        }

        return DB::transaction(function () use ($user, $server) {
            $specs = $server->specs ?? [];
            
            // 5% Risk of catastrophic failure immediately
            if (rand(1, 100) <= 5) {
                $server->health = 0;
                $server->status = ServerStatus::HARDWARE_FAULT;
                $server->current_fault = 'CATASTROPHIC_SHATTERING';
                $server->addMaintenanceLogEntry('error', 'CRYOGENIC FAILURE: Hardware shattered due to thermal shock!', 0);
                $server->save();

                \App\Models\GameLog::log($user, "CRITICAL: Server {$server->model_name} in {$server->rack->room->name} shattered during LN2 injection!", 'danger', 'hardware');

                return response()->json([
                    'success' => false,
                    'error' => 'THERMISCHER SCHOCK: Hardware zerstört!',
                    'data' => $server->toGameState()
                ], 422);
            }

            // Success: +300% performance (active for 120s)
            $specs['overclocked_until'] = \Carbon\Carbon::now()->addSeconds(120)->toIso8601String();
            $server->specs = $specs;
            
            // Take minor health damage from heat shock
            $server->health = max(0, $server->health - rand(2, 8));
            $server->addMaintenanceLogEntry('maintenance', 'LN2 Overclocking initiated. +300% Performance active.', 0);
            $server->save();

            \App\Models\GameLog::log($user, "OVERCLOCK: LN2 cooling injected into {$server->model_name}. System running at 4x speed!", 'success', 'hardware');

            return response()->json([
                'success' => true,
                'message' => 'Cryogenic overclocking active! System is screaming.',
                'data' => $server->toGameState()
            ]);
        });
    }

    /**
     * FEATURE 208: Insure a server.
     */
    public function insureServer(string $id, Request $request): JsonResponse
    {
        $request->validate(['plan' => 'required|string']);
        
        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->findOrFail($id);
        
        $service = app(\App\Services\Game\InsuranceService::class);
        $result = $service->insureServer($user, $server, $request->plan);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * FEATURE 208: Cancel insurance for a server.
     */
    public function cancelInsurance(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->findOrFail($id);
        
        $service = app(\App\Services\Game\InsuranceService::class);
        $result = $service->cancelInsurance($user, $server);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * FEATURE 299: Cyber-Insurance Fraud (Evil Path)
     */
    public function commitInsuranceFraud(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->findOrFail($id);
        
        $service = app(\App\Services\Game\InsuranceService::class);
        $result = $service->commitFraud($user, $server);

        if (!$result['success']) {
            return response()->json($result, 400); // Also returns 400 on "caught" 
        }

        return response()->json($result);
    }

    /**
     * FEATURE 202: Enable darknet hosting for a server.
     */
    public function enableDarknet(string $id, Request $request): JsonResponse
    {
        $request->validate(['type' => 'required|string']);
        
        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->findOrFail($id);
        
        $service = app(\App\Services\Game\DarknetService::class);
        $result = $service->enableDarknet($user, $server, $request->type);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * FEATURE 202: Disable darknet hosting for a server.
     */
    public function disableDarknet(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->findOrFail($id);
        
        $service = app(\App\Services\Game\DarknetService::class);
        $result = $service->disableDarknet($user, $server);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * FEATURE 254: Tune server hardware (Clock/Voltage)
     */
    public function tune(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'cpu_clock_mhz' => 'required|integer|min:100|max:10000',
            'cpu_voltage_v' => 'required|numeric|min:0.5|max:2.5',
            'ram_latency' => 'nullable|numeric|min:0.5|max:1.5', // Multiplier for performance
            'gpu_clock_mhz' => 'nullable|integer|min:100|max:5000',
        ]);

        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->findOrFail($id);

        if ($server->status !== ServerStatus::OFFLINE) {
            return response()->json(['success' => false, 'error' => 'Server muss offline sein für Hardware-Tuning.'], 400);
        }

        $server->cpu_clock_mhz = $request->cpu_clock_mhz;
        $server->cpu_voltage_v = $request->cpu_voltage_v;
        
        $specs = $server->specs ?? [];
        if ($request->has('ram_latency')) {
            $specs['tuning_ram_latency'] = $request->ram_latency;
        }
        if ($request->has('gpu_clock_mhz')) {
            $specs['tuning_gpu_clock'] = $request->gpu_clock_mhz;
        }
        $server->specs = $specs;
        
        $server->save();

        $server->addMaintenanceLogEntry('maintenance', "Hardware tuning updated: {$server->cpu_clock_mhz} MHz @ " . number_format($server->cpu_voltage_v, 3) . "V");

        return response()->json([
            'success' => true,
            'message' => 'Tuning profiles updated.',
            'server' => $server->toGameState(),
        ]);
    }

    /**
     * Run a hardware stress test
     */
    public function stressTest(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->findOrFail($id);

        if ($server->status !== ServerStatus::ONLINE) {
            return response()->json(['success' => false, 'error' => 'Server muss online sein für Stresstests.'], 400);
        }

        $result = app(\App\Services\Game\TuningService::class)->runStressTest($server);
        
        if ($result['damage'] > 0) {
            $server->save(); // Save damage/faults
            \App\Models\GameLog::log($user, "STRESS_TEST_FAILURE: Hardware damage detected on {$server->model_name} (-{$result['damage']}% Health)", 'danger', 'hardware');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'passed' => $result['success'],
                'stability' => $result['stability'],
                'damage' => $result['damage'],
                'fault' => $result['fault'],
                'server' => $server->refresh()->toGameState(),
            ]
        ]);
    }

    /**
     * FEATURE 260: Sell server to secondary market
     */
    public function sell(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $server = Server::with(['rack.room', 'activeOrders'])->whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->findOrFail($id);

        if ($server->status !== ServerStatus::OFFLINE) {
            return response()->json(['success' => false, 'error' => 'Server muss offline sein für den Verkauf.'], 400);
        }

        if ($server->activeOrders->count() > 0) {
            return response()->json(['success' => false, 'error' => 'Server mit aktiven Kundenaufträgen kann nicht verkauft werden.'], 400);
        }

        if ($server->is_leased) {
            return response()->json(['success' => false, 'error' => 'Geleaste Hardware kann nicht verkauft werden. Geben Sie sie stattdessen zurück.'], 400);
        }

        $resaleValue = $server->calculateResaleValue();

        return DB::transaction(function () use ($user, $server, $resaleValue) {
            $user->economy->credit($resaleValue, "Resale of hardware: {$server->model_name}", 'hardware');
            
            $rack = $server->rack;
            $server->delete();
            $rack->recalculatePowerAndHeat();

            \App\Models\GameLog::log($user, "HARDWARE RESALE: {$server->model_name} sold to secondary market for \$" . number_format($resaleValue, 2), 'success', 'economy');

            return response()->json([
                'success' => true,
                'message' => "Server sold for \$" . number_format($resaleValue, 2),
                'resale_value' => $resaleValue,
            ]);
        });
    }

    /**
     * FEATURE 265: Physical Data Destruction (Shredding)
     */
    public function shred(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->findOrFail($id);

        if ($server->status !== ServerStatus::OFFLINE) {
            return response()->json(['success' => false, 'error' => 'Server muss offline sein für sichere Vernichtung.'], 400);
        }

        if ($server->activeOrders()->count() > 0) {
            return response()->json(['success' => false, 'error' => 'Hardware mit aktiven Kundendaten kann nicht geschreddert werden.'], 400);
        }

        $service = app(\App\Services\Game\HardwareDepreciationService::class);
        $result = $service->shredServer($user, $server);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }
    /**
     * FEATURE 261: Swap a component in a server for a new one from inventory
     */
    public function swapComponent(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'component_id' => 'required|exists:user_components,id',
            'slot_index' => 'nullable|integer', // For multi-slot components like RAM
        ]);

        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->findOrFail($id);
        $newComponent = \App\Models\UserComponent::where('user_id', $user->id)
            ->where('status', 'inventory')
            ->findOrFail($request->component_id);

        if ($server->status !== ServerStatus::OFFLINE) {
            return response()->json(['success' => false, 'error' => 'Server muss offline sein für Hardware-Upgrades.'], 400);
        }

        $config = $newComponent->getConfig();
        $type = $newComponent->component_type;
        $specs = $server->specs ?? [];

        return DB::transaction(function () use ($user, $server, $newComponent, $type, $config, $specs, $request) {
            $oldComponentId = null;
            $slot = $request->input('slot_index', 0);

            // Identification of target slot and old component
            if ($type === 'cpu') {
                $cpuIds = $specs['cpu_ids'] ?? [];
                $oldComponentId = $cpuIds[$slot] ?? null;
                $cpuIds[$slot] = $newComponent->id;
                $specs['cpu_ids'] = $cpuIds;
            } elseif ($type === 'ram') {
                $ramIds = $specs['ram_ids'] ?? [];
                $oldComponentId = $ramIds[$slot] ?? null;
                $ramIds[$slot] = $newComponent->id;
                $specs['ram_ids'] = $ramIds;
            } elseif ($type === 'storage') {
                $storageIds = $specs['storage_ids'] ?? [];
                $oldComponentId = $storageIds[$slot] ?? null;
                $storageIds[$slot] = $newComponent->id;
                $specs['storage_ids'] = $storageIds;
            } else {
                return response()->json(['success' => false, 'error' => 'Nicht unterstützter Komponententyp für Tausch.'], 400);
            }

            // Return old component to inventory
            if ($oldComponentId) {
                $oldComp = \App\Models\UserComponent::find($oldComponentId);
                if ($oldComp) {
                    $oldComp->assigned_server_id = null;
                    $oldComp->status = 'inventory';
                    $oldComp->save();
                }
            }

            // Assign new component
            $newComponent->assigned_server_id = $server->id;
            $newComponent->status = 'installed';
            $newComponent->save();

            // Refresh Server Specs from components
            $server->specs = $specs;
            $server->is_custom = true; // Upgrade path converts pre-builts to custom tracking
            
            // Re-calculate physical specs
            $this->recalculateServerPhysicals($server);
            $server->save();

            $server->addMaintenanceLogEntry('upgrade', "Swapped {$type}: Installed {$config['name']}", 0);

            return response()->json([
                'success' => true,
                'message' => 'Component upgraded successfully.',
                'server' => $server->toGameState(),
            ]);
        });
    }

    /**
     * Helper to recalculate power, heat, and capacity based on installed components
     */
    protected function recalculateServerPhysicals(Server $server): void
    {
        $specs = $server->specs;
        $componentIds = array_filter(array_merge(
            [$specs['motherboard_id'] ?? null],
            $specs['cpu_ids'] ?? [],
            $specs['ram_ids'] ?? [],
            $specs['storage_ids'] ?? []
        ));

        $components = \App\Models\UserComponent::whereIn('id', $componentIds)->get();
        
        $totalPower = 0.05; // Base idle power
        $totalHeat = 0.04;
        $totalCores = 0;
        $totalRam = 0;
        $totalStorage = 0;
        $totalBandwidth = 0;

        foreach ($components as $comp) {
            $conf = $comp->getConfig();
            if (!$conf) continue;

            $totalPower += ($conf['power_draw_kw'] ?? 0);
            $totalHeat += ($conf['heat_output_kw'] ?? 0);
            $totalCores += ($conf['cpu_cores'] ?? 0);
            $totalRam += ($conf['ram_gb'] ?? 0);
            $totalStorage += ($conf['storage_tb'] ?? 0);
            $totalBandwidth = max($totalBandwidth, ($conf['bandwidth_mbps'] ?? 0));
        }

        $server->power_draw_kw = round($totalPower, 2);
        $server->heat_output_kw = round($totalHeat, 2);
        $server->cpu_cores = $totalCores;
        $server->ram_gb = $totalRam;
        $server->storage_tb = $totalStorage;
        $server->bandwidth_mbps = $totalBandwidth;
    }

    /**
     * FEATURE 65: Toggle Crypto Mining for a server
     */
    public function toggleMining(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->findOrFail($id);

        if (!$server->is_mining && $server->status !== ServerStatus::ONLINE && $server->status !== ServerStatus::DEGRADED) {
            return response()->json(['success' => false, 'error' => 'Server muss online sein, um Mining zu starten.'], 400);
        }

        $server->is_mining = !$server->is_mining;
        $server->save();

        if ($server->is_mining) {
            \App\Models\GameLog::log($user, "⚡ Crypto Mining gestartet auf Server {$server->model_name}.", 'warning', 'infrastructure');
        } else {
            \App\Models\GameLog::log($user, "🛑 Crypto Mining gestoppt auf Server {$server->model_name}.", 'info', 'infrastructure');
        }

        // We need to recalculate the power and heat for the rack
        if ($server->rack) {
            $server->rack->recalculatePowerAndHeat();
        }

        return response()->json([
            'success' => true,
            'message' => 'Mining status toggled.',
            'server' => $server->toGameState()
        ]);
    }

    /**
     * FEATURE 305: Battery Cell Refurbishment
     * Resets battery SoH (Health) to 100% for a fixed cost.
     */
    public function refurbishBattery(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('type', 'battery')
            ->findOrFail($id);

        $cost = 1200.0;

        if (!$user->economy->canAfford($cost)) {
            return response()->json(['success' => false, 'error' => "Refurbishment costs $1,200. Insufficient funds."], 400);
        }

        return DB::transaction(function () use ($user, $server, $cost) {
            $user->economy->debit($cost, "Battery Cell Refurbishment: {$server->model_name}", 'maintenance', $server);
            
            $server->health = 100.0;
            $server->save();

            $server->addMaintenanceLogEntry('maintenance', "Battery cells replaced. Health (SoH) restored to 100%.", $cost);

            \App\Models\GameLog::log($user, "HARDWARE UPGRADE: Battery cells in {$server->model_name} (Room: {$server->rack->room->name}) have been refurbished.", 'success', 'hardware');

            return response()->json([
                'success' => true,
                'message' => 'Batteriezellen erfolgreich generalüberholt.',
                'server' => $server->toGameState(),
            ]);
        });
    }
}
