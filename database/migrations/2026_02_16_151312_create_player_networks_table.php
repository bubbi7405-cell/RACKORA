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
        Schema::create('player_networks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // IP Layer
            $table->integer('ipv4_total')->default(16); // Starting pool /28
            $table->integer('ipv4_used')->default(0);
            $table->integer('ipv6_total')->default(65536); // Huge pool /64 approx
            $table->integer('ipv6_used')->default(0);
            
            // ASN & Peering
            $table->integer('asn')->nullable(); // Private or Public
            $table->integer('peering_level')->default(0); // 0=Transit, 1=Community, 2=Premium
            $table->decimal('peering_score', 5, 2)->default(100.00); // Affects Latency
            
            // DDoS & Security
            $table->integer('ddos_protection_level')->default(0); // 0=None, 1=Cloud, 2=Hardware, 3=AI
            $table->decimal('network_reputation', 5, 2)->default(100.00); // Drops if spam/attack source
            
            // Global Quality Cache (for Dashboard)
            $table->decimal('avg_latency_ms', 8, 2)->default(30.00);
            $table->decimal('avg_packet_loss', 5, 3)->default(0.000);
            $table->decimal('sla_compliance_rate', 5, 2)->default(100.00);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_networks');
    }
};
