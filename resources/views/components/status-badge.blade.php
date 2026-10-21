@props(['status'])
@php
    $tone = match ($status) {
        'subscribed', 'sent', 'done', 'active', 'published', 'verified' => 'green',
        'sending', 'queued' => 'blue',
        'scheduled', 'unconfirmed', 'paused', 'pending', 'draft' => 'yellow',
        'unsubscribed', 'skipped', 'cancelled', 'inactive' => 'gray',
        'blacklisted', 'failed', 'bounced', 'complained', 'error' => 'red',
        default => 'gray',
    };
@endphp
<span class="badge-{{ $tone }}">{{ ucfirst($status) }}</span>
