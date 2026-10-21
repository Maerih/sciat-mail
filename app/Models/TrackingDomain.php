<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Model;

class TrackingDomain extends Model
{
    use BelongsToWorkspace, HasUid;

    protected $fillable = ['workspace_id', 'host', 'scheme', 'is_default'];

    protected function casts(): array
    {
        return ['verified' => 'boolean', 'is_default' => 'boolean', 'checked_at' => 'datetime'];
    }

    public function baseUrl(): string
    {
        return $this->scheme.'://'.$this->host;
    }

    /** Host the CNAME must point at: the app's own host. */
    public static function cnameTarget(): string
    {
        return parse_url(config('app.url'), PHP_URL_HOST);
    }
}
