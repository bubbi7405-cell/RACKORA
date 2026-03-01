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
        // 1. Add compliance scores to player_economy
        Schema::table('player_economy', function (Blueprint $table) {
            $table->decimal('security_score', 5, 2)->default(10.0)->after('reputation');
            $table->decimal('privacy_score', 5, 2)->default(10.0)->after('security_score');
        });

        // 2. Base Certificates
        Schema::create('certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description');
            $table->string('category'); // security, privacy, quality, tier
            $table->json('requirements');
            $table->decimal('bonus_reputation', 5, 2)->default(5.0);
            $table->timestamps();
        });

        // 3. User Certificates (Earned)
        Schema::create('user_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('certificate_id')->constrained('certificates')->onDelete('cascade');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // 4. Compliance Audits (In-Progress)
        Schema::create('compliance_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('certificate_id')->constrained('certificates')->onDelete('cascade');
            $table->string('status')->default('pending'); // active, success, failed, expired
            $table->integer('progress')->default(0);
            $table->json('checklog')->nullable(); // historical record of unmet requirements
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completes_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_audits');
        Schema::dropIfExists('user_certificates');
        Schema::dropIfExists('certificates');
        
        Schema::table('player_economy', function (Blueprint $table) {
            $table->dropColumn(['security_score', 'privacy_score']);
        });
    }
};
