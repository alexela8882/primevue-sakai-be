<?php

namespace App\Http\Resources\Core;

use App\Http\Resources\ModelCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RelatedListResource extends JsonResource
{
    public static function customCollection($resource, $data, $fields, $pickLists)
    {
        $data['collection'] = new ModelCollection($resource, $fields, $pickLists);

        return $data;
    }

    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
