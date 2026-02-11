<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->string('product_type'); // vserver, dedicated, gpu_rental, storage
            $table->json('requirements'); // {cpu_cores, ram_gb, storage_gb, bandwidth}
            $table->decimal('price_per_month', 12, 2);
            $table->string('status')->default('pending'); 
            // Status: pending, provisioning, active, suspended, cancelled, completed
            $table->uuid('assigned_server_id')->nullable();
            $table->foreign('assigned_server_id')->references('id')->on('servers')->nullOnDelete();
            $table->integer('contract_months')->default(1);
            $table->timestamp('ordered_at')->useCurrent();
            $table->timestamp('patience_expires_at')->nullable(); // Customer leaves if not fulfilled
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'patience_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_orders');
    }
};
