<?php

namespace App\Services\Game;

use App\Models\Server;
use App\Models\GameLog;
use Illuminate\Support\Facades\Cache;

class SoftwareService
{
    // Caching duration for software catalog
    const CACHE_TTL = 3600;

    /**
     * Get available software definitions.
     */
    public function getDefinitions(): array
    {
        return Cache::remember('software_catalog', self::CACHE_TTL, function () {
            return [
                // Web Servers
                'nginx_latest' => [
                    'id' => 'nginx_latest',
                    'name' => 'Nginx (Latest)',
                    'category' => 'web_server',
                    'version' => '1.25.3',
                    'install_time' => 30, // seconds
                    'cost' => 0,
                    'requirements' => ['os_family' => ['linux', 'bsd'], 'ram' => 0.5, 'cpu' => 0.1],
                    'performance_bonus' => 1.1, // +10% web hosting revenue/capacity
                    'description' => 'High-performance web server and reverse proxy.',
                ],
                'apache_httpd' => [
                    'id' => 'apache_httpd',
                    'name' => 'Apache HTTP Server',
                    'category' => 'web_server',
                    'version' => '2.4.58',
                    'install_time' => 45,
                    'cost' => 0,
                    'requirements' => ['os_family' => ['linux', 'windows'], 'ram' => 1.0, 'cpu' => 0.2],
                    'performance_bonus' => 1.05,
                    'description' => 'Reliable and extensible web server with wide module support.',
                ],
                'litespeed_ent' => [
                    'id' => 'litespeed_ent',
                    'name' => 'LiteSpeed Enterprise',
                    'category' => 'web_server',
                    'version' => '6.2.1',
                    'install_time' => 20,
                    'cost' => 40, // Monthly license cost? Or one-time install cost? Let's say one-time setup fee + monthly (billing logic needed separate)
                    'license_cost' => 10, // monthly
                    'requirements' => ['os_family' => ['linux'], 'ram' => 2.0, 'cpu' => 0.5],
                    'performance_bonus' => 1.25,
                    'description' => 'Event-driven, high-performance web server. Drop-in Apache replacement.',
                ],

                // Databases
                'mysql_community' => [
                    'id' => 'mysql_community',
                    'name' => 'MySQL Community Server',
                    'category' => 'database',
                    'version' => '8.0',
                    'install_time' => 60,
                    'cost' => 0,
                    'requirements' => ['os_family' => ['linux', 'windows'], 'ram' => 2.0, 'cpu' => 1.0],
                    'performance_bonus' => 1.0,
                    'description' => 'Standard relational database management system.',
                ],
                'postgresql' => [
                    'id' => 'postgresql',
                    'name' => 'PostgreSQL 16',
                    'category' => 'database',
                    'version' => '16.1',
                    'install_time' => 75,
                    'cost' => 0,
                    'requirements' => ['os_family' => ['linux', 'windows'], 'ram' => 2.0, 'cpu' => 1.0],
                    'performance_bonus' => 1.15, // Better for complex queries
                    'description' => 'Advanced open source relational database.',
                ],
                'redis' => [
                    'id' => 'redis',
                    'name' => 'Redis',
                    'category' => 'database', 
                    'type' => 'cache',
                    'version' => '7.2',
                    'install_time' => 15,
                    'cost' => 0,
                    'requirements' => ['os_family' => ['linux'], 'ram' => 1.0, 'cpu' => 0.2],
                    'performance_bonus' => 1.2, // Huge boost for web hosting latency
                    'description' => 'In-memory data structure store, used as a database, cache, and message broker.',
                ],

                // Game Servers
                'minecraft_java' => [
                    'id' => 'minecraft_java',
                    'name' => 'Minecraft Java Server',
                    'category' => 'game_server',
                    'version' => '1.20.4',
                    'install_time' => 120,
                    'cost' => 0,
                    'requirements' => ['os_family' => ['linux', 'windows'], 'ram' => 4.0, 'cpu' => 2.0],
                    'performance_bonus' => 1.0,
                    'description' => 'Official dedicated server software for Minecraft: Java Edition.',
                ],
                'csgo_dedicated' => [
                    'id' => 'csgo_dedicated',
                    'name' => 'CS:GO Dedicated Server',
                    'category' => 'game_server',
                    'version' => 'Active Duty',
                    'install_time' => 300,
                    'cost' => 0,
                    'requirements' => ['os_family' => ['linux', 'windows'], 'ram' => 2.0, 'cpu' => 1.0],
                    'performance_bonus' => 1.0,
                    'description' => 'Dedicated server for Counter-Strike: Global Offensive.',
                ],
                 'palworld_server' => [
                    'id' => 'palworld_server',
                    'name' => 'Palworld Dedicated Server',
                    'category' => 'game_server',
                    'version' => 'Latest',
                    'install_time' => 600, // Large download
                    'cost' => 0,
                    'requirements' => ['os_family' => ['linux', 'windows'], 'ram' => 16.0, 'cpu' => 4.0],
                    'performance_bonus' => 1.0,
                    'description' => 'Dedicated server for Palworld. Memory hungry.',
                ],

                // DevOps / Tools
                'docker_engine' => [
                    'id' => 'docker_engine',
                    'name' => 'Docker Engine',
                    'category' => 'tool',
                    'version' => '24.0',
                    'install_time' => 45,
                    'cost' => 0,
                    'requirements' => ['os_family' => ['linux'], 'ram' => 2.0, 'cpu' => 1.0],
                    'performance_bonus' => 1.1, // Containerization efficiency
                    'description' => 'Platform for developing, shipping, and running applications in containers.',
                ],
                 'cpanel' => [
                    'id' => 'cpanel',
                    'name' => 'cPanel & WHM',
                    'category' => 'tool',
                    'version' => 'Latest',
                    'install_time' => 900,
                    'cost' => 50, // install fee
                    'license_cost' => 15, // monthly
                    'requirements' => ['os_family' => ['linux'], 'os_specific' => ['centos', 'rhel', 'ubuntu_lts'], 'ram' => 2.0, 'cpu' => 1.0],
                    'performance_bonus' => 1.3, // Management efficiency -> higher revenue
                    'description' => 'Industry standard web hosting control panel. Installing this CONVERTS the server into a highly dense Shared Hosting Node (20 web hosting contracts per core).',
                ],
            ];
        });
    }

