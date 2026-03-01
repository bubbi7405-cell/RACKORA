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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('customer_id')->nullable();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('status')->default('open'); // open, in_progress, resolved, failed
            $table->string('priority')->default('medium'); // low, medium, high, critical
            $table->integer('complexity')->default(10); // arbitrary work units
            $table->integer('progress')->default(0); // 0-100
            $table->foreignUuid('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
