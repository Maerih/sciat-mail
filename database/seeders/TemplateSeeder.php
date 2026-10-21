<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

/** Starter templates shared by every client (workspace_id = null). Safe to re-run. */
class TemplateSeeder extends Seeder
{
    public const TEMPLATES = [
        'newsletter' => 'Newsletter',
        'promotion' => 'Promotion — discount code',
        'announcement' => 'Announcement',
        'simple-letter' => 'Simple personal letter',
        'event' => 'Event invitation',
    ];

    public function run(): void
    {
        foreach (self::TEMPLATES as $file => $name) {
            Template::updateOrCreate(
                ['workspace_id' => null, 'name' => $name],
                ['category' => 'starter', 'html' => file_get_contents(database_path("templates/{$file}.html"))]
            );
        }
    }
}
