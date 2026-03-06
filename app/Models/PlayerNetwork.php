<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlayerNetwork extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'ipv4_total',
        'ipv4_used',
        'ipv6_total',
        'ipv6_used',
        'ipv4_subnets',
        'ipv6_subnets',
        'isp_provider',
        'bandwidth_contract_mbps',
        'bandwidth_contract_cost',
        'bandwidth_tier',
        'regional_latency',
        'regional_presence',
        'asn',
        'peering_level',
        'peering_score',
        'ddos_protection_level',
        'network_reputation',
        'avg_latency_ms',
        'avg_packet_loss',
        'jitter_ms',
        'bgp_routes_announced',
        'traffic_in_gbps',
        'traffic_out_gbps',
        'last_ddos_at',
        'ddos_events_total',
        'sla_compliance_rate',
        'has_orbital_redundancy',
    ];

    protected $casts = [
        'peering_score' => 'decimal:2',
        'network_reputation' => 'decimal:2',
        'avg_latency_ms' => 'decimal:2',
        'avg_packet_loss' => 'decimal:3',
        'jitter_ms' => 'decimal:2',
        'traffic_in_gbps' => 'decimal:2',
        'traffic_out_gbps' => 'decimal:2',
        'bandwidth_contract_cost' => 'decimal:2',
        'sla_compliance_rate' => 'decimal:2',
        'ipv4_subnets' => 'array',
        'ipv6_subnets' => 'array',
        'regional_latency' => 'array',
        'regional_presence' => 'array',
        'last_ddos_at' => 'datetime',
    ];

    // ─── Relationships ──────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── IP Metrics ─────────────────────────────────────

    public function getIpv4UsagePercent(): float
    {
        if ($this->ipv4_total <= 0) return 100;
        return ($this->ipv4_used / $this->ipv4_total) * 100;
    }

    public function getIpv6UsagePercent(): float
    {
        if ($this->ipv6_total <= 0) return 0;
        return ($this->ipv6_used / $this->ipv6_total) * 100;
    }

    public function isIpv4Exhausted(): bool
    {
        return $this->ipv4_used >= $this->ipv4_total;
    }

    // ─── Peering ────────────────────────────────────────

    public function getPeeringLabel(): string
    {
        return match($this->peering_level) {
            0 => 'Standard Transit',
            1 => 'Community Peering',
            2 => 'Premium Peering',
            3 => 'Tier-1 Settlement-Free',
            default => 'Standard Transit',
        };
    }

    // ─── DDoS Protection ────────────────────────────────

    public function getDdosProtectionLabel(): string
    {
        return match($this->ddos_protection_level) {
            0 => 'None',
            1 => 'Cloud Proxy (Basic)',
            2 => 'Hardware Mitigation',
            3 => 'AI-Driven Shield',
            default => 'None',
        };
    }

    public function getDdosMitigationCapacity(): float
    {
        return match($this->ddos_protection_level) {
            0 => 0,
            1 => 0.4,  // 40% mitigation
            2 => 0.7,  // 70% mitigation
            3 => 0.95, // 95% mitigation
            default => 0,
        };
    }

    // ─── ISP ────────────────────────────────────────────

    public function getIspLabel(): string
    {
        return match($this->isp_provider) {
            'generic_transit' => 'Generic Transit',
            'tier3_local' => 'LocalNet ISP',
            'tier2_regional' => 'RegionalConnect',
            'tier1_global' => 'GlobalTier Networks',
            'premium_enterprise' => 'Enterprise Direct',
            default => ucfirst(str_replace('_', ' ', $this->isp_provider)),
        };
    }

    // ─── Bandwidth ──────────────────────────────────────

    public function getBandwidthTierLabel(): string
    {
        return match($this->bandwidth_tier) {
            'standard' => 'Standard',
            'premium' => 'Premium',
            'enterprise' => 'Enterprise',
            'dedicated' => 'Dedicated Line',
            default => 'Standard',
        };
    }

    public function getTotalBandwidthCapacityMbps(): int
    {
        return $this->bandwidth_contract_mbps;
    }

    // ─── Regional Latency ───────────────────────────────

    public function getLatencyForRegion(string $region): float
    {
        $latencies = $this->regional_latency ?? [];
        return $latencies[$region] ?? 100.0; // Default high latency for unknown regions
    }

    public function hasPresenceInRegion(string $region): bool
    {
        $presence = $this->regional_presence ?? [];
        return in_array($region, $presence);
    }

    // ─── Network Health Score ───────────────────────────

    public function getNetworkHealthScore(): float
    {
        $score = 100.0;

        // Packet loss penalty (max -30)
        $score -= min(30, $this->avg_packet_loss * 3000);

        // Latency penalty (max -20)
        $score -= min(20, max(0, ($this->avg_latency_ms - 30) / 3.5));

        // Jitter penalty (max -15)
        $score -= min(15, $this->jitter_ms * 3);

        // IP exhaustion penalty (max -15)
        $ipUsage = $this->getIpv4UsagePercent();
        if ($ipUsage > 85) {
            $score -= ($ipUsage - 85) * 1.0;
        }

        // SLA compliance bonus
        $score = min(100, $score + max(0, ($this->sla_compliance_rate - 98) * 2));

        return max(0, round($score, 1));
    }

    // ─── Severity Classification ────────────────────────

    public function getNetworkSeverity(): string
    {
        $health = $this->getNetworkHealthScore();
        if ($health >= 90) return 'nominal';
        if ($health >= 70) return 'caution';
        if ($health >= 50) return 'warning';
        return 'critical';
    }
}
