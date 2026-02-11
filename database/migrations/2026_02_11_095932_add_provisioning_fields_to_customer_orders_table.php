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
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->timestamp('provisioning_started_at')->nullable()->after('provisioned_at');
            $table->timestamp('provisioning_completes_at')->nullable()->after('provisioning_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropColumn(['provisioning_started_at', 'provisioning_completes_at']);
        });
    }
};
