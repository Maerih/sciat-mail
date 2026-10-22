<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_providers', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete(); // null = shared by all clients
            $table->string('name');
            $table->string('type', 32);
            $table->text('config'); // encrypted
            $table->string('sender_id', 32)->nullable();
            $table->string('status', 16)->default('active');
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('per_second')->default(10);
            $table->string('secret', 40); // in webhook URLs
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sms_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sender_id', 32)->nullable();
            $table->text('message')->nullable();
            $table->string('opt_out_text')->nullable();
            $table->json('audience')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sms_campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('sms_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscriber_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 3)->default('out'); // out | in
            $table->string('source', 16)->default('campaign'); // campaign | automation | api | reply | inbound | test
            $table->string('phone', 20)->index();
            $table->string('sender', 32)->nullable();
            $table->text('body');
            $table->unsignedTinyInteger('parts')->default(1);
            $table->string('status', 16)->default('queued')->index(); // queued | sent | delivered | failed | received
            $table->string('provider_message_id')->nullable()->index();
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['sms_campaign_id', 'status']);
            $table->index(['workspace_id', 'direction', 'created_at']);
        });

        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email')->index();
            $table->timestamp('sms_opted_out_at')->nullable()->after('unsubscribed_at');
        });
        Schema::table('mail_lists', function (Blueprint $table) {
            $table->string('phone_field', 64)->nullable()->after('reply_to');
        });
        Schema::table('workspaces', function (Blueprint $table) {
            $table->char('sms_country', 2)->nullable()->after('timezone');
            $table->unsignedInteger('monthly_sms_limit')->nullable()->after('contact_limit');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn(['sms_country', 'monthly_sms_limit']));
        Schema::table('mail_lists', fn (Blueprint $table) => $table->dropColumn('phone_field'));
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropColumn(['phone', 'sms_opted_out_at']);
        });
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('sms_campaigns');
        Schema::dropIfExists('sms_providers');
    }
};
