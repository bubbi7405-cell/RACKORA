<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivateNetworkFirewallRule extends Model
{
    use HasUuids;

    protected $fillable = [
        'private_network_id',
        'type', // ALLOW, DENY
        'protocol', // TCP, UDP, ICMP, ANY
        'port_range',
        'source_cidr',
        'priority',
        'description'
    ];

    protected $casts = [
        'priority' => 'integer',
    ];

    public function network(): BelongsTo
    {
        return $this->belongsTo(PrivateNetwork::class, 'private_network_id');
    }
}
