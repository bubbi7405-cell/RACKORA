<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('password')]
        );

        User::updateOrCreate(
            ['email' => 'danny@codepony.de'],
            [
                'name' => 'Danny',
                'password' => bcrypt('Mandysandy.2007'),
            ]
        );
        // Seed Global Game State
        $this->call([
            GameConfigSeeder::class,
            HardwareGenerationSeeder::class,
            CertificateSeeder::class,
            CompetitorSeeder::class,
            WorldEventSeeder::class,
            MarketRegionSeeder::class,
        ]);

        // Initialize Dynamic Market State
        \App\Services\Market\MarketSimulationService::initializeMarketState();
    }
}
