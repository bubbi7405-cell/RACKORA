<?php

namespace App\Services\Game;

use App\Models\Server;
use App\Models\GameConfig;
use App\Models\GameLog;
use Carbon\Carbon;

class OsService
{
    /**
     * The OS Catalog defining all available operating systems.
     */
    public function getDefinitions(): array
    {
        return [
            // Linux Distributions (Free)
            'ubuntu_lts' => [
                'name' => 'Ubuntu Server LTS',
                'family' => 'linux',
                'version' => '22.04',
                'license_cost' => 0.00,
                'security_base' => 90,
                'performance_mod' => 1.0,
                'install_time' => 30, // seconds
                'tags' => ['general', 'web', 'db', 'lts'],
                'description' => 'The standard for stability and support. Good for general workloads.'
            ],
            'debian_stable' => [
                'name' => 'Debian Stable',
                'family' => 'linux',
                'version' => '12',
                'license_cost' => 0.00,
                'security_base' => 95,
                'performance_mod' => 1.02, // Lightweight
                'install_time' => 45,
                'tags' => ['stable', 'server', 'minimal'],
                'description' => 'Rock-solid stability with minimal overhead.'
            ],
            'almalinux' => [
                'name' => 'AlmaLinux',
                'family' => 'linux',
                'version' => '9',
                'license_cost' => 0.00,
                'security_base' => 88,
                'performance_mod' => 0.98,
                'install_time' => 40,
                'tags' => ['enterprise-clone', 'rhel-compatible'],
                'description' => 'Community enterprise operating system.'
            ],
            'arch_linux' => [
                'name' => 'Arch Linux (Risk/High Perf)',
                'family' => 'linux',
                'version' => 'Rolling',
                'license_cost' => 0.00,
                'security_base' => 60, // Bleeding edge = risky
                'performance_mod' => 1.05, // Optimized
                'install_time' => 20,
                'tags' => ['performance', 'rolling', 'risk'],
                'description' => 'Bleeding edge performance, but high instability risk.'
            ],

            // Enterprise Linux (Paid)
            'rhel_enterprise' => [
                'name' => 'Red Hat Enterprise Linux',
                'family' => 'linux',
                'version' => '9',
                'license_cost' => 50.00, // Monthly subscription
                'security_base' => 98,
                'performance_mod' => 0.97, // Heavy security overhead
                'install_time' => 60,
                'tags' => ['enterprise', 'supported', 'secure'],
                'description' => 'The gold standard for enterprise deployments.'
            ],
            'suse_enterprise' => [
                'name' => 'SUSE Linux Enterprise',
                'family' => 'linux',
                'version' => '15',
                'license_cost' => 45.00,
                'security_base' => 96,
                'performance_mod' => 0.98,
                'install_time' => 60,
                'tags' => ['enterprise', 'sap', 'europe'],
                'description' => 'Optimized for critical business applications.'
            ],

            // Windows Server (Paid License)
            'windows_standard' => [
                'name' => 'Windows Server Standard',
                'family' => 'windows',
                'version' => '2022',
                'license_cost' => 25.00,
                'security_base' => 75,
                'performance_mod' => 0.90, // Higher overhead
                'install_time' => 120, // Slower install
                'tags' => ['windows', 'aspnet', 'domain'],
                'description' => 'Standard edition for Windows environments.'
            ],
            'windows_datacenter' => [
                'name' => 'Windows Server Datacenter',
                'family' => 'windows',
                'version' => '2022',
                'license_cost' => 150.00,
                'security_base' => 80,
                'performance_mod' => 0.92,
                'install_time' => 150,
                'tags' => ['windows', 'virtualization', 'unlimited'],
                'description' => 'Unlimited virtualization rights and advanced features.'
            ],

            // Specialized OS
            'gaming_optimized' => [
                'name' => 'GameServer OS (Low Latency)',
                'family' => 'special',
                'version' => 'v2.4',
                'license_cost' => 10.00,
                'security_base' => 70,
                'performance_mod' => 1.15, // +15% Performance for Gaming
                'install_time' => 30,
                'tags' => ['gaming', 'latency-tuned', 'udp'],
                'description' => 'Custom kernel tuned for low latency and high UDP packet rates.'
            ],
            'ai_compute' => [
                'name' => 'NeuralOS (AI Compute)',
                'family' => 'special',
                'version' => 'v1.0',
                'license_cost' => 100.00,
                'security_base' => 85,
                'performance_mod' => 1.10, // +10% for AI Tasks
                'install_time' => 90,
                'tags' => ['ai', 'gpu', 'cuda'],
                'description' => 'Pre-installed CUDA drivers and PyTorch. Optimized for GPU nodes.'
            ],
            'storage_optimized' => [
                'name' => 'VaultFS (Storage)',
                'family' => 'special',
                'version' => 'v3.1',
                'license_cost' => 0.00, // Open Source
                'security_base' => 92,
                'performance_mod' => 1.00,
                'install_time' => 45,
                'tags' => ['storage', 'zfs', 'redundancy'],
                'description' => 'Optimized for high-throughput storage arrays.'
            ],

            // Proprietary OS
            'pony_os_v1' => [
                'name' => 'PonyOS v1.0 (Alpha)',
                'family' => 'proprietary',
                'version' => 'Alpha 1',
                'license_cost' => 0.00, // Proprietary = Free to own
                'security_base' => 65, // Alpha status = lower security
                'performance_mod' => 1.25, // Optimized for your hardware
                'install_time' => 15,
                'tags' => ['proprietary', 'performance', 'alpha'],
                'description' => 'Ihr hauseigenes Betriebssystem. Extreme Performance, aber noch im Alpha-Stadium.',
                'research_req' => 'proprietary_os_v1',
            ],
        ];
    }

