<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseUnitResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'org_unit_id' => $this->org_unit_id,
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'credit_units' => $this->credit_units,
            'level' => $this->level,
            'is_active' => (bool) $this->is_active,
            'institution_id' => $this->institution_id,
            'owner_user_id' => $this->owner_user_id,
            'org_unit' => $this->whenLoaded('orgUnit', fn () => $this->orgUnit === null
                ? null
                : [
                    'id' => $this->orgUnit->id,
                    'name' => $this->orgUnit->name,
                    'type' => $this->orgUnit->type,
                ]),
            // A teacher list is names and codes, not full user records. The
            // teaching staff is not public within an institution.
            'teachers' => $this->whenLoaded('teachers', fn () => $this->teachers->map(fn ($teacher) => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'email' => $teacher->email,
                'is_responsible' => (bool) ($teacher->pivot->is_responsible ?? false),
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}