<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('member')->after('password');
            $table->foreignId('current_workspace_id')->nullable()->after('role');
        });

        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->string('name');
            $table->string('timezone', 64)->default('UTC');
            $table->string('company')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamps();
        });

        // workspace_id NULL = shared by every workspace
        Schema::create('sending_servers', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 32);
            $table->text('config'); // encrypted JSON credentials
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('hourly_limit')->nullable();
            $table->unsignedInteger('daily_limit')->nullable();
            $table->string('default_from_email')->nullable();
            $table->string('webhook_secret', 64);
            $table->text('last_error')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sending_domains', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('verification_token', 64);
            $table->string('dkim_selector', 63);
            $table->text('dkim_private_key'); // encrypted
            $table->text('dkim_public_key');
            $table->boolean('ownership_verified')->default(false);
            $table->boolean('dkim_verified')->default(false);
            $table->boolean('spf_verified')->default(false);
            $table->boolean('dmarc_verified')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('tracking_domains', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('host');
            $table->string('scheme', 5)->default('https');
            $table->boolean('verified')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique('host');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_domains');
        Schema::dropIfExists('sending_domains');
        Schema::dropIfExists('sending_servers');
        Schema::dropIfExists('workspaces');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'current_workspace_id']));
    }
};
