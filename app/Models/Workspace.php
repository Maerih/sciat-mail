<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory, HasUid;

    protected $fillable = ['name', 'timezone', 'company', 'address', 'contact_email', 'website', 'brand_color', 'logo_path', 'sms_country'];

    protected $casts = ['sending_suspended_at' => 'datetime', 'health_reviewed_at' => 'datetime'];

    public function isSuspended(): bool
    {
        return $this->sending_suspended_at !== null;
    }

    protected static ?Workspace $current = null;

    /**
     * The active client. For a signed-in user it always follows users.current_workspace_id
     * (route-model binding runs before route middleware, and a switch must apply at once);
     * without a user (queue, CLI) it is whatever setCurrent() was given.
     */
    public static function current(): ?Workspace
    {
        $user = auth()->user();
        // client portal users are pinned to their own workspace, whatever else is stored
        $wanted = $user?->isClient() ? $user->workspace_id : $user?->current_workspace_id;
        if ($user && static::$current?->id !== $wanted) {
            static::$current = $wanted ? static::find($wanted) : null;
        }

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
        return $this->hasMany(Campaign::class)->whereNull('automation_id');
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
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
