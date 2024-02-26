<?php

namespace App\Services;

use App\Http\Resources\Core\FieldResource;
use App\Models\Core\Field;
use App\Models\Core\FieldType;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;

class FieldService
{
    protected $lookupAddable = [
        'extraInfo',
        'level',
        'filterSourceField',
        'rules' => [
            'auto_fill',
        ],
    ];

    protected $selectionRules = [
        'ms_dropdown',
        'ms_list_view',
        'checkbox_inline',
        'checkbox',
        'tab_multi_select',
        'ms_pop_up',
        'checkbox_inline',
    ];

    public function getLookupReturnables(FieldResource|Field $field, bool $includeId = true, bool $displayFieldNameOnly = false, bool $includePopUp = false)
    {
        if ($field->fieldType->name == 'lookupModel') {
            $relationFields = $field->relation->entity->fields->pluck('name')->toArray();

            $returnables = (array) (new RelationService)->getDisplayFields($field->relation, $includePopUp);

            if (! $displayFieldNameOnly) {
                foreach (self::$lookupAddable as $key => $value) {
                    if ($key === 'rules') {
                        $rule = $field->rules->whereIn('name', $value)->first();

                        if ($rule && in_array('auto_fill', $value)) {
                            $returnables = array_merge($returnables, Arr::pluck($rule->value, 0.0));
                        }
                    } else {
                        $returnables = array_merge($returnables, array_intersect([$field->name], $relationFields));
                    }
                }
            }

            if ($includeId) {
                $returnables[] = '_id';
            }

            if ($field->relation->entity->name == 'Currency') {
                $returnables = array_merge($returnables, ['code', 'symbol']);
            }

            return array_unique($returnables);
        }

        return null;
    }

    public function hasMultipleValues(Field|FieldResource $field): bool
    {
        if ($field->fieldType instanceof FieldType) {
            $fieldType = $field->fieldType->name;

            return ($fieldType == 'lookupModel' && $field->relation->method == 'belongsToMany') || ($fieldType == 'picklist' && $field->rules->whereIn('name', self::$selectionRules)->count());
        }

        return false;
    }

    public function resolveExecuteLookupField(Request $request, Field $field, mixed $model)
    {
        $lookupModels = [];

        $isRequired = $field->rules->where('name', 'required')->first();

        $input = (array) $request->input($field->name);

        $relation = $field->relation;

        $model = App::make($relation->entity->model_class);

        // if lookup is a controlling field, store model
        if ($field->controls) {
            $lookupModels[$field->name] = $model;
        }

        $items = $model->whereIn('_id', $input)->get();

        $itemsDiff = collect($input)->diff($items->pluck('_id'));

        if ($itemsDiff->isNotEmpty() && $isRequired) {
            throw new Exception("Error. Invalid IDs for field {$field->name}: {$itemsDiff->implode(', ')}");
        }

        if ($field->uniqueName !== 'serviceschedule_branch_id') {
            if ($itemsDiff->isNotEmpty()) {
                throw new Exception("Error. The following items for field \"{$field->name}\" are unidentified: {$itemsDiff->implode(', ')}");
            }
        }

        // if lookup has a controlling field
        if (isset($field->rules['filtered_by'])) {
            $controllingFieldName = $field->rules['filtered_by'];

            if (! isset($lookupModels[$controllingFieldName])) {
                $controllingField = $field->entity->fields->where('name', $controllingFieldName)->first();

                if (! $controllingField) {
                    throw new Exception("Error. Unrecognized controlling field name: {$controllingFieldName}");
                }
            }

            $controllingFieldvalue = $request->input('$cFieldName');

            $unknownItems = $items->whereNotIn($field->filterSourceField, (array) $controllingFieldvalue);

            if ($unknownItems->isNotEmpty()) {
                throw new Exception("Error.  The following items for field {$field->name} are incompatible with controlling field: {$unknownItems->implode(', ')}");
            }
        }

        $isEmpty = $request->filled($field->name);

        if (is_array($request->input($field->name))) {
            if (empty($request->input($field->name))) {
                $isEmpty = false;
            }
        }

        if ($isEmpty) {
            if ($isRequired) {
                throw new Exception("Field {$field->name} is required.");
            } elseif ($model || ! $request->missing($field->name)) {
                $input = null;
            } else {
                return false;
            }
        }

        $hasMultipleValues = (new FieldService)->hasMultipleValues($field);

        if ($hasMultipleValues) {
            return [
                'entity' => $field->relation->relatedEntity->model_class,
                'method' => $field->relation->method,
                'fkey' => $field->relation->foreign_key,
                'lkey' => $field->relation->local_key,
                'data' => $input,
            ];
        }

        return true;
    }
}
