<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->integer('cpu_clock_mhz')->nullable()->after('cpu_cores');
            $table->decimal('cpu_voltage_v', 4, 3)->nullable()->after('cpu_clock_mhz');
            $table->integer('base_clock_mhz')->nullable()->after('cpu_voltage_v');
            $table->decimal('base_voltage_v', 4, 3)->nullable()->after('base_clock_mhz');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['cpu_clock_mhz', 'cpu_voltage_v', 'base_clock_mhz', 'base_voltage_v']);
        });
    }
};
