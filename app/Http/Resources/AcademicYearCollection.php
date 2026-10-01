<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A plain list of academic years.
 *
 * The wrap is disabled so the response body is the array itself, not an object
 * with a `data` key around it. That matches how the auth endpoints already
 * respond, so a client parses every list the same way.
 */
class AcademicYearCollection extends ResourceCollection
{
    public static $wrap = null;

    /**
     * @var class-string<AcademicYearResource>
     */
    public $collects = AcademicYearResource::class;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return $this->collection
            ->map(fn (AcademicYearResource $resource): array => $resource->toArray($request))
            ->values()
            ->all();
    }
}