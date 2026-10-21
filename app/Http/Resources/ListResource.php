<?php

namespace App\Http\Resources;

use App\Models\MailList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MailList */
class ListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uid' => $this->uid,
            'name' => $this->name,
            'from_name' => $this->from_name,
            'from_email' => $this->from_email,
            'reply_to' => $this->reply_to,
            'double_optin' => $this->double_optin,
            'subscriber_counts' => $this->when($request->routeIs('api.lists.show'), fn () => $this->statusCounts()),
            'fields' => FieldResource::collection($this->whenLoaded('fields')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
