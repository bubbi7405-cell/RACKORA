<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('name');
            $table->string('company_logo')->nullable()->after('company_name');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->string('led_color')->nullable()->after('status');
            $table->json('custom_rgb')->nullable()->after('led_color');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'company_logo']);
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['led_color', 'custom_rgb']);
        });
    }
};
