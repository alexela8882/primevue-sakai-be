<?php

namespace App\Http\Resources\Static;

use Illuminate\Http\Resources\Json\JsonResource;

class ViewFilterResource extends JsonResource
{
    public static function customItemCollection($item)
    {
        return $item;
    }

    public static function customCollection($resource)
    {
        return $resource;
    }
}
