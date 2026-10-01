<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrgUnitResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'type' => $this->type,
            'name' => $this->name,
            'code' => $this->code,
            'depth' => $this->depth === null ? null : (int) $this->depth,
            'is_active' => (bool) $this->is_active,
            'institution_id' => $this->institution_id,
            'owner_user_id' => $this->owner_user_id,
            'parent' => $this->whenLoaded('parent', fn () => $this->parent === null
                ? null
                : [
                    'id' => $this->parent->id,
                    'name' => $this->parent->name,
                    'type' => $this->parent->type,
                ]),
            'children' => $this->whenLoaded('children', fn () => $this->children->map(fn ($child) => [
                'id' => $child->id,
                'parent_id' => $child->parent_id,
                'name' => $child->name,
                'code' => $child->code,
                'type' => $child->type,
                'depth' => $child->depth === null ? null : (int) $child->depth,
                'is_active' => (bool) $child->is_active,
            ])->values()),
            'course_units' => $this->whenLoaded('courseUnits', fn () => $this->courseUnits->map(fn ($course) => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}