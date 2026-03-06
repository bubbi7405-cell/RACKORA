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
        Schema::create('modernization_missions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->uuid('target_id'); // Rack ID or Server ID
            $table->string('target_type'); // rack or server
            $table->json('assigned_employee_ids'); // [tech_id, lead_id]
            $table->string('status')->default('active'); // active, completed, cancelled
            $table->decimal('cost', 12, 2);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completes_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modernization_missions');
    }
};
