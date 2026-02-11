<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\EventType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class GameEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'severity',
        'status',
        'title',
        'description',
        'affected_room_id',
        'affected_rack_id',
        'affected_server_id',
        'available_actions',
        'chosen_action',
        'warning_at',
        'escalates_at',
        'deadline_at',
        'resolved_at',
        'consequences',
        'damage_cost',
        'affected_customers_count',
    ];

    protected $casts = [
        'type' => EventType::class,
        'status' => EventStatus::class,
        'available_actions' => 'array',
        'consequences' => 'array',
        'warning_at' => 'datetime',
        'escalates_at' => 'datetime',
        'deadline_at' => 'datetime',
        'resolved_at' => 'datetime',
        'damage_cost' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function affectedRoom(): BelongsTo
    {
        return $this->belongsTo(GameRoom::class, 'affected_room_id');
    }

    public function affectedRack(): BelongsTo
    {
        return $this->belongsTo(ServerRack::class, 'affected_rack_id');
    }

    public function affectedServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'affected_server_id');
    }

    public function shouldEscalate(): bool
    {
        return $this->status === EventStatus::ACTIVE &&
               $this->escalates_at &&
               Carbon::now()->gte($this->escalates_at);
    }

    public function shouldAutoFail(): bool
    {
        return $this->status->isActive() &&
               $this->deadline_at &&
               Carbon::now()->gte($this->deadline_at);
    }

    public function getTimeRemainingSeconds(): int
    {
        if (!$this->deadline_at || $this->status->isResolved()) {
            return 0;
        }
        
        $remaining = Carbon::now()->diffInSeconds($this->deadline_at, false);
        return max(0, $remaining);
    }

    public function getUrgencyPercent(): float
    {
        if (!$this->warning_at || !$this->deadline_at) {
            return 0;
        }

        $total = $this->warning_at->diffInSeconds($this->deadline_at);
        $elapsed = $this->warning_at->diffInSeconds(Carbon::now());

        if ($total <= 0) return 100;
        
        return min(100, ($elapsed / $total) * 100);
    }

    public function escalate(): void
    {
        $this->status = EventStatus::ESCALATED;
        $this->severity = 'critical';
        $this->save();
    }

    public function resolve(string $action, array $consequences = []): void
    {
        $this->status = EventStatus::RESOLVED;
        $this->chosen_action = $action;
        $this->resolved_at = now();
        $this->consequences = $consequences;
        $this->save();
    }

    public function fail(array $consequences = []): void
    {
        $this->status = EventStatus::FAILED;
        $this->resolved_at = now();
        $this->consequences = $consequences;
        $this->save();
    }

    public function toGameState(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'typeLabel' => $this->type->label(),
            'typeColor' => $this->type->color(),
            'typeIcon' => $this->type->icon(),
            'severity' => $this->severity,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'statusColor' => $this->status->color(),
            'title' => $this->title,
            'description' => $this->description,
            'affectedRoomId' => $this->affected_room_id,
            'affectedRackId' => $this->affected_rack_id,
            'affectedServerId' => $this->affected_server_id,
            'availableActions' => $this->available_actions,
            'chosenAction' => $this->chosen_action,
            'timing' => [
                'warningAt' => $this->warning_at->toIso8601String(),
                'escalatesAt' => $this->escalates_at?->toIso8601String(),
                'deadlineAt' => $this->deadline_at->toIso8601String(),
                'resolvedAt' => $this->resolved_at?->toIso8601String(),
                'remainingSeconds' => $this->getTimeRemainingSeconds(),
                'urgencyPercent' => $this->getUrgencyPercent(),
            ],
            'consequences' => $this->consequences,
            'damageCost' => (float) $this->damage_cost,
            'affectedCustomersCount' => $this->affected_customers_count,
            'isActive' => $this->status->isActive(),
        ];
    }
}
