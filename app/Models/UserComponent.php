<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserComponent extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'component_type',
        'component_key',
        'assigned_server_id',
        'status',
        'delivery_status',
        'delivery_type',
        'health',
        'purchased_at',
        'arrival_at',
        'is_leased',
        'lease_cost_per_hour',
        'needs_shredding',
        'meta',
    ];

    protected $casts = [
        'health' => 'decimal:2',
        'purchased_at' => 'datetime',
        'arrival_at' => 'datetime',
        'is_leased' => 'boolean',
        'lease_cost_per_hour' => 'decimal:2',
        'needs_shredding' => 'boolean',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'assigned_server_id');
    }

    public function getConfig(): ?array
    {
        $allComponents = GameConfig::get('server_components', []);
        return $allComponents[$this->component_type][$this->component_key] ?? null;
    }

    public function toGameState(): array
    {
        $config = $this->getConfig();
        return [
            'id' => $this->id,
            'type' => $this->component_type,
            'key' => $this->component_key,
            'name' => $config['name'] ?? 'Unknown Component',
            'assignedServerId' => $this->assigned_server_id,
            'status' => $this->status,
            'deliveryStatus' => $this->delivery_status,
            'deliveryType' => $this->delivery_type,
            'arrivalAt' => $this->arrival_at ? $this->arrival_at->toIso8601String() : null,
            'health' => (float) $this->health,
            'isLeased' => (bool) $this->is_leased,
            'leaseCostPerHour' => (float) $this->lease_cost_per_hour,
            'needsShredding' => (bool) $this->needs_shredding,
            'config' => $config,
        ];
    }
}
