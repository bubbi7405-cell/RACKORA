<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\PlayerEconomy;
use App\Services\Game\SpecializationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SpecializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_specialization_flow()
    {
        $user = User::factory()->create();
        
        // Manually create economy since factory doesn't do it
        $economy = PlayerEconomy::create([
            'user_id' => $user->id,
            'balance' => 100000,
            'level' => 10,
            'reputation' => 50,
            'corporate_specialization' => 'balanced'
        ]);
        
        $user->specialization = 'balanced';
        $user->save();

        $service = app(SpecializationService::class);

        // 1. Initial State
        $this->assertEquals('balanced', $user->specialization);
        $defs = $service->getDefinitions();
        $this->assertArrayHasKey('budget_mass', $defs);

        // 2. Switch to Budget Mass
        // unlock_cost is 5000
        $initialBalance = $economy->balance;
        
        $service->setSpecialization($user, 'budget_mass');
        
        $user->refresh();
        $economy->refresh();
        
        $this->assertEquals('budget_mass', $user->specialization);
        $this->assertEquals('budget_mass', $economy->corporate_specialization);
        $this->assertEquals($initialBalance - 5000, $economy->balance);
        $this->assertNotNull($user->specialization_updated_at);

        // 3. Try switching again immediately (Cooldown)
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Rebranding cooldown');
        
        $service->setSpecialization($user, 'high_performance');
    }

    public function test_specialization_level_requirement()
    {
        $user = User::factory()->create();
        
        $economy = PlayerEconomy::create([
            'user_id' => $user->id,
            'balance' => 10000,
            'level' => 5, // Too low
            'reputation' => 50
        ]);

        $service = app(SpecializationService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Level 10');

        $service->setSpecialization($user, 'budget_mass');
    }
}
