<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Game\PrivateNetworkService;
use Illuminate\Http\Request;

class PrivateNetworkController extends Controller
{
    public function __construct(
        protected PrivateNetworkService $service
    ) {}

    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->getUserNetworks($request->user())
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'cidr' => 'sometimes|string',
        ]);

        try {
            $network = $this->service->createNetwork(
                $request->user(),
                $request->name,
                $request->cidr ?? '10.0.0.0/24'
            );
            return response()->json([
                'success' => true,
                'data' => $network->toGameState(),
                'message' => 'Network created.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $this->service->deleteNetwork($request->user(), $id);
            return response()->json([
                'success' => true,
                'message' => 'Network deleted.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function attachServer(Request $request, string $id)
    {
        $request->validate([
            'server_id' => 'required|string',
        ]);

        try {
            $this->service->attachServer($request->user(), $id, $request->server_id);
            return response()->json([
                'success' => true,
                'message' => 'Server attached to network.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function detachServer(Request $request)
    {
        $request->validate([
            'server_id' => 'required|string',
        ]);

        try {
            $this->service->detachServer($request->user(), $request->server_id);
            return response()->json([
                'success' => true,
                'message' => 'Server detached from network.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
    public function storeRule(Request $request, string $id)
    {
        $request->validate([
            'type' => 'required|in:ALLOW,DENY',
            'protocol' => 'required|in:TCP,UDP,ICMP,ANY',
            'port_range' => ['nullable', 'string', 'regex:/^(\d+(-\d+)?)(,\d+(-\d+)?)*$/'],
            'source_cidr' => ['nullable', 'string', 'regex:/^(\d{1,3}\.){3}\d{1,3}(\/\d{1,2})?$/'],
            'priority' => 'nullable|integer|min:0|max:10000',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $rule = $this->service->addFirewallRule($request->user(), $id, $request->all());
            return response()->json([
                'success' => true,
                'message' => 'Firewall rule added.',
                'data' => [
                    'id' => $rule->id,
                    'type' => $rule->type,
                    'protocol' => $rule->protocol,
                    'port' => $rule->port_range,
                    'source' => $rule->source_cidr,
                    'priority' => $rule->priority,
                    'description' => $rule->description,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function destroyRule(Request $request, string $ruleId)
    {
        try {
            $this->service->deleteFirewallRule($request->user(), $ruleId);
            return response()->json([
                'success' => true,
                'message' => 'Firewall rule deleted.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function testRule(Request $request, string $id)
    {
        $request->validate([
            'protocol' => 'required|in:TCP,UDP,ICMP,ANY',
            'port' => 'nullable|integer',
            'source_ip' => 'required|string',
        ]);

        try {
            $network = \App\Models\PrivateNetwork::where('user_id', $request->user()->id)->where('id', $id)->firstOrFail();
            $allowed = $this->service->isTrafficAllowed(
                $network,
                $request->protocol,
                $request->port,
                $request->source_ip
            );

            return response()->json([
                'success' => true,
                'allowed' => $allowed,
                'message' => $allowed ? 'Traffic ALLOWED by firewall.' : 'Traffic DENIED by firewall.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
