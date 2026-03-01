<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $blueprint) {
            $blueprint->json('maintenance_log')->nullable()->after('last_maintenance_at');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $blueprint) {
            $blueprint->dropColumn('maintenance_log');
        });
    }
};
