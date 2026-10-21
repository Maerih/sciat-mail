<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_servers', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 20)->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('provider', 32);
            $table->text('api_key')->nullable(); // encrypted
            $table->string('status', 16)->default('active');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('verification_status', 16)->default('unverified')->after('status')->index();
            $table->string('verification_reason')->nullable()->after('verification_status');
            $table->timestamp('verified_at')->nullable()->after('verification_reason');
        });

        Schema::table('mail_lists', function (Blueprint $table) {
            $table->string('verification_job_status', 16)->nullable()->after('welcome_html');
            $table->unsignedInteger('verification_job_total')->default(0)->after('verification_job_status');
        });
    }

    public function down(): void
    {
        Schema::table('mail_lists', fn (Blueprint $table) => $table->dropColumn(['verification_job_status', 'verification_job_total']));
        Schema::table('subscribers', fn (Blueprint $table) => $table->dropColumn(['verification_status', 'verification_reason', 'verified_at']));
        Schema::dropIfExists('verification_servers');
    }
};
