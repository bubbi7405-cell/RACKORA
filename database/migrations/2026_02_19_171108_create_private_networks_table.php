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
        Schema::create('private_networks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('cidr')->default('10.0.0.0/24');
            $table->string('vlan_tag')->nullable();
            $table->integer('server_count')->default(0);
            $table->timestamps();
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->foreignUuid('private_network_id')->nullable()->constrained('private_networks')->nullOnDelete();
            $table->string('private_ip_address')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropForeign(['private_network_id']);
            $table->dropColumn(['private_network_id', 'private_ip_address']);
        });
        Schema::dropIfExists('private_networks');
    }
};
