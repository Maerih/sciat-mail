<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('contact_email');
            $table->string('brand_color', 7)->nullable()->after('logo_path');
            $table->string('website')->nullable()->after('brand_color');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn(['logo_path', 'brand_color', 'website']));
        Schema::dropIfExists('settings');
    }
};
