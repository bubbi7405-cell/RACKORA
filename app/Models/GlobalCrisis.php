<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalCrisis extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'type',      // solar_flare, fiber_cut, crypto_ransom
        'phase',     // warning, impact, aftermath
        'started_at',
        'impact_starts_at', // When buffer ends
        'resolved_at',
        'severity',  // 1-5
        'data'       // { "progress": 50, "damage_count": 0, "target_region": "us_east" }
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'impact_starts_at' => 'datetime',
        'resolved_at' => 'datetime',
        'severity' => 'integer',
        'data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return is_null($this->resolved_at);
    }

    public function isWarningPhase(): bool
    {
        return $this->phase === 'warning';
    }

    public function isImpactPhase(): bool
    {
        return $this->phase === 'impact';
    }
}
