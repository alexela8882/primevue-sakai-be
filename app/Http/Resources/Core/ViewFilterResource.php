<?php

namespace App\Http\Resources\Core;

use App\Http\Resources\ModelCollection;
use App\Models\Core\Field;
use App\Models\Core\Picklist;
use App\Services\RelationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ViewFilterResource extends JsonResource
{
    public static function customCollection($resource)
    {
        $filters = $resource->pluck('filters')->collapse();

        $fields = Field::query()
            ->whereIn('_id', $filters->pluck('field_id'))
            ->with([
                'fieldType',
                'relation',
                'relation.entity',
                'relation.entity.fields',
            ])
            ->get();

        $listNames = $fields->filter(fn (Field $field) => $field->fieldType->name == 'picklist')->map(fn (Field $field) => $field->listName);

        if ($listNames->isNotEmpty()) {
            $listNames = $listNames->merge(['filter_operators']);
        }

        $listItems = Picklist::query()
            ->whereIn('name', $listNames)
            ->with('listItems')
            ->get()
            ->map(function (Picklist $picklist) {
                if ($picklist->name == 'filter_operators') {
                    return [$picklist->name => $picklist->listItems->pluck('label', '_id')];
                }

                return [$picklist->name => $picklist->listItems->pluck('value', '_id')];
            })
            ->collapse();

        $resource = $resource->map(function ($resource) use ($fields, $listItems) {
            if (is_array($resource->filters)) {
                $resource->filters = Arr::map($resource->filters, function ($filter) use ($fields, $listItems) {
                    $field = $fields->firstWhere('_id', $filter['field_id']);

                    if ($field->fieldType->name == 'lookupModel' && $filter['values'] != null) {
                        $displayFields = (new RelationService)->getActualDisplayFields($field->relation);

                        $test = $field->relation->entity->getModel()->whereIn('_id', (array) $filter['values'])->select($field->relation->displayFieldName)->get();

                        $values = new ModelCollection($test, $displayFields, [], false, false, true);
                    } elseif ($field->fieldType->name == 'picklist') {
                        $values = $listItems[$field->listName]->only($filter['values'])->values();
                    } else {
                        $values = $filter['values'];
                    }

                    return [
                        'field_id' => $field->label,
                        'operator_id' => $listItems['filter_operators'][$filter['operator_id']],
                        'values' => $values,
                    ];
                });
            }

            return $resource;
        });

        return parent::collection($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            '_id' => $this->_id,
            'query_id' => $this->query_id,
            'queryType' => $this->queryType,
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
