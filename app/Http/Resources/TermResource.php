<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TermResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'name' => $this->name,
            'sequence' => (int) $this->sequence,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'status' => $this->status,
            'is_active' => $this->status === \App\Models\Term::STATUS_ACTIVE,
            'institution_id' => $this->institution_id,
            'owner_user_id' => $this->owner_user_id,
            'academic_year' => $this->whenLoaded('academicYear', fn () => $this->academicYear === null
                ? null
                : [
                    'id' => $this->academicYear->id,
                    'name' => $this->academicYear->name,
                    'starts_on' => $this->academicYear->starts_on?->toDateString(),
                    'ends_on' => $this->academicYear->ends_on?->toDateString(),
                ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}