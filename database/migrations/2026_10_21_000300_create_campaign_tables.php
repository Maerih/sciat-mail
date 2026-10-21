<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // workspace_id NULL = system starter template
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 32)->default('custom');
            $table->longText('html');
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 16)->default('regular');
            $table->string('status', 16)->default('draft')->index();
            $table->string('subject')->nullable();
            $table->string('preheader')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('reply_to')->nullable();
            $table->longText('html')->nullable();
            $table->longText('plain')->nullable();
            $table->boolean('track_open')->default(true);
            $table->boolean('track_click')->default(true);
            $table->boolean('sign_dkim')->default(true);
            $table->boolean('skip_failed')->default(true);
            $table->json('server_weights')->nullable(); // {server_id: weight}; null = all servers
            $table->json('utm')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->json('sending_window')->nullable(); // [{weekday, start, end}]
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mail_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('exclude')->default(false);
        });

        // One row per recipient per campaign — the delivery ledger
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 24)->unique();
            $table->foreignId('campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subscriber_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('kind', 16)->default('campaign'); // campaign|test|system
            $table->string('status', 16)->default('pending')->index();
            $table->foreignId('sending_server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_message_id')->nullable()->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamps();
            $table->unique(['campaign_id', 'email']);
            $table->index(['campaign_id', 'status']);
        });

        // Unified event log: open, click, bounce, complaint, unsubscribe
        Schema::create('delivery_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->index();
            $table->foreignId('subscriber_id')->nullable()->index();
            $table->string('type', 16);
            $table->string('subtype', 32)->nullable(); // hard/soft bounce etc.
            $table->text('url')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->text('detail')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['campaign_id', 'type']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['delivery_events', 'deliveries', 'campaign_audiences', 'campaigns', 'templates'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
