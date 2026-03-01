<?php

use App\Models\WorldEvent;
use Illuminate\Support\Facades\Cache;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- STARTING ENERGY CRISIS SIMULATION ---\n";

// 1. Create the Event
echo "Triggering: Heatwave in Tokyo...\n";
$event = WorldEvent::create([
    'title' => 'Heatwave: Asia Pacific',
    'description' => 'Extreme temperatures in Tokyo are straining the power grid. Cooling costs are skyrocketing.',
    'type' => 'crisis',
    'modifier_type' => 'energy_price:asia-east-tokyo',
    'modifier_value' => 1.8, // +80% price
    'starts_at' => now(),
    'ends_at' => now()->addMinutes(15),
    'is_active' => true
]);

echo "Event Created: ID " . $event->id . "\n";

// 2. Force a Tick to update cache immediately
echo "Forcing Game Tick for Energy Market...\n";
app(\App\Services\Game\EnergyService::class)->tickMarket();

// 3. Read back the price
$prices = Cache::get('energy_regional_prices', []);
$tokyoPrice = $prices['asia_east'] ?? 'N/A';
$globalPrice = $prices['global_avg'] ?? 'N/A';

echo "SIMULATION RESULTS:\n";
echo "Global Spot Price: $" . number_format($globalPrice, 4) . "\n";
echo "Tokyo Regional Price: $" . number_format($tokyoPrice, 4) . "\n";

if ($tokyoPrice > 0.25) { // Assuming base is ~0.22
    echo "SUCCESS: Price spike detected in Tokyo!\n";
} else {
    echo "WARNING: Price did not spike as expected.\n";
}

echo "--- END SIMULATION ---\n";
