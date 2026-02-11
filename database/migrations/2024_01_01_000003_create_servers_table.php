<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('rack_id');
            $table->foreign('rack_id')->references('id')->on('server_racks')->onDelete('cascade');
            $table->string('type'); // vserver_node, dedicated, gpu_server, storage_server
            $table->string('model_name');
            $table->integer('size_u'); // 1U, 2U, 4U
            $table->integer('start_slot'); // Starting U-slot position (1-indexed)
            $table->decimal('power_draw_kw', 8, 2);
            $table->decimal('heat_output_kw', 8, 2);
            $table->integer('cpu_cores');
            $table->integer('ram_gb');
            $table->integer('storage_tb');
            $table->integer('bandwidth_mbps');
            $table->integer('vserver_capacity')->default(0); // For vserver nodes
            $table->integer('vservers_used')->default(0);
            $table->string('status')->default('offline'); 
            // Status: offline, provisioning, online, degraded, damaged, locked
            $table->decimal('health', 5, 2)->default(100.0);
            $table->decimal('purchase_cost', 12, 2);
            $table->decimal('monthly_depreciation', 10, 2)->default(0);
            $table->timestamp('provisioning_started_at')->nullable();
            $table->timestamp('provisioning_completes_at')->nullable();
            $table->timestamp('last_maintenance_at')->nullable();
            $table->json('specs')->nullable(); // Additional specs
            $table->timestamps();

            $table->index(['rack_id', 'status']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
