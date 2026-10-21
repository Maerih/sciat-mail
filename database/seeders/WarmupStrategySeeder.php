<?php

namespace Database\Seeders;

use App\Models\WarmupStrategy;
use Illuminate\Database\Seeder;

/** Built-in warmup presets (workspace_id = null). Safe to re-run. */
class WarmupStrategySeeder extends Seeder
{
    public function run(): void
    {
        foreach (WarmupStrategy::PRESETS as $preset) {
            WarmupStrategy::updateOrCreate(['workspace_id' => null, 'name' => $preset['name']], $preset);
        }
    }
}
