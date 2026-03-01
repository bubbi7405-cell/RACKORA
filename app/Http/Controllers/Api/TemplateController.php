<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\ServerTemplate;
use App\Services\Game\RolloutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    protected $rolloutService;

    public function __construct(RolloutService $rolloutService)
    {
        $this->rolloutService = $rolloutService;
    }

    /**
     * List all templates for the user.
     */
    public function index(Request $request): JsonResponse
    {
        $templates = $this->rolloutService->getUserTemplates($request->user());
        
        return response()->json([
            'success' => true,
            'templates' => $templates
        ]);
    }

    /**
     * Create a template from a specific server.
     */
    public function createFromServer(Request $request): JsonResponse
    {
        $request->validate([
            'server_id' => 'required|exists:servers,id',
            'name' => 'required|string|max:50',
        ]);

        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('id', $request->server_id)
            ->firstOrFail();

        $template = $this->rolloutService->createTemplate($user, $server, $request->name);

        return response()->json([
            'success' => true,
            'message' => 'Template successfully created.',
            'template' => $template
        ]);
    }

    /**
     * Apply a template to a server.
     */
    public function applyToServer(Request $request): JsonResponse
    {
        $request->validate([
            'server_id' => 'required|exists:servers,id',
            'template_id' => 'required|exists:server_templates,id',
        ]);

        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('id', $request->server_id)
            ->firstOrFail();

        $template = $user->serverTemplates()->where('id', $request->template_id)->firstOrFail();

        $success = $this->rolloutService->applyTemplate($server, $template);

        if (!$success) {
            return response()->json([
                'success' => false,
                'error' => 'Template cannot be applied. OS already installed or in progress.'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rollout started using template.',
            'server' => $server->toGameState()
        ]);
    }

    /**
     * Delete a template.
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        $success = $this->rolloutService->deleteTemplate($request->user(), $id);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Template deleted.' : 'Template not found.'
        ]);
    }
}
