<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A plain list of papers.
 */
class ExamCollection extends ResourceCollection
{
    public static $wrap = null;

    /**
     * @var class-string<ExamResource>
     */
    public $collects = ExamResource::class;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return $this->collection
            ->map(fn (ExamResource $resource): array => $resource->toArray($request))
            ->values()
            ->all();
    }
}