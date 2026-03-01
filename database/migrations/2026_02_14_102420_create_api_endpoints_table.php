<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->uuid('server_id')->nullable();
            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
            
            $table->string('path'); // e.g. /v1/auth
            $table->string('method')->default('GET');
            $table->string('status')->default('online'); // online, offline, rate_limited, errored
            
            $table->integer('rpm')->default(0); // Requests per minute (virtual)
            $table->integer('max_rpm')->default(100);
            $table->decimal('latency_ms', 10, 2)->default(10.0);
            $table->decimal('uptime', 5, 2)->default(100.0);
            
            $table->decimal('revenue_per_1k_req', 10, 4)->default(0.05); // Revenue generation
            $table->json('config')->nullable(); // {complexity, security_level, logging_enabled}
            
            $table->timestamps();

            $table->unique(['server_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_endpoints');
    }
};
