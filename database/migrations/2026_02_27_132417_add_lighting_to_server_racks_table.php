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
        Schema::table('server_racks', function (Blueprint $table) {
            $table->string('led_color')->default('#00ff00')->after('status');
            $table->string('led_mode')->default('static')->after('led_color'); // static, pulse, rainbow
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_racks', function (Blueprint $table) {
            $table->dropColumn(['led_color', 'led_mode']);
        });
    }
};
