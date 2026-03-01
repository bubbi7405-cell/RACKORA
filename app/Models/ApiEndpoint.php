<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiEndpoint extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'server_id',
        'path',
        'method',
        'status',
        'rpm',
        'max_rpm',
        'latency_ms',
        'uptime',
        'revenue_per_1k_req',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
        'rpm' => 'integer',
        'max_rpm' => 'integer',
        'latency_ms' => 'float',
        'uptime' => 'float',
        'revenue_per_1k_req' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function toGameState(): array
    {
        return [
            'id' => $this->id,
            'serverId' => $this->server_id,
            'path' => $this->path,
            'method' => $this->method,
            'status' => $this->status,
            'rpm' => $this->rpm,
            'maxRpm' => $this->max_rpm,
            'latency' => $this->latency_ms,
            'uptime' => $this->uptime,
            'revenue' => $this->revenue_per_1k_req,
            'config' => $this->config,
        ];
    }
}
