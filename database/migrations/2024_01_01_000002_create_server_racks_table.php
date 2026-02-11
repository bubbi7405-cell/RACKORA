<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_racks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('room_id');
            $table->foreign('room_id')->references('id')->on('game_rooms')->onDelete('cascade');
            $table->string('type'); // rack_12u, rack_24u, rack_42u
            $table->string('name');
            $table->integer('total_units'); // 12, 24, or 42
            $table->integer('used_units')->default(0);
            $table->integer('max_power_kw');
            $table->decimal('current_power_kw', 8, 2)->default(0);
            $table->decimal('current_heat_kw', 8, 2)->default(0);
            $table->json('position'); // {slot: 0-N} position in room
            $table->string('status')->default('operational'); // operational, overheating, offline, damaged
            $table->decimal('temperature', 5, 2)->default(22.0);
            $table->decimal('purchase_cost', 12, 2);
            $table->timestamps();

            $table->index(['room_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_racks');
    }
};
