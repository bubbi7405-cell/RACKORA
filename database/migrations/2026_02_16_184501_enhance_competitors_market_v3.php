<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitors', function (Blueprint $table) {
            // Sector-level market shares
            $table->json('sector_shares')->nullable()->after('regional_shares');

            // Simulated infrastructure scores
            $table->decimal('capacity_score', 8, 2)->default(500.00)->after('intelligence');
            $table->decimal('uptime_score', 6, 3)->default(99.500)->after('capacity_score');
            $table->decimal('latency_score', 6, 2)->default(35.00)->after('uptime_score');
            $table->decimal('price_modifier', 5, 3)->default(1.000)->after('latency_score');

            // Strategic resources
            $table->decimal('marketing_budget', 12, 2)->default(5000.00)->after('price_modifier');
            $table->decimal('innovation_index', 5, 2)->default(50.00)->after('marketing_budget');

            // AI Behavior
            $table->string('archetype')->default('balanced')->after('personality');
            // archetypes: aggressive_expander, premium_stability, budget_volume, stealth_innovator, regional_specialist
            $table->integer('last_decision_tick')->default(0)->after('innovation_index');
            $table->integer('decision_cooldown')->default(5)->after('last_decision_tick');

            // Economic pressure tracking
            $table->decimal('profit_margin', 6, 3)->default(0.150)->after('decision_cooldown');
            $table->integer('expansion_streak')->default(0)->after('profit_margin');
            $table->integer('contraction_streak')->default(0)->after('expansion_streak');
        });
    }

    public function down(): void
    {
        Schema::table('competitors', function (Blueprint $table) {
            $table->dropColumn([
                'sector_shares', 'capacity_score', 'uptime_score', 'latency_score',
                'price_modifier', 'marketing_budget', 'innovation_index', 'archetype',
                'last_decision_tick', 'decision_cooldown', 'profit_margin',
                'expansion_streak', 'contraction_streak',
            ]);
        });
    }
};
