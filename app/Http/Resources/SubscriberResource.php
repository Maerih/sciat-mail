<?php

namespace App\Http\Resources;

use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subscriber */
class SubscriberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uid' => $this->uid,
            'email' => $this->email,
            'status' => $this->status,
            'verification' => $this->verification_status,
            'fields' => (object) ($this->data ?? []),
            'tags' => $this->tags ?? [],
            'source' => $this->source,
            'last_open_at' => $this->last_open_at?->toIso8601String(),
            'last_click_at' => $this->last_click_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
