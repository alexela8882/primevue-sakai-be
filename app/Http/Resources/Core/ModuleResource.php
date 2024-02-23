<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
		
		$return = [
			'_id' => $this->_id,
            'name' => $this->name,
            'label' => $this->label,
            'icon' => $this->icon,
            'route' => $this->route,
            'color' => $this->color,
            'description' => $this->description,
            'order' => $this->order,
            'folder_id' => $this->folder_id,
			'mainEntity' => $this->whenLoaded('entity') ? $this->entity->name : null,
		];

        return $return;
    }
}
