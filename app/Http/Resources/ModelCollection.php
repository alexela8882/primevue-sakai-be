<?php

namespace App\Http\Resources;

use App\Models\Model\Base;
use App\Services\ModelService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ModelCollection extends ResourceCollection
{
    private ModelService $modelService;

    public function __construct(
        $resource,
        private Collection $fields,
        private ?array $pickLists,
        private bool $fromReport = false,
        private bool $displayFieldNameOnly = false,
        private bool $withoutPagination = false,
    ) {
        parent::__construct($resource);

        $this->resource = $resource;

        $this->modelService = new ModelService;
    }

    public function toArray($request)
    {
        $paginated = $this->resource->toArray();

        return $this->withoutPagination
            ? $this->collection->transform(fn (Base $base) => $this->modelService->getModelInformation($base, $this->fields, $this->pickLists, $this->fromReport, $this->displayFieldNameOnly))
            : [
                'data' => $this->collection->transform(fn (Base $base) => $this->modelService->getModelInformation($base, $this->fields, $this->pickLists, $this->fromReport, $this->displayFieldNameOnly)),
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

    public function paginationInformation($request, $paginated, $default)
    {
        unset($default['links'], $default['meta']);

        return $default;
    }
}
