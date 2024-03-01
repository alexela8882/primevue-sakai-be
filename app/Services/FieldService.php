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
    protected static $lookupAddable = [
        'extraInfo',
        'level',
        'filterSourceField',
        'rules' => [
            'auto_fill',
        ],
    ];

    protected static $selectionRules = [
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

    public function resolveExecuteLookupField(Request $request, $model, &$input, $entity, Field $field, &$lookupModels)
    {
        $isRequired = $field->rules->firstWhere('name', 'required');

        if ($input == '') {
            $input = (array) null;
        } else {
            $input = (array) $input;
        }

        $relation = $field->relation;

        $query = App::make($relation->entity->model_class);

        // if lookup is a controlling field, store model
        if ($field->controls) {
            $lookupModels[$field->name] = $query;
        }

        $invalidIdentifiers = collect($input)->diff($query->whereIn('_id', $input)->pluck('_id'));

        if ($invalidIdentifiers->isNotEmpty() && $isRequired) {
            throw new Exception("Error. Invalid IDs for {$field->name}: {$invalidIdentifiers->implode(', ')}");
        }

        if ($field->uniqueName != 'serviceschedule_branch_id') {
            $existingItems = $query->whereIn('_id', $input)->get();
            $unknownItems = collect($input)->diff($existingItems->pluck('_id'));

            if ($unknownItems->isNotEmpty()) {
                throw new Exception("Error. The following items for field {$field->name} are unidentified: {$unknownItems->implode(', ')}");
            }
        }

        $arrReq = is_array($request) ? $request : $request->all();

        // if lookup has a controlling field
        if (isset($field->rules['filtered_by'])) {
            $cFieldName = $field->rules['filtered_by'];
            if (! isset($lookupModels[$cFieldName])) {
                $cField = $entity->fields->where('name', $cFieldName)->first();
                if (! $cField) {
                    throw new \Exception('Error. Unrecognized controlling field '.$cFieldName);
                }
            }

            $cvalue = $request['$cFieldName'];

            $unknownItems = $existingItems->whereNotIn($field->filterSourceField, (array) $cvalue)->get();
            if ($unknownItems->isNotEmpty()) {
                throw new \Exception('Error.  The following items for field "'.$field->name.'" are incompatible with controlling field: '.implode(',', $unknownItems->toArray()));
            }
        }

        $isEmpty = ($input == null || $input == '' || is_array($input) && ! count($input));

        if ($isEmpty) {
            if ($isRequired) {
                throw new \Exception('Field '.$field->name.' is required');
            } elseif ($model || array_key_exists($field->name, $arrReq)) {
                $input = null;
            } else {
                return false;
            }
        }

        if ($field->hasMultipleValues()) {
            return [
                'entity' => $field->relation->entity->model_class,
                'method' => $field->relation->method,
                'fkey' => $field->relation->foreign_key,
                'lkey' => $field->relation->local_key,
                'data' => $input,
            ];
        }

        return true;
    }
}