    /**
     * Get a specific OS definition.
     */
    public function getDefinition(string $osType): array
    {
        $defs = $this->getDefinitions();
        return $defs[$osType] ?? [];
    }

    /**
     * Start the installation process on a server.
     */
    public function install(Server $server, string $osType): void
    {
        $defs = $this->getDefinitions();

        if (!isset($defs[$osType])) {
            throw new \Exception("Invalid OS type: {$osType}");
        }

        $def = $defs[$osType];

        // Status Check
        if ($server->status === 'provisioning' || $server->os_install_status === 'installing') {
            throw new \Exception("Server is already provisioning an OS.");
        }

        // License Cost Check? (Assuming cost is monthly, not one-time install, but prompts usually imply setup fee? 
        // User prompt: "Linux: Free... Windows: Lizenzkosten pro Core". 
        // I'll deduct monthly in billing loop, but maybe setup fee here? 
        // For simple MVP: No setup fee, just monthly.)
        
        $server->os_install_status = 'installing';
        // Set server status to provisioning if not already
        $previousStatus = $server->status;
        $server->status = 'provisioning';
        
        $server->installed_os_type = $osType;
        $server->installed_os_version = $def['version'];
        
        $server->os_install_started_at = now();
        $server->os_install_completes_at = now()->addSeconds($def['install_time']);
        
        // Reset Health/Security on reinstall
        $server->os_health = 100.00;
        $server->security_patch_level = (float) $def['security_base']; 
        $server->compatibility_score = $this->calculateCompatibility($server, $def);
        
        // Set License Info
        $server->license_type = $def['license_cost'] > 0 ? 'paid' : 'free';
        $server->license_status = $def['license_cost'] > 0 ? 'trial' : 'active'; // Start logic
        
        $server->save();
        
        GameLog::log($server->tenant ?? \App\Models\User::first(), "Started installation of {$def['name']} on Server {$server->model_name}", 'info', 'server');
    }

    /**
     * Tick function to check install progress.
     */
    public function processInstallTick(Server $server): void
    {
        if ($server->os_install_status !== 'installing') {
            return;
        }

        if (now()->greaterThanOrEqualTo($server->os_install_completes_at)) {
            // Installation Complete
            $server->os_install_status = 'installed';
            $server->status = 'online'; // Boot up!
            $server->os_install_completes_at = null;
            $server->save();
            
            GameLog::log($server->tenant ?? \App\Models\User::first(), "Installation of {$server->installed_os_type} complete on Server {$server->model_name}", 'success', 'server');
        }
        
        // Calculate CPU Usage during install?
        // Maybe simulate load in loop?
    }

    /**
     * Calculate compatibility score (Simplified).
     */
    private function calculateCompatibility(Server $server, array $def): float
    {
        $score = 100.0;
        
        // AI OS needs GPU
        if (in_array('ai', $def['tags']) && !str_contains($server->type->value, 'gpu')) {
            $score -= 40;
        }
        
        // Gaming OS needs high CPU
        if (in_array('gaming', $def['tags']) && $server->cpu_cores < 8) {
            $score -= 20;
        }
        
        // Windows needs RAM
        if ($def['family'] === 'windows' && $server->ram_gb < 8) {
            $score -= 15;
        }
        
        return max(0, $score);
    }
}
