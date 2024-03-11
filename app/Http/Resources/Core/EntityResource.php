<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    protected static $forReport;

    public static function customCollection($resource, $forReport = false)
    {
        self::$forReport = $forReport;

        return parent::collection($resource);
    }

    public function toArray(Request $request): array
    {

        if ($this->forReport) {
            $data = [
                '_id' => $request->_id,
                'label' => $request->label,
                'name' => $request->name,
            ];
        } else {
            $data = parent::toArray($request);
        }

        $data['fields'] = FieldResource::customCollection($request->fields);

        return $data;

    }
}
