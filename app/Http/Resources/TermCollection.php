<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A plain list of terms, in teaching order.
 */
class TermCollection extends ResourceCollection
{
    public static $wrap = null;

    /**
     * @var class-string<TermResource>
     */
    public $collects = TermResource::class;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return $this->collection
            ->map(fn (TermResource $resource): array => $resource->toArray($request))
            ->values()
            ->all();
    }
}