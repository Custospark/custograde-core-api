<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademicYearResource extends JsonResource
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
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'is_current' => (bool) $this->is_current,
            'institution_id' => $this->institution_id,
            'owner_user_id' => $this->owner_user_id,
            // Summarised rather than expanded: a year dragged with every term
            // behind it makes a list endpoint far heavier than it needs to be.
            'terms' => $this->whenLoaded('terms', fn () => $this->terms->map(fn ($term) => [
                'id' => $term->id,
                'name' => $term->name,
                'sequence' => $term->sequence,
                'status' => $term->status,
                'starts_on' => $term->starts_on?->toDateString(),
                'ends_on' => $term->ends_on?->toDateString(),
            ])->values()),
            'current_term' => $this->whenLoaded('currentTerm', fn () => $this->currentTerm === null
                ? null
                : [
                    'id' => $this->currentTerm->id,
                    'name' => $this->currentTerm->name,
                    'status' => $this->currentTerm->status,
                    'starts_on' => $this->currentTerm->starts_on?->toDateString(),
                    'ends_on' => $this->currentTerm->ends_on?->toDateString(),
                ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}