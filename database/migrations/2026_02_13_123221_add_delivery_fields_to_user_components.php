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
        Schema::table('user_components', function (Blueprint $table) {
            $table->string('delivery_status')->default('inventory')->after('status');
            $table->string('delivery_type')->nullable()->after('delivery_status');
            $table->timestamp('arrival_at')->nullable()->after('delivery_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_components', function (Blueprint $table) {
            $table->dropColumn(['delivery_status', 'delivery_type', 'arrival_at']);
        });
    }
};
