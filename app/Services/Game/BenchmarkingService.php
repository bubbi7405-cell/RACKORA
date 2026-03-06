<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\GameConfig;
use App\Models\GameLog;
use Illuminate\Support\Facades\DB;

class BenchmarkingService
{
    /**
     * Get the secret optimal settings for a specific model and user.
     * These settings grant a permanent efficiency bonus if found.
     */
    public function getSecretSettings(User $user, string $modelKey): array
    {
        // Deterministic but unique per user/model
        $hash = crc32($user->id . $modelKey);
        mt_srand($hash);
        
        // Target is usually a 10-25% overclock with 5-15% overvolting
        $targetClockMod = 1.10 + (mt_rand(0, 150) / 1000); 
        $targetVoltageMod = 1.05 + (mt_rand(0, 100) / 1000);
        
        mt_srand(); // Reset seed
        
        return [
            'target_clock_mod' => round($targetClockMod, 3),
            'target_voltage_mod' => round($targetVoltageMod, 3),
        ];
    }

    /**
     * Run a simulated benchmark test.
     * Does not damage real hardware, but costs a fee.
     */
    public function runTest(User $user, string $modelKey, float $clockMod, float $voltageMod): array
    {
        $cost = 500; // Testing fee
        if (!$user->economy->canAfford($cost)) {
            return ['success' => false, 'error' => 'Insufficient funds for lab testing. ($500 required)'];
        }

        $user->economy->debit($cost, "Hardware Benchmarking: {$modelKey}", 'research');

        $secrets = $this->getSecretSettings($user, $modelKey);
        
        // Calculate stability using TuningService logic (roughly)
        // Clock penalty: 10% OC = 15% penalty
        $clockPenalty = max(0, ($clockMod - 1.0) * 150);
        // Voltage support: 10% OV = 12% support
        $voltageSupport = max(0, ($voltageMod - 1.0) * 120);
        
        $stability = 100.0 - $clockPenalty + $voltageSupport;
        $stability = max(0, min(100, $stability));

        // Precision check for secret settings (within 1% tolerance)
        $isSecretFound = (
            abs($clockMod - $secrets['target_clock_mod']) <= 0.01 &&
            abs($voltageMod - $secrets['target_voltage_mod']) <= 0.01
        );

        $success = mt_rand(1, 100) <= $stability;
        
        if ($isSecretFound && $success) {
            $this->unlockOptimization($user, $modelKey, $clockMod, $voltageMod);
        }

        return [
            'success' => true,
            'test_passed' => $success,
            'stability' => round($stability, 1),
            'is_secret_found' => $isSecretFound,
            'performance_gain' => round(($clockMod - 1.0) * 100, 1),
            'thermal_load' => round(($voltageMod - 1.0) * 200, 1),
        ];
    }

    protected function unlockOptimization(User $user, string $modelKey, float $clock, float $voltage): void
    {
        $economy = $user->economy;
        $meta = $economy->metadata ?? [];
        $benchmarks = $meta['benchmarks'] ?? [];

        if (isset($benchmarks[$modelKey]['optimized']) && $benchmarks[$modelKey]['optimized']) {
            return; // Already unlocked
        }

        $benchmarks[$modelKey] = [
            'optimized' => true,
            'voltage' => $voltage,
            'clock' => $clock,
            'unlocked_at' => now()->toIso8601String(),
        ];

        $meta['benchmarks'] = $benchmarks;
        $economy->metadata = $meta;
        $economy->save();

        GameLog::log($user, "🔬 BENCHMARK COMPLETE: Found 'Secret Settings' for {$modelKey}! All units of this model now gain +10% Efficiency.", 'success', 'research');
    }

    public function getUserBenchmarks(User $user): array
    {
        return $user->economy->metadata['benchmarks'] ?? [];
    }
}
