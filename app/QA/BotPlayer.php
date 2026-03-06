<?php

namespace App\QA;

use App\Models\User;
use Illuminate\Support\Facades\Log;

abstract class BotPlayer
{
    protected User $user;
    protected array $stats = [];
    protected array $logs = [];

    public function __construct(User $user)
    {
        $this->user = $user->load(['economy', 'rooms.racks.servers', 'customers.orders']);
    }

    /**
     * Main simulation tick.
     */
    abstract public function tick(int $currentTick): void;

    /**
     * Get the bot's strategy name.
     */
    abstract public function getStrategyName(): string;

    /**
     * Perform an action through the simulator.
     */
    protected function performAction(string $action, array $params = []): array
    {
        $simulator = new GameActionSimulator($this->user);
        $result = $simulator->execute($action, $params);
        
        $logEntry = [
            'tick' => now()->toIso8601String(),
            'action' => $action,
            'params' => $params,
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? ($result['error'] ?? null)
        ];
        
        $this->logs[] = $logEntry;
        
        if (!($result['success'] ?? false)) {
            Log::warning("QA_BOT [{$this->getStrategyName()}]: Action {$action} failed", $result);
        }

        return $result;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function getUser(): User
    {
        return $this->user->fresh(['economy', 'rooms.racks.servers', 'customers.orders']);
    }
}
