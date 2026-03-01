<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('peering_agreements')) {
            return;
        }

        Schema::create('peering_agreements', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->foreignId('user_id')->constrained()->onDelete('cascade');
            $blueprint->string('provider_name');
            $blueprint->string('tier'); // tier1, tier2, regional
            $blueprint->decimal('monthly_cost', 15, 2);
            $blueprint->integer('capacity_gbps');
            $blueprint->decimal('latency_bonus', 5, 2); // 0.8 = 20% reduction
            $blueprint->string('status')->default('active'); // active, pending, terminated
            $blueprint->timestamp('signed_at');
            $blueprint->timestamp('expires_at')->nullable();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peering_agreements');
    }
};
