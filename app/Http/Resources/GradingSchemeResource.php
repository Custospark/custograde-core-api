<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradingSchemeResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'pass_mark' => (float) $this->pass_mark,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_default' => (bool) $this->is_default,
            'institution_id' => $this->institution_id,
            'owner_user_id' => $this->owner_user_id,
            // Bands are the point of a grading scheme, so they are included
            // whenever the caller has loaded them and omitted otherwise.
            'bands' => $this->whenLoaded('bands', fn () => GradeBandResource::collection($this->bands)->resolve($request)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}