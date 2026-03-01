<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\GameLog;
use Illuminate\Support\Str;

class SupportService
{
    public function generateTicket(User $user, Customer $customer, float $penalty = 0): void
    {
        $subjects = [
            'network' => ['Latenz-Peaks festgestellt', 'Paketverlust im Segment', 'BGP Routing-Fehler'],
            'billing' => ['Rechnungsfrage', 'Zahlungsmethode aktualisieren'],
            'general' => ['Allgemeine Anfrage', 'Performance-Optimierung benötigt'],
            'critical' => ['SERVER_DOWN', 'KRITISCHER_DATENVERLUST_DROHT', 'SICHERHEITSBREACH_VERDACHT']
        ];

        $category = 'general';
        if ($penalty > 2.0) $category = 'critical';
        elseif ($penalty > 0.5) $category = 'network';

        $subject = $subjects[$category][array_rand($subjects[$category])];
        
        // Don't spam tickets for the same customer if they have an open one
        $exists = SupportTicket::where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->exists();
            
        if ($exists) return;

        SupportTicket::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'subject' => $subject,
            'description' => "Der Kunde beklagt sich über die Servicequalität bei seinem aktuellen Auftrag.",
            'status' => 'open',
            'priority' => $category === 'critical' ? 'critical' : ($category === 'network' ? 'high' : 'medium'),
            'complexity' => rand(20, 100),
            'expires_at' => now()->addMinutes(rand(15, 60)),
        ]);

        GameLog::log($user, "Neues Support-Ticket von {$customer->company_name}: {$subject}", 'info', 'support');
    }

    public function getActiveTickets(User $user)
    {
        return SupportTicket::where('user_id', $user->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->with('customer')
            ->get();
    }
}
