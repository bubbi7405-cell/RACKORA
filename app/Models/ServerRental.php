<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerRental extends Model
{
    use HasUuids;

    protected $fillable = [
        'provider_id',
        'tenant_id',
        'server_id',
        'price_per_hour',
        'status',
        'rented_at',
        'expires_at',
    ];

    protected $casts = [
        'price_per_hour' => 'decimal:2',
        'rented_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }
}
