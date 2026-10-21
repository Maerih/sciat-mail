<?php

namespace App\Http\Resources;

use App\Models\ListField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ListField */
class FieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tag' => $this->tag,
            'label' => $this->label,
            'type' => $this->type,
            'options' => $this->options,
            'required' => $this->required,
        ];
    }
}
