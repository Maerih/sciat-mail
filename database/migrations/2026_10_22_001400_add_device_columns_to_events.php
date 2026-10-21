<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_events', function (Blueprint $table) {
            $table->string('device', 16)->nullable()->after('user_agent');
            $table->string('os', 24)->nullable()->after('device');
            $table->string('client', 32)->nullable()->after('os');
            $table->char('country', 2)->nullable()->after('client');
            $table->index(['campaign_id', 'type', 'client']);
        });
    }

    public function down(): void
    {
        Schema::table('delivery_events', function (Blueprint $table) {
            $table->dropIndex(['campaign_id', 'type', 'client']);
            $table->dropColumn(['device', 'os', 'client', 'country']);
        });
    }
};
