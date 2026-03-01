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
            $table->boolean('is_negotiable')->default(false)->after('contract_months');
            $table->decimal('base_price_requested', 15, 2)->nullable()->after('is_negotiable');
            $table->integer('negotiation_attempts')->default(0)->after('base_price_requested');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropColumn(['is_negotiable', 'base_price_requested', 'negotiation_attempts']);
        });
    }
};
