<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    /**
     * Toggle an automation setting for the player.
     */
    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string|in:auto_reboot,auto_provisioning,auto_cleanup,cooling_automation',
            'value' => 'required|boolean',
        ]);

        $user = $request->user();
        
        // Ensure player is initialized
        if (!$user->economy) {
            return response()->json(['success' => false, 'error' => 'Player not initialized'], 400);
        }

        // Check research for specific modules
        if ($request->key === 'auto_cleanup' && !$user->isResearched('auto_cleanup')) {
            return response()->json(['success' => false, 'error' => 'Research required: Garbage Collector Script'], 403);
        }
        if ($request->key === 'cooling_automation' && !$user->isResearched('cooling_automation')) {
            return response()->json(['success' => false, 'error' => 'Research required: Adaptive Thermal Governor'], 403);
        }

        $user->economy->setAutomation($request->key, $request->value);

        return response()->json([
            'success' => true,
            'settings' => $user->economy->automation_settings,
        ]);
    }
}
