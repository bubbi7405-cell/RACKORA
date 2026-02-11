<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'company_name',
        'tier',
        'revenue_per_month',
        'satisfaction',
        'patience_minutes',
        'tolerance_incidents',
        'incidents_count',
        'status',
        'acquired_at',
        'last_incident_at',
        'churn_at',
        'preferences',
    ];

    protected $casts = [
        'revenue_per_month' => 'decimal:2',
        'satisfaction' => 'decimal:2',
        'acquired_at' => 'datetime',
        'last_incident_at' => 'datetime',
        'churn_at' => 'datetime',
        'preferences' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CustomerOrder::class);
    }

    public function activeOrders(): HasMany
    {
        return $this->orders()->whereIn('status', ['active', 'provisioning']);
    }

    public function pendingOrders(): HasMany
    {
        return $this->orders()->where('status', 'pending');
    }

    public function registerIncident(): void
    {
        $this->incidents_count++;
        $this->last_incident_at = now();
        $this->satisfaction = max(0, $this->satisfaction - 15);

        if ($this->incidents_count >= $this->tolerance_incidents) {
            $this->status = 'churning';
        } elseif ($this->satisfaction < 30) {
            $this->status = 'unhappy';
        }

        $this->save();
    }

    public function improveSatisfaction(float $amount = 5): void
    {
        $this->satisfaction = min(100, $this->satisfaction + $amount);
        
        if ($this->satisfaction >= 50 && $this->status === 'unhappy') {
            $this->status = 'active';
        }
        
        $this->save();
    }

    public function churn(): void
    {
        $this->status = 'churned';
        $this->churn_at = now();
        $this->save();

        // Cancel all orders
        $this->orders()->whereIn('status', ['pending', 'active', 'provisioning'])
            ->update(['status' => 'cancelled']);
    }

    public function isActiveCustomer(): bool
    {
        return !in_array($this->status, ['churned', 'churning']);
    }

    public function getMonthlyRevenue(): float
    {
        return $this->activeOrders->sum('price_per_month');
    }

    public function toGameState(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'companyName' => $this->company_name,
            'tier' => $this->tier,
            'revenuePerMonth' => (float) $this->revenue_per_month,
            'actualRevenue' => $this->getMonthlyRevenue(),
            'satisfaction' => (float) $this->satisfaction,
            'status' => $this->status,
            'incidentsCount' => $this->incidents_count,
            'toleranceIncidents' => $this->tolerance_incidents,
            'activeOrdersCount' => $this->activeOrders->count(),
            'pendingOrdersCount' => $this->pendingOrders->count(),
            'acquiredAt' => $this->acquired_at->toIso8601String(),
        ];
    }
}
