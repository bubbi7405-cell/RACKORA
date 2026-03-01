<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\Server;
use App\Models\GameLog;
use App\Enums\ServerStatus;
use Illuminate\Support\Facades\DB;

/**
 * FEATURE 202: Darknet Operations Marketplace
 * 
 * Host untraceable/illegal traffic for extreme profit multipliers.
 * Risk: Federal Raids and total seizure of hardware/reputation.
 */
class DarknetService
{
    /**
     * Darknet Traffic Types
     */
    public const TRAFFIC_TYPES = [
        'encrypted_comms' => [
            'name' => 'End-to-End Encrypted Comms',
            'profit_mult' => 1.5,
            'heat_gain' => 0.05,
            'min_level' => 5,
            'description' => 'Host "unbreakable" messaging for privacy-focused (or suspicious) groups.',
        ],
        'shadow_marketplace' => [
            'name' => 'Shadow Marketplace Node',
            'profit_mult' => 3.0,
            'heat_gain' => 0.2,
            'min_level' => 10,
            'description' => 'A hidden node for gray-market goods. High profit, high heat.',
        ],
        'crypto_mixer' => [
            'name' => 'Bulletproof Crypto Mixer',
            'profit_mult' => 5.0,
            'heat_gain' => 0.5,
            'min_level' => 15,
            'description' => 'Launder virtual currency through thousands of hop-nodes. Massive rewards, extreme federal heat.',
        ],
    ];

    /**
     * Enable darknet hosting for a specific server.
     */
    public function enableDarknet(User $user, Server $server, string $type): array
    {
        $traffic = self::TRAFFIC_TYPES[$type] ?? null;
        if (!$traffic) {
            return ['success' => false, 'error' => 'Ungültiger Traffic-Typ.'];
        }

        if ($user->economy->level < $traffic['min_level']) {
            return ['success' => false, 'error' => "Benötigt Level {$traffic['min_level']}."];
        }

        $specs = $server->specs ?? [];
        if ($specs['darknet_active'] ?? false) {
             return ['success' => false, 'error' => 'Server hostet bereits Darknet-Traffic.'];
        }

        $specs['darknet_active'] = true;
        $specs['darknet_type'] = $type;
        $specs['darknet_started_at'] = now()->toIso8601String();
        $server->specs = $specs;
        $server->save();

        GameLog::log($user, "🌑 DARKNET: Server {$server->model_name} hostet jetzt '{$traffic['name']}'. Profit steigt, aber Vorsicht vor Behörden!", 'warning', 'darknet');

        return [
            'success' => true,
            'traffic' => $traffic,
        ];
    }

    /**
     * Disable darknet hosting.
     */
    public function disableDarknet(User $user, Server $server): array
    {
        $specs = $server->specs ?? [];
        if (!($specs['darknet_active'] ?? false)) {
            return ['success' => false, 'error' => 'Kein Darknet-Traffic aktiv.'];
        }

        unset($specs['darknet_active']);
        unset($specs['darknet_type']);
        unset($specs['darknet_started_at']);
        $server->specs = $specs;
        $server->save();

        GameLog::log($user, "🧊 DARKNET: Darknet-Hosting auf {$server->model_name} beendet. Heat sinkt langsam.", 'info', 'darknet');

        return ['success' => true, 'message' => 'Darknet-Hosting deaktiviert.'];
    }

