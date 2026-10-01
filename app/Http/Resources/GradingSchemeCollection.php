<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A plain list of grading schemes.
 */
class GradingSchemeCollection extends ResourceCollection
{
    public static $wrap = null;

    /**
     * @var class-string<GradingSchemeResource>
     */
    public $collects = GradingSchemeResource::class;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return $this->collection
            ->map(fn (GradingSchemeResource $resource): array => $resource->toArray($request))
            ->values()
            ->all();
    }
}