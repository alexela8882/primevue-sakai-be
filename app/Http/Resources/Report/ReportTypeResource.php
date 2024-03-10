<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            '_id' => $request->_id,
            'name' => $request->name,
            'entityData' => $request->entityData,
            'defaultFields' => $request->reportFields,
        ];

    }
}
