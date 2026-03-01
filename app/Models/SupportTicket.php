<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'customer_id',
        'subject',
        'description',
        'status',
        'priority',
        'complexity',
        'progress',
        'assigned_employee_id',
        'expires_at',
        'resolved_at',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'resolved_at' => 'datetime',
        'complexity' => 'integer',
        'progress' => 'integer',
        'metadata' => 'json',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    /**
     * Convert to game state array for frontend.
     */
    public function toGameState(): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customer_id,
            'customerName' => $this->customer?->name ?? 'System Alert',
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'complexity' => $this->complexity,
            'progress' => $this->progress,
            'assignedEmployee' => $this->employee?->name,
            'expiresAt' => $this->expires_at?->toIso8601String(),
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
