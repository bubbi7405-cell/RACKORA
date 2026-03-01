<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_economy', function (Blueprint $table) {
            $table->decimal('global_market_share', 6, 3)->default(0.000)->after('reputation');
            $table->json('regional_shares')->nullable()->after('global_market_share');
            $table->json('sector_shares')->nullable()->after('regional_shares');
            $table->decimal('arpu', 10, 2)->default(0.00)->after('sector_shares');                     // Average Revenue Per User
            $table->decimal('innovation_index', 5, 2)->default(10.00)->after('arpu');                  // 0-100
            $table->decimal('risk_exposure', 5, 2)->default(10.00)->after('innovation_index');          // 0-100
            $table->decimal('marketing_budget', 12, 2)->default(0.00)->after('risk_exposure');
            $table->decimal('marketing_effectiveness', 5, 3)->default(1.000)->after('marketing_budget');
            $table->decimal('customer_acquisition_cost', 10, 2)->default(0.00)->after('marketing_effectiveness');
        });
    }

    public function down(): void
    {
        Schema::table('player_economy', function (Blueprint $table) {
            $table->dropColumn([
                'global_market_share', 'regional_shares', 'sector_shares',
                'arpu', 'innovation_index', 'risk_exposure',
                'marketing_budget', 'marketing_effectiveness', 'customer_acquisition_cost',
            ]);
        });
    }
};
