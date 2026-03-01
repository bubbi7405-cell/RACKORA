<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\CustomerOrder;
use App\Models\GameLog;
use Illuminate\Support\Facades\DB;

/**
 * FEATURE 206: Bestechung & Moral-Entscheidungen (Client Graft)
 * 
 * VIP-Kunden bieten Bestechungen an um in der Support-Queue vorzudringen.
 * Schnelles Geld vs. langfristiger Reputationsschaden.
 */
class BriberyService
{
    /**
     * Bestechungsangebote generieren.
     * Wird aufgerufen wenn ein VIP-Kunde ein Support-Ticket hat.
     */
    public function generateBribeOffer(User $user): ?array
    {
        $economy = $user->economy;
        if ($economy->level < 5) return null;

        // Nur wenn aktive VIP/Enterprise-Kunden existieren
        $vipOrders = CustomerOrder::where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where('sla_tier', 'premium')
                  ->orWhere('sla_tier', 'enterprise');
            })
            ->inRandomOrder()
            ->first();

        if (!$vipOrders) return null;

        // 10% Chance pro Tick dass ein Bestechungsangebot reinkommt
        if (rand(1, 100) > 10) return null;

        $bribeAmount = round(rand(500, 3000) * (1 + $economy->level * 0.1), 2);
        $repPenalty = rand(3, 8);

        $offer = [
            'id' => uniqid('bribe_'),
            'order_id' => $vipOrders->id,
            'customer_name' => $vipOrders->customer?->company_name ?? 'VIP Client',
            'amount' => $bribeAmount,
            'reputation_penalty' => $repPenalty,
            'description' => "Ein VIP-Kunde bietet \${$bribeAmount} damit sein Ticket bevorzugt behandelt wird.",
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
            'status' => 'pending',
        ];

        // In Economy-Metadata speichern
        $meta = $economy->metadata ?? [];
        $pendingBribes = $meta['pending_bribes'] ?? [];
        
        // Maximal 3 offene Angebote
        if (count($pendingBribes) >= 3) return null;
        
        $pendingBribes[] = $offer;
        $meta['pending_bribes'] = $pendingBribes;
        $economy->metadata = $meta;
        $economy->save();

        GameLog::log($user, "💰 Bestechungsangebot: {$offer['customer_name']} bietet \${$bribeAmount} für Priority-Support.", 'warning', 'management');

        return $offer;
    }

    /**
     * Bestechung annehmen.
     */
    public function acceptBribe(User $user, string $bribeId): array
    {
        $economy = $user->economy;
        $meta = $economy->metadata ?? [];
        $pendingBribes = $meta['pending_bribes'] ?? [];

        $bribeIndex = null;
        $bribe = null;
        foreach ($pendingBribes as $i => $b) {
            if ($b['id'] === $bribeId) {
                $bribeIndex = $i;
                $bribe = $b;
                break;
            }
        }

        if (!$bribe) {
            return ['success' => false, 'error' => 'Angebot nicht gefunden oder abgelaufen.'];
        }

        if (now()->gt(\Carbon\Carbon::parse($bribe['expires_at']))) {
            unset($pendingBribes[$bribeIndex]);
            $meta['pending_bribes'] = array_values($pendingBribes);
            $economy->metadata = $meta;
            $economy->save();
            return ['success' => false, 'error' => 'Angebot abgelaufen.'];
        }

        return DB::transaction(function () use ($user, $economy, $meta, $pendingBribes, $bribeIndex, $bribe) {
            // Geld kassieren
            $economy->credit($bribe['amount'], "Bestechung angenommen: {$bribe['customer_name']}", 'bribe');
            
            // Reputation verlieren
            $economy->adjustReputation(-$bribe['reputation_penalty']);
            
            // Moral-Score tracken (für spätere Konsequenzen)
            $meta['moral_score'] = ($meta['moral_score'] ?? 100) - 5;
            $meta['total_bribes_accepted'] = ($meta['total_bribes_accepted'] ?? 0) + 1;
            $meta['total_bribe_income'] = ($meta['total_bribe_income'] ?? 0) + $bribe['amount'];
            
            // Angebot entfernen
            unset($pendingBribes[$bribeIndex]);
            $meta['pending_bribes'] = array_values($pendingBribes);
            $economy->metadata = $meta;
            $economy->save();

            GameLog::log($user, "💸 Bestechung angenommen! +\${$bribe['amount']}, -{$bribe['reputation_penalty']} Reputation. Moral erodiert.", 'danger', 'management');

            return [
                'success' => true,
                'amount' => $bribe['amount'],
                'reputation_lost' => $bribe['reputation_penalty'],
                'moral_score' => $meta['moral_score'],
            ];
        });
    }

    /**
     * Bestechung ablehnen (Moral-Bonus).
     */
    public function declineBribe(User $user, string $bribeId): array
    {
        $economy = $user->economy;
        $meta = $economy->metadata ?? [];
        $pendingBribes = $meta['pending_bribes'] ?? [];

        $bribeIndex = null;
        foreach ($pendingBribes as $i => $b) {
            if ($b['id'] === $bribeId) {
                $bribeIndex = $i;
                break;
            }
        }

        if ($bribeIndex === null) {
            return ['success' => false, 'error' => 'Angebot nicht gefunden.'];
        }

        unset($pendingBribes[$bribeIndex]);
        $meta['pending_bribes'] = array_values($pendingBribes);
        
        // Moral-Bonus für's Ablehnen
        $meta['moral_score'] = min(100, ($meta['moral_score'] ?? 100) + 2);
        $economy->adjustReputation(2.0); // Kleine Rep-Belohnung
        
        $economy->metadata = $meta;
        $economy->save();

        GameLog::log($user, "🛡️ Bestechung abgelehnt! Integrität bewahrt. +2 Reputation.", 'success', 'management');

        return [
            'success' => true,
            'message' => 'Bestechung abgelehnt. Ihre Integrität wird sich auszahlen.',
            'moral_score' => $meta['moral_score'],
        ];
    }

    /**
     * Bestechungs-Status für API abrufen.
     */
    public function getState(User $user): array
    {
        $economy = $user->economy;
        $meta = $economy->metadata ?? [];
        
        // Abgelaufene Bestechungen entfernen
        $pendingBribes = $meta['pending_bribes'] ?? [];
        $pendingBribes = array_filter($pendingBribes, fn($b) => now()->lt(\Carbon\Carbon::parse($b['expires_at'])));
        
        return [
            'pending_offers' => array_values($pendingBribes),
            'moral_score' => $meta['moral_score'] ?? 100,
            'total_accepted' => $meta['total_bribes_accepted'] ?? 0,
            'total_income' => $meta['total_bribe_income'] ?? 0,
            'unlocked' => $economy->level >= 5,
        ];
    }
}
