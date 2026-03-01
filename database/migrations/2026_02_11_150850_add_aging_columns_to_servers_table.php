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
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedBigInteger('total_runtime_seconds')->default(0)->after('last_maintenance_at');
            $table->unsignedBigInteger('lifespan_seconds')->default(157680000)->after('total_runtime_seconds'); // Approx 5 years
            $table->timestamp('purchase_date')->default(DB::raw('CURRENT_TIMESTAMP'))->after('provisioning_completes_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['total_runtime_seconds', 'lifespan_seconds', 'purchase_date']);
        });
    }
};
