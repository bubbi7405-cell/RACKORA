<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'room_id',
        'type',
        'specialization',
        'name',
        'level',
        'salary',
        'efficiency',
        'stress',
        'energy',
        'current_task',
        'task_progress',
        'total_actions',
        'hired_at',
        'xp',
        'skill_points',
        'perks',
        'sabbatical_until',
        'loyalty',
        'metadata',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'efficiency' => 'decimal:2',
        'stress' => 'decimal:2',
        'energy' => 'decimal:2',
        'task_progress' => 'decimal:2',
        'loyalty' => 'decimal:2',
        'hired_at' => 'datetime',
        'perks' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * FEATURE 284: Check if employee is on sabbatical
     */
    public function isOnSabbatical(): bool
    {
        return $this->sabbatical_until && \Carbon\Carbon::parse($this->sabbatical_until)->isFuture();
    }

    /**
     * Get remaining sabbatical time in human-readable format
     */
    public function getSabbaticalTimeRemaining(): ?string
    {
        if (!$this->isOnSabbatical()) return null;
        return \Carbon\Carbon::parse($this->sabbatical_until)->diffForHumans();
    }

    /**
     * FEATURE 161: Check if employee is at a seminar
     */
    public function isOnSeminar(): bool
    {
        return isset($this->metadata['seminar_until']) && \Carbon\Carbon::parse($this->metadata['seminar_until'])->isFuture();
    }
}
