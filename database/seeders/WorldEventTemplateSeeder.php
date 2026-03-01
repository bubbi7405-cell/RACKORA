<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameConfig;

class WorldEventTemplateSeeder extends Seeder
{
    public function run()
    {
        $templates = [
            [
                'title' => 'KI-Goldrausch',
                'description' => 'Ein neues Sprachmodell dominiert den Markt. Die Nachfrage nach GPU-Clustern und HPC-Leistung steigt massiv an.',
                'type' => 'boom',
                'modifier_type' => 'revenue',
                'modifier_value' => 0.30,
                'duration_minutes' => 60
            ],
            [
                'title' => 'Energie-Versorgungsengpass',
                'description' => 'Wartungsarbeiten an Unterseekabeln und Kraftwerken führen weltweit zu steigenden Strompreisen.',
                'type' => 'crisis',
                'modifier_type' => 'power_cost',
                'modifier_value' => 0.45,
                'duration_minutes' => 45
            ],
            [
                'title' => 'Steuer-Oase-Erlass',
                'description' => 'Diverse Regionen führen kurzfristige Steuererleichterungen für IT-Unternehmen ein.',
                'type' => 'boom',
                'modifier_type' => 'tax_reduction',
                'modifier_value' => 0.15,
                'duration_minutes' => 30
            ],
            [
                'title' => 'Datenschutz-Skandal',
                'description' => 'Ein großer Mitbewerber wurde gehackt. Kunden legen nun extremen Wert auf High-Compliance-Infrastruktur.',
                'type' => 'info',
                'modifier_type' => 'compliance_demand',
                'modifier_value' => 1.0,
                'duration_minutes' => 40
            ],
            [
                'title' => 'Chip-Knappheit',
                'description' => 'Lieferengpässe in Taiwan verzögern die Hardware-Herstellung. Neue Hardware ist kurzzeitig teurer.',
                'type' => 'crisis',
                'modifier_type' => 'hardware_cost',
                'modifier_value' => 0.20,
                'duration_minutes' => 50
            ]
        ];

        GameConfig::set('world_event_templates', $templates, 'simulation', 'Templates for world-wide dynamic events.');
    }
}
