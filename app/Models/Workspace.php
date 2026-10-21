<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory, HasUid;

    protected $fillable = ['name', 'timezone', 'company', 'address', 'contact_email'];

    protected static ?Workspace $current = null;

    /**
     * The active client. For a signed-in user it always follows users.current_workspace_id
     * (route-model binding runs before route middleware, and a switch must apply at once);
     * without a user (queue, CLI) it is whatever setCurrent() was given.
     */
    public static function current(): ?Workspace
    {
        $user = auth()->user();
        if ($user && static::$current?->id !== $user->current_workspace_id) {
            static::$current = $user->current_workspace_id ? static::find($user->current_workspace_id) : null;
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
