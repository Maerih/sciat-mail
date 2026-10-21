<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('label', 4);
            $table->string('subject')->nullable();
            $table->string('from_name')->nullable();
            $table->string('preheader')->nullable();
            $table->longText('html')->nullable(); // null = use the campaign's content
            $table->timestamps();
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->json('ab_config')->nullable()->after('sending_window'); // {test_percent, metric, wait_hours}
            $table->foreignId('winner_variant_id')->nullable()->after('ab_config')->constrained('campaign_variants')->nullOnDelete();
            $table->timestamp('test_ends_at')->nullable()->after('winner_variant_id');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('variant_id')->nullable()->after('automation_run_id')->constrained('campaign_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', fn (Blueprint $table) => $table->dropConstrainedForeignId('variant_id'));
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('winner_variant_id');
            $table->dropColumn(['ab_config', 'test_ends_at']);
        });
        Schema::dropIfExists('campaign_variants');
    }
};
