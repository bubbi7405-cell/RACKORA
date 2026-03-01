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
        Schema::create('user_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('component_type'); // cpu, ram, storage, motherboard
            $table->string('component_key'); // e.g. intel_xeon_e2224
            $table->uuid('assigned_server_id')->nullable();
            $table->string('status')->default('inventory'); // inventory, installed, broken
            $table->decimal('health', 5, 2)->default(100.00);
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();

            $table->foreign('assigned_server_id')->references('id')->on('servers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_components');
    }
};
