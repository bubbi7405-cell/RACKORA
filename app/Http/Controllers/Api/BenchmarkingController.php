<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\BenchmarkingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BenchmarkingController extends Controller
{
    public function __construct(
        protected BenchmarkingService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'benchmarks' => $this->service->getUserBenchmarks($request->user()),
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $request->validate([
            'model_key' => 'required|string',
            'clock_mod' => 'required|numeric|min:1|max:2',
            'voltage_mod' => 'required|numeric|min:1|max:1.5',
        ]);

        $result = $this->service->runTest(
            $request->user(),
            $request->model_key,
            (float) $request->clock_mod,
            (float) $request->voltage_mod
        );

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }
}
