<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GameConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. World Event Templates (World News) — Deutsche Texte + regionale Zuordnung
        \App\Models\GameConfig::set('world_event_templates', [
            // ── GLOBALE EVENTS ──────────────────────────────────
            [
                'title' => 'Energiekrise',
                'description' => 'Globale Energiepreise steigen durch Lieferkettenprobleme. Stromkosten +50%.',
                'type' => 'crisis',
                'modifier_type' => 'power_cost',
                'modifier_value' => 1.5,
                'duration_minutes' => 60,
                'affected_regions' => null, // global
            ],
            [
                'title' => 'Tech-Boom',
                'description' => 'Ein neuer KI-Startup-Hype treibt die Server-Nachfrage. Bestellfrequenz +30%.',
                'type' => 'boom',
                'modifier_type' => 'order_frequency',
                'modifier_value' => 1.3,
                'duration_minutes' => 45,
                'affected_regions' => null,
            ],
            [
                'title' => 'Halbleiter-Knappheit',
                'description' => 'Ein Brand in einer Chip-Fabrik hat weltweite Engpässe ausgelöst. Reparaturkosten verdoppelt.',
                'type' => 'crisis',
                'modifier_type' => 'repair_cost',
                'modifier_value' => 2.0,
                'duration_minutes' => 90,
                'affected_regions' => null,
            ],
            [
                'title' => 'Cyber Monday',
                'description' => 'E-Commerce-Nachfrage am Limit! Neue Bestellwerte +20% höher.',
                'type' => 'boom',
                'modifier_type' => 'order_value',
                'modifier_value' => 1.2,
                'duration_minutes' => 120,
                'affected_regions' => null,
            ],
            [
                'title' => 'Globaler Markteinbruch',
                'description' => 'Eine globale Rezession drückt Hardware-Preise. Liquidatoren verkaufen Premium-Komponenten mit 80% Rabatt!',
                'type' => 'boom',
                'modifier_type' => 'hardware_cost',
                'modifier_value' => 0.2,
                'duration_minutes' => 20,
                'affected_regions' => null,
            ],
            [
                'title' => 'Lieferkettenstörung',
                'description' => 'Kritische Schifffahrtsrouten blockiert. Hardware-Kosten steigen um 35%.',
                'type' => 'crisis',
                'modifier_type' => 'hardware_cost',
                'modifier_value' => 1.35,
                'duration_minutes' => 45,
                'affected_regions' => null,
            ],

            // ── REGIONALE EVENTS ─────────────────────────────────
            [
                'title' => 'Black Friday Server-Rush',
                'description' => 'E-Commerce-Boom in den USA! Hosting-Nachfrage steigt massiv.',
                'type' => 'boom',
                'modifier_type' => 'order_value',
                'modifier_value' => 1.3,
                'duration_minutes' => 60,
                'affected_regions' => ['us_east', 'us_west'],
            ],
            [
                'title' => 'KI-Goldrausch',
                'description' => 'Ein neues Sprachmodell dominiert den Markt. Die Nachfrage nach GPU-Clustern und HPC-Leistung steigt massiv an.',
                'type' => 'boom',
                'modifier_type' => 'revenue',
                'modifier_value' => 1.3,
                'duration_minutes' => 90,
                'affected_regions' => ['asia_east', 'us_west'],
            ],
            [
                'title' => 'DSGVO-Verschärfung',
                'description' => 'Neue EU-Richtlinien erhöhen die Compliance-Anforderungen. Datenschutz-Kunden zahlen +20% Premium.',
                'type' => 'info',
                'modifier_type' => 'compliance_demand',
                'modifier_value' => 1.2,
                'duration_minutes' => 120,
                'affected_regions' => ['eu_central', 'nordics'],
            ],
            [
                'title' => 'Nordische Grünstrom-Subvention',
                'description' => 'Schweden und Norwegen subventionieren grüne Rechenzentren. Stromkosten -30% in der Region.',
                'type' => 'boom',
                'modifier_type' => 'power_cost',
                'modifier_value' => 0.7,
                'duration_minutes' => 90,
                'affected_regions' => ['nordics'],
            ],
            [
                'title' => 'Singapur FinTech-Boom',
                'description' => 'Der Finanzsektor in Singapur boomt. Ultra-Low-Latenz-Kunden zahlen Premiumpreise.',
                'type' => 'boom',
                'modifier_type' => 'order_value',
                'modifier_value' => 1.5,
                'duration_minutes' => 60,
                'affected_regions' => ['asia_south'],
            ],
            [
                'title' => 'São Paulo Netzinstabilität',
                'description' => 'Schwere Unwetter in Brasilien destabilisieren das Stromnetz. Ausfallrisiko steigt.',
                'type' => 'crisis',
                'modifier_type' => 'failure_rate',
                'modifier_value' => 1.8,
                'duration_minutes' => 45,
                'affected_regions' => ['south_america'],
            ],
            [
                'title' => 'Hitzewelle in Europa',
                'description' => 'Extreme Temperaturen in Mitteleuropa. Kühlkosten steigen, Netzstabilität sinkt.',
                'type' => 'crisis',
                'modifier_type' => 'power_cost',
                'modifier_value' => 1.4,
                'duration_minutes' => 60,
                'affected_regions' => ['eu_central'],
            ],
            [
                'title' => 'Taifun-Saison Tokio',
                'description' => 'Ein massiver Taifun bedroht die Infrastruktur in Ostasien. Latenz steigt, Ausfallrisiko erhöht.',
                'type' => 'crisis',
                'modifier_type' => 'failure_rate',
                'modifier_value' => 2.0,
                'duration_minutes' => 30,
                'affected_regions' => ['asia_east'],
            ],
            [
                'title' => 'Oregon Wasserkraft-Bonus',
                'description' => 'Überdurchschnittliche Schneeschmelze liefert günstige Wasserkraft. Stromkosten -25%.',
                'type' => 'boom',
                'modifier_type' => 'power_cost',
                'modifier_value' => 0.75,
                'duration_minutes' => 120,
                'affected_regions' => ['us_west'],
            ],
            [
                'title' => 'Brasilianischer Startup-Boom',
                'description' => 'São Paulo entwickelt sich zum Tech-Hub. Neue Kunden strömen auf den Markt.',
                'type' => 'boom',
                'modifier_type' => 'order_frequency',
                'modifier_value' => 1.6,
                'duration_minutes' => 60,
                'affected_regions' => ['south_america'],
            ],
        ], 'simulation', 'Templates für dynamische Welt-Ereignisse (global + regional).');

        // 2. Employee Types
        \App\Models\GameConfig::set('employee_types', [
            'sys_admin' => [
                'name' => 'System Administrator',
                'base_salary' => 150.00, 
                'hiring_cost' => 500,
                'description' => 'Automatically repairs servers and cleans racks. Highly stressed by incidents.',
                'tasks' => ['Repairing Server', 'Cleaning Dust', 'Monitoring Hardware']
            ],
            'support_agent' => [
                'name' => 'Support Agent',
                'base_salary' => 80.00,
                'hiring_cost' => 200,
                'description' => 'Reduces customer churn. Stressed by unhappy customers and service outages.',
                'tasks' => ['Handling Ticket', 'Retention Case', 'Escalating SLA Issue']
            ],
            'security_engineer' => [
                'name' => 'Security Engineer',
                'base_salary' => 250.00,
                'hiring_cost' => 1500,
                'description' => 'Passively improves security score and provides active defense against breaches.',
                'tasks' => ['Vulnerability Scanning', 'Patching Zero-Days', 'Active Countermeasures']
            ],
            'compliance_officer' => [
                'name' => 'Compliance Officer',
                'base_salary' => 200.00,
                'hiring_cost' => 1000,
                'description' => 'Boosts privacy scores and ensures audits complete faster with higher success probability.',
                'tasks' => ['Internal Audit', 'Data Mapping', 'SLA Contract Review']
            ],
            'network_engineer' => [
                'name' => 'Network Engineer',
                'base_salary' => 180.00,
                'hiring_cost' => 800,
                'description' => 'Optimizes routing to reduce latency and negotiates better peering to lower bandwidth costs.',
                'tasks' => ['BGP Optimization', 'Congestion Control', 'ISP Peering Negotiation']
            ]
        ], 'simulation', 'Available personnel roles and their base stats.');

        // 3. Energy Market Parameters
        \App\Models\GameConfig::set('energy_market_settings', [
            'base_price' => 0.12,
            'volatility' => 0.02, // 2% per tick max
            'min_price' => 0.04,
            'max_price' => 0.45,
            'reversion_speed' => 0.05
        ], 'economy', 'Parameters for the energy market simulation.');

        // 4. Regions — Jede Region hat einzigartige wirtschaftliche Profile
        \App\Models\GameConfig::set('regions', [
            'us_east' => [
                'name' => 'US East (Virginia)',
                'flag' => '🇺🇸',
                'description' => 'Das Herz des Internets. Ausgewogene Kosten und hohe Nachfrage.',
                'base_power_cost' => 0.12,
                'latency_modifier' => 1.0, 
                'level_required' => 1,
                'tax_rate' => 0.05,
                'carbon_tax_per_kw' => 0.01,
                'labor_multiplier' => 1.0,
                'demand_weight' => 1.3,
                'preferences' => [
                    'cpu_focus' => 1.0,
                    'ram_focus' => false,
                    'max_latency_ms' => 150,
                    'is_privacy_focused' => false,
                    'is_performance_focused' => false,
                    'is_eco_focused' => false,
                    'compliance_weight' => 0.3,
                    'enterprise_ratio' => 0.25,
                ]
            ],
            'us_west' => [
                'name' => 'US West (Oregon)',
                'flag' => '🇺🇸',
                'description' => 'Günstige Wasserkraft, aber höhere Latenz nach Europa. Beliebt bei Startups.',
                'base_power_cost' => 0.08,
                'latency_modifier' => 1.2,
                'level_required' => 5,
                'tax_rate' => 0.08,
                'carbon_tax_per_kw' => 0.02,
                'labor_multiplier' => 1.2,
                'demand_weight' => 1.0,
                'preferences' => [
                    'cpu_focus' => 1.1,
                    'ram_focus' => false,
                    'max_latency_ms' => 180,
                    'is_privacy_focused' => false,
                    'is_performance_focused' => false,
                    'is_eco_focused' => true,
                    'compliance_weight' => 0.2,
                    'enterprise_ratio' => 0.15,
                ]
            ],
            'eu_central' => [
                'name' => 'EU Central (Frankfurt)',
                'flag' => '🇩🇪',
                'description' => 'Strenge Datenschutzgesetze. Höhere Stromkosten, dafür Premium-Kunden mit DSGVO-Anforderungen.',
                'base_power_cost' => 0.25,
                'latency_modifier' => 1.1,
                'level_required' => 10,
                'tax_rate' => 0.19,
                'carbon_tax_per_kw' => 0.05,
                'labor_multiplier' => 1.1,
                'demand_weight' => 1.1,
                'preferences' => [
                    'cpu_focus' => false,
                    'ram_focus' => 1.2,
                    'max_latency_ms' => 45,
                    'is_privacy_focused' => true,
                    'is_performance_focused' => false,
                    'is_eco_focused' => false,
                    'compliance_weight' => 0.85,
                    'enterprise_ratio' => 0.40,
                ]
            ],
            'asia_east' => [
                'name' => 'Asia East (Tokio)',
                'flag' => '🇯🇵',
                'description' => 'High-Tech-Hub mit extremer Dichte. Teurer Strom, aber gigantische Nachfrage nach Performance.',
                'base_power_cost' => 0.22,
                'latency_modifier' => 1.5,
                'level_required' => 15,
                'tax_rate' => 0.10,
                'carbon_tax_per_kw' => 0.03,
                'labor_multiplier' => 1.3,
                'demand_weight' => 1.2,
                'preferences' => [
                    'cpu_focus' => 1.2,
                    'ram_focus' => false,
                    'storage_focus' => 1.2,
                    'max_latency_ms' => 35,
                    'is_privacy_focused' => false,
                    'is_performance_focused' => true,
                    'is_eco_focused' => false,
                    'compliance_weight' => 0.4,
                    'enterprise_ratio' => 0.35,
                ]
            ],
            'nordics' => [
                'name' => 'Nordic (Stockholm)',
                'flag' => '🇸🇪',
                'description' => 'Billige grüne Energie und natürliche Kühlung. Ideal für nachhaltige und HPC-Workloads.',
                'base_power_cost' => 0.06,
                'latency_modifier' => 1.3,
                'level_required' => 20,
                'tax_rate' => 0.22,
                'carbon_tax_per_kw' => 0.00,
                'labor_multiplier' => 1.4,
                'demand_weight' => 0.8,
                'preferences' => [
                    'cpu_focus' => 1.3,
                    'ram_focus' => 1.1,
                    'max_latency_ms' => 80,
                    'is_privacy_focused' => true,
                    'is_performance_focused' => true,
                    'is_eco_focused' => true,
                    'compliance_weight' => 0.7,
                    'enterprise_ratio' => 0.30,
                ]
            ],
            'asia_south' => [
                'name' => 'Asia South (Singapur)',
                'flag' => '🇸🇬',
                'description' => 'Finanz-Hub mit Ultra-Low-Latenz. Premium-Preise, aber extrem profitabel für Enterprise-Kunden.',
                'base_power_cost' => 0.18,
                'latency_modifier' => 1.4,
                'level_required' => 25,
                'tax_rate' => 0.07,
                'carbon_tax_per_kw' => 0.02,
                'labor_multiplier' => 1.5,
                'demand_weight' => 0.9,
                'preferences' => [
                    'cpu_focus' => 1.1,
                    'ram_focus' => 1.3,
                    'max_latency_ms' => 25,
                    'is_privacy_focused' => false,
                    'is_performance_focused' => true,
                    'is_eco_focused' => false,
                    'compliance_weight' => 0.5,
                    'enterprise_ratio' => 0.55,
                ]
            ],
            'south_america' => [
                'name' => 'Südamerika (São Paulo)',
                'flag' => '🇧🇷',
                'description' => 'Aufstrebender Markt mit volatilen Energiepreisen. Niedrige Konkurrenz, aber instabileres Netz.',
                'base_power_cost' => 0.10,
                'latency_modifier' => 1.6,
                'level_required' => 12,
                'tax_rate' => 0.15,
                'carbon_tax_per_kw' => 0.01,
                'labor_multiplier' => 0.7,
                'demand_weight' => 0.7,
                'preferences' => [
                    'cpu_focus' => 1.0,
                    'ram_focus' => false,
                    'max_latency_ms' => 200,
                    'is_privacy_focused' => false,
                    'is_performance_focused' => false,
                    'is_eco_focused' => false,
                    'compliance_weight' => 0.15,
                    'enterprise_ratio' => 0.10,
                ]
            ],
        ], 'world', 'Available geographical regions for data centers.');

        // 5. Global Simulation Constants
        \App\Models\GameConfig::set('engine_constants', [
            'tick_rate_seconds' => 15,
            'xp_multiplier' => 1.0,
            'base_repair_cost_multiplier' => 0.2,
        ], 'engine', 'Core multipliers and timing values for the entire engine.');

        // 6. Specialization Definitions
        \App\Models\GameConfig::set('specialization_definitions', [
            'balanced' => [
                'name' => 'Generalist Hoster',
                'description' => 'A bit of everything. Standard setup.',
                'order_types' => ['web_hosting' => 1.0, 'game_server' => 1.0, 'database_hosting' => 1.0, 'ml_training' => 1.0],
                'price_modifier' => 1.0,
                'patience_modifier' => 1.0,
                'requirement_modifier' => 1.0,
                'reputation_impact' => 0,
                'unlock_cost' => 0,
                'passives' => []
            ],
            'budget' => [
                'name' => 'Mass-Market Disrupter',
                'description' => 'Cheap prices bring in the masses. Patience is low, but volume is king.',
                'order_types' => ['web_hosting' => 2.5, 'game_server' => 1.5, 'database_hosting' => 0.5, 'ml_training' => 0.1],
                'price_modifier' => 0.75,
                'patience_modifier' => 0.6,
                'requirement_modifier' => 0.85,
                'reputation_impact' => -5,
                'unlock_cost' => 5000,
                'passives' => ['churn_reduction' => -0.05]
            ],
            'premium' => [
                 'name' => 'Elite Infrastructure',
                 'description' => 'Focus on high-value corporate clients who demand absolute uptime.',
                 'order_types' => ['web_hosting' => 0.4, 'game_server' => 0.5, 'database_hosting' => 2.0, 'ml_training' => 1.5],
                 'price_modifier' => 1.6,
                 'patience_modifier' => 1.8,
                 'requirement_modifier' => 1.25,
                 'reputation_impact' => 10,
                 'unlock_cost' => 50000,
                 'passives' => ['reputation_gain' => 0.1]
            ],
            'hpc_specialist' => [
                 'name' => 'Neural & Edge AI',
                 'description' => 'Cutting edge computation. Requires heavy-duty cooling and hardware.',
                 'order_types' => ['web_hosting' => 0.05, 'game_server' => 0.05, 'database_hosting' => 0.8, 'ml_training' => 6.0],
                 'price_modifier' => 3.0,
                 'patience_modifier' => 1.2,
                 'requirement_modifier' => 1.6,
                 'reputation_impact' => 5,
                 'unlock_cost' => 250000,
                 'passives' => ['cooling_penalty' => 0.2]
            ],
            'green_eco' => [
                 'name' => 'Terra-Form Hosting',
                 'description' => 'Sustainable hosting. Reduces power costs through federal green subsidies.',
                 'order_types' => ['web_hosting' => 1.5, 'game_server' => 0.5, 'database_hosting' => 1.0, 'ml_training' => 0.5],
                 'price_modifier' => 1.25,
                 'patience_modifier' => 1.4,
                 'requirement_modifier' => 1.0,
                 'reputation_impact' => 15,
                 'unlock_cost' => 25000,
                 'passives' => ['power_cost_reduction' => 0.2]
            ],
            'crypto_vault' => [
                'name' => 'Crypto & Ledger Hub',
                'description' => 'Focus on immutable storage and blockchain node hosting. High risk, high volatility.',
                'order_types' => ['web_hosting' => 0.2, 'game_server' => 0.1, 'database_hosting' => 3.5, 'ml_training' => 1.0],
                'price_modifier' => 2.2,
                'patience_modifier' => 0.5,
                'requirement_modifier' => 1.4,
                'reputation_impact' => -15,
                'unlock_cost' => 75000,
                'passives' => ['bandwidth_drain' => 0.3]
            ]
        ], 'strategy', 'Global behavior and economy modifiers for player specializations.');

        // 7. Logic Formulas (Obsidian Architecture)
        \App\Models\GameConfig::set('formula_churn', 'base_rate + (power_failures * 0.05) + (utilization * 0.01) - (support_efficiency * 0.1)', 'logic', 'Customer Retention Logic');
        \App\Models\GameConfig::set('formula_power_cost', 'base_price * (demand_factor / volatility) * region_multiplier', 'logic', 'Regional Energy Matrix');
        \App\Models\GameConfig::set('formula_revenue_yield', 'uptime * (1 - hardware_age) * multiplier', 'logic', 'Revenue Yield Factor');
        \App\Models\GameConfig::set('formula_sla_penalty', '(hourly_value / 60) * 10 * (downtime_ticks / 5)', 'logic', 'Service Level Agreement Penalty');
    }
}
