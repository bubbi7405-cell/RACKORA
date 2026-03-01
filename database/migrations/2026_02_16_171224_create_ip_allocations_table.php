<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('server_id')->nullable();
            $table->uuid('order_id')->nullable();
            $table->string('type');                                // ipv4, ipv6
            $table->string('address');                             // 10.0.1.1
            $table->string('subnet_cidr')->nullable();             // /24
            $table->string('purpose')->default('server');          // server, customer, management, gateway
            $table->string('status')->default('allocated');        // allocated, available, reserved, blacklisted
            $table->string('region')->nullable();                  // eu, us-east, asia, etc.
            $table->json('metadata')->nullable();                  // Additional data
            $table->timestamps();

            $table->index(['user_id', 'type', 'status']);
            $table->index(['server_id']);
            $table->index(['order_id']);
            $table->unique(['address', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_allocations');
    }
};
