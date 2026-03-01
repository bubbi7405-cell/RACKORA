<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\ManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ManagementController extends Controller
{
    public function __construct(
        protected ManagementService $managementService
    ) {}

    /**
     * Commit a strategic decision
     */
    public function makeDecision(Request $request)
    {
        $request->validate([
            'decision_type' => 'required|string',
            'option_key' => 'required|string',
        ]);

        $user = Auth::user();
        $economy = $user->economy;

        if (!$economy) {
            return response()->json(['success' => false, 'error' => 'Economy not initialized'], 400);
        }

        try {
            $this->managementService->makeDecision(
                $economy,
                $request->decision_type,
                $request->option_key
            );

            return response()->json([
                'success' => true,
                'message' => 'Strategic decision recorded.',
                'gameState' => [
                    'economy' => $economy->toGameState()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getSpecializations()
    {
        $service = app(\App\Services\Game\SpecializationService::class);
        $user = Auth::user();

        return response()->json([
             'success' => true,
             'definitions' => $service->getDefinitions(),
             'current' => $user->specialization ?? 'balanced', 
             'updated_at' => $user->specialization_updated_at
        ]);
    }

    public function setSpecialization(Request $request)
    {
        $request->validate([
            'specialization' => 'required|string'
        ]);

        $service = app(\App\Services\Game\SpecializationService::class);
        $user = Auth::user();

        try {
            $service->setSpecialization($user, $request->specialization);
            
            return response()->json([
                'success' => true,
                'message' => 'Company specialization updated.',
                'current' => $user->specialization,
                'gameState' => [
                    'economy' => $user->economy->toGameState()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function getSkillTree()
    {
        $service = app(\App\Services\Game\PlayerSkillService::class);
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'tree' => $service->getTree(),
            'unlocked' => $user->economy->unlocked_skills ?? [],
            'points' => $user->economy->skill_points
        ]);
    }

    public function unlockSkill(Request $request)
    {
        $request->validate([
            'skill_id' => 'required|string'
        ]);

        $service = app(\App\Services\Game\PlayerSkillService::class);
        $user = Auth::user();

        try {
            $service->unlockSkill($user, $request->skill_id);
            
            return response()->json([
                'success' => true,
                'message' => 'Specialization unlocked!',
                'gameState' => [
                    'economy' => $user->economy->toGameState()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Update company branding (name and logo)
     */
    public function updateCompanyBranding(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:50',
            'company_logo' => 'nullable|string'
        ]);

        $user = Auth::user();
        $user->company_name = $request->company_name;
        if ($request->has('company_logo')) {
            $user->company_logo = $request->company_logo;
        }
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Company branding updated.',
            'companyName' => $user->company_name,
            'companyLogo' => $user->company_logo
        ]);
    }

    /**
     * FEATURE 195: Board of Directors – Get current board state & KPI missions.
     */
    public function getBoardState()
    {
        $user = Auth::user();
        $service = app(\App\Services\Game\BoardOfDirectorsService::class);

        return response()->json([
            'success' => true,
            'data' => $service->getBoardState($user)
        ]);
    }

    /**
     * FEATURE 206: Bribery – Get current bribery offers and moral score.
     */
    public function getBriberyState()
    {
        $user = Auth::user();
        $service = app(\App\Services\Game\BriberyService::class);

        return response()->json([
            'success' => true,
            'data' => $service->getState($user)
        ]);
    }

    /**
     * FEATURE 206: Accept a bribe offer.
     */
    public function acceptBribe(Request $request)
    {
        $request->validate(['bribe_id' => 'required|string']);
        
        $user = Auth::user();
        $service = app(\App\Services\Game\BriberyService::class);
        $result = $service->acceptBribe($user, $request->bribe_id);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * FEATURE 206: Decline a bribe offer.
     */
    public function declineBribe(Request $request)
    {
        $request->validate(['bribe_id' => 'required|string']);

        $user = Auth::user();
        $service = app(\App\Services\Game\BriberyService::class);
        $result = $service->declineBribe($user, $request->bribe_id);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * FEATURE 202: Darknet – Get current heat level and types.
     */
    public function getDarknetState()
    {
        $user = Auth::user();
        $service = app(\App\Services\Game\DarknetService::class);

        return response()->json([
            'success' => true,
            'data' => $service->getState($user)
        ]);
    }

    /**
     * Perform PR Outreach for a specific customer
     */
    public function prOutreach(Request $request, string $customerId)
    {
        $user = Auth::user();
        $customer = \App\Models\Customer::where('user_id', $user->id)
            ->where('id', $customerId)
            ->firstOrFail();

        $cost = 2500;
        if (!$user->economy->canAfford($cost)) {
            throw new \Exception("Insufficient funds ($2.5k needed)");
        }

        if ($customer->satisfaction >= 95) {
            throw new \Exception("Customer is already satisfied.");
        }

        $user->economy->debit($cost, "PR Outreach: {$customer->company_name}", 'marketing');
        
        $customer->satisfaction = min(100, $customer->satisfaction + 15);
        $customer->save();

        \App\Models\GameLog::log($user, "PR_OUTREACH: Handled reputation crisis for {$customer->company_name}.", 'success', 'marketing');

        return response()->json([
            'success' => true,
            'message' => 'PR Outreach completed. Satisfaction increased.',
            'customer' => $customer->toGameState()
        ]);
    }
}
