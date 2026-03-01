<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Services\Game\EmployeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSkillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed config if necessary or mock it
        \App\Models\GameConfig::set('employee_types', [
            'sys_admin' => [
                'name' => 'System Administrator',
                'hiring_cost' => 1000,
                'base_salary' => 50,
                'description' => 'Fixes servers'
            ]
        ]);
        
        \App\Models\GameConfig::set('market.economic_state', 'growth', 'market');
    }

    public function test_employee_gains_xp_and_levels_up()
    {
        $user = User::factory()->create();
        // Ensure economy exists (factory might create it, or we need to)
        if (!$user->economy) {
            $user->economy()->create(['balance' => 10000]);
        } else {
            $user->economy->update(['balance' => 10000]);
        }
        $user->refresh();

        $service = app(EmployeeService::class);
        $result = $service->hire($user, 'sys_admin');
        
        $this->assertTrue($result['success']);
        $employee = $result['data'];
        
        $this->assertEquals(0, $employee->xp);
        $this->assertEquals(0, $employee->skill_points);
        $this->assertEquals(1, $employee->level); 
        
        $service->awardXp($employee, 500);
        $employee->refresh();
        
        $this->assertEquals(2, $employee->level);
        $this->assertEquals(1, $employee->skill_points);
    }

    public function test_unlock_perk()
    {
        $user = User::factory()->create();
        $service = app(EmployeeService::class);
        
        $employee = Employee::create([
            'user_id' => $user->id,
            'type' => 'sys_admin',
            'name' => 'Tester',
            'level' => 5, // High enough for perks
            'skill_points' => 5,
            'salary' => 100,
            'efficiency' => 1.0,
            'xp' => 5000
        ]);

        $result = $service->unlockPerk($user, $employee->id, 'cli_wizard');
        
        $this->assertTrue($result['success']);
        $employee->refresh();
        $this->assertTrue($service->hasPerk($employee, 'cli_wizard'));
        $this->assertEquals(4, $employee->skill_points); // Cost is 1
    }
}
