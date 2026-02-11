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
            $table->string('sla_tier')->default('standard')->after('product_type');
            $table->decimal('uptime_percent', 5, 2)->default(100.00)->after('price_per_month');
            $table->integer('downtime_ticks')->default(0)->after('uptime_percent');
            $table->integer('total_ticks')->default(0)->after('downtime_ticks');
        });
    }

    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropColumn(['sla_tier', 'uptime_percent', 'downtime_ticks', 'total_ticks']);
        });
    }
};
