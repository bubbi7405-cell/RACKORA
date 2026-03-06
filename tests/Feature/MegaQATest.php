<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;

class MegaQATest extends TestCase
{
    /**
     * Test the Mega QA system by running a small simulation.
     */
    public function test_mega_qa_simulation_runs_successfully(): void
    {
        $exitCode = Artisan::call('qa:mega', [
            '--bots' => 2,
            '--ticks' => 5,
            '--clean' => true
        ]);

        $this->assertEquals(0, $exitCode, 'The Mega QA command failed.');
        
        // Final sanity check: Bot users should exist in the database
        $this->assertGreaterThan(0, User::where('name', 'LIKE', 'QA_BOT_%')->count());
    }
}
