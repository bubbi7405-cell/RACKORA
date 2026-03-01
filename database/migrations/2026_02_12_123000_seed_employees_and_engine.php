<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\GameConfig;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Employee Types
        $employees = [
            'sys_admin' => [
                'name' => 'System Administrator',
                'description' => 'Repairs servers and maintains infrastructure.',
                'base_salary' => 25, // $25/hr
                'hiring_cost' => 1500,
                'efficiency' => 1.0,
                'stress_resistance' => 1.0
            ],
            'support_agent' => [
                'name' => 'Support Agent',
                'description' => 'Reduces customer churn and handles tickets.',
                'base_salary' => 18, // $18/hr
                'hiring_cost' => 800,
                'efficiency' => 1.0,
                'stress_resistance' => 0.8
            ],
            'sales_rep' => [
                 'name' => 'Sales Representative',
                 'description' => 'Coming soon: Boosts order generation.',
                 'base_salary' => 30,
                 'hiring_cost' => 2000,
                 'efficiency' => 1.0,
                 'stress_resistance' => 1.2
            ]
        ];
        GameConfig::set('employee_types', $employees, 'personnel', 'Employee roles');

        // 2. Engine Constants
        $engine = [
            'xp_multiplier' => 1.0,
            'income_tax_rate' => 0.15,
            'global_energy_cost' => 0.12, // $/kWh
            'customer_patience_modifier' => 1.0,
            'reputation_decay_rate' => 0.5, // per hour
            'base_churn_rate' => 0.02
        ];
        GameConfig::set('engine_constants', $engine, 'engine', 'Core game multipliers');
    }

    public function down(): void
    {
        GameConfig::where('key', 'employee_types')->delete();
        GameConfig::where('key', 'engine_constants')->delete();
    }
};
