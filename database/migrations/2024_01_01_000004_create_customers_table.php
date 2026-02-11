<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('company_name');
            $table->string('tier'); // hobbyist, startup, enterprise, whale
            $table->decimal('revenue_per_month', 12, 2);
            $table->decimal('satisfaction', 5, 2)->default(100.0);
            $table->integer('patience_minutes')->default(60); // How long they wait for orders
            $table->integer('tolerance_incidents')->default(3); // Incidents before leaving
            $table->integer('incidents_count')->default(0);
            $table->string('status')->default('active'); // active, unhappy, churning, churned
            $table->timestamp('acquired_at');
            $table->timestamp('last_incident_at')->nullable();
            $table->timestamp('churn_at')->nullable();
            $table->json('preferences')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['tier', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
