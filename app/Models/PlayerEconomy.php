<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\PaymentTransaction;

class PlayerEconomy extends Model
{
    use HasUuids;

    protected $table = 'player_economy';

    protected $fillable = [
        'user_id',
        'balance',
        'total_revenue',
        'total_expenses',
        'hourly_income',
        'hourly_expenses',
        'reputation',
        'experience_points',
        'level',
        'power_price_per_kwh',
        'bandwidth_cost_per_gbps',
        'current_tick',
        'last_income_tick',
        'automation_settings',
        'strategic_policies',
        'pending_decisions',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'total_revenue' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'hourly_income' => 'decimal:2',
        'hourly_expenses' => 'decimal:2',
        'reputation' => 'decimal:2',
        'power_price_per_kwh' => 'decimal:4',
        'bandwidth_cost_per_gbps' => 'decimal:2',
        'current_tick' => 'integer',
        'last_income_tick' => 'datetime',
        'automation_settings' => 'array',
        'strategic_policies' => 'array',
        'pending_decisions' => 'array',
    ];

    public function isAutomationEnabled(string $key): bool
    {
        if (!$this->automation_settings) return false;
        return (bool) ($this->automation_settings[$key] ?? false);
    }

    public function setAutomation(string $key, bool $value): void
    {
        $settings = $this->automation_settings ?? [];
        $settings[$key] = $value;
        $this->automation_settings = $settings;
        $this->save();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canAfford(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    public function debit(float $amount, string $description, string $category = 'misc_expense', ?Model $related = null): bool
    {
        if (!$this->canAfford($amount)) {
            return false;
        }

        $this->balance -= $amount;
        $this->total_expenses += $amount;
        $this->save();

        // Log transaction
        PaymentTransaction::create([
            'user_id' => $this->user_id,
            'type' => 'expense',
            'category' => $category,
            'amount' => -$amount,
            'balance_after' => $this->balance,
            'description' => $description,
            'related_id' => $related?->id,
            'related_type' => $related ? get_class($related) : null,
        ]);

        return true;
    }

    public function credit(float $amount, string $description = 'Income', string $category = 'income', ?Model $related = null): void
    {
        $this->balance += $amount;
        $this->total_revenue += $amount;
        $this->save();

        // Log transaction
         PaymentTransaction::create([
            'user_id' => $this->user_id,
            'type' => 'income',
            'category' => $category,
            'amount' => $amount,
            'balance_after' => $this->balance,
            'description' => $description,
            'related_id' => $related?->id,
            'related_type' => $related ? get_class($related) : null,
        ]);
    }

    public function addExperience(int $xp): void
    {
        $this->experience_points += $xp;
        $this->checkLevelUp();
        $this->save();
    }

    public function adjustReputation(float $amount): void
    {
        $this->reputation = max(0, min(100, $this->reputation + $amount));
        $this->save();
    }

    private function checkLevelUp(): void
    {
        $xpForNextLevel = $this->getXpForLevel($this->level + 1);
        
        while ($this->experience_points >= $xpForNextLevel) {
            $this->level++;
            $xpForNextLevel = $this->getXpForLevel($this->level + 1);
        }
    }

    public function getXpForLevel(int $level): int
    {
        // Exponential XP curve
        return (int) (100 * pow(1.5, $level - 1));
    }

    public function getXpProgress(): float
    {
        $currentLevelXp = $this->getXpForLevel($this->level);
        $nextLevelXp = $this->getXpForLevel($this->level + 1);
        $xpInCurrentLevel = $this->experience_points - $currentLevelXp;
        $xpNeeded = $nextLevelXp - $currentLevelXp;

        if ($xpNeeded <= 0) return 100;
        
        return min(100, ($xpInCurrentLevel / $xpNeeded) * 100);
    }

    public function getNetIncomePerHour(): float
    {
        return $this->hourly_income - $this->hourly_expenses;
    }

    /**
     * Get the current game hour (0-23)
     */
    public function getGameHour(): int
    {
        return ((int) ($this->current_tick * 15 / 60)) % 24;
    }

    /**
     * Get the current game minute (0, 15, 30, 45)
     */
    public function getGameMinute(): int
    {
        return (int) (($this->current_tick * 15) % 60);
    }

    /**
     * Get the formatted game time (HH:MM)
     */
    public function getFormattedTime(): string
    {
        return sprintf('%02d:%02d', $this->getGameHour(), $this->getGameMinute());
    }

    /**
     * Get progress of the day (0.0 to 1.0)
     */
    public function getDayProgress(): float
    {
        $minutesInDay = 24 * 60;
        $currentMinutes = ($this->current_tick * 15) % $minutesInDay;
        return $currentMinutes / $minutesInDay;
    }

    public function toGameState(): array
    {
        return [
            'balance' => (float) $this->balance,
            'totalRevenue' => (float) $this->total_revenue,
            'totalExpenses' => (float) $this->total_expenses,
            'hourlyIncome' => (float) $this->hourly_income,
            'hourlyExpenses' => (float) $this->hourly_expenses,
            'netIncomePerHour' => $this->getNetIncomePerHour(),
            'reputation' => (float) $this->reputation,
            'level' => $this->level,
            'currentTick' => $this->current_tick,
            'gameTime' => [
                'hour' => $this->getGameHour(),
                'minute' => $this->getGameMinute(),
                'formatted' => $this->getFormattedTime(),
                'dayProgress' => $this->getDayProgress(),
            ],
            'experience' => [
                'current' => $this->experience_points,
                'forNextLevel' => $this->getXpForLevel($this->level + 1),
                'progress' => $this->getXpProgress(),
            ],
            'costs' => [
                'powerPerKwh' => (float) $this->power_price_per_kwh,
                'bandwidthPerGbps' => (float) $this->bandwidth_cost_per_gbps,
            ],
            'automation' => $this->automation_settings ?? [],
            'policies' => $this->strategic_policies ?? [],
            'pendingDecisions' => $this->pending_decisions ?? [],
        ];
    }

    public function getPolicy(string $key, $default = null)
    {
        return $this->strategic_policies[$key] ?? $default;
    }

    public function hasPendingDecision(string $type): bool
    {
        if (!$this->pending_decisions) return false;
        foreach ($this->pending_decisions as $decision) {
            if ($decision['type'] === $type) return true;
        }
        return false;
    }
}
