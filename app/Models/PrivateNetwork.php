<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrivateNetwork extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'cidr',
        'vlan_tag',
        'server_count'
    ];
    
    protected $casts = [
        'server_count' => 'integer',
        'vlan_tag' => 'string'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class, 'private_network_id');
    }

    public function firewallRules(): HasMany
    {
        return $this->hasMany(PrivateNetworkFirewallRule::class)->orderBy('priority', 'asc');
    }
    
    /**
     * Convert to game state array.
     */
    public function toGameState(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cidr' => $this->cidr,
            'vlanTag' => $this->vlan_tag,
            'serverCount' => $this->servers()->count(),
            'firewallRules' => $this->firewallRules->map(fn($r) => [
                'id' => $r->id,
                'type' => $r->type,
                'protocol' => $r->protocol,
                'port_range' => $r->port_range,
                'source_cidr' => $r->source_cidr,
                'priority' => $r->priority,
                'description' => $r->description,
            ]),
            'metrics' => [
                'allowed' => $this->traffic_allowed_count ?? 0,
                'denied' => $this->traffic_denied_count ?? 0,
            ]
        ];
    }
}