    /**
     * Install software on a server.
     */
    public function install(Server $server, string $softwareId): void
    {
        $defs = $this->getDefinitions();
        if (!isset($defs[$softwareId])) {
            throw new \Exception("Software definition not found: {$softwareId}");
        }
        
        $software = $defs[$softwareId];

        // 1. Check Requirements
        // OS Requirement
        $osType = $server->installed_os_type; 
        if (!$osType) {
             throw new \Exception("No OS installed on server.");
        }
        
        // Simple OS Family mapping
        $osFamily = $this->getOsFamily($osType);
        
        if (isset($software['requirements']['os_family']) && !in_array($osFamily, $software['requirements']['os_family'])) {
             // Exception or user friendly error
             throw new \Exception("Incompatible OS. {$software['name']} requires " . implode('/', $software['requirements']['os_family']) . ", but server has {$osType} ({$osFamily}).");
        }
        
        if (isset($software['requirements']['os_specific']) && !in_array($osType, $software['requirements']['os_specific'])) {
             throw new \Exception("Incompatible OS Distribution. {$software['name']} requires specific OS types.");
        }

        // Resource Requirements
        if ($server->ram_gb < ($software['requirements']['ram'] ?? 0)) {
             throw new \Exception("Insufficient RAM.");
        }
        
        // Check duplicate installation
        $installed = $server->installed_applications ?? [];
        foreach ($installed as $app) {
            if ($app['id'] === $softwareId) {
                throw new \Exception("Software is already installed.");
            }
        }

        // 2. Start Installation Process
        $server->app_install_status = 'installing';
        $server->app_installing_id = $softwareId;
        $server->app_install_started_at = now();
        $server->app_install_completes_at = now()->addSeconds($software['install_time'] ?? 30);

        $server->save();
        
        // 3. Apply Costs
        if (($software['cost'] ?? 0) > 0) {
            $user = $server->tenant ?? $server->rack->room->user; // Owner pays? Usually owner.
            if ($user) {
                $user->economy->debit($software['cost'], "Software Purchase: {$software['name']}", 'capex', $server);
            }
        }

        GameLog::log($server->tenant ?? \App\Models\User::first(), "Installed {$software['name']} on {$server->model_name}.", 'success', 'software');
    }

