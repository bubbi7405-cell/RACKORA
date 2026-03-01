<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiEndpoint;
use App\Services\Game\ApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiSimulationController extends Controller
{
    public function __construct(
        private ApiService $apiService
    ) {}

    /**
     * Get all API endpoints for user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $endpoints = ApiEndpoint::where('user_id', $user->id)
            ->with(['server'])
            ->get()
            ->map(fn($e) => $e->toGameState());

        return response()->json([
            'success' => true,
            'data' => $endpoints,
        ]);
    }

    /**
     * Create a new virtual API endpoint
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'server_id' => 'required|uuid|exists:servers,id',
            'path' => 'required|string|max:100',
            'method' => 'string|in:GET,POST,PUT,DELETE',
            'max_rpm' => 'integer|min:10|max:10000',
            'complexity' => 'string|in:low,medium,high',
        ]);

        $user = $request->user();
        $endpoint = $this->apiService->createEndpoint($user, $request->all());

        return response()->json([
            'success' => true,
            'data' => $endpoint->toGameState(),
        ]);
    }

    /**
     * Delete an endpoint
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $endpoint = ApiEndpoint::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();
            
        $endpoint->delete();

        return response()->json(['success' => true]);
    }
}
