<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('host')->index();
            $table->string('status', 16)->default('inactive'); // inactive | connected
            $table->boolean('track_visits')->default(true);
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->string('check_error')->nullable();
            $table->timestamps();
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->foreignId('website_id')->nullable()->after('mail_list_id')->constrained()->nullOnDelete();
        });

        Schema::create('website_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscriber_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor', 32); // random id kept in the visitor's browser
            $table->string('path', 500);
            $table->string('referrer', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['website_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_visits');
        Schema::table('forms', fn (Blueprint $table) => $table->dropConstrainedForeignId('website_id'));
        Schema::dropIfExists('websites');
    }
};
