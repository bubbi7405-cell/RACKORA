<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Get profile data for the active user
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Auto-initialize if critical data is missing (Self-healing)
        if (!$user->economy) {
             $gameService = app(\App\Services\Game\GameStateService::class);
             $gameService->initializePlayer($user);
             $user->refresh();
        }

        $user->load('economy');
        
        // Fetch security logs (logins, etc) from GameLog
        $securityLogs = \App\Models\GameLog::where('user_id', $user->id)
            ->where('category', 'security')
            ->latest()
            ->limit(15)
            ->get();

        // Fetch historical stats for charting
        $statsHistory = \App\Models\GameStatistic::where('user_id', $user->id)
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        // Fetch achievements
        $achievementService = app(\App\Services\Game\AchievementService::class);
        $achievements = $achievementService->getAchievementsForUser($user);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'company_name' => $user->company_name,
                    'company_logo' => $user->company_logo,
                    'slogan' => $user->slogan,
                    'avatar' => $user->avatar,
                    'banner' => $user->banner,
                    'accent_color' => $user->accent_color,
                    'specialization' => $user->specialization,
                    'created_at' => $user->created_at,
                    'last_active_at' => $user->last_active_at,
                ],
                'stats' => $user->economy ? $user->economy->toGameState() : [
                    'balance' => 0,
                    'level' => 1,
                    'reputation' => 0,
                    'xp' => 0,
                    'xp_to_next_level' => 1000
                ],
                'stats_history' => $statsHistory,
                'security_logs' => $securityLogs,
                'achievements' => $achievements->take(6),
                'preferences' => $user->settings ?? [
                    'theme' => 'dark',
                    'volume' => 0.5,
                    'notifications' => true,
                    'alert_intensity' => 'high',
                    'sound_enabled' => true,
                ]
            ]
        ]);
    }

    /**
     * Update basic profile info
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'company_name' => 'nullable|string|max:50',
            'slogan' => 'nullable|string|max:100',
            'accent_color' => 'nullable|string|max:20',
        ]);

        $user->update($request->only('name', 'email', 'company_name', 'slogan', 'accent_color'));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'user' => $user
        ]);
    }

    /**
     * Update user password
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = Auth::user();
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        \App\Models\GameLog::log($user, "Password changed successfully", 'info', 'security');

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.'
        ]);
    }

    /**
     * Update game preferences (stored in JSON settings)
     */
    public function updatePreferences(Request $request)
    {
        $user = Auth::user();
        $user->settings = array_merge($user->settings ?? [], $request->all());
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Preferences saved.',
            'preferences' => $user->settings
        ]);
    }

    /**
     * Get active sessions
     */
    public function sessions(Request $request)
    {
        $user = Auth::user();
        $tokens = $user->tokens()->orderBy('last_used_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'sessions' => $tokens->map(function($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'last_used_at' => $token->last_used_at,
                    'created_at' => $token->created_at,
                    'is_current' => $token->id === Auth::user()->currentAccessToken()->id,
                    'ip' => 'Unknown', // Sanctum doesn't store IP by default unless customized
                    'agent' => 'Unknown'
                ];
            })
        ]);
    }

    /**
     * Revoke a specific session
     */
    public function revokeSession(Request $request, $id)
    {
        $user = Auth::user();
        $user->tokens()->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Session revoked.'
        ]);
    }

    /**
     * GDPR Data Export
     */
    public function exportData(Request $request)
    {
        $user = Auth::user();
        
        // Eager load everything for the export
        $user->load([
            'economy', 'network', 'rooms.racks.servers', 
            'customers', 'events', 'achievements', 'employees',
            'certifications', 'audits'
        ]);

        $data = [
            'profile' => $user->toArray(),
            'game_logs' => \App\Models\GameLog::where('user_id', $user->id)->get()->toArray(),
            'statistics' => \App\Models\GameStatistic::where('user_id', $user->id)->get()->toArray(),
            'generated_at' => now()->toIso8601String(),
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Delete Account (DANGER)
     */
    public function deleteAccount(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password',
            'confirm' => 'required|accepted'
        ]);

        $user = Auth::user();

        // Log the deletion for audit (system level, not user level since user is gone)
        \Illuminate\Support\Facades\Log::info("User deleted account: {$user->id} ({$user->email})");

        // Revoke tokens first
        $user->tokens()->delete();

        // Delete the user (cascading deletes should handle relations if DB is set up right, 
        // otherwise we might need manual cleanup, but for now assuming cascade or acceptable orphans)
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully.'
        ]);
    }

    /**
     * Upload avatar or banner image
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'type' => 'required|in:avatar,banner,company_logo',
            'image' => 'required|image|max:2048', // 2MB max
        ]);

        $user = Auth::user();
        $type = $request->input('type');

        // Delete old image if exists
        if ($user->$type) {
             Storage::disk('public')->delete($user->$type);
        }

        $path = $request->file('image')->store('profile/' . $type . 's', 'public');
        
        $user->$type = $path;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => ucfirst($type) . ' updated successfully.',
            'url' => Storage::disk('public')->url($path),
            'path' => $path
        ]);
    }
}
