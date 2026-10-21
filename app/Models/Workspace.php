<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasUid;

    protected $fillable = ['name', 'timezone', 'company', 'address', 'contact_email'];

    protected static ?Workspace $current = null;

    public static function current(): ?Workspace
    {
        return static::$current;
    }

    public static function setCurrent(?Workspace $workspace): void
    {
        static::$current = $workspace;
    }

    public function lists(): HasMany
    {
        return $this->hasMany(MailList::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function sendingDomains(): HasMany
    {
        return $this->hasMany(SendingDomain::class);
    }

    public function trackingDomains(): HasMany
    {
        return $this->hasMany(TrackingDomain::class);
    }

    public function defaultTrackingDomain(): ?TrackingDomain
    {
        return $this->trackingDomains()->where('verified', true)->orderByDesc('is_default')->first();
    }

    /** Servers this workspace may send through: its own plus shared ones. */
    public function availableServers()
    {
        return SendingServer::query()
            ->where(fn ($q) => $q->whereNull('workspace_id')->orWhere('workspace_id', $this->id));
    }
}
