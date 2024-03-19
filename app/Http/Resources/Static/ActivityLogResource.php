<?php

namespace App\Http\Resources\Static;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ActivityLogResource extends JsonResource
{
    public static function customItemCollection($item) {
      return $item;
    }

    public static function customCollection($resource) {
      return $resource;
    }
}
