<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\Game\SoftwareService;
use Illuminate\Http\Request;

class SoftwareController extends Controller
{
    public function __construct(
        protected SoftwareService $softwareService
    ) {}

    public function getCatalog(Request $request)
    {
        return response()->json($this->softwareService->getDefinitions());
    }

    public function install(Request $request, string $serverId)
    {
         $user = $request->user();
         $server = Server::where('id', $serverId)
             ->where(function ($q) use ($user) {
                 $q->where('tenant_id', $user->id)
                   ->orWhereHas('rack.room', fn($r) => $r->where('user_id', $user->id));
             })
             ->firstOrFail();

         $request->validate([
             'software_id' => 'required|string',
         ]);

         try {
             $this->softwareService->install($server, $request->input('software_id'));
             return response()->json(['success' => true, 'message' => 'Software installation initiated.', 'server' => $server->toGameState()]);
         } catch (\Exception $e) {
             return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
         }
    }

    public function uninstall(Request $request, string $serverId)
    {
         $user = $request->user();
         $server = Server::where('id', $serverId)
             ->where(function ($q) use ($user) {
                 $q->where('tenant_id', $user->id)
                   ->orWhereHas('rack.room', fn($r) => $r->where('user_id', $user->id));
             })
             ->firstOrFail();

         $request->validate([
             'software_id' => 'required|string',
         ]);

         try {
             $this->softwareService->uninstall($server, $request->input('software_id'));
             return response()->json(['success' => true, 'message' => 'Software uninstalled.', 'server' => $server->toGameState()]);
         } catch (\Exception $e) {
             return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
         }
    }

    public function update(Request $request, string $serverId)
    {
         $user = $request->user();
         $server = Server::where('id', $serverId)
             ->where(function ($q) use ($user) {
                 $q->where('tenant_id', $user->id)
                   ->orWhereHas('rack.room', fn($r) => $r->where('user_id', $user->id));
             })
             ->firstOrFail();

         $request->validate([
             'software_id' => 'required|string',
         ]);

         try {
             $this->softwareService->update($server, $request->input('software_id'));
             return response()->json(['success' => true, 'message' => 'Software updated.', 'server' => $server->toGameState()]);
         } catch (\Exception $e) {
             return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
         }
    }
}
