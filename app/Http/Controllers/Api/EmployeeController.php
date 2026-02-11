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
}
