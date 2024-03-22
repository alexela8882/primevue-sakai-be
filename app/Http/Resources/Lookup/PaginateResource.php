<?php

namespace App\Http\Resources\Lookup;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PaginateResource extends ResourceCollection
{
    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->resource = $resource;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request)
    {

        $paginated = $this->resource->toArray();

        return [
            'data' => ProductFamilyResource::collection($this->collection),
            'meta' => [
                'pagination' => [
                    'total' => $paginated['total'] ?? null,
                    'count' => $paginated['per_page'] ?? null,
                    'per_page' => $paginated['per_page'] ?? null,
                    'current_page' => $paginated['current_page'] ?? null,
                    'links' => [
                        'first' => $paginated['first_page_url'] ?? null,
                        'last' => $paginated['last_page_url'] ?? null,
                        'prev' => $paginated['prev_page_url'] ?? null,
                        'next' => $paginated['next_page_url'] ?? null,
                    ],
                ],
            ],
        ];

    }
}
