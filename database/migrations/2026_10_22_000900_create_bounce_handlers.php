<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mailboxes polled over IMAP for bounce (DSN) and complaint (ARF) reports
        Schema::create('bounce_handlers', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(993);
            $table->string('encryption', 8)->default('ssl');
            $table->string('username');
            $table->text('password'); // encrypted
            $table->string('folder')->default('INBOX');
            $table->boolean('delete_processed')->default(false);
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('processed_count')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::table('sending_servers', function (Blueprint $table) {
            $table->string('bounce_address')->nullable()->after('default_from_email');
        });
    }

    public function down(): void
    {
        Schema::table('sending_servers', fn (Blueprint $table) => $table->dropColumn('bounce_address'));
        Schema::dropIfExists('bounce_handlers');
    }
};
