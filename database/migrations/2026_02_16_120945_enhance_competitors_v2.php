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
        Schema::table('competitors', function (Blueprint $table) {
            $table->string('personality')->default('balanced')->after('name'); // aggressive, industrial, stealth, tech_pioneer
            $table->decimal('assets_value', 15, 2)->default(1000000.00)->after('reputation');
            $table->integer('intelligence')->default(50)->after('aggression'); // 1-100, how well they counter player
        });
    }

    public function down(): void
    {
        Schema::table('competitors', function (Blueprint $table) {
            $table->dropColumn(['personality', 'assets_value', 'intelligence']);
        });
    }
};
