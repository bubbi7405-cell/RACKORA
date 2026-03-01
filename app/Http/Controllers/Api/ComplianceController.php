<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\ComplianceAudit;
use App\Services\Game\ComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    public function __construct(
        private ComplianceService $complianceService
    ) {}

    /**
     * Get all certificates and user status
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userCerts = $user->certifications()->get()->keyBy('id');
        $activeAudits = $user->audits()->where('status', 'active')->get()->keyBy('certificate_id');
        $allCerts = Certificate::all();

        $data = $allCerts->map(function ($cert) use ($userCerts, $activeAudits) {
            $userCert = $userCerts->get($cert->id);
            $audit = $activeAudits->get($cert->id);

            return [
                'id' => $cert->id,
                'key' => $cert->key,
                'name' => $cert->name,
                'description' => $cert->description,
                'category' => $cert->category,
                'requirements' => $cert->requirements,
                'bonus_reputation' => (float) $cert->bonus_reputation,
                'isCertified' => $userCert !== null,
                'expiresAt' => $userCert ? $userCert->pivot->expires_at->toIso8601String() : null,
                'activeAudit' => $audit ? [
                    'id' => $audit->id,
                    'progress' => $audit->progress,
                    'completesAt' => $audit->completes_at->toIso8601String(),
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'userResearch' => $user->researches()->where('status', 'completed')->pluck('key'),
                'economy' => $user->economy->toGameState(),
            ]
        ]);
    }

    /**
     * Start an audit for a certificate
     */
    public function startAudit(Request $request): JsonResponse
    {
        $request->validate([
            'certificate_id' => 'required|exists:certificates,id',
        ]);

        $user = $request->user();
        $certificate = Certificate::findOrFail($request->certificate_id);

        $result = $this->complianceService->startAudit($user, $certificate);

        return response()->json($result);
    }
}
