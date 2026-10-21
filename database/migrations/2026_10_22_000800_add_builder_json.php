<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Drag-and-drop project data, kept next to the exported HTML so designs re-open losslessly. */
    public function up(): void
    {
        foreach (['templates', 'campaigns'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->longText('builder_json')->nullable()->after('html');
            });
        }
    }

    public function down(): void
    {
        foreach (['templates', 'campaigns'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('builder_json'));
        }
    }
};
