<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\Server;
use App\Models\GameLog;
use App\Enums\ServerStatus;
use Illuminate\Support\Facades\DB;

/**
 * FEATURE 208: Umfassende Hardware-Versicherung
 * 
 * Prämienzahlungen zum Absichern teurer Server.
 * Deckung bei Feuer (F31), kryogenischem Shattering (F194), und kritischen Ausfällen.
 */
class InsuranceService
{
    /**
     * Verfügbare Versicherungspläne.
     */
    public const PLANS = [
        'basic' => [
            'name' => 'Basic Coverage',
            'description' => 'Deckt 50% des Kaufpreises bei totalem Hardwareverlust.',
            'premium_rate' => 0.005, // 0.5% des Serverwertes pro Stunde
            'coverage_pct' => 0.50,
            'covers' => ['hardware_failure'],
            'min_level' => 3,
        ],
        'standard' => [
            'name' => 'Standard Protection',
            'description' => 'Deckt 75% bei Hardwareausfall, Feuer und Überhitzung.',
            'premium_rate' => 0.012,
            'coverage_pct' => 0.75,
            'covers' => ['hardware_failure', 'fire', 'overheating'],
            'min_level' => 8,
        ],
        'premium' => [
            'name' => 'Premium Shield',
            'description' => 'Deckt 90% bei allen Schadensarten inkl. kryogenischem Shattering.',
            'premium_rate' => 0.025,
            'coverage_pct' => 0.90,
            'covers' => ['hardware_failure', 'fire', 'overheating', 'catastrophic_shattering', 'geopolitical'],
            'min_level' => 12,
        ],
        'quantum' => [
            'name' => 'Quantum Guarantee',
            'description' => '100% Erstattung bei JEDEM Verlust. Enthält Express-Ersatzlieferung.',
            'premium_rate' => 0.05,
            'coverage_pct' => 1.0,
            'covers' => ['*'], // Wildcard: alles
            'express_replacement' => true,
            'min_level' => 18,
        ],
    ];

    /**
     * Versicherung für einen Server abschließen.
     */
    public function insureServer(User $user, Server $server, string $planKey): array
    {
        $plan = self::PLANS[$planKey] ?? null;
        if (!$plan) {
            return ['success' => false, 'error' => 'Versicherungsplan nicht gefunden.'];
        }

        if ($user->economy->level < $plan['min_level']) {
            return ['success' => false, 'error' => "Benötigt Level {$plan['min_level']}."];
        }

        $specs = $server->specs ?? [];
        if ($specs['insurance_plan'] ?? null) {
            return ['success' => false, 'error' => 'Server ist bereits versichert. Zuerst kündigen.'];
        }

        $specs['insurance_plan'] = $planKey;
        $specs['insurance_since'] = now()->toIso8601String();
        $server->specs = $specs;
        $server->save();

        GameLog::log($user, "🛡️ Versicherung '{$plan['name']}' für Server {$server->model_name} abgeschlossen.", 'success', 'financial');

        return [
            'success' => true,
            'plan' => $plan,
            'hourly_premium' => round($server->purchase_cost * $plan['premium_rate'], 2),
        ];
    }

    /**
     * Versicherung kündigen.
     */
    public function cancelInsurance(User $user, Server $server): array
    {
        $specs = $server->specs ?? [];
        if (!($specs['insurance_plan'] ?? null)) {
            return ['success' => false, 'error' => 'Keine aktive Versicherung.'];
        }

        unset($specs['insurance_plan']);
        unset($specs['insurance_since']);
        $server->specs = $specs;
        $server->save();

        GameLog::log($user, "❌ Versicherung für Server {$server->model_name} gekündigt.", 'info', 'financial');

        return ['success' => true, 'message' => 'Versicherung gekündigt.'];
    }

    /**
     * Schadensfall melden und Erstattung ausgeben.
     */
    public function fileClaim(User $user, Server $server, string $damageType): array
    {
        $specs = $server->specs ?? [];
        $planKey = $specs['insurance_plan'] ?? null;

        if (!$planKey) {
            return ['success' => false, 'error' => 'Server nicht versichert.', 'payout' => 0];
        }

        $plan = self::PLANS[$planKey] ?? null;
        if (!$plan) {
            return ['success' => false, 'error' => 'Unbekannter Versicherungsplan.', 'payout' => 0];
        }

        // Prüfe ob Schadensart gedeckt ist
        $covered = in_array('*', $plan['covers']) || in_array($damageType, $plan['covers']);
        if (!$covered) {
            GameLog::log($user, "⚠️ Versicherungsclaim abgelehnt: '{$damageType}' nicht durch '{$plan['name']}' gedeckt.", 'warning', 'financial');
            return ['success' => false, 'error' => "Schadensart '{$damageType}' nicht gedeckt.", 'payout' => 0];
        }

        $payout = round($server->purchase_cost * $plan['coverage_pct'], 2);

        return DB::transaction(function () use ($user, $server, $plan, $payout, $damageType, $specs) {
            $user->economy->credit($payout, "Versicherungsauszahlung: {$plan['name']} ({$damageType})", 'insurance');
            
            // Versicherung nach Auszahlung beenden (einmalig)
            unset($specs['insurance_plan']);
            unset($specs['insurance_since']);
            $specs['last_insurance_claim'] = now()->toIso8601String();
            $server->specs = $specs;
            $server->save();

            $user->economy->save();

            GameLog::log($user, "💰 Versicherungsauszahlung: \${$payout} für {$server->model_name} ({$damageType}). Police beendet.", 'success', 'financial');

            return [
                'success' => true,
                'payout' => $payout,
                'plan' => $plan['name'],
                'message' => "Erstattung von \${$payout} ausgezahlt.",
            ];
        });
    }

