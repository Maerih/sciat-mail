<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sending_servers', function (Blueprint $table) {
            $table->boolean('prewarmed')->default(false)->after('warmup_started_on');
            $table->json('email_warmup')->nullable()->after('prewarmed'); // enabled, from_email, started_on, daily_max
        });

        Schema::table('sending_domains', function (Blueprint $table) {
            $table->foreignId('warmup_strategy_id')->nullable()->constrained()->nullOnDelete();
            $table->date('warmup_started_on')->nullable();
        });

        Schema::create('warmup_mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(993);
            $table->string('encryption', 8)->default('ssl');
            $table->string('username');
            $table->text('password');
            $table->string('status', 16)->default('active');
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('warmup_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sending_server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warmup_mailbox_id')->constrained()->cascadeOnDelete();
            $table->string('token', 32)->unique();
            $table->string('subject');
            $table->string('placement', 12)->default('pending'); // pending | inbox | spam | missing | failed
            $table->boolean('rescued')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->index(['sending_server_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warmup_emails');
        Schema::dropIfExists('warmup_mailboxes');
        Schema::table('sending_domains', fn (Blueprint $table) => $table->dropConstrainedForeignId('warmup_strategy_id'));
        Schema::table('sending_domains', fn (Blueprint $table) => $table->dropColumn('warmup_started_on'));
        Schema::table('sending_servers', fn (Blueprint $table) => $table->dropColumn(['prewarmed', 'email_warmup']));
    }
};
