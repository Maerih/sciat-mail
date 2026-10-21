<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_lists', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('from_name');
            $table->string('from_email');
            $table->string('reply_to')->nullable();
            $table->string('company')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('default_sending_server_id')->nullable()->constrained('sending_servers')->nullOnDelete();
            $table->boolean('double_optin')->default(false);
            $table->boolean('send_welcome_email')->default(false);
            $table->boolean('unsubscribe_notification')->default(false);
            $table->string('notification_email')->nullable();
            $table->text('welcome_subject')->nullable();
            $table->longText('welcome_html')->nullable();
            $table->timestamps();
        });

        Schema::create('list_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_list_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('tag', 64);
            $table->string('type', 16)->default('text');
            $table->json('options')->nullable();
            $table->string('default_value')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('visible')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['mail_list_id', 'tag']);
        });

        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('mail_list_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('status', 16)->default('subscribed')->index();
            $table->json('data')->nullable();   // custom field values keyed by tag
            $table->json('tags')->nullable();
            $table->string('source', 16)->default('manual');
            $table->string('ip', 45)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('last_open_at')->nullable();
            $table->timestamp('last_click_at')->nullable();
            $table->timestamps();
            $table->unique(['mail_list_id', 'email']);
            $table->index('email');
        });

        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('mail_list_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('matching', 3)->default('all');
            $table->json('conditions');
            $table->timestamps();
        });

        // workspace_id NULL = global suppression
        Schema::create('blacklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('reason', 32)->default('manual');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'email']);
            $table->index('email');
        });

        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('mail_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->json('mapping')->nullable();
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('mail_list_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('button_text')->default('Subscribe');
            $table->string('redirect_url')->nullable();
            $table->text('success_message')->nullable();
            $table->boolean('only_required_fields')->default(false);
            $table->boolean('consent_enabled')->default(false);
            $table->text('consent_text')->nullable();
            $table->string('status', 16)->default('published');
            $table->unsignedInteger('submissions')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['forms', 'imports', 'blacklists', 'segments', 'subscribers', 'list_fields', 'mail_lists'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
