<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Automation emails can reach the same person once per run (recurring, re-entry);
     * regular campaigns keep run 0 so their snapshot still dedupes by email.
     */
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('automation_run_id')->default(0)->after('subscriber_id');
            $table->unique(['campaign_id', 'email', 'automation_run_id']);
            $table->dropUnique(['campaign_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->unique(['campaign_id', 'email']);
            $table->dropUnique(['campaign_id', 'email', 'automation_run_id']);
            $table->dropColumn('automation_run_id');
        });
    }
};
