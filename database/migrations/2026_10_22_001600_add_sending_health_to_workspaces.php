<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->timestamp('sending_suspended_at')->nullable()->after('contact_limit');
            $table->string('suspension_reason')->nullable()->after('sending_suspended_at');
            // Events before this moment are ignored after an admin reviews and lifts a suspension
            $table->timestamp('health_reviewed_at')->nullable()->after('suspension_reason');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn(['sending_suspended_at', 'suspension_reason', 'health_reviewed_at']));
    }
};
