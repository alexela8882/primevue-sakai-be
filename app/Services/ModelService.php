<?php

namespace App\Services;

use App\Models\Core\Country;
use App\Models\Model\Base;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ModelService
{
    public function getModelInformation(Request|Base|User $base, Collection $fields, ?array $pickLists, bool $fromReport = false, bool $displayFieldNameOnly = false)
    {
        $data = ['_id' => $base->_id];

        $appends = $base->getAppends();

        foreach ($fields as $field) {
            $value = null;

            if ($field->fieldType->name == 'lookupModel') {
                if ($field->name == 'branch_id' && $base->{$field->name} == 'esco-global' && ! $fromReport) {
                    $value = 'esco-global';
                } elseif ($field->name == 'lastSchedule' && ! $fromReport) {
                    $schedID = $base->lastSchedule ?? null;
                    $details = [];

                    if ($schedID) {
                        $details['scheduleCode'] = ServiceSchedule::find($schedID)->scheduleCode ?? null;
                        $details['serviceStartDate'] = ScheduleAssignment::where('service_schedule_id', $schedID)->orderBy('serviceStartDate')->first()->serviceStartDate ?? null;
                        $details['serviceEndDate'] = ScheduleAssignment::where('service_schedule_id', $schedID)->orderBy('serviceEndDate', 'DESC')->first()->serviceEndDate ?? null;
                    }

                    $value = $details;
                } else {
                    $lookupFieldReturnables = (new FieldService)->getLookupReturnables($field, true, $displayFieldNameOnly);

                    $items = $this->getItemReturnables($base, $field, $lookupFieldReturnables);

                    if ($field->relation->method == 'belongsTo' || $field->relation->method == 'hasOne') {
                        $items = $items->first();

                        // SAVE FOR LATER
                        // if (
                        //     ($this->recurseLookup && $items) || ($field->uniqueName == 'salesquote_quote_to_name_id' && $items)
                        // ) {
                        //     $fields = $field->relation->getActualDisplayFields();
                        //     $pls = PickList::getPicklistsFromFields($fields);
                        //     $itemTransformer = new ModelTransformer($fields, $pls, [], $this->moduleName, $this->includeDeepFields, $this->recurseLookup - 1, $this->lookupDisplayFieldNameOnly);
                        //     $items = FractalModelTransformer::createItem($items, $itemTransformer);
                        // } else

                        if ($items) {
                            $items = $items->toArray();
                        }
                    } elseif ($fromReport) {
                        $items = $field->relation->entity->getModel()->whereIn('_id', (array) $base->{$field->name})->get($lookupFieldReturnables);
                    }

                    $value = $items;
                }

                if ($field->relation->entity->name == 'AddressBook' && ! $fromReport) {
                    if (is_array($value) && array_key_exists('country_id', $value) && $value['country_id']) {
                        $value['country_id'] = ['_id' => $value['country_id'], 'name' => Country::find($value['country_id'])->name ?? null];
                    }
                }
            } elseif ($field->fieldType->name === 'picklist' && isset($base->{$field->name})) {
                if (is_array($base->{$field->name})) {
                    $value = array_values(array_intersect_key($pickLists[$field->listName], array_flip($base->{$field->name})));
                } else {
                    $value = $pickLists[$field->listName][$base->{$field->name}] ?? null;
                }

                // DI KO LAM SAN TONG CALLER. DI NAMAN TINATAWAG SA V1
                // if ($this->caller === 'picklist') {
                //     $value = ['values' => $value, 'picklist' => true];
                // }

                if ($field->entity->name == 'BreakdownLog') {
                    $value = ['value' => $value, '_id' => $base->{$field->name}];
                }
            } elseif ($field->fieldType->name == 'boolean') {
                $value = $base->{$field->name} ?? false;
            }

            // TO BE RESOLVED BY CHARISSE
            // elseif ($field->rusType) {
            //     $value = $base->{$field->name} ?? null;

            //     if ($value === null) {
            //         $value = \Rus::resolve($base, $field); // TO BE RESOLVED BY CHARISSE
            //     }
            // } elseif ($field->formulaType) {
            //     $value = $base->{$field->name} ?? null;

            //     if ($value === null) {
            //         $value = FormulaParser::parseField($field, $base, true); // TO BE RESOLVED BY CHARISSE
            //     }
            // }

            else {
                $value = $base->{$field->name};
            }

            $data[$field->name] = $value;
        }

        if (! $displayFieldNameOnly && ! empty($appends)) {
            foreach ($appends as $append) {
                $data[$append] = $base->{$append};
            }
        }

        return $data;
    }

    protected function getItemReturnables($model, $field, $returnableFields)
    {
        return $model
            ->dynamicRelationship($field->relation->method, $field->relation->class, $field->relation->foreign_key, $field->relation->local_key, $field->uniqueName, true)
            ->get($returnableFields);
    }
}
