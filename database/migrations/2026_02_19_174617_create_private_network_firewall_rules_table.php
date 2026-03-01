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
        Schema::create('private_network_firewall_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('private_network_id')->constrained('private_networks')->onDelete('cascade');
            $table->enum('type', ['ALLOW', 'DENY']);
            $table->enum('protocol', ['TCP', 'UDP', 'ICMP', 'ANY']);
            $table->string('port_range')->nullable(); // "80", "80-443", "80,443"
            $table->string('source_cidr')->default('0.0.0.0/0');
            $table->integer('priority')->default(100);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['private_network_id', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('private_network_firewall_rules');
    }
};
