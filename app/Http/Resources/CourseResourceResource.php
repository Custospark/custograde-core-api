<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A document attached to a course.
 *
 * There is deliberately no `path` and no `disk` here. Those are storage details,
 * and a marking guide is a private file, so a client is never told where it
 * lives on disk. There is also no `download_url`: the service does not mint a
 * signed link yet, so publishing one would advertise a route that does not
 * exist. Signed download URLs arrive in a later phase, and this resource is the
 * single place that will need to change when they do.
 */
class CourseResourceResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_unit_id' => $this->course_unit_id,
            'title' => $this->title,
            'description' => $this->description,
            'kind' => $this->kind,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => (int) $this->size_bytes,
            'version' => (int) $this->version,
            'is_current' => (bool) $this->is_current,
            'uploaded_by' => $this->uploaded_by,
            'uploaded_at' => $this->created_at?->toIso8601String(),
            'uploader' => $this->whenLoaded('uploader', fn () => $this->uploader === null
                ? null
                : [
                    'id' => $this->uploader->id,
                    'name' => $this->uploader->name,
                ]),
            'course_unit' => $this->whenLoaded('courseUnit', fn () => $this->courseUnit === null
                ? null
                : [
                    'id' => $this->courseUnit->id,
                    'code' => $this->courseUnit->code,
                    'title' => $this->courseUnit->title,
                ]),
        ];
    }
}