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
        'replay_data',
        'damage_cost',
        'affected_customers_count',
        'management_score',
        'management_grade',
        'action_cost',
        'has_post_mortem',
        'reputation_recovered',
        'affected_region',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'type' => EventType::class,
        'status' => EventStatus::class,
        'available_actions' => 'array',
        'consequences' => 'array',
        'replay_data' => 'array',
        'warning_at' => 'datetime',
        'escalates_at' => 'datetime',
        'deadline_at' => 'datetime',
        'resolved_at' => 'datetime',
        'damage_cost' => 'decimal:2',
        'has_post_mortem' => 'boolean',
        'reputation_recovered' => 'decimal:2',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActions(): array
    {
        return $this->available_actions ?? [];
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

    public function getPostMortem(): array
    {
        if ($this->status->isActive()) {
            return [];
        }

        $isSuccess = $this->status === EventStatus::RESOLVED;
        $lessons = [];

        // Type-specific lessons
        switch ($this->type) {
            case EventType::OVERHEATING:
                if (!$isSuccess) $lessons[] = "Check your room cooling capacity. Overheating occurs when rack heat exceeds room cooling.";
                else $lessons[] = "Prompt response prevented permanent server damage.";
                break;
            case EventType::POWER_OUTAGE:
                $lessons[] = "Investing in UPS research can provide critical extra minutes during blackouts.";
                break;
            case EventType::DDOS_ATTACK:
                $lessons[] = "Higher Network Tiers (Fiber/Backbone) provide better resistance to volumetric attacks.";
                break;
            case EventType::HARDWARE_FAILURE:
                $lessons[] = "Technicians can automatically fix minor hardware faults before they escalate.";
                break;
        }

        // Grade-specific feedback
        if ($isSuccess) {
            switch ($this->management_grade) {
                case 'S': $lessons[] = "Exceptional performance. Response time was near-instant."; break;
                case 'C':
                case 'D': $lessons[] = "Response was slow. Consider automating repetitive manual tasks."; break;
            }
        } else {
            $lessons[] = "Unresolved incidents lead to massive reputation loss and potential customer churn.";
        }

        return [
            'lessons' => $lessons,
            'summary' => $isSuccess ? "Incident successfully contained." : "Incident response failed.",
            'timeToResolve' => $this->resolved_at ? $this->warning_at->diffForHumans($this->resolved_at, true) : 'N/A',
        ];
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
            'managementScore' => $this->management_score,
            'managementGrade' => $this->management_grade,
            'actionCost' => (float) $this->action_cost,
            'isActive' => $this->status->isActive(),
            'postMortem' => $this->getPostMortem(),
            'hasPostMortem' => $this->has_post_mortem,
            'reputationRecovered' => (float) $this->reputation_recovered,
            'replay_data' => $this->replay_data,
        ];
    }
}
