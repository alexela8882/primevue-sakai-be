<?php

namespace App\Http\Resources\Core;

use App\Services\ModelService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModelResource extends JsonResource
{
    private ModelService $modelService;

    protected static $fields;

    protected static $pickLists;

    public function __construct($resource, Collection $fields, array $pickLists)
    {
        parent::__construct($resource);

        $this->resource = $resource;

        static::$fields = $fields;

        static::$pickLists = $pickLists;

        $this->modelService = new ModelService;
    }

    public function toArray(Request $request): array
    {
        return $this->modelService->getModelInformation($this->resource, self::$fields, self::$pickLists);
    }
}
