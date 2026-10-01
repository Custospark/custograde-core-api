<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A plain list of course documents.
 *
 * Metadata only, in line with CourseResourceResource. No storage path is exposed
 * anywhere in this list, so a document cannot be fetched by guessing a name.
 */
class CourseResourceCollection extends ResourceCollection
{
    public static $wrap = null;

    /**
     * @var class-string<CourseResourceResource>
     */
    public $collects = CourseResourceResource::class;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return $this->collection
            ->map(fn (CourseResourceResource $resource): array => $resource->toArray($request))
            ->values()
            ->all();
    }
}