    /**
     * Stündliche Prämienberechnung für alle versicherten Server eines Users.
     * Wird im GameLoop aufgerufen.
     */
    public function processHourlyPremiums(User $user): float
    {
        $servers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->get();
        $totalPremium = 0;

        foreach ($servers as $server) {
            $planKey = $server->specs['insurance_plan'] ?? null;
            if (!$planKey) continue;

            $plan = self::PLANS[$planKey] ?? null;
            if (!$plan) continue;

            $premium = round($server->purchase_cost * $plan['premium_rate'], 2);
            $totalPremium += $premium;
        }

        if ($totalPremium > 0) {
            $user->economy->debit($totalPremium, "Versicherungsprämien", 'insurance');
        }

        return $totalPremium;
    }

    /**
     * Versicherungsstatus aller Server für die API.
     */
    public function getInsuranceState(User $user): array
    {
        $servers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->get();
        $insuredServers = [];
        $totalMonthlyPremium = 0;

        foreach ($servers as $server) {
            $planKey = $server->specs['insurance_plan'] ?? null;
            if (!$planKey) continue;

            $plan = self::PLANS[$planKey] ?? null;
            if (!$plan) continue;

            $premium = round($server->purchase_cost * $plan['premium_rate'], 2);
            $totalMonthlyPremium += $premium;

            $insuredServers[] = [
                'server_id' => $server->id,
                'server_name' => $server->model_name,
                'plan' => $planKey,
                'plan_name' => $plan['name'],
                'hourly_premium' => $premium,
                'coverage' => $plan['coverage_pct'] * 100 . '%',
                'since' => $server->specs['insurance_since'] ?? null,
            ];
        }

        return [
            'plans' => self::PLANS,
            'insured_servers' => $insuredServers,
            'total_hourly_premium' => $totalMonthlyPremium,
            'unlocked' => $user->economy->level >= 3,
        ];
    }

    /**
     * FEATURE 299: Cyber-Insurance Fraud (Evil Path)
     * Intentionally cause a hardware fire to claim insurance money.
     * Risk: 50% chance of "Fraud Investigation" and instant penalty/loss.
     */
    public function commitFraud(User $user, Server $server): array
    {
        $specs = $server->specs ?? [];
        $planKey = $specs['insurance_plan'] ?? null;

        if (!$planKey) {
            return ['success' => false, 'error' => 'Dieser Server ist nicht versichert. Betrug unmöglich.'];
        }

        $plan = self::PLANS[$planKey] ?? null;
        if (!$plan) {
            return ['success' => false, 'error' => 'Ungültiger Versicherungsplan.'];
        }

        // Cancel Active Orders to prevent weird states
        if ($server->activeOrders()->count() > 0) {
            return ['success' => false, 'error' => 'Server mit aktiven Kundenverträgen können nicht unbemerkt "abbrennen". Kündigen Sie die Verträge zuerst.'];
        }

        // 50% chance to succeed
        $isSuccess = rand(1, 100) <= 50;
        
        // Payout is 150% of the normal value as a "bonus" for the successful fraud
        $payout = round($server->purchase_cost * 1.5, 2);
        
        // Fraud penalty if caught = 300% of purchase cost + massive rep loss
        $penalty = round($server->purchase_cost * 3.0, 2);

        return DB::transaction(function () use ($user, $server, $plan, $payout, $penalty, $isSuccess) {
            $rack = $server->rack;
            $serverName = $server->model_name;

            if ($isSuccess) {
                // SUCCESS
                $user->economy->credit($payout, "Versicherungsauszahlung (Brandschaden komplett): {$serverName}", 'insurance');
                $user->economy->addReputation(-5); // Minor suspicion
                $user->economy->save();

                GameLog::log($user, "🔥 BRANDSTIFTUNG ERFOLGREICH: Server {$serverName} vollständig zerstört. \${$payout} von der Versicherung ('{$plan['name']}') kassiert.", 'success', 'financial');

                // Destroy the server
                $server->delete();
                if ($rack) $rack->recalculatePowerAndHeat();

                return [
                    'success' => true,
                    'message' => "Versicherungsbetrug erfolgreich! \${$payout} ausgezahlt. Server wurde restlos vernichtet."
                ];
            } else {
                // FAILURE (CAUGHT!)
                $user->economy->debit($penalty, "SEC STRAFE / VERSICHERUNGSBETRUG: {$serverName}", 'insurance');
                $user->economy->addReputation(-50); // Massive rep loss
                $user->economy->save();

                GameLog::log($user, "🚨 BETRUGSDEZERNAT ALARMIERT: Brandstiftung bei {$serverName} aufgeflogen! Server beschlagnahmt, \${$penalty} Strafe gezahlt.", 'danger', 'security');

                // Server seized / deleted (no payout)
                $server->delete();
                if ($rack) $rack->recalculatePowerAndHeat();

                return [
                    'success' => false, // Will throw 400 with message
                    'error' => "AUFFLIEGEN! Das Versicherungs-Audit hat die Brandstiftung entdeckt. Server beschlagnahmt, Strafe: \${$penalty}!"
                ];
            }
        });
    }
}
