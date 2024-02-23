<?php

namespace App\Services;

use App\Models\Core\Field;
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

    public function getLookupReturnables(Field $field, bool $includeId = true, bool $displayFieldNameOnly = false, bool $includePopUp = false)
    {
        if ($field->fieldType->name == 'lookupModel') {
            $relationFields = $field->relation->entity->fields->pluck('name')->toArray();

            $returnables = (array) (new RelationService)->getDisplayFields($field->relation, $includePopUp);

            if (! $displayFieldNameOnly) {
                foreach ($this->lookupAddable as $key => $value) {
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
}
