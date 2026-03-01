<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add columns if they don't already exist
        Schema::table('player_economy', function (Blueprint $table) {
            if (!Schema::hasColumn('player_economy', 'federal_heat')) {
                $table->decimal('federal_heat', 10, 4)->default(0.0)->after('reputation');
            }
            if (!Schema::hasColumn('player_economy', 'risk_exposure')) {
                $table->decimal('risk_exposure', 10, 4)->default(0.0)->after('federal_heat');
            }
            if (!Schema::hasColumn('player_economy', 'metadata')) {
                $table->json('metadata')->nullable()->after('risk_exposure');
            }
        });

        if (!Schema::hasTable('server_templates')) {
            Schema::create('server_templates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('name');
                $table->string('os_type')->nullable();
                $table->string('os_version')->nullable();
                $table->json('installed_applications')->nullable();
                $table->decimal('install_cost_mult', 5, 2)->default(1.0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('server_templates');
        Schema::table('player_economy', function (Blueprint $table) {
            $table->dropColumn(['federal_heat', 'risk_exposure', 'metadata']);
        });
    }
};