    public function processInstallTick(Server $server): void
    {
        if ($server->app_install_status !== 'installing' || !$server->app_install_completes_at) {
            return;
        }

        if (now()->greaterThanOrEqualTo($server->app_install_completes_at)) {
            $this->finalizeInstallation($server);
        }
    }

    private function finalizeInstallation(Server $server): void
    {
        $softwareId = $server->app_installing_id;
        $defs = $this->getDefinitions();
        $software = $defs[$softwareId];

        $app = [
            'id' => $softwareId,
            'name' => $software['name'],
            'version' => $software['version'],
            'installed_at' => now()->toIso8601String(),
            'status' => 'active',
        ];

        $installed = $server->installed_applications ?? [];
        $installed[] = $app;
        
        $server->installed_applications = $installed;
        $server->app_install_status = 'installed';
        $server->app_installing_id = null;
        $server->app_install_completes_at = null;

        // Domain-specific conversions (e.g. cPanel)
        if ($softwareId === 'cpanel') {
            $server->type = \App\Enums\ServerType::SHARED_NODE;
            $server->vserver_capacity = max(50, (int) ($server->getEffectiveCpuCores() * 20));
        }

        $server->save();

        GameLog::log($server->tenant ?? \App\Models\User::first(), "Installation of {$software['name']} complete on {$server->model_name}.", 'success', 'software');
    }

    public function uninstall(Server $server, string $softwareId): void
    {
        $installed = $server->installed_applications ?? [];
        $newInstalled = [];
        $found = false;
        
        foreach ($installed as $app) {
            if (($app['id'] ?? '') === $softwareId) {
                $found = true;
                continue;
            }
            $newInstalled[] = $app;
        }

        if (!$found) {
            throw new \Exception("Software '{$softwareId}' is not installed on this server.");
        }

        $server->installed_applications = $newInstalled;

        // Revert domain-specific conversions or type changes
        if ($softwareId === 'cpanel') {
            $server->type = \App\Enums\ServerType::DEDICATED;
            $server->vserver_capacity = 0;
            $server->vservers_used = 0;
        }

        $server->save();
        
        GameLog::log($server->tenant ?? \App\Models\User::first(), "Uninstalled '{$softwareId}' from server {$server->model_name}.", 'warning', 'software');
    }

    /**
     * Update an application to the latest version.
     */
    public function update(Server $server, string $softwareId): void
    {
        $installed = $server->installed_applications ?? [];
        $defs = $this->getDefinitions();
        
        if (!isset($defs[$softwareId])) {
            throw new \Exception("Software definition for '{$softwareId}' not found in catalog.");
        }

        $latest = $defs[$softwareId];
        $found = false;
        $updatedList = [];

        foreach ($installed as $app) {
            if (($app['id'] ?? '') === $softwareId) {
                $found = true;
                $app['version'] = $latest['version'];
                $app['updated_at'] = now()->toIso8601String();
                $app['status'] = 'active';
            }
            $updatedList[] = $app;
        }

        if (!$found) {
            throw new \Exception("Cannot update '{$softwareId}': not installed.");
        }

        // Apply a small "Update Fee" (10% of original cost or base $5)
        $updateFee = max(5, ($latest['cost'] ?? 0) * 0.1);
        $user = $server->tenant ?? $server->rack->room->user;
        if ($user && !$user->economy->canAfford($updateFee)) {
            throw new \Exception("Insufficient funds for update fee ($" . number_format($updateFee, 2) . ")");
        }

        if ($user) {
            $user->economy->debit($updateFee, "Software Update: {$latest['name']} to v{$latest['version']}", 'maintenance', $server);
        }

        $server->installed_applications = $updatedList;
        $server->save();

        GameLog::log($user, "Updated {$latest['name']} to version {$latest['version']} on {$server->model_name}.", 'success', 'software');
    }

    private function getOsFamily(string $osType): string
    {
        if (str_contains($osType, 'windows')) return 'windows';
        if (str_contains($osType, 'bsd')) return 'bsd';
        return 'linux'; // Default to linux
    }
}
