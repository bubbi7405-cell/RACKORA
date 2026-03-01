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
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('company_logo');
            $table->string('banner')->nullable()->after('avatar');
            $table->string('slogan')->nullable()->after('banner');
            $table->string('accent_color')->nullable()->after('slogan');
            $table->json('settings')->nullable()->after('accent_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'banner', 'slogan', 'accent_color', 'settings']);
        });
    }
};
