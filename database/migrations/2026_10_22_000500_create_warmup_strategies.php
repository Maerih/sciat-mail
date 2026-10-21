<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // workspace_id NULL = built-in preset
        Schema::create('warmup_strategies', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('curve', 16)->default('linear'); // linear | exponential
            $table->unsignedInteger('initial_daily');
            $table->unsignedInteger('target_daily');
            $table->unsignedSmallInteger('days');
            $table->timestamps();
        });

        Schema::table('sending_servers', function (Blueprint $table) {
            $table->foreignId('warmup_strategy_id')->nullable()->after('daily_limit')->constrained()->nullOnDelete();
            $table->date('warmup_started_on')->nullable()->after('warmup_strategy_id');
        });
    }

    public function down(): void
    {
        Schema::table('sending_servers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warmup_strategy_id');
            $table->dropColumn('warmup_started_on');
        });
        Schema::dropIfExists('warmup_strategies');
    }
};
