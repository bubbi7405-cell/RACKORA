<?php

namespace App\Services\Game;

use App\Models\Employee;
use App\Models\User;
use App\Models\Server;
use App\Enums\ServerStatus;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public const TYPES = [
        'sys_admin' => [
            'name' => 'System Administrator',
            'base_salary' => 150.00, 
            'hiring_cost' => 500,
            'description' => 'Automatically repairs 1 server per tick.',
        ],
        'support_agent' => [
            'name' => 'Support Agent',
            'base_salary' => 80.00,
            'hiring_cost' => 200,
            'description' => 'Reduces customer churn chance by 10% per agent (max 50%).',
        ]
    ];

    public function getAvailableTypes(): array
    {
        return self::TYPES;
    }

    public function getEmployees(User $user)
    {
        return Employee::where('user_id', $user->id)->get();
    }

    public function hire(User $user, string $type): array
    {
        if (!isset(self::TYPES[$type])) {
            return ['success' => false, 'error' => 'Invalid employee type'];
        }

        $config = self::TYPES[$type];
        
        if (!$user->economy->canAfford($config['hiring_cost'])) {
            return ['success' => false, 'error' => 'Insufficient funds'];
        }

        return DB::transaction(function () use ($user, $type, $config) {
            $employee = Employee::create([
                'user_id' => $user->id,
                'type' => $type,
                'name' => $this->generateName(),
                'salary' => $config['base_salary'],
                'efficiency' => 1.0,
                'hired_at' => now(),
            ]);

            if (!$user->economy->debit($config['hiring_cost'], "Hired {$config['name']}", 'hr', $employee)) {
                 throw new \Exception("Insufficient funds transaction failed");
            }

            return ['success' => true, 'data' => $employee, 'message' => "Welcome, {$employee->name}!"];
        });
    }

    public function fire(User $user, string $employeeId): array
    {
        $employee = Employee::where('user_id', $user->id)->where('id', $employeeId)->first();
        
        if (!$employee) {
            return ['success' => false, 'error' => 'Employee not found'];
        }

        // Variable severance pay? let's keep it simple: free to fire, or maybe 1h salary?
        $severance = $employee->salary;
        
        if (!$user->economy->debit($severance, "Severance pay for {$employee->name}", 'hr', $employee)) {
             return ['success' => false, 'error' => 'Cannot afford severance pay'];
        }

        $employee->delete();
        
        return ['success' => true, 'message' => "Employee terminated."];
    }
    
    public function getTotalHourlySalary(User $user): float
    {
        return (float) Employee::where('user_id', $user->id)->sum('salary');
    }

    public function processAutomation(User $user)
    {
        $employees = $this->getEmployees($user);
        $sysAdmins = $employees->where('type', 'sys_admin');
        
        // System Admins: Repair servers
        if ($sysAdmins->count() > 0) {
            $repairsPerTick = $sysAdmins->sum('efficiency'); // e.g. 1.0
            
            // Find damaged servers
            $damagedServers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
                ->where('health', '<', 100)
                ->where('status', '!=', ServerStatus::PROVISIONING) // Don't touch provisioning
                ->limit(floor($repairsPerTick)) // Integer repairs
                ->get();

            foreach ($damagedServers as $server) {
                // Free repair? Or cost?
                // Usually automation is "free labor" but "parts cost money".
                // Let's say labor is free (salary), parts cost = 20% of purchase price (same as manual).
                // But automated repair could be cheaper? Or same?
                // Let's make it standard cost to balance economy.
                $repairCost = $server->purchase_cost * 0.2;

                if ($user->economy->canAfford($repairCost)) {
                    // Perform repair
                     $user->economy->debit($repairCost, "Auto-repair: {$server->model_name}", 'maintenance', $server);
                     $server->health = 100;
                     if ($server->status === ServerStatus::OFFLINE) {
                         $server->status = ServerStatus::ONLINE;
                     }
                     $server->save();
                }
            }
        }
    }

    private function generateName(): string
    {
        $first = ['Alex', 'Sam', 'Jordan', 'Casey', 'Riley', 'Taylor', 'Morgan', 'Quinn', 'Skyler', 'Charlie'];
        $last = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
        
        return $first[array_rand($first)] . ' ' . $last[array_rand($last)];
    }
}
