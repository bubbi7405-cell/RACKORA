<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\Server;
use App\Models\ServerTemplate;
use Illuminate\Support\Collection;

class RolloutService
{
    /**
     * Create a template from an existing server's configuration.
     */
    public function createTemplate(User $user, Server $server, string $name): ServerTemplate
    {
        return ServerTemplate::create([
            'user_id' => $user->id,
            'name' => $name,
            'os_type' => $server->installed_os_type,
            'os_version' => $server->installed_os_version,
            'installed_applications' => $server->installed_applications ?? [],
            'install_cost_mult' => 1.25, // Using templates is slightly more expensive due to automation overhead
        ]);
    }

    /**
     * Apply a template to a server.
     */
    public function applyTemplate(Server $server, ServerTemplate $template): bool
    {
        $osService = app(\App\Services\Game\OsService::class);
        $def = $osService->getDefinition($template->os_type);
        
        if (empty($def)) {
            return false;
        }

        if ($server->status === 'provisioning' || $server->os_install_status === 'installing') {
            return false;
        }

        // Use standard OS install logic first
        $osService->install($server, $template->os_type);

        // Apply template customizations (Applications & Speedup)
        $server->installed_applications = $template->installed_applications;
        
        // Automation bonus: Templates install 25% faster than manual OS setup
        $baseDuration = $def['install_time'];
        $server->os_install_completes_at = now()->addSeconds((int)($baseDuration * 0.75));
        
        $server->save();
        
        return true;
    }

    /**
     * Get all templates for a user.
     */
    public function getUserTemplates(User $user): Collection
    {
        return $user->serverTemplates;
    }

    /**
     * Delete a template.
     */
    public function deleteTemplate(User $user, string $id): bool
    {
        $template = $user->serverTemplates()->find($id);
        if ($template) {
            return $template->delete();
        }
        return false;
    }
}
