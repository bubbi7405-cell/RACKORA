<?php

namespace App\Models;

use App\Enums\ServerStatus;
use App\Enums\ServerType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Server extends Model
{
    use HasUuids;

    protected $fillable = [
        'rack_id',
        'type',
        'model_name',
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
        'monthly_depreciation',
        'provisioning_started_at',
        'provisioning_completes_at',
        'last_maintenance_at',
        'specs',
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
        'monthly_depreciation' => 'decimal:2',
        'provisioning_started_at' => 'datetime',
        'provisioning_completes_at' => 'datetime',
        'last_maintenance_at' => 'datetime',
        'specs' => 'array',
    ];

    public function rack(): BelongsTo
    {
        return $this->belongsTo(ServerRack::class, 'rack_id');
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

    public function completeProvisioning(): void
    {
        $this->status = ServerStatus::ONLINE;
        $this->provisioning_started_at = null;
        $this->provisioning_completes_at = null;
        $this->save();
    }

    public function getEndSlot(): int
    {
        return $this->start_slot + $this->size_u - 1;
    }

    public function getAvailableVserverSlots(): int
    {
        return $this->vserver_capacity - $this->vservers_used;
    }

    public function canHostVserver(): bool
    {
        return $this->type === ServerType::VSERVER_NODE && 
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
            'heat' => (float) $this->heat_output_kw,
            'specs' => [
                'cpuCores' => $this->cpu_cores,
                'ramGb' => $this->ram_gb,
                'storageTb' => $this->storage_tb,
                'bandwidthMbps' => $this->bandwidth_mbps,
            ],
            'vserver' => [
                'capacity' => $this->vserver_capacity,
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
            'warnings' => [
                'damaged' => $this->isDamaged(),
                'needsMaintenance' => !$this->isHealthy(),
            ],
            'purchaseCost' => (float) $this->purchase_cost,
            'activeOrdersCount' => $this->activeOrders->count(),
        ];
    }
}
