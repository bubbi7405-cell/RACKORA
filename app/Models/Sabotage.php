<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sabotage extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'target_competitor_id',
        'target_user_id',
        'type',
        'status',
        'cost',
        'detected',
        'result',
        'resolved_at',
    ];

    protected $casts = [
        'result' => 'array',
        'resolved_at' => 'datetime',
        'detected' => 'boolean',
        'cost' => 'decimal:2',
    ];

    public function attacker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class, 'target_competitor_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
