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
        Schema::create('dark_fiber_leases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('region_a');
            $table->string('region_b');
            $table->string('provider_name');
            $table->decimal('monthly_cost', 12, 2);
            $table->decimal('setup_fee', 12, 2);
            $table->decimal('latency_reduction', 5, 2)->default(0.40); // 40% reduction
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active'); // active, pending, expired
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dark_fiber_leases');
    }
};
