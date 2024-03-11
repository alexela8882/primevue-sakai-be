<?php

namespace App\Services;

use App\Http\Resources\ModelCollection;
use App\Models\Core\Currency;
use App\Models\Core\Field;
use App\Models\Product\Pricebook;

class LookupService
{
    public function checkFieldItem($fieldId, $itemId)
    {

        $field = Field::find($fieldId);
        if (! $field) {
            throw new \Exception('Error. Unknown field '.$fieldId);
        }
        if ($field->fieldType->name != 'lookupModel') {
            throw new \Exception('Error Field type must be a lookup');
        }

        $item = $field->relation->relatedEntity->getModel()->find($itemId);
        if (! $item) {
            throw new \Exception('Error. Cannot find '.$itemId.' in '.$field->entity->name);
        }

        return [$field, $item];
    }

    public function getAutoFillFields($fieldId, $itemId)
    {

        [$field, $item] = $this->checkFieldItem($fieldId, $itemId);

        $rules = $field->rules->whereIn('name', ['auto_fill', 'auto_fill_value']);
        if ($rules && $rules->isNotEmpty()) {
            $values = [];
            $fieldSrcsNames = [];
            foreach ($rules as $rule) {
                $fieldSrcsNames = array_merge($fieldSrcsNames, array_pluck($rule->value, 0.0));
            }
            $fieldSrcs = $field->relation->relatedEntity->fields()->with('fieldType')->whereIn('name', $fieldSrcsNames)->get();

            foreach ($fieldSrcs as $fieldSrc) {
                if (array_key_exists($fieldSrc->name, $values)) {
                    continue;
                }

                if ($fieldSrc->fieldType->name == 'lookupModel') {
                    $relEntity = $fieldSrc->relation->relatedEntity->load('fields');
                    $model = $relEntity->getModel()->find($item->{$fieldSrc->name});
                    if (! $model) {
                        continue;
                    }
                    //  throw new \Exception('Error. Cannot find ' . $item->{$fieldSrc->name} . ' in given field source ' . $relEntity->name);

                    $picklists = $this->picklist->getPicklistsFromFields($relEntity->fields);
                    $values[$fieldSrc->name] = $this->fractalTransformer->createItem($model, new ModelTransformer($relEntity->fields()->whereIn('name', $fieldSrc->relation->displayFieldName)->get(), $picklists, [], null, false, 0, true));
                } elseif ($fieldSrc->fieldType->name == 'picklist') {
                    $values[$fieldSrc->name] = picklist_id($fieldSrc->listName, $item->{$fieldSrc->name});
                } else {
                    $values[$fieldSrc->name] = $item->{$fieldSrc->name};
                }
            }

            return $values;
        } else {
            throw new \Exception('Error. Cannot return a field that has no autofill/autofill_value rule.');
        }
    }

    public function getFieldValues($fieldId, $itemId, $depth = 2, $withId = true)
    {
        $field = Field::find($fieldId);
        if (! $field) {
            throw new \Exception('Error. Unknown field '.$fieldId);
        }
        if ($field->fieldType->name != 'lookupModel') {
            throw new \Exception('Error Field type must be a lookup');
        }

        $item = $field->entity->getRepository()->find($itemId);
        if (! $item) {
            throw new \Exception('Error. Cannot find '.$itemId.' in '.$field->entity->name);
        }

        $fieldValues = $item->{$field->name};

        $displayFields = $field->relation->getDisplayFields();
        if (count($displayFields)) {

            if (! is_array($fieldValues)) {
                $relEntityFields = $field->relation->relatedEntity->fields()->get();
                $returnSingle = true;
                $fieldValues = [$fieldValues];
            } else {
                $returnSingle = false;
            }

            $relatedItems = $field->relation->relatedEntity->getRepository()->getModel()->whereIn('_id', $fieldValues)->get();

            $list = collect([]);
            foreach ($relatedItems as $relatedItem) {
                $values = [];
                foreach ($displayFields as $displayField) {
                    if ($withId) {
                        $values['_id'] = $itemId;
                    }

                    $field = $relEntityFields->where('name', $displayField)->first();
                    if ($field->fieldType->name == 'lookupModel' && --$depth != 0) {
                        $values[$displayField] = $this->getFieldValues($field->_id, $item->{$field->name}, $depth);
                    } else {
                        $values[$displayField] = $relatedItem->{$displayField};
                    }
                }
                $list->push(makeObject($values));
            }
            if ($returnSingle) {
                return $list->first();
            } else {
                return $list;
            }

        } else {
            return $item;
        }
    }

    public function getOpportunityPricebooks($fields = null, $picklists = null)
    {

        $currencyId = request('SalesOpportunity::currency_id');

        if (! $currencyId) {
            return ['message' => 'Error. Currency undefined', 'status_code' => 422];
        }
        $currency = Currency::where('_id', $currencyId)->orWhere('code', $currencyId)->first();

        if (! $currency) {
            return ['message' => 'Error. Unknown currency '.$currencyId, 'status_code' => 422];
        }

        $pbs = Pricebook::where('isStandard', false)->get()->filter(function ($pb) use ($currency) {
            return collect($pb->currencies)->contains($currency->_id);
        })->pluck('_id');

        $limit = (int) request('limit', 50);

        $paginator = Pricebook::whereIn('_id', $pbs)->paginate($limit);
        $collection = $paginator->getCollection();

        if (! $fields) {
            $fields = Field::where('uniqueName', 'pricebook_name');
        }
        if (! $picklists) {
            $picklists = (new PicklistService)->getPicklistsFromFields($fields);
        }

        return new ModelCollection($collection, $fields, $picklists);

    }
}
