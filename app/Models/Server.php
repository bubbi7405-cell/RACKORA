<?php

namespace App\Models;

use App\Enums\ServerStatus;
use App\Enums\ServerType;
use App\Enums\BackupPlan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Server extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'rack_id',
        'type',
        'model_name',
        'hardware_generation',
        'size_u',
        'start_slot',
        'power_draw_kw',
        'heat_output_kw',
        'cpu_cores',
        'ram_gb',
        'storage_tb',
        'bandwidth_mbps',
        'vserver_capacity',
        'vservers_used',
        'status',
        'current_fault',
        'is_diagnosed',
        'health',
        'purchase_cost',
        'resale_value',
        'monthly_depreciation',
        'provisioning_started_at',
        'provisioning_completes_at',
        'last_maintenance_at',
        'maintenance_scheduled_at',
        'maintenance_log',
        'specs',
        'total_runtime_seconds',
        'lifespan_seconds',
        'purchase_date',
        'backup_plan',
        'last_backup_at',
        'backup_health',
        'led_color',
        'custom_rgb',
        'installed_os_type',
        'installed_os_version',
        'os_install_status',
        'os_install_started_at',
        'os_install_completes_at',
        'app_install_status',
        'app_installing_id',
        'app_install_started_at',
        'app_install_completes_at',
        'os_health',
        'security_patch_level',
        'is_auto_updates_enabled',
        'license_type',
        'license_status',
        'license_expires_at',
        'compatibility_score',
        'compatibility_score',
        'os_config',
        'installed_applications',
        'app_install_status',
        'app_installing_id',
        'app_install_completes_at',
        'last_backup_data',
        'private_network_id',
        'private_ip_address',
        'is_leased',
        'lease_cost_per_hour',
        'nickname',
        'cpu_clock_mhz',
        'cpu_voltage_v',
        'base_voltage_v',
        'is_mining',
        'total_mined_crypto',
        'battery_capacity_kwh',
        'battery_level_kwh',
        'weight_kg',
        'failover_server_id',
        'power_priority',
        'metadata',
    ];

    protected $casts = [
        'type' => ServerType::class,
        'status' => ServerStatus::class,
        'current_fault' => 'string',
        'is_diagnosed' => 'boolean',
        'power_draw_kw' => 'decimal:2',
        'heat_output_kw' => 'decimal:2',
        'health' => 'decimal:2',
        'purchase_cost' => 'decimal:2',
        'resale_value' => 'decimal:2',
        'monthly_depreciation' => 'decimal:2',
        'provisioning_started_at' => 'datetime',
        'provisioning_completes_at' => 'datetime',
        'last_maintenance_at' => 'datetime',
        'maintenance_scheduled_at' => 'datetime',
        'maintenance_log' => 'array',
        'specs' => 'array',
        'purchase_date' => 'datetime',
        'total_runtime_seconds' => 'integer',
        'lifespan_seconds' => 'integer',
        'backup_plan' => BackupPlan::class,
        'last_backup_at' => 'datetime',
        'backup_health' => 'decimal:2',
        'custom_rgb' => 'array',
        'os_install_started_at' => 'datetime',
        'os_install_completes_at' => 'datetime',
        'app_install_started_at' => 'datetime',
        'app_install_completes_at' => 'datetime',
        'os_health' => 'decimal:2',
        'security_patch_level' => 'decimal:2',
        'is_auto_updates_enabled' => 'boolean',
        'license_expires_at' => 'datetime',
        'compatibility_score' => 'decimal:2',
        'os_config' => 'array',
        'installed_applications' => 'array',
        'last_backup_data' => 'array',
        'is_leased' => 'boolean',
        'lease_cost_per_hour' => 'decimal:2',
        'cpu_clock_mhz' => 'integer',
        'cpu_voltage_v' => 'decimal:3',
        'base_voltage_v' => 'decimal:3',
        'is_mining' => 'boolean',
        'total_mined_crypto' => 'decimal:8',
        'battery_capacity_kwh' => 'decimal:2',
        'battery_level_kwh' => 'decimal:2',
        'weight_kg' => 'decimal:2',
        'power_priority' => 'integer',
        'metadata' => 'json',
    ];

    protected $attributes = [
        'status' => 'offline',
        'backup_plan' => 'none',
        'backup_health' => 100.0,
    ];

    public function rack(): BelongsTo
    {
        return $this->belongsTo(ServerRack::class, 'rack_id');
    }

    public function privateNetwork(): BelongsTo
    {
        return $this->belongsTo(PrivateNetwork::class, 'private_network_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function generationModel()
    {
        return $this->belongsTo(HardwareGeneration::class, 'hardware_generation', 'generation');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CustomerOrder::class, 'assigned_server_id');
    }

    public function activeOrders(): HasMany
    {
        return $this->orders()->where('status', 'active');
    }

    /**
     * Check if provisioning is complete (timestamp-based)
     */
    public function isProvisioningComplete(): bool
    {
        if ($this->status !== ServerStatus::PROVISIONING) {
            return true;
        }
        
        return $this->provisioning_completes_at && 
               Carbon::now()->gte($this->provisioning_completes_at);
    }

    /**
     * Get remaining provisioning time in seconds
     */
    public function getProvisioningRemainingSeconds(): int
    {
        if (!$this->provisioning_completes_at) {
            return 0;
        }
        
        $remaining = Carbon::now()->diffInSeconds($this->provisioning_completes_at, false);
        return max(0, $remaining);
    }

    public function getProvisioningProgress(): float
    {
        if ($this->status !== ServerStatus::PROVISIONING) {
            return $this->status === ServerStatus::ONLINE ? 100 : 0;
        }

        if (!$this->provisioning_started_at || !$this->provisioning_completes_at) {
            return 0;
        }

        $total = $this->provisioning_started_at->diffInSeconds($this->provisioning_completes_at);
        $elapsed = $this->provisioning_started_at->diffInSeconds(Carbon::now());

        if ($total <= 0) return 100;
        
        return min(100, ($elapsed / $total) * 100);
    }

    public function startProvisioning(int $durationSeconds = 300, float $speedBonus = 0.0): void
    {
        // Apply provisioning speed research bonus
        if ($speedBonus > 0) {
            $durationSeconds = (int) ($durationSeconds * (1 - min(0.8, $speedBonus)));
        }
        
        $this->status = ServerStatus::PROVISIONING;
        $this->provisioning_started_at = Carbon::now();
        $this->provisioning_completes_at = Carbon::now()->addSeconds(max(30, $durationSeconds));
        $this->save();
    }

    public function addMaintenanceLogEntry(string $type, string $description, ?float $cost = null): void
    {
        $log = $this->maintenance_log ?? [];
        array_unshift($log, [
            'id' => uniqid(),
            'timestamp' => now()->toIso8601String(),
            'type' => $type, // 'repair', 'maintenance', 'diagnose'
            'description' => $description,
            'cost' => $cost,
        ]);
        
        // Keep last 10 entries
        $this->maintenance_log = array_slice($log, 0, 10);
        $this->save();
    }

    public function completeProvisioning(): void
    {
        $this->status = ServerStatus::ONLINE;
        $this->provisioning_started_at = null;
        $this->provisioning_completes_at = null;
        $this->save();
    }

    /**
     * Synchronize all installation and provisioning states (JIT)
     */
    public function syncTaskStates(): void
    {
        // 1. Provisioning
        if ($this->status === ServerStatus::PROVISIONING && $this->isProvisioningComplete()) {
            $this->completeProvisioning();
            $this->rack?->recalculatePowerAndHeat();
        }

        // 2. OS Installation
        if ($this->os_install_status === 'installing' && $this->os_install_completes_at && now()->gte($this->os_install_completes_at)) {
            app(\App\Services\Game\OsService::class)->processInstallTick($this);
        }

        // 3. Application Installation
        if ($this->app_install_status === 'installing' && $this->app_install_completes_at && now()->gte($this->app_install_completes_at)) {
            app(\App\Services\Game\SoftwareService::class)->processInstallTick($this);
        }
    }

    public function getEndSlot(): int
    {
        return $this->start_slot + $this->size_u - 1;
    }

    public function getAvailableVserverSlots(): int
    {
        return $this->getEffectiveVserverCapacity() - $this->vservers_used;
    }

    /**
     * FEATURE 201: Get effective vServer capacity considering hypervisor research.
     */
    public function getEffectiveVserverCapacity(): int
    {
        $base = $this->vserver_capacity;
        $owner = $this->rack?->room?->user;
        if ($owner) {
            $multiplier = app(\App\Services\Game\ResearchService::class)->getBonus($owner, 'vserver_multiplier');
            if ($multiplier > 0) {
                $base = (int) ($base * $multiplier);
            }
        }
        return $base;
    }

    public function canHostVserver(): bool
    {
        $canHost = $this->type === ServerType::VSERVER_NODE || 
                   $this->type === ServerType::CUSTOM || 
                   $this->type === ServerType::EXPERIMENTAL;
        return $canHost && 
               $this->status->canAcceptOrders() &&
               $this->getAvailableVserverSlots() > 0;
    }

    public function isDamaged(): bool
    {
        return $this->health < 50;
    }

    public function isHealthy(): bool
    {
        return $this->health >= 80;
    }

    /**
     * Get percentage of lifespan consumed.
     */
    public function getLifespanUsage(): float
    {
        if ($this->lifespan_seconds <= 0) return 100;
        return min(100, ($this->total_runtime_seconds / $this->lifespan_seconds) * 100);
    }

    public function getEfficiencyPenalty(): float
    {
        // Efficiency Loss Curve:
        // 0-50% life: 0% penalty
        // 50-100% life: linear 0% -> 50% penalty
        // >100% life: 50% + 5% per 10% over
        
        $usage = $this->getLifespanUsage();
        
        if ($usage <= 50) return 0.0;
        
        if ($usage <= 100) {
            // Map 50-100 to 0-0.50
            return ($usage - 50) / 100; 
        }
        
        // > 100% usage
        $base = 0.50;
        $over = $usage - 100;
        return $base + ($over / 200); // Slower growth after EOL
    }

    public function getEffectivePowerDraw(): float
    {
        $base = (float) $this->power_draw_kw;
        $agingPenalty = $this->getEfficiencyPenalty();

        // FEATURE 254: Overvolting Impact on Power
        $tuningMod = 1.0;
        if ($this->base_voltage_v > 0 && $this->base_clock_mhz > 0) {
            $vRatio = $this->cpu_voltage_v / $this->base_voltage_v;
            $fRatio = $this->cpu_clock_mhz / $this->base_clock_mhz;
            $tuningMod = ($vRatio * $vRatio) * $fRatio;
        }

        // FEATURE 65: Crypto Mining overrides
        if ($this->is_mining) {
            $tuningMod = 1.5;
        }

        // FEATURE 244: Benchmarking Bonus
        $benchmarkBonus = 1.0;
        $owner = $this->rack?->room?->user;
        if ($owner) {
             $benchmarks = $owner->economy->metadata['benchmarks'] ?? [];
             if (isset($benchmarks[$this->model_name]['optimized']) && $benchmarks[$this->model_name]['optimized']) {
                 $benchmarkBonus = 0.90; // 10% Power reduction = Higher efficiency
             }
        }

        return $base * (1.0 + $agingPenalty) * $tuningMod * $benchmarkBonus;
    }

    public function getEffectiveHeatOutput(): float
    {
        $base = (float) $this->heat_output_kw;
        $agingPenalty = $this->getEfficiencyPenalty();

        // FEATURE 254: Overvolting Impact on Heat
        $tuningMod = 1.0;
        if ($this->base_voltage_v > 0 && $this->base_clock_mhz > 0) {
            $vRatio = $this->cpu_voltage_v / $this->base_voltage_v;
            $fRatio = $this->cpu_clock_mhz / $this->base_clock_mhz;
            $tuningMod = ($vRatio * $vRatio) * $fRatio;
        }

        // FEATURE 65: Crypto Mining
        if ($this->is_mining) {
            $tuningMod = 1.8;
        }

        // FEATURE 244: Benchmarking Bonus
        $benchmarkBonus = 1.0;
        $owner = $this->rack?->room?->user;
        if ($owner) {
             $benchmarks = $owner->economy->metadata['benchmarks'] ?? [];
             if (isset($benchmarks[$this->model_name]['optimized']) && $benchmarks[$this->model_name]['optimized']) {
                 $benchmarkBonus = 0.90;
             }
        }

        // Dust impact could be added here if we want to move it from rack to server
        return $base * (1.0 + $agingPenalty * 1.5) * $tuningMod * $benchmarkBonus;
    }

    public function getOsPerformanceModifier(): float
    {
        if (!$this->installed_os_type) return 1.0;
        
        // This service call might be expensive in loops, consider caching on model or eager loading
        $def = app(\App\Services\Game\OsService::class)->getDefinition($this->installed_os_type);
        return $def['performance_modifier'] ?? 1.0;
    }

    public function getEffectiveCpuCores(): float
    {
        $base = (float) $this->cpu_cores;
        $osMod = $this->getOsPerformanceModifier();
        
        // FEATURE 194: LN2 Overclocking (Global bonus)
        $overclockMod = 1.0;
        $overclockUntil = $this->specs['overclocked_until'] ?? null;
        if ($overclockUntil && Carbon::now()->lt(Carbon::parse($overclockUntil))) {
            $overclockMod = 4.0; // +300%
        }

        // FEATURE 254: Manual Overclocking (Local tuning)
        $tuningMod = 1.0;
        if ($this->base_clock_mhz > 0) {
            $tuningMod = $this->cpu_clock_mhz / $this->base_clock_mhz;
        }

        return $base * $osMod * $overclockMod * $tuningMod;
    }

    public function calculateResaleValue(): float
    {
        // FEATURE 260: Secondary Market Resale
        // Price = PurchaseCost * (Health/100) * (RemainingLife/TotalLife) * 0.8 (Base resale)
        $base = (float) $this->purchase_cost;
        if ($base <= 0) return 0.0;

        $healthMod = $this->health / 100.0;
        $lifeMod = 1.0 - ($this->getLifespanUsage() / 100.0);
        
        $resale = $base * $healthMod * max(0.2, $lifeMod) * 0.8;
        
        return round($resale, 2);
    }

    public function toGameState(): array
    {
        return [
            'id' => $this->id,
            'rackId' => $this->rack_id,
            'type' => $this->type->value,
            'modelName' => $this->model_name,
            'sizeU' => $this->size_u,
            'startSlot' => $this->start_slot,
            'endSlot' => $this->getEndSlot(),
            'power' => (float) $this->power_draw_kw,
            'effectivePower' => $this->getEffectivePowerDraw(),
            'heat' => (float) $this->heat_output_kw,
            'effectiveHeat' => $this->getEffectiveHeatOutput(),
            'specs' => array_merge([
                'cpuCores' => $this->cpu_cores,
                'effectiveCpuCores' => $this->getEffectiveCpuCores(), // NEW
                'ramGb' => $this->ram_gb,
                'storageTb' => $this->storage_tb,
                'bandwidthMbps' => $this->bandwidth_mbps,
            ], $this->specs ?? []),
            'vserver' => [
                'capacity' => $this->vserver_capacity,
                'effectiveCapacity' => $this->getEffectiveVserverCapacity(),
                'used' => $this->vservers_used,
                'available' => $this->getAvailableVserverSlots(),
            ],
            'status' => $this->status->value,
            'statusColor' => $this->status->color(),
            'currentFault' => $this->current_fault,
            'isDiagnosed' => (bool) $this->is_diagnosed,
            'health' => (float) $this->health,
            'provisioning' => [
                'isProvisioning' => $this->status === ServerStatus::PROVISIONING,
                'progress' => $this->getProvisioningProgress(),
                'remainingSeconds' => $this->getProvisioningRemainingSeconds(),
                'completesAt' => $this->provisioning_completes_at?->toIso8601String(),
            ],
            'aging' => [
                'totalRuntime' => $this->total_runtime_seconds,
                'lifespan' => $this->lifespan_seconds,
                'wearPercentage' => $this->getLifespanUsage(),
                'efficiencyPenalty' => $this->getEfficiencyPenalty(), // NEW
                'purchaseDate' => $this->purchase_date?->toIso8601String(),
            ],
            'os' => [
                'type' => $this->installed_os_type,
                'version' => $this->installed_os_version,
                'status' => $this->os_install_status, // none, installing, installed
                'health' => (float) $this->os_health,
                'security' => (float) $this->security_patch_level,
                'compatibility' => (float) $this->compatibility_score,
                'license' => $this->license_status,
                'autoUpdate' => (bool) $this->is_auto_updates_enabled,
                'installStartedAt' => $this->os_install_started_at?->toIso8601String(),
                'installCompletesAt' => $this->os_install_completes_at?->toIso8601String(),
            ],
            'software' => [
                'installed' => $this->installed_applications ?? [],
                'status' => $this->app_install_status, // none, installing, installed
                'installingId' => $this->app_installing_id,
                'installStartedAt' => $this->app_install_started_at?->toIso8601String(),
                'installCompletesAt' => $this->app_install_completes_at?->toIso8601String(),
            ],
            'networking' => [
                'privateNetworkId' => $this->private_network_id,
                'privateIp' => $this->private_ip_address,
            ],
            'warnings' => [
                'damaged' => $this->isDamaged(),
                'needsMaintenance' => !$this->isHealthy(),
                'endOfLife' => $this->getLifespanUsage() > 90,
                'inefficient' => $this->getEfficiencyPenalty() > 0.10,
            ],
            'purchaseCost' => (float) $this->purchase_cost,
            'resaleValue' => (float) ($this->resale_value ?? $this->purchase_cost * 0.8),
            'hardwareGeneration' => $this->hardware_generation,
            'activeOrdersCount' => $this->activeOrders->count(),
            'backup' => [
                'plan' => $this->backup_plan?->value ?? 'none',
                'lastBackupAt' => $this->last_backup_at?->toIso8601String(),
                'health' => (float) ($this->backup_health ?? 100),
                'hourlyCost' => $this->backup_plan?->hourlyCost() ?? 0.0,
                'recoveryChance' => $this->backup_plan?->recoveryChance() ?? 0,
            ],
            'maintenanceLog' => $this->maintenance_log ?? [],
            'ledColor' => $this->led_color,
            'customRgb' => $this->custom_rgb,
            'tenantId' => $this->tenant_id,
            'isLeased' => (bool) $this->is_leased,
            'leaseCostPerHour' => (float) $this->lease_cost_per_hour,
            'nickname' => $this->nickname,
            'powerPriority' => (int) $this->power_priority,
            'tuning' => [
                'cpuClock' => $this->cpu_clock_mhz,
                'cpuVoltage' => (float) $this->cpu_voltage_v,
                'baseClock' => $this->base_clock_mhz,
                'baseVoltage' => (float) $this->base_voltage_v,
                'stability' => app(\App\Services\Game\TuningService::class)->calculateStability($this),
                'degradationMult' => app(\App\Services\Game\TuningService::class)->getDegradationMultiplier($this),
                'isOverclocked' => $this->cpu_clock_mhz > $this->base_clock_mhz,
                'isOvervolted' => $this->cpu_voltage_v > $this->base_voltage_v,
            ],
            'mining' => [
                'isMining' => (bool) $this->is_mining,
                'totalMined' => (float) $this->total_mined_crypto,
            ],
            'battery' => [
                'capacity' => (float) $this->battery_capacity_kwh,
                'level' => (float) $this->battery_level_kwh,
                'percent' => $this->battery_capacity_kwh > 0 ? (float) ($this->battery_level_kwh / $this->battery_capacity_kwh) * 100 : 0,
            ],
            'resaleValue' => $this->calculateResaleValue(),
        ];
    }
}
