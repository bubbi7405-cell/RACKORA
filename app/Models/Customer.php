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

    public function reviews(): HasMany
    {
        return $this->hasMany(CustomerReview::class);
    }

    public function activeOrders(): HasMany
    {
        return $this->orders()->whereIn('status', ['active', 'provisioning']);
    }

    public function pendingOrders(): HasMany
    {
        return $this->orders()->where('status', 'pending');
    }

    public function registerIncident(?string $message = null): void
    {
        $this->incidents_count++;
        $this->last_incident_at = now();
        $this->satisfaction = max(0, $this->satisfaction - 10.0);

        if ($this->incidents_count >= $this->tolerance_incidents) {
            $this->status = 'churning';
        } elseif ($this->satisfaction < 30) {
            $this->status = 'unhappy';
        }

        $this->save();
        
        // Potential future: Store incident message in database for NOC dashboard
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

    /**
     * Get the customer's target region key (e.g., 'eu_central', 'asia_east').
     */
    public function getRegion(): ?string
    {
        return $this->preferences['target_region'] ?? null;
    }

    /**
     * Get the full region data from GameConfig.
     */
    public function getRegionData(): array
    {
        $region = $this->getRegion();
        if (!$region) return [];
        $regions = \App\Models\GameConfig::get('regions', []);
        return $regions[$region] ?? [];
    }

    /**
     * Check if the customer requires high-compliance infrastructure.
     */
    public function isComplianceHeavy(): bool
    {
        return (bool) ($this->preferences['is_compliance_heavy'] ?? false);
    }

    /**
     * Check if the customer is performance-focused (e.g., Asia-East clients).
     */
    public function isPerformanceFocused(): bool
    {
        return (bool) ($this->preferences['is_performance_focused'] ?? false);
    }

    /**
     * Check if the customer is ecology-focused.
     */
    public function isEcoFocused(): bool
    {
        return (bool) ($this->preferences['is_eco_focused'] ?? false);
    }

    /**
     * Calculate a loyalty score based on customer history and satisfaction.
     * Returns a value from 0.0 (new/unhappy) to 1.0 (loyal veteran).
     */
    public function getLoyaltyScore(): float
    {
        $ageDays = $this->acquired_at ? now()->diffInDays($this->acquired_at) : 0;
        $ageScore = min(1.0, $ageDays / 30); // Max after 30 days (game time)
        $satScore = (float)$this->satisfaction / 100;
        $incidentPenalty = min(0.5, $this->incidents_count * 0.1);

        return max(0, ($ageScore * 0.4) + ($satScore * 0.5) - $incidentPenalty + 0.1);
    }

    /**
     * Calculate the regional bonus multiplier for revenue.
     * Compliance-heavy or performance-focused customers pay a premium if their needs are met.
     */
    public function getRegionalRevenueMultiplier(): float
    {
        $mult = 1.0;
        if ($this->isComplianceHeavy()) $mult += 0.15;
        if ($this->isPerformanceFocused()) $mult += 0.10;
        if ($this->tier === 'diamond') $mult += 0.25;
        return $mult;
    }

    public function toGameState(): array
    {
        $regionData = $this->getRegionData();

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
            'loyaltyScore' => round($this->getLoyaltyScore(), 2),
            'region' => [
                'key' => $this->getRegion(),
                'name' => $regionData['name'] ?? null,
                'flag' => $regionData['flag'] ?? '🌍',
            ],
            'flags' => [
                'isComplianceHeavy' => $this->isComplianceHeavy(),
                'isPerformanceFocused' => $this->isPerformanceFocused(),
                'isEcoFocused' => $this->isEcoFocused(),
            ],
            'brand' => [
                'color' => $this->preferences['brand_color'] ?? '#3b82f6',
                'icon' => $this->preferences['brand_icon'] ?? 'box',
                'shape' => $this->preferences['brand_shape'] ?? 'rounded'
            ],
            'recentReviews' => $this->reviews()->latest()->take(3)->get()->map(fn($r) => $r->toGameState()),
            // F119: Uptime sparkline data (last 24 ticks)
            'uptimeHistory' => $this->getUptimeSparkline(),
            // F262: AI Churn Prediction
            'churnRisk' => $this->getChurnRisk(),
        ];
    }

    /**
     * FEATURE 119: Customer Uptime History Sparklines
     * Record whether the customer's orders are currently being served by healthy servers.
     * Called each tick from the game loop.
     */
    public function recordUptimeTick(): void
    {
        $prefs = $this->preferences ?? [];
        $history = $prefs['uptime_history'] ?? [];

        // Check if all active orders have healthy online servers
        $allHealthy = true;
        foreach ($this->activeOrders as $order) {
            $server = $order->assignedServer;
            if (!$server || $server->status !== \App\Enums\ServerStatus::ONLINE || $server->health < 50) {
                $allHealthy = false;
                break;
            }
        }

        // If no active orders, consider it "up" (no service to break)
        if ($this->activeOrders->isEmpty()) {
            $allHealthy = true;
        }

        $history[] = $allHealthy ? 1 : 0;

        // Keep only last 24 data points
        if (count($history) > 24) {
            $history = array_slice($history, -24);
        }

        $prefs['uptime_history'] = array_values($history);
        $this->preferences = $prefs;
        // Don't save here — let the caller batch-save
    }

    /**
     * Get the uptime sparkline data for the frontend.
     */
    public function getUptimeSparkline(): array
    {
        $history = $this->preferences['uptime_history'] ?? [];
        $total = count($history);
        $upCount = array_sum($history);

        return [
            'history' => $history,
            'percentage' => $total > 0 ? round(($upCount / $total) * 100, 1) : 100,
        ];
    }

    /**
     * FEATURE 262: AI-Driven Customer Churn Prediction
     * Calculates a risk score (0-100) indicating how likely this customer is to churn.
     * High risk = customer is likely to leave within the next 7 days.
     */
    public function getChurnRisk(): array
    {
        $risk = 0;
        $factors = [];

        // Factor 1: Satisfaction (biggest weight)
        if ($this->satisfaction < 20) {
            $risk += 40;
            $factors[] = 'Critical satisfaction';
        } elseif ($this->satisfaction < 40) {
            $risk += 25;
            $factors[] = 'Low satisfaction';
        } elseif ($this->satisfaction < 60) {
            $risk += 10;
            $factors[] = 'Below average satisfaction';
        }

        // Factor 2: Incident frequency
        $incidentRatio = $this->tolerance_incidents > 0
            ? $this->incidents_count / $this->tolerance_incidents
            : 0;
        if ($incidentRatio > 0.8) {
            $risk += 25;
            $factors[] = 'Near incident tolerance limit';
        } elseif ($incidentRatio > 0.5) {
            $risk += 10;
            $factors[] = 'Moderate incident count';
        }

        // Factor 3: Uptime quality
        $uptimePercentage = $this->getUptimeSparkline()['percentage'] ?? 100;
        if ($uptimePercentage < 90) {
            $risk += 20;
            $factors[] = 'Poor uptime experience';
        } elseif ($uptimePercentage < 95) {
            $risk += 10;
            $factors[] = 'Below SLO uptime';
        }

        // Factor 4: Loyalty decay
        $loyalty = $this->getLoyaltyScore();
        if ($loyalty < 0.3) {
            $risk += 15;
            $factors[] = 'Low loyalty score';
        }

        // Factor 5: Status already degraded
        if ($this->status === 'unhappy') {
            $risk += 15;
            $factors[] = 'Already flagged as unhappy';
        } elseif ($this->status === 'churning') {
            $risk = 95;
            $factors = ['Actively churning'];
        }

        $risk = min(100, $risk);

        $level = 'low';
        if ($risk >= 70) $level = 'critical';
        elseif ($risk >= 40) $level = 'high';
        elseif ($risk >= 20) $level = 'moderate';

        return [
            'score' => $risk,
            'level' => $level,
            'factors' => $factors,
        ];
    }

    public function generateReview(string $context = 'periodic'): ?CustomerReview
    {
        // Don't generate reviews too often (e.g., once an hour max)
        $lastReview = $this->reviews()->where('created_at', '>', now()->subHour())->first();
        if ($lastReview) return null;

        $sat = (float) $this->satisfaction;
        
        // Rating calculation
        $rating = 3;
        if ($sat >= 90) $rating = 5;
        elseif ($sat >= 70) $rating = 4;
        elseif ($sat >= 40) $rating = 3;
        elseif ($sat >= 20) $rating = 2;
        else $rating = 1;

        $sentiment = 'neutral';
        if ($rating >= 4) $sentiment = 'positive';
        elseif ($rating <= 2) $sentiment = 'negative';

        $templates = $this->getReviewTemplates($rating, $context);
        $content = $templates[array_rand($templates)];

        $review = $this->reviews()->create([
            'rating' => $rating,
            'content' => $content,
            'sentiment' => $sentiment,
            'context' => $context
        ]);

        // Log to GameLog so it shows in the UI
        $type = ($sentiment === 'positive') ? 'success' : (($sentiment === 'negative') ? 'danger' : 'info');
        \App\Models\GameLog::log($this->user, "REVIEW_{$sentiment}: {$this->company_name} left a {$rating}-star review: \"{$content}\"", $type, 'customers');

        return $review;
    }

    private function getReviewTemplates(int $rating, string $context): array
    {
        $data = [
            5 => [
                "Absolut spitze! Die Performance ist beeindruckend.",
                "Bester Hoster, den wir je hatten. Support ist auch top.",
                "Perfekte Uptime, genau das haben wir gesucht.",
                "Rackora liefert einfach ab. 5 Sterne!",
            ],
            4 => [
                "Gute Leistung, wir sind zufrieden.",
                "Solider Service für einen fairen Preis.",
                "Bisher läuft alles reibungslos.",
                "Empfehlenswert für Startups.",
            ],
            3 => [
                "Ist okay, könnte aber an manchen Stellen besser sein.",
                "Durchschnittliche Performance. Nichts Besonderes.",
                "Ein paar Latenz-Schwankungen, aber verkraftbar.",
                "Standard-Hosting halt.",
            ],
            2 => [
                "Eher enttäuscht. Zu viele kleine Probleme.",
                "Die Latenz ist oft grenzwertig.",
                "Könnte stabiler laufen.",
                "Nicht ganz das, was wir uns erhofft hatten.",
            ],
            1 => [
                "Katastrophe! Nur Probleme hier.",
                "Ständige Ausfälle, wir werden kündigen.",
                "Absolut unzuverlässig. Finger weg!",
                "Schlechtester Service aller Zeiten.",
            ]
        ];

        if ($context === 'after_repair' && $rating >= 4) {
            return [
                "Schnelle Reaktion auf den Hardware-Fehler. Danke!",
                "Problem wurde zügig gelöst. Guter Job.",
            ];
        }

        return $data[$rating] ?? $data[3];
    }
}
