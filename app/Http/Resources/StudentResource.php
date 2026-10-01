<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A candidate on the roster (STU-01, STU-07).
 *
 * full_name and initials are computed here rather than stored, because the schools
 * entering this data do not agree on how many parts a name has. initials is what a
 * marker sees under blind marking, where a shape is enough to tell two stacks of
 * papers apart without naming anyone.
 */
class StudentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reg_no' => $this->reg_no,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName(),
            'initials' => $this->initials(),
            'class_name' => $this->class_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status,
            'institution_id' => $this->institution_id,
            'owner_user_id' => $this->owner_user_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}