<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SandboxController extends Controller
{
    /**
     * Simulate a server build to see resulting stats.
     */
    public function simulate(Request $request): JsonResponse
    {
        $request->validate([
            'motherboard_key' => 'required|string',
            'cpu_key' => 'required|string',
            'cpu_count' => 'required|integer|min:1',
            'ram_key' => 'required|string',
            'ram_count' => 'required|integer|min:1',
            'storage_key' => 'required|string',
            'storage_count' => 'required|integer|min:1',
        ]);

        $allComponents = GameConfig::get('server_components', []);
        
        $mb = $allComponents['motherboard'][$request->motherboard_key] ?? null;
        $cpu = $allComponents['cpu'][$request->cpu_key] ?? null;
        $ram = $allComponents['ram'][$request->ram_key] ?? null;
        $storage = $allComponents['storage'][$request->storage_key] ?? null;

        if (!$mb || !$cpu || !$ram || !$storage) {
            return response()->json(['success' => false, 'error' => 'One or more components not found'], 404);
        }

        // Validate counts against motherboard slots
        if ($request->cpu_count > ($mb['cpu_slots'] ?? 1)) {
            return response()->json(['success' => false, 'error' => 'Too many CPUs for this motherboard'], 400);
        }
        if ($request->ram_count > ($mb['ram_slots'] ?? 4)) {
            return response()->json(['success' => false, 'error' => 'Too many RAM sticks for this motherboard'], 400);
        }
        if ($request->storage_count > ($mb['storage_slots'] ?? 2)) {
            return response()->json(['success' => false, 'error' => 'Too many storage drives for this motherboard'], 400);
        }

        // Calculate Stats
        $totalCores = ($cpu['cores'] ?? 0) * $request->cpu_count;
        $totalRam = ($ram['size_gb'] ?? 0) * $request->ram_count;
        $totalStorage = ($storage['size_tb'] ?? 0) * $request->storage_count;
        
        $totalPowerW = ($mb['base_power_draw_w'] ?? 20);
        $totalPowerW += ($cpu['power_draw_w'] ?? 0) * $request->cpu_count;
        $totalPowerW += ($ram['power_draw_w'] ?? 0) * $request->ram_count;
        $totalPowerW += ($storage['power_draw_w'] ?? 0) * $request->storage_count;

        $totalPrice = $mb['price'];
        $totalPrice += $cpu['price'] * $request->cpu_count;
        $totalPrice += $ram['price'] * $request->ram_count;
        $totalPrice += $storage['price'] * $request->storage_count;

        return response()->json([
            'success' => true,
            'data' => [
                'name' => "Simulated " . $mb['size_u'] . "U Build",
                'size_u' => $mb['size_u'],
                'power_draw_kw' => round($totalPowerW / 1000, 3),
                'heat_output_kw' => round($totalPowerW / 1000, 3),
                'cpu_cores' => $totalCores,
                'ram_gb' => $totalRam,
                'storage_tb' => $totalStorage,
                'total_price' => $totalPrice,
                'vserver_capacity' => $totalCores * 4,
                'slots_used' => [
                    'cpu' => $request->cpu_count . '/' . $mb['cpu_slots'],
                    'ram' => $request->ram_count . '/' . $mb['ram_slots'],
                    'storage' => $request->storage_count . '/' . $mb['storage_slots'],
                ]
            ]
        ]);
    }
}
