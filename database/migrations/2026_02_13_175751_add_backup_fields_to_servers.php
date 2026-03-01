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
            $table->string('backup_plan')->default('none')->after('status');
            $table->timestamp('last_backup_at')->nullable()->after('backup_plan');
            $table->decimal('backup_health', 5, 2)->default(100.00)->after('last_backup_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['backup_plan', 'last_backup_at', 'backup_health']);
        });
    }
};
