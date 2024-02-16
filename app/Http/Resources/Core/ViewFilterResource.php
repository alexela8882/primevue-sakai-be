<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ViewFilterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
			'_id' => $this->_id,
            'query_id' => $this->query_id,
            'sortField' => $this->created_at,
            'sortOrder' => $this->sortOrder,
            'filters' => $this->filters,
            'filterLogic' => $this->filterLogic,
            'filterName' => $this->filterName,
			'moduleName' => $this->moduleName,
			'fields' => $this->fields,
			'isDefault' => $this->isDefault,
			'currentDisplay' => $this->currentDisplay,
			'summarize_by' => $this->summarize_by,
			'group_by' => $this->group_by,
			'owner' => $this->owner,
			'created_by' => $this->created_by,
			'updated_by' => $this->updated_by,
			'updated_at' => $this->updated_at,
			'created_at' => $this->created_at,
			'entity_id' => $this->entity_id,
		];
    }
}
