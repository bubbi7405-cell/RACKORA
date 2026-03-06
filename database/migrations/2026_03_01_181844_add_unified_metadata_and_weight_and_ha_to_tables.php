<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('model_name');
            $table->decimal('weight_kg', 8, 2)->default(10.0)->after('size_u');
            $table->uuid('failover_server_id')->nullable()->after('rack_id');
        });

        Schema::table('server_racks', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('name');
            $table->decimal('max_weight_kg', 8, 2)->default(500.0)->after('total_units');
        });

        Schema::table('game_rooms', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('name');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('company_name');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['metadata', 'weight_kg', 'failover_server_id']);
        });

        Schema::table('server_racks', function (Blueprint $table) {
            $table->dropColumn(['metadata', 'max_weight_kg']);
        });

        Schema::table('game_rooms', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
