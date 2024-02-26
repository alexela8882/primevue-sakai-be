<?php

namespace App\Http\Resources\Core;

use App\Services\ModelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModelResource extends JsonResource
{
    private ModelService $modelService;

    protected static $fields;

    protected static $pickLists;

    public function __construct($resource)
    {
        parent::__construct($resource);

        $this->resource = $resource;

        $this->modelService = new ModelService;
    }

    public static function information($fields, $pickLists, $wrap = 'data')
    {
        static::$fields = $fields;

        static::$pickLists = $pickLists;

        static::$wrap = $wrap;
    }

    public function toArray(Request $request): array
    {
        return $this->modelService->getModelInformation($this->resource, self::$fields, self::$pickLists);
    }
}
