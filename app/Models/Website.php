<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client website connected with one script tag. The script loads the site's connected
 * signup forms (popups and inline placeholders) and records page views.
 */
class Website extends Model
{
    use BelongsToWorkspace, HasUid;

    protected $fillable = ['name', 'url', 'track_visits'];

    protected function casts(): array
    {
        return ['track_visits' => 'boolean', 'connected_at' => 'datetime', 'last_seen_at' => 'datetime', 'checked_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(fn (Website $website) => $website->host = static::hostOf($website->url));
    }

    public static function hostOf(?string $url): string
    {
        return preg_replace('/^www\./', '', strtolower((string) parse_url((string) $url, PHP_URL_HOST)));
    }

    /** True for the site's own host and its subdomains (shop.example.com belongs to example.com). */
    public function matchesHost(?string $host): bool
    {
        $host = preg_replace('/^www\./', '', strtolower((string) $host));

        return $host !== '' && ($host === $this->host || str_ends_with($host, '.'.$this->host));
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function markSeen(): void
    {
        if ($this->isConnected() && $this->last_seen_at?->greaterThan(now()->subMinutes(5))) {
            return; // avoid a write per page view
        }
        $this->forceFill(['status' => 'connected', 'connected_at' => $this->connected_at ?? now(), 'last_seen_at' => now(), 'check_error' => null])->save();
    }

    public function scriptUrl(): string
    {
        return route('site.script', $this->uid);
    }

    public function snippet(): string
    {
        return '<script src="'.$this->scriptUrl().'" async></script>';
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(WebsiteVisit::class);
    }
}
