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
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('stress', 5, 2)->default(0.00)->after('efficiency');
            $table->decimal('energy', 5, 2)->default(100.00)->after('stress');
            $table->string('current_task')->nullable()->after('energy');
            $table->decimal('task_progress', 5, 2)->default(0.00)->after('current_task');
            $table->integer('total_actions')->default(0)->after('task_progress');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['stress', 'energy', 'current_task', 'task_progress', 'total_actions']);
        });
    }
};
