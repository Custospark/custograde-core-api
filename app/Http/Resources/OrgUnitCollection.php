<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A plain list of academic units.
 *
 * The unit tree is a flat adjacency list, so a branch comes back as rows with
 * parent_id set rather than as nested objects. A client renders the hierarchy
 * itself; nesting here would make a branch harder to page and harder to diff.
 */
class OrgUnitCollection extends ResourceCollection
{
    public static $wrap = null;

    /**
     * @var class-string<OrgUnitResource>
     */
    public $collects = OrgUnitResource::class;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return $this->collection
            ->map(fn (OrgUnitResource $resource): array => $resource->toArray($request))
            ->values()
            ->all();
    }
}