<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // power_outage, overheating, ddos_attack, network_failure, hardware_failure
            $table->string('severity'); // warning, critical, catastrophic
            $table->string('status')->default('warning'); // warning, active, escalated, resolved, failed
            $table->string('title');
            $table->text('description');
            $table->uuid('affected_room_id')->nullable();
            $table->foreign('affected_room_id')->references('id')->on('game_rooms')->nullOnDelete();
            $table->uuid('affected_rack_id')->nullable();
            $table->foreign('affected_rack_id')->references('id')->on('server_racks')->nullOnDelete();
            $table->uuid('affected_server_id')->nullable();
            $table->foreign('affected_server_id')->references('id')->on('servers')->nullOnDelete();
            $table->json('available_actions'); // [{id, name, cost, success_rate, time_seconds}]
            $table->string('chosen_action')->nullable();
            $table->timestamp('warning_at')->useCurrent();
            $table->timestamp('escalates_at')->nullable();
            $table->timestamp('deadline_at')->nullable(); // Auto-fail if not resolved
            $table->timestamp('resolved_at')->nullable();
            $table->json('consequences')->nullable(); // What happened as result
            $table->decimal('damage_cost', 12, 2)->default(0);
            $table->integer('affected_customers_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_events');
    }
};
