<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\Server;
use App\Models\Employee;
use App\Models\ModernizationMission;
use App\Models\GameLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ModernizationService
{
    public function __construct(
        protected ResearchService $researchService,
        protected EmployeeService $employeeService
    ) {}

    /**
     * Start a modernization mission for a server.
     */
    public function startMission(User $user, Server $server, array $employeeIds): array
    {
        // 1. Check Research
        if (!$this->researchService->isUnlocked($user, 'modernization_missions')) {
            return ['success' => false, 'error' => 'Forschung "Legacy Modernization Protocols" erforderlich.'];
        }

        // 2. Check Server Health/Age
        if ($server->getLifespanUsage() < 50) {
            return ['success' => false, 'error' => 'Dieser Server ist noch zu neu für eine Modernisierung.'];
        }

        // 3. Check Employees
        $employees = Employee::where('user_id', $user->id)->whereIn('id', $employeeIds)->get();
        if ($employees->count() < 2) {
            return ['success' => false, 'error' => 'Es werden 2 Mitarbeiter benötigt.'];
        }

        $tech = $employees->firstWhere('type', 'sys_admin');
        $lead = $employees->filter(fn($e) => $e->type === 'sys_admin' && $e->level >= 10)->first();

        if (!$tech || !$lead || $tech->id === $lead->id) {
            return ['success' => false, 'error' => 'Bedingung nicht erfüllt: Benötigt 1x SysAdmin und 1x Lead SysAdmin (Min. Level 10).'];
        }

        // Check if employees are busy
        foreach ($employees as $emp) {
            if ($emp->current_task && !str_contains($emp->current_task, 'Idle')) {
                // Ignore some tasks? For now, be strict.
               // return ['success' => false, 'error' => "{$emp->name} ist bereits beschäftigt."];
            }
        }

        // 4. Check Funds
        $cost = $server->purchase_cost * 0.20; // 20% of initial price
        if (!$user->economy->canAfford($cost)) {
            return ['success' => false, 'error' => 'Nicht genug Guthaben. Kosten: $' . number_format($cost, 2)];
        }

        return DB::transaction(function() use ($user, $server, $employees, $cost) {
            $user->economy->debit($cost, "Modernization: {$server->model_name}", 'infrastructure', $server);

            $durationMinutes = 30; // 30 ticks
            
            $mission = ModernizationMission::create([
                'user_id' => $user->id,
                'target_id' => $server->id,
                'target_type' => 'server',
                'assigned_employee_ids' => $employees->pluck('id')->toArray(),
                'status' => 'active',
                'cost' => $cost,
                'started_at' => now(),
                'completes_at' => now()->addMinutes($durationMinutes),
            ]);

            // Set employee tasks
            foreach ($employees as $emp) {
                $emp->current_task = "🛠️ Modernizing Server #{$server->id}";
                $emp->task_progress = 0;
                $emp->save();
            }

            GameLog::log($user, "MISSION STARTED: Modernisierung von {$server->model_name} durch {$employees->pluck('name')->implode(' & ')} begonnen.", 'info', 'infrastructure');

            return ['success' => true, 'mission' => $mission];
        });
    }

    /**
     * Tick-based processing of active missions.
     */
    public function tick(User $user): void
    {
        $activeMissions = ModernizationMission::where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        foreach ($activeMissions as $mission) {
            if (now()->greaterThanOrEqualTo($mission->completes_at)) {
                $this->completeMission($mission);
            } else {
                // Update employee progress (purely visual in DB)
                $totalDuration = Carbon::parse($mission->started_at)->diffInSeconds($mission->completes_at);
                $elapsed = Carbon::parse($mission->started_at)->diffInSeconds(now());
                $progress = ($elapsed / $totalDuration) * 100;

                foreach ($mission->assigned_employee_ids as $empId) {
                    $emp = Employee::find($empId);
                    if ($emp) {
                        $emp->task_progress = min(100, $progress);
                        $emp->save();
                    }
                }
            }
        }
    }

    protected function completeMission(ModernizationMission $mission): void
    {
        $mission->status = 'completed';
        $mission->save();

        $server = Server::find($mission->target_id);
        if ($server) {
            // EFFECT: Reset Technical Debt
            // We reduce runtime by 80%, giving it a second life.
            $reduction = $server->total_runtime_seconds * 0.8;
            $server->total_runtime_seconds = max(0, $server->total_runtime_seconds - $reduction);
            $server->health = 100;
            
            // Add a flag to metadata so we know it was modernized
            $meta = $server->metadata ?? [];
            $meta['modernized_count'] = ($meta['modernized_count'] ?? 0) + 1;
            $server->metadata = $meta;
            
            $server->addMaintenanceLogEntry('modernization', "Full hardware modernization completed. Life extended by 80%.", 0);
            $server->save();
        }

        // Release employees
        foreach ($mission->assigned_employee_ids as $empId) {
            $emp = Employee::find($empId);
            if ($emp) {
                $emp->current_task = 'Idle';
                $emp->task_progress = 0;
                $emp->total_actions++;
                $emp->save();
                
                // Award XP
                $this->employeeService->awardXp($emp, 300);
            }
        }

        $user = User::find($mission->user_id);
        if ($user) {
            GameLog::log($user, "MISSION COMPLETE: {$server->model_name} wurde erfolgreich modernisiert. Effizienz wiederhergestellt!", 'success', 'infrastructure');
            $user->economy->addExperience(500);
        }
    }
}
