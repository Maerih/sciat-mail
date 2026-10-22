<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->unsignedInteger('monthly_email_limit')->nullable()->after('website'); // null = unlimited
            $table->unsignedInteger('contact_limit')->nullable()->after('monthly_email_limit');
        });

        // Client portal users belong to exactly one workspace (role "client")
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('role')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('workspace_id'));
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn(['monthly_email_limit', 'contact_limit']));
    }
};
