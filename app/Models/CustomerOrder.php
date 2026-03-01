<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CustomerOrder extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'product_type',
        'requirements',
        'price_per_month',
        'status',
        'assigned_server_id',
        'contract_months',
        'is_negotiable',
        'base_price_requested',
        'negotiation_attempts',
        'ordered_at',
        'patience_expires_at',
        'provisioned_at',
        'expires_at',
        'sla_tier',
        'uptime_percent',
        'downtime_ticks',
        'total_ticks',
        'current_latency_ms',
        'metrics_history',
    ];

    protected $casts = [
        'requirements' => 'array',
        'price_per_month' => 'decimal:2',
        'ordered_at' => 'datetime',
        'patience_expires_at' => 'datetime',
        'provisioned_at' => 'datetime',
        'provisioning_started_at' => 'datetime',
        'provisioning_completes_at' => 'datetime',
        'expires_at' => 'datetime',
        'uptime_percent' => 'decimal:2',
        'metrics_history' => 'array',
        'current_latency_ms' => 'float',
    ];

    public function isProvisioningComplete(): bool
    {
        return $this->provisioning_completes_at && now()->greaterThanOrEqualTo($this->provisioning_completes_at);
    }

    public const SLA_THRESHOLDS = [
        'standard' => 99.00,
        'premium' => 99.90,
        'enterprise' => 99.99,
        'whale' => 99.999,
    ];

    public function getSlaThreshold(): float
    {
        return self::SLA_THRESHOLDS[$this->sla_tier] ?? 99.00;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'assigned_server_id');
    }

    public function assignedServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'assigned_server_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPatienceExpired(): bool
    {
        return $this->patience_expires_at && 
               Carbon::now()->gte($this->patience_expires_at);
    }

    public function getPatienceRemainingSeconds(): int
    {
        if (!$this->patience_expires_at || !$this->isPending()) {
            return 0;
        }
        
        $remaining = Carbon::now()->diffInSeconds($this->patience_expires_at, false);
        return (int) max(0, $remaining);
    }

    public function getPatienceProgress(): float
    {
        if (!$this->ordered_at || !$this->patience_expires_at) {
            return 0;
        }

        $total = $this->ordered_at->diffInSeconds($this->patience_expires_at);
        $elapsed = $this->ordered_at->diffInSeconds(Carbon::now());

        if ($total <= 0) return 100;
        
        return min(100, ($elapsed / $total) * 100);
    }

    public function provision(Server $server): void
    {
        $this->assigned_server_id = $server->id;
        $this->status = 'provisioning';
        $this->save();
    }

    public function activate(): void
    {
        $this->status = 'active';
        $this->provisioned_at = now();
        $this->expires_at = now()->addMonths($this->contract_months);
        $this->save();
    }

    public function cancel(): void
    {
        $this->status = 'cancelled';
        $this->save();

        // Decrease customer satisfaction
        $this->customer->registerIncident();
    }

    public function expire(): void
    {
        $this->status = 'completed';
        $this->save();
    }

    public function getHourlyValue(): float
    {
        return $this->price_per_month / (30 * 24);
    }

    public function toGameState(): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customer_id,
            'customerName' => $this->customer?->company_name,
            'targetRegion' => $this->customer?->preferences['target_region'] ?? null,
            'greenPreference' => $this->customer?->preferences['green_preference'] ?? false,
            'productType' => $this->product_type,
            'requirements' => $this->requirements,
            'pricePerMonth' => (float) $this->price_per_month,
            'hourlyValue' => $this->getHourlyValue(),
            'status' => $this->status,
            'assignedServerId' => $this->assigned_server_id,
            'contractMonths' => $this->contract_months,
            'orderedAt' => $this->ordered_at->toIso8601String(),
            'patience' => [
                'expiresAt' => $this->patience_expires_at?->toIso8601String(),
                'remainingSeconds' => $this->getPatienceRemainingSeconds(),
                'progress' => $this->getPatienceProgress(),
                'isExpired' => $this->isPatienceExpired(),
            ],
            'provisionedAt' => $this->provisioned_at?->toIso8601String(),
            'provisioning' => [
                'startedAt' => $this->provisioning_started_at?->toIso8601String(),
                'completesAt' => $this->provisioning_completes_at?->toIso8601String(),
            ],
            'expiresAt' => $this->expires_at?->toIso8601String(),
            'sla' => [
                'tier' => $this->sla_tier,
                'target' => $this->getSlaThreshold(),
                'current' => (float) $this->uptime_percent,
                'isViolated' => $this->uptime_percent < $this->getSlaThreshold(),
            ],
            'negotiation' => [
                'isNegotiable' => (bool) $this->is_negotiable,
                'basePriceRequested' => (float) $this->base_price_requested,
                'attempts' => (int) $this->negotiation_attempts,
            ],
            'metrics' => [
                'currentLatency' => (float) $this->current_latency_ms,
                'history' => $this->metrics_history ?? [],
            ]
        ];
    }
}
