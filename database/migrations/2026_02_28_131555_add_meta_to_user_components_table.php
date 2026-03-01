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
        Schema::table('user_components', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('needs_shredding');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_components', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
