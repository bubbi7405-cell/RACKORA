<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$competitors = App\Models\Competitor::all();
echo "Total Competitors: " . $competitors->count() . "\n";
foreach ($competitors as $c) {
    echo "- Name: " . $c->name . " | Archetype: " . $c->archetype . " | Status: " . $c->status . "\n";
}
