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
        'spare_parts_count',
        'total_revenue',
        'total_expenses',
        'hourly_income',
        'hourly_expenses',
        'total_power_kw',
        'total_bandwidth_gbps',
        'reputation',
        'shred_count',
        'experience_points',
        'level',
        'corporate_specialization',
        'power_price_per_kwh',
        'bandwidth_cost_per_gbps',
        'current_tick',
        'last_income_tick',
        'specialized_reputation',
        'automation_settings',
        'strategic_policies',
        'pending_decisions',
        'energy_contract_type',
        'energy_contract_price',
        'energy_contract_expires_at',
        'skill_points',
        'unlocked_skills',
        'difficulty',
        'security_score',
        'privacy_score',
        'global_market_share',
        'regional_shares',
        'sector_shares',
        'arpu',
        'innovation_index',
        'risk_exposure',
        'marketing_budget',
        'customer_acquisition_cost',
        'federal_heat',
        'metadata',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'spare_parts_count' => 'integer',
        'total_revenue' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'hourly_income' => 'decimal:2',
        'hourly_expenses' => 'decimal:2',
        'total_power_kw' => 'decimal:4',
        'total_bandwidth_gbps' => 'decimal:4',
        'reputation' => 'decimal:2',
        'shred_count' => 'integer',
        'specialized_reputation' => 'array',
        'power_price_per_kwh' => 'decimal:4',
        'bandwidth_cost_per_gbps' => 'decimal:2',
        'current_tick' => 'integer',
        'last_income_tick' => 'datetime',
        'automation_settings' => 'array',
        'strategic_policies' => 'array',
        'pending_decisions' => 'array',
        'energy_contract_price' => 'decimal:4',
        'energy_contract_expires_at' => 'datetime',
        'skill_points' => 'integer',
        'unlocked_skills' => 'array',
        'security_score' => 'decimal:2',
        'privacy_score' => 'decimal:2',
        'global_market_share' => 'decimal:3',
        'regional_shares' => 'array',
        'sector_shares' => 'array',
        'arpu' => 'decimal:2',
        'innovation_index' => 'decimal:2',
        'risk_exposure' => 'decimal:2',
        'marketing_budget' => 'decimal:2',
        'customer_acquisition_cost' => 'decimal:2',
        'federal_heat' => 'decimal:4',
        'metadata' => 'array',
    ];

    public function adjustSpecializedReputation(string $category, float $amount): void
    {
        $rep = $this->specialized_reputation ?? [
            'budget' => 0.0,
            'premium' => 0.0,
            'hpc' => 0.0,
            'green' => 0.0,
        ];

        $current = (float) ($rep[$category] ?? 0.0);
        $rep[$category] = max(0, min(100, $current + $amount));
        
        $this->specialized_reputation = $rep;
    }

    public function getSpecializedReputation(string $category): float
    {
        return (float) ($this->specialized_reputation[$category] ?? 0.0);
    }

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
        $engine = \App\Models\GameConfig::get('engine_constants', []);
        $multiplier = $engine['xp_multiplier'] ?? 1.0;

        // SPECIALIZATION: XP Bonus
        $specService = app(\App\Services\Game\SpecializationService::class);
        $specMods = $specService->getActiveModifiers($this->user);
        if (isset($specMods['passives']['xp_gain'])) {
            $multiplier *= (1.0 + (float) $specMods['passives']['xp_gain']);
        }
        
        $this->experience_points += (int) ($xp * $multiplier);
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
            $this->skill_points += 2; // Award 2 skill points per level
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
            'specialization' => $this->corporate_specialization,
            'currentTick' => $this->current_tick,
            'gameSpeed' => (int) ($this->game_speed ?? 1),
            'isPaused' => (bool) ($this->is_paused ?? false),
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
            'specializedReputation' => $this->specialized_reputation ?? [
                'budget' => 0.0,
                'premium' => 0.0,
                'hpc' => 0.0,
                'green' => 0.0
            ],
            'energy_contract' => [
                'type' => $this->energy_contract_type,
                'fixedPrice' => (float) $this->energy_contract_price,
                'expiresAt' => $this->energy_contract_expires_at?->toIso8601String(),
            ],
            'automation' => $this->automation_settings ?? [],
            'policies' => $this->strategic_policies ?? [],
            'pendingDecisions' => $this->pending_decisions ?? [],
            'specialties' => [
                'points' => (int) $this->skill_points,
                'unlocked' => $this->unlocked_skills ?? [],
            ],
            'difficulty' => $this->difficulty,
            'compliance' => [
                'securityScore' => (float) $this->security_score,
                'privacyScore' => (float) $this->privacy_score,
                'shredCount' => (int) $this->shred_count,
            ],
            'heat' => (float) $this->federal_heat,
            'risk' => (float) $this->risk_exposure,
            'metadata' => $this->metadata ?? [],
        ];
    }

    public function getDifficultyModifiers(): array
    {
        $d = $this->difficulty ?? 'normal';
        return match ($d) {
            'easy' => [
                'income_mod' => 1.5,
                'expense_mod' => 0.7,
                'event_freq_mod' => 0.4,
                'satisfaction_decay_mod' => 0.5,
                'xp_mod' => 1.2,
                'repair_cost_mod' => 0.5,
            ],
            'hard' => [
                'income_mod' => 0.8,
                'expense_mod' => 1.3,
                'event_freq_mod' => 2.5,
                'satisfaction_decay_mod' => 1.5,
                'xp_mod' => 0.9,
                'repair_cost_mod' => 1.5,
            ],
            'ironman' => [
                'income_mod' => 0.7,
                'expense_mod' => 1.5,
                'event_freq_mod' => 3.0,
                'satisfaction_decay_mod' => 2.0,
                'xp_mod' => 1.5, // High reward
                'repair_cost_mod' => 2.0,
            ],
            default => [
                'income_mod' => 1.0,
                'expense_mod' => 1.0,
                'event_freq_mod' => 1.0,
                'satisfaction_decay_mod' => 1.0,
                'xp_mod' => 1.0,
                'repair_cost_mod' => 1.0,
            ],
        };
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
