<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\EmployeeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'employees' => $this->service->getEmployees($request->user()),
            'available_types' => $this->service->getAvailableTypes(),
            'skill_trees' => EmployeeService::SKILL_TREES,
            'active_bonuses' => $this->service->getAllActiveBonuses($request->user())
        ]);
    }

    public function hire(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string',
        ]);

        $result = $this->service->hire($request->user(), $request->type);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    public function fire(Request $request, string $id): JsonResponse
    {
        $result = $this->service->fire($request->user(), $id);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    public function train(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|uuid',
        ]);

        $result = $this->service->train($request->user(), $request->employee_id);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    public function giveRaise(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|uuid',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $result = $this->service->giveRaise($request->user(), $request->employee_id, (float) $request->amount);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    public function unlockPerk(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|uuid',
            'perk_id' => 'required|string',
        ]);

        $result = $this->service->unlockPerk($request->user(), $request->employee_id, $request->perk_id);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    public function respec(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|uuid',
        ]);

        $result = $this->service->respec($request->user(), $request->employee_id);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * FEATURE 284: Send employee on sabbatical
     */
    public function sendOnSabbatical(Request $request, string $id): JsonResponse
    {
        $result = $this->service->sendOnSabbatical($request->user(), $id);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * FEATURE 128: Golden Handcuffs (Retention Bonus)
     */
    public function giveRetentionBonus(Request $request, string $id): JsonResponse
    {
        $hours = (int) ($request->hours ?? 24);
        $result = $this->service->giveRetentionBonus($request->user(), $id, $hours);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * FEATURE 69: Counter a headhunter offer
     * Player matches or exceeds the competitor salary to retain the employee.
     */
    public function counterHeadhunter(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'action' => 'required|string|in:match,exceed,reject',
        ]);

        $user = $request->user();
        $employee = \App\Models\Employee::where('user_id', $user->id)->findOrFail($id);

        $offer = $employee->metadata['headhunter_offer'] ?? null;
        if (!$offer) {
            return response()->json(['success' => false, 'error' => 'No active headhunter offer.'], 400);
        }

        $action = $request->action;

        if ($action === 'reject') {
            // Let the employee decide on their own (removes player intervention)
            $meta = $employee->metadata ?? [];
            $meta['headhunter_offer']['deadline'] = now()->toIso8601String(); // Expire immediately
            $employee->metadata = $meta;
            $employee->save();

            return response()->json([
                'success' => true,
                'message' => "You declined to counter the offer. {$employee->name} will decide on their own.",
            ]);
        }

        // Match or exceed
        $newSalary = $action === 'exceed'
            ? round($offer['salary'] * 1.1, 2) // 10% above competitor offer
            : $offer['salary'];

        $employee->salary = $newSalary;
        $employee->loyalty = min(100, $employee->loyalty + 20); // Loyalty boost

        $meta = $employee->metadata ?? [];
        unset($meta['headhunter_offer']);
        $employee->metadata = $meta;
        $employee->save();

        \App\Models\GameLog::log($user, "✅ COUNTER-OFFER: {$employee->name} stays! New salary: \${$newSalary}/h (+20 loyalty).", 'success', 'hr');

        return response()->json([
            'success' => true,
            'message' => "{$employee->name} accepted your counter-offer and stays loyal!",
            'employee' => $this->service->formatEmployee($employee),
        ]);
    }
}
