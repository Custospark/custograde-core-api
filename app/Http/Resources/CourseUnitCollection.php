<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A plain list of course units.
 */
class CourseUnitCollection extends ResourceCollection
{
    public static $wrap = null;

    /**
     * @var class-string<CourseUnitResource>
     */
    public $collects = CourseUnitResource::class;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return $this->collection
            ->map(fn (CourseUnitResource $resource): array => $resource->toArray($request))
            ->values()
            ->all();
    }
}