<?php

namespace App\QA;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Simulates high-level game actions that a player would take through the UI.
 * This class translates bot intents into API requests.
 */
class GameActionSimulator
{
    protected User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Executes a game action against the backend.
     */
    public function execute(string $action, array $params = []): array
    {
        switch ($action) {
            case 'buy_rack':
                return $this->callAction('POST', 'rack/purchase', $params);
            case 'buy_room':
                return $this->callAction('POST', 'rooms/purchase', $params);
            case 'place_server':
                return $this->callAction('POST', 'server/place', $params);
            case 'accept_order':
                return $this->callAction('POST', "orders/{$params['order_id']}/accept", $params);
            case 'reject_order':
                return $this->callAction('POST', "orders/{$params['order_id']}/reject", $params);
            case 'repair_server':
                return $this->callAction('POST', 'server/repair', $params);
            case 'toggle_power':
                return $this->callAction('POST', "server/{$params['server_id']}/power/toggle", $params);
            case 'research':
                return $this->callAction('POST', 'research/start', $params);
            case 'buy_component':
                return $this->callAction('POST', 'hardware/components/buy', $params);
            case 'assemble_server':
                return $this->callAction('POST', 'hardware/assemble', $params);
            default:
                // If the action looks like a URI, try it directly
                if (str_contains($action, '/')) {
                    $method = $params['_method'] ?? 'POST';
                    return $this->callAction($method, $action, $params);
                }
                return ['success' => false, 'error' => "Unknown action: {$action}"];
        }
    }

    protected function callAction(string $method, string $uri, array $params = []): array
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->user);

        $request = Request::create("api/{$uri}", $method, $params);
        $request->headers->set('Accept', 'application/json');

        try {
            $response = app()->handle($request);
            $data = json_decode($response->getContent(), true);

            if ($response->getStatusCode() >= 400) {
                return [
                    'success' => false, 
                    'error' => $data['message'] ?? $data['error'] ?? 'HTTP ' . $response->getStatusCode(),
                    'status' => $response->getStatusCode()
                ];
            }

            return $data ?? ['success' => true];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
