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
        Schema::create('sabotages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // users table uses BigInt id
            $table->foreignUuid('target_competitor_id')->nullable()->constrained('competitors')->nullOnDelete(); // competitors table uses UUID id
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete(); // users uses BigInt
            $table->string('type'); 
            $table->string('status')->default('pending'); 
            $table->decimal('cost', 15, 2)->default(0);
            $table->boolean('detected')->default(false);
            $table->json('result')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sabotages');
    }
};
