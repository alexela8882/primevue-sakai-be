<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PicklistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $values = [];

        foreach ($this->items as $key => $item) {
            $values['value'] = $item['value'];
            $values['active'] = $item['active'];
            $values['_id'] = (string) $item['_id'];
            isset($item['order']) ? $values['order'] = $item['order'] : '';
            isset($item['symbol']) ? $values['symbol'] = $item['symbol'] : '';
        }

        return [
            'name' => $this->name,
            'values' => $values,
            'values' => $values,
        ];
    }
}
