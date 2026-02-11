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
            'key' => 'required|string|in:auto_reboot,auto_provisioning',
            'value' => 'required|boolean',
        ]);

        $user = $request->user();
        
        // Ensure player is initialized
        if (!$user->economy) {
            return response()->json(['success' => false, 'error' => 'Player not initialized'], 400);
        }

        $user->economy->setAutomation($request->key, $request->value);

        return response()->json([
            'success' => true,
            'settings' => $user->economy->automation_settings,
        ]);
    }
}
