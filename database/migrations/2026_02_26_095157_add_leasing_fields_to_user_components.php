<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_components', function (Blueprint $blueprint) {
            $blueprint->boolean('is_leased')->default(false)->after('arrival_at');
            $blueprint->decimal('lease_cost_per_hour', 10, 2)->default(0)->after('is_leased');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_components', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['is_leased', 'lease_cost_per_hour']);
        });
    }
};
