<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mail_list_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger_type', 32);
            $table->json('trigger_config')->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->json('flow');
            $table->boolean('allow_reentry')->default(false);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
            $table->index(['mail_list_id', 'status', 'trigger_type']);
        });

        // One subscriber's journey through an automation
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained()->cascadeOnDelete();
            $table->string('trigger_ref', 40)->default('');
            $table->string('status', 16)->default('waiting');
            $table->string('step_id', 20)->nullable();
            $table->timestamp('wake_at')->nullable();
            $table->json('history')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['automation_id', 'subscriber_id', 'trigger_ref']);
            $table->index(['status', 'wake_at']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('automation_id')->nullable()->after('workspace_id')->constrained()->cascadeOnDelete();
        });

    }

    public function down(): void
    {
        Schema::table('campaigns', fn (Blueprint $table) => $table->dropConstrainedForeignId('automation_id'));
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automations');
    }
};
