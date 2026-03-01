<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpAllocation extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'server_id',
        'order_id',
        'type',
        'address',
        'subnet_cidr',
        'purpose',
        'status',
        'region',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Scope to allocated IPs.
     */
    public function scopeAllocated($query)
    {
        return $query->where('status', 'allocated');
    }

    /**
     * Scope to available IPs.
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope to IPv4 addresses.
     */
    public function scopeIpv4($query)
    {
        return $query->where('type', 'ipv4');
    }

    /**
     * Scope to IPv6 addresses.
     */
    public function scopeIpv6($query)
    {
        return $query->where('type', 'ipv6');
    }

    /**
     * Get display label for status.
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'allocated' => 'IN_USE',
            'available' => 'AVAILABLE',
            'reserved' => 'RESERVED',
            'blacklisted' => 'BLOCKED',
            default => 'UNKNOWN',
        };
    }
}
