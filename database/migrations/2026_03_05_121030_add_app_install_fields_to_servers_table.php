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
        Schema::table('servers', function (Blueprint $table) {
            $table->string('app_installing_id')->nullable()->after('app_install_status');
            $table->timestamp('app_install_started_at')->nullable()->after('app_installing_id');
            $table->timestamp('app_install_completes_at')->nullable()->after('app_install_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'app_installing_id',
                'app_install_started_at',
                'app_install_completes_at',
            ]);
        });
    }
};
