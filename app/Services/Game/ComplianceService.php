<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\Certificate;
use App\Models\ComplianceAudit;
use App\Models\UserCertificate;
use App\Models\GameEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ComplianceService
{
    public function __construct(
        protected ResearchService $researchService,
        protected StatsService $statsService
    ) {}

    /**
     * Main tick process for compliance
     */
    public function tick(User $user): void
    {
        $this->updateScores($user);
        $this->processActiveAudits($user);
        $this->checkCertificateExpirations($user);
        $this->triggerRegulatoryActions($user);
    }

    /**
     * Trigger fines or forced audits if compliance is too low
     */
    public function triggerRegulatoryActions(User $user): void
    {
        $economy = $user->economy;
        if (!$economy || $economy->level < 5) return;

        // Chance to get fined if security or privacy is below 20%
        if ($economy->security_score < 20 || $economy->privacy_score < 20) {
            if (rand(1, 1000) === 1) { // Rare tick event
                $fine = 2500 * $economy->level;

                // Specialization V2: Audit Expert (Penalty Reduction)
                $empService = app(EmployeeService::class);
                $reduction = $empService->getAggregatedBonus($user, 'compliance_penalty_reduction');
                $fine *= (1.0 - min(0.9, $reduction));
                
                $economy->debit($fine, "Regulatory Fine: Non-compliance with industry safety standards.", 'fine');
                
                \App\Models\GameLog::log($user, "REGULATORY FINE: Your company was fined $" . number_format($fine) . " for failing to meet minimum security and privacy standards.", 'critical', 'compliance');
            }
        }
    }

    /**
     * Calculate and update security and privacy scores
     */
    public function updateScores(User $user): void
    {
        $economy = $user->economy;
        if (!$economy) return;

        // Base score
        $security = 10.0;
        $privacy = 10.0;

        // --- SECURITY SCORE ---
        // Research bonuses
        if ($this->researchService->hasResearch($user, 'firewall_v1')) $security += 15;
        if ($this->researchService->hasResearch($user, 'firewall_v2')) $security += 20;
        if ($this->researchService->hasResearch($user, 'ids_v1')) $security += 15;
        if ($this->researchService->hasResearch($user, 'ids_v2')) $security += 20;
        if ($this->researchService->hasResearch($user, 'dmz_architecture')) $security += 10;
        
        // Employee impact (Security Engineer)
        $securityStaff = $user->employees()->where('type', 'security_engineer')->sum('efficiency');
        $security += $securityStaff * 10;

        // Negative impact from recent unresolved security events
        $activeSecurityEvents = GameEvent::where('user_id', $user->id)
            ->whereIn('type', ['security_breach', 'ddos_attack'])
            ->whereIn('status', ['warning', 'active', 'escalated'])
            ->count();
        $security -= $activeSecurityEvents * 25;

        // FEATURE 157: Black Market Backdoors
        $backdoorCount = \App\Models\UserComponent::where('user_id', $user->id)
            ->where('meta->has_backdoor', true)
            ->count();
        $security -= ($backdoorCount * 3.0); // -3% security per backdoor component

        // --- PRIVACY SCORE ---
        // Research bonuses
        if ($this->researchService->hasResearch($user, 'encryption_v1')) $privacy += 20;
        if ($this->researchService->hasResearch($user, 'encryption_v2')) $privacy += 30;
        if ($this->researchService->hasResearch($user, 'data_sovereignty')) $privacy += 20;
        
        // Employee impact (Compliance Officer)
        $complianceStaff = $user->employees()->where('type', 'compliance_officer')->sum('efficiency');
        $privacy += $complianceStaff * 15;

        // Negative impact from recent data leaks
        $recentLeaks = GameEvent::where('user_id', $user->id)
            ->where('type', 'data_leak')
            ->where('updated_at', '>=', now()->subMinutes(60))
            ->count();
        $privacy -= $recentLeaks * 40;

        // Clamp scores 0-100
        $economy->security_score = max(0, min(100, $security));
        $economy->privacy_score = max(0, min(100, $privacy));
        $economy->save();
    }

    /**
     * Process ongoing audits
     */
    public function processActiveAudits(User $user): void
    {
        $audits = ComplianceAudit::where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        foreach ($audits as $audit) {
            $certificate = $audit->certificate;
            $metRequirements = true;
            $failLog = [];

            // Check requirements every tick
            foreach ($certificate->requirements as $type => $value) {
                switch ($type) {
                    case 'min_security':
                        if ($user->economy->security_score < $value) {
                            $metRequirements = false;
                            $failLog[] = "Security score below requirement ({$user->economy->security_score}/{$value})";
                        }
                        break;
                    case 'min_privacy':
                        if ($user->economy->privacy_score < $value) {
                            $metRequirements = false;
                            $failLog[] = "Privacy score below requirement ({$user->economy->privacy_score}/{$value})";
                        }
                        break;
                    case 'min_uptime':
                        // Simple check against recent stats
                        $avgUptime = $user->stats_history()->limit(10)->avg('uptime') ?? 100;
                        if ($avgUptime < $value) {
                            $metRequirements = false;
                            $failLog[] = "Average uptime below requirement (" . round($avgUptime, 1) . "%)";
                        }
                        break;
                    case 'research':
                        if (!$this->researchService->hasResearch($user, $value)) {
                            $metRequirements = false;
                            $failLog[] = "Required research missing: {$value}";
                        }
                        break;
                    case 'shred_count':
                        if (($user->economy->shred_count ?? 0) < $value) {
                            $metRequirements = false;
                            $failLog[] = "Insufficient secure destruction history ({$user->economy->shred_count}/{$value} units shredded)";
                        }
                        break;
                    case 'min_green_rep':
                        $greenRep = $user->economy->getSpecializedReputation('green');
                        if ($greenRep < $value) {
                            $metRequirements = false;
                            $failLog[] = "Green Reputation too low (" . round($greenRep, 1) . "/{$value})";
                        }
                        break;
                }
            }

            if (!$metRequirements) {
                // Audit failed
                $audit->status = 'failed';
                $audit->checklog = $failLog;
                $audit->save();
                
                \App\Models\GameLog::log($user, "Audit Failed: Certification for {$certificate->name} was rejected due to unmet requirements.", 'critical', 'compliance');
                continue;
            }

            // Update progress
            if ($audit->completes_at && $audit->started_at) {
                $total = $audit->started_at->diffInSeconds($audit->completes_at);
                $elapsed = $audit->started_at->diffInSeconds(Carbon::now());
                $audit->progress = min(100, (int)(($elapsed / max(1, $total)) * 100));
            }

            // Check for completion
            if ($audit->completes_at && Carbon::now()->gte($audit->completes_at)) {
                $this->grantCertificate($user, $certificate);
                $audit->status = 'success';
                $audit->progress = 100;
            }

            $audit->save();
        }
    }

    /**
     * Grant a certificate to the user
     */
    public function grantCertificate(User $user, Certificate $certificate): void
    {
        UserCertificate::updateOrCreate(
            ['user_id' => $user->id, 'certificate_id' => $certificate->id],
            [
                'issued_at' => now(),
                'expires_at' => now()->addMinutes(600), // Valid for 10 hours of gameplay
            ]
        );

        // Reputation bonus
        $user->economy->adjustReputation($certificate->bonus_reputation);
        
        \App\Models\GameLog::log($user, "Certification Granted: Your company is now officially certified for {$certificate->name}!", 'success', 'compliance');
    }

    /**
     * Check for expired certificates
     */
    public function checkCertificateExpirations(User $user): void
    {
        $expired = UserCertificate::where('user_id', $user->id)
            ->where('expires_at', '<=', now())
            ->delete();

        if ($expired > 0) {
            \App\Models\GameLog::log($user, "Certification Expired: One or more of your industry certifications have expired and require a new audit.", 'warning', 'compliance');
        }
    }

    /**
     * Start a new audit
     */
    public function startAudit(User $user, Certificate $certificate): array
    {
        // Check if already has active audit
        $existing = ComplianceAudit::where('user_id', $user->id)
            ->where('certificate_id', $certificate->id)
            ->where('status', 'active')
            ->exists();

        if ($existing) {
            return ['success' => false, 'message' => 'Audit already in progress for this certificate.'];
        }

        // Cost check? (Optional, can be added)
        $cost = 5000; // Fixed audit fee
        if (!$user->economy->debit($cost, "Audit Fee: {$certificate->name}", 'compliance')) {
            return ['success' => false, 'message' => 'Insufficient funds for audit fee ($5,000).'];
        }

        ComplianceAudit::create([
            'user_id' => $user->id,
            'certificate_id' => $certificate->id,
            'status' => 'active',
            'progress' => 0,
            'started_at' => now(),
            'completes_at' => now()->addMinutes(15), // Takes 15 minutes of gameplay
        ]);

        return ['success' => true, 'message' => 'Compliance audit started.'];
    }
}
