<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->string('name');
            $table->string('email');
            $table->string('from_name')->nullable();
            $table->string('imap_host');
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_encryption', 8)->default('ssl');
            $table->string('smtp_host');
            $table->unsignedSmallInteger('smtp_port')->default(465);
            $table->string('smtp_encryption', 8)->default('ssl');
            $table->string('username');
            $table->text('password'); // encrypted, used for IMAP and SMTP
            $table->text('signature')->nullable();
            $table->string('color', 7)->default('#22c55e');
            $table->string('status', 16)->default('active');
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('inbox_mailbox_user', function (Blueprint $table) {
            $table->foreignId('inbox_mailbox_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['inbox_mailbox_id', 'user_id']);
        });

        Schema::create('inbox_threads', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('inbox_mailbox_id')->constrained()->cascadeOnDelete();
            $table->string('subject')->nullable();
            $table->string('contact_email')->nullable()->index();
            $table->string('contact_name')->nullable();
            $table->string('snippet', 300)->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('unread')->default(0);
            $table->boolean('starred')->default(false);
            $table->string('status', 12)->default('open'); // open | done
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->index(['inbox_mailbox_id', 'status', 'last_message_at']);
        });

        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('inbox_mailbox_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inbox_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // team member who sent it from here
            $table->string('direction', 3); // in | out
            $table->string('folder', 120)->nullable();
            $table->unsignedBigInteger('imap_uid')->nullable();
            $table->string('message_id', 500)->nullable();
            $table->string('in_reply_to', 500)->nullable();
            $table->text('references')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->json('attachments')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['inbox_mailbox_id', 'folder', 'imap_uid']);
            $table->index(['inbox_mailbox_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_messages');
        Schema::dropIfExists('inbox_threads');
        Schema::dropIfExists('inbox_mailbox_user');
        Schema::dropIfExists('inbox_mailboxes');
    }
};