    /**
     * Process Heat level and Federal Raids.
     * Called per tick in GameLoop.
     */
    public function tick(User $user): void
    {
        $economy = $user->economy;
        $currentHeat = (float) $economy->federal_heat;
        
        $activeDarknetServers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', ServerStatus::ONLINE)
            ->get()
            ->filter(fn($s) => $s->specs['darknet_active'] ?? false);

        $totalHeatGain = 0;
        $totalRiskExposure = 0;
        foreach ($activeDarknetServers as $server) {
            $type = $server->specs['darknet_type'] ?? 'encrypted_comms';
            $trafficHeat = self::TRAFFIC_TYPES[$type]['heat_gain'] ?? 0.05;
            $totalHeatGain += $trafficHeat;
            
            // Risk exposure is higher for less secure OS (e.g. 60% secure = 0.4 risk factor)
            $osSecurity = (float) ($server->security_patch_level ?? 90);
            $riskFactor = max(0.1, (100 - $osSecurity) / 100);
            $totalRiskExposure += ($trafficHeat * (1 + $riskFactor * 2));
        }

        if ($totalHeatGain > 0) {
            $currentHeat += ($totalHeatGain / 60); // Heat grows per minute
        } else {
            // Heat decay if no active darknet ops
            $currentHeat = max(0, $currentHeat - 0.01);
        }

        $economy->federal_heat = round($currentHeat, 4);
        $economy->risk_exposure = round($totalRiskExposure, 4);
        
        // FEDERAL RAID CHANCE
        if ($currentHeat > 10) {
            $raidChance = ($currentHeat - 10) * 0.1;
            if (rand(1, 1000) <= ($raidChance * 10)) {
                $this->triggerFederalRaid($user, $activeDarknetServers);
                $economy->federal_heat = 0; // Reset after raid
                
                $meta = $economy->metadata ?? [];
                $meta['last_raid_at'] = now()->toIso8601String();
                $economy->metadata = $meta;
            }
        }

        $economy->save();
    }

    /**
     * Trigger a Federal Raid – the worst nightmare.
     */
    private function triggerFederalRaid(User $user, $activeServers): void
    {
        DB::transaction(function () use ($user, $activeServers) {
            $seizedCount = 0;
            $repPenalty = 0;
            $fine = 0;

            foreach ($activeServers as $server) {
                $type = $server->specs['darknet_type'] ?? 'encrypted_comms';
                $impact = self::TRAFFIC_TYPES[$type];
                
                // Seize the server
                $server->status = ServerStatus::OFFLINE;
                $specs = $server->specs;
                $specs['seized_by_fbi'] = true;
                unset($specs['darknet_active']);
                $server->specs = $specs;
                $server->save();
                
                $seizedCount++;
                $repPenalty += 15.0;
                $fine += $server->purchase_cost * 0.5;
            }

            $user->economy->adjustReputation(-$repPenalty);
            $user->economy->balance -= $fine;
            $user->economy->save();

            GameLog::log($user, "🚨 FEDERAL RAID: Die Behörden haben Ihr Netzwerk infiltriert! {$seizedCount} Server wurden beschlagnahmt. Strafe: \${$fine}, Rep: -{$repPenalty}.", 'danger', 'darknet');
            
            // Notification
            app(NewsService::class)->broadcastGlobal(
                "FLASH: Cyber-Crime task force raids local data center provider '{$user->company_name}' after tracking illegal marketplaces.",
                'breaking',
                'CRIME'
            );
        });
    }

    /**
     * Get hourly darknet profit.
     */
    public function getHourlyProfit(User $user): float
    {
        $servers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', ServerStatus::ONLINE)
            ->get()
            ->filter(fn($s) => $s->specs['darknet_active'] ?? false);

        $totalProfit = 0;
        foreach ($servers as $server) {
            $type = $server->specs['darknet_type'] ?? 'encrypted_comms';
            $mult = self::TRAFFIC_TYPES[$type]['profit_mult'] ?? 1.5;
            // Base profit from active orders * multiplier
            $serverBaseIncome = $server->activeOrders()->sum('price_per_month') / 720;
            $totalProfit += ($serverBaseIncome * ($mult - 1));
        }

        return $totalProfit;
    }

    public function getState(User $user): array
    {
        $economy = $user->economy;
        
        return [
            'traffic_types' => self::TRAFFIC_TYPES,
            'current_heat' => (float) $economy->federal_heat,
            'max_heat' => 100,
            'metadata' => $economy->metadata ?? [],
            'unlocked' => $economy->level >= 5,
        ];
    }
}
