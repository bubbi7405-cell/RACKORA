<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModernizationMission extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'target_id',
        'target_type',
        'assigned_employee_ids',
        'status',
        'cost',
        'started_at',
        'completes_at',
    ];

    protected $casts = [
        'assigned_employee_ids' => 'array',
        'cost' => 'decimal:2',
        'started_at' => 'datetime',
        'completes_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
