<?php

namespace App\Http\Resources;

use App\Models\Automation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Automation */
class AutomationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uid' => $this->uid,
            'name' => $this->name,
            'status' => $this->status,
            'trigger' => $this->trigger_type,
            'trigger_summary' => $this->triggerSummary(),
            'list_uid' => $this->list?->uid,
            'runs' => $this->runCounts(),
        ];
    }
}
