<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_networks', function (Blueprint $table) {
            // Subnet Management
            $table->json('ipv4_subnets')->nullable()->after('ipv6_used');
            $table->json('ipv6_subnets')->nullable()->after('ipv4_subnets');

            // ISP & Bandwidth Contracts
            $table->string('isp_provider')->default('generic_transit')->after('ipv6_subnets');
            $table->integer('bandwidth_contract_mbps')->default(1000)->after('isp_provider');
            $table->decimal('bandwidth_contract_cost', 10, 2)->default(50.00)->after('bandwidth_contract_mbps');
            $table->string('bandwidth_tier')->default('standard')->after('bandwidth_contract_cost');

            // Regional Presence
            $table->json('regional_latency')->nullable()->after('bandwidth_tier');
            $table->json('regional_presence')->nullable()->after('regional_latency');

            // Advanced Metrics
            $table->decimal('jitter_ms', 8, 2)->default(0)->after('avg_packet_loss');
            $table->integer('bgp_routes_announced')->default(0)->after('jitter_ms');
            $table->decimal('traffic_in_gbps', 10, 2)->default(0)->after('bgp_routes_announced');
            $table->decimal('traffic_out_gbps', 10, 2)->default(0)->after('traffic_in_gbps');
            $table->timestamp('last_ddos_at')->nullable()->after('traffic_out_gbps');
            $table->integer('ddos_events_total')->default(0)->after('last_ddos_at');
        });
    }

    public function down(): void
    {
        Schema::table('player_networks', function (Blueprint $table) {
            $table->dropColumn([
                'ipv4_subnets',
                'ipv6_subnets',
                'isp_provider',
                'bandwidth_contract_mbps',
                'bandwidth_contract_cost',
                'bandwidth_tier',
                'regional_latency',
                'regional_presence',
                'jitter_ms',
                'bgp_routes_announced',
                'traffic_in_gbps',
                'traffic_out_gbps',
                'last_ddos_at',
                'ddos_events_total',
            ]);
        });
    }
};
