<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bandwidth_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('isp_name');
            $table->string('isp_tier')->default('tier3');        // tier1, tier2, tier3
            $table->integer('capacity_mbps');
            $table->decimal('monthly_cost', 10, 2);
            $table->string('commitment')->default('monthly');     // monthly, annual, multi_year
            $table->decimal('burst_ratio', 4, 2)->default(1.0);   // 1.0 = no burst, 1.5 = 50% burst
            $table->json('regions')->nullable();                   // ['eu', 'us-east', 'asia']
            $table->decimal('latency_bonus_ms', 6, 2)->default(0);
            $table->string('status')->default('active');           // active, suspended, terminated
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bandwidth_contracts');
    }
};
