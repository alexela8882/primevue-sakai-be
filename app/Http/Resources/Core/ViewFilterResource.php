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
            'query_type' => $this->query_type,
            'sortField' => $this->sortField,
            'sortOrder' => $this->sortOrder,
            'filters' => $this->filters,
            'filterLogic' => $this->filterLogic,
            'filterName' => $this->filterName,
            'module_id' => $this->module_id,
            'moduleName' => $this->moduleName,
            'fields' => $this->fields,
            'isDefault' => $this->isDefault,
            'currentDisplay' => $this->currentDisplay,
            'search_fields' => $this->search_fields,
            'summarize_by' => $this->summarize_by,
            'group_by' => $this->group_by,
            'owner' => $this->owner,
            'pageSize' => $this->pageSize,
            'title_ids' => $this->title_ids,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
            'entity_id' => $this->entity_id,
        ];
    }
}
