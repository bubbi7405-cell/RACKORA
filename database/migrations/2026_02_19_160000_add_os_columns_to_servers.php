<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('installed_os_type')->nullable()->after('status');
            $table->string('installed_os_version')->nullable()->after('installed_os_type');
            $table->string('os_install_status')->default('none')->after('installed_os_version'); // none, installing, installed, failed
            $table->timestamp('os_install_started_at')->nullable()->after('os_install_status');
            $table->timestamp('os_install_completes_at')->nullable()->after('os_install_started_at');
            $table->decimal('os_health', 5, 2)->default(100.00)->after('os_install_completes_at');
            $table->decimal('security_patch_level', 5, 2)->default(100.00)->after('os_health');
            $table->boolean('is_auto_updates_enabled')->default(false)->after('security_patch_level');
            $table->string('license_type')->nullable()->after('is_auto_updates_enabled'); // free, standard, datacenter, enterprise
            $table->string('license_status')->default('none')->after('license_type'); // active, expired, trial, none
            $table->timestamp('license_expires_at')->nullable()->after('license_status');
            $table->decimal('compatibility_score', 5, 2)->default(100.00)->after('license_expires_at');
            $table->json('os_config')->nullable()->after('compatibility_score'); // For custom kernel params
        });

        // Initialize existing servers with a basic OS to prevent breakage
        DB::table('servers')->update([
            'installed_os_type' => 'ubuntu_lts',
            'installed_os_version' => '22.04',
            'os_install_status' => 'installed',
            'os_health' => 100.00,
            'security_patch_level' => 100.00,
            'is_auto_updates_enabled' => true,
            'license_type' => 'free',
            'license_status' => 'active',
            'compatibility_score' => 100.00
        ]);
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'installed_os_type', 
                'installed_os_version', 
                'os_install_status',
                'os_install_started_at',
                'os_install_completes_at',
                'os_health',
                'security_patch_level',
                'is_auto_updates_enabled',
                'license_type',
                'license_status',
                'license_expires_at',
                'compatibility_score',
                'os_config'
            ]);
        });
    }
};
