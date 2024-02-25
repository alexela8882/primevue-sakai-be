<?php

namespace App\Services;

use App\Http\Resources\Core\FieldResource;
use App\Models\Core\Field;
use App\Models\Core\FieldType;
use Illuminate\Support\Arr;

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
}
