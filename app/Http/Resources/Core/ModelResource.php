<?php

namespace App\Http\Resources\Core;

use App\Http\Resources\FieldCollection;
use App\Http\Resources\PanelCollection;
use App\Http\Resources\ViewFilterCollection;
use App\Models\Core\Module;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModelResource extends JsonResource
{
    public function __construct($resource, Module $module, private Collection $fields, private Collection $panels, private Collection $viewFilters)
    {
        parent::__construct($resource);

        $this->resource = $resource;

        $this->wrap($module->name);
    }

    public function toArray(Request $request): array
    {
        $paginated = $this->resource->toArray();

        return [
            'collection' => [
                'data' => $this->collection,
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
            ],
            'fields' => new FieldCollection($this->fields),
            'panels' => new PanelCollection($this->panels),
            'viewFilters' => new ViewFilterCollection($this->viewFilters),
        ];
    }

    public function paginationInformation($request, $paginated, $default)
    {
        unset($default['links'], $default['meta']);
        dd(true);

        return $default;
    }
}
