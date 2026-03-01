<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_events', function (Blueprint $table) {
            $table->json('affected_regions')->nullable()->after('modifier_value');
        });
    }

    public function down(): void
    {
        Schema::table('world_events', function (Blueprint $table) {
            $table->dropColumn('affected_regions');
        });
    }
};
