<?php

namespace App\Services\Admin;

use App\Models\AdminAuditLog;
use App\Models\GameConfigHistory;
use Illuminate\Support\Facades\Auth;

class AdminLogService
{
    /**
     * Log an admin action.
     */
    public static function log($action, $target = null, $changes = null)
    {
        AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'target_type' => $target ? get_class($target) : null,
            'target_id' => $target ? $target->id : null,
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Log a config change and save version history.
     */
    public static function logConfigChange($key, $oldValue, $newValue, $comment = null)
    {
        $lastVersion = GameConfigHistory::where('config_key', $key)->max('version') ?? 0;

        GameConfigHistory::create([
            'config_key' => $key,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'user_id' => Auth::id(),
            'comment' => $comment,
            'version' => $lastVersion + 1,
        ]);

        self::log('update_config', null, [
            'key' => $key,
            'old' => $oldValue,
            'new' => $newValue,
            'comment' => $comment
        ]);
    }
}
