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
        Schema::table('peering_agreements', function (Blueprint $table) {
            $table->uuid('competitor_id')->nullable()->after('user_id');
            $table->foreign('competitor_id')->references('id')->on('competitors')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('peering_agreements', function (Blueprint $table) {
            $table->dropForeign(['competitor_id']);
            $table->dropColumn('competitor_id');
        });
    }
};
