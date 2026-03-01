<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaign extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'cost',
        'duration_minutes',
        'started_at',
        'ends_at',
        'status',
        'results',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'started_at' => 'datetime',
        'ends_at' => 'datetime',
        'results' => 'array',
        'duration_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && 
               $this->started_at && 
               now()->gte($this->started_at) && 
               now()->lt($this->ends_at);
    }
}
