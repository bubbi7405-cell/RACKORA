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
        Schema::create('server_rentals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('provider_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tenant_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignUuid('server_id')->constrained('servers')->onDelete('cascade');
            $table->decimal('price_per_hour', 10, 2);
            $table->string('status')->default('available'); // available, rented, cancelled
            $table->timestamp('rented_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_rentals');
    }
};
