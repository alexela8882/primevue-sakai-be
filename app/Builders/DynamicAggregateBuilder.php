<?php

class DynamicAggegateBuilder
{
    public function testAggegate($fields, $module, $viewFilterFields)
    {

        $collectionFieldNames = ($module->hasViewFilter) ? $currentViewFilterFields->pluck('name')->toArray() : $fields->pluck('name')->toArray();
        $project = [];
        foreach ($collectionFieldNames as $cf) {
            $project[$cf] = '1';
        }

        $tfField = null;
        $firstMatch = [];
        $firstMatch[] = ['deleted_at' => null];
        if (Input::get('type')) {
            $tfField = $fields->where('typeFilter', '!=', null)->first();
            $firstMatch[] = [$tfField->name => Input::get('type')];
        }

        $ignorefilter = ($module->hasViewFilter && $this->currentViewFilter) ? $this->currentViewFilter->ignorePermission ?? false : false;
        $mainEntity = $this->entityRepository->where(['name' => $this->mainEntityName]);
        if (! $ignorefilter) {
            $field = $mainEntity->fields()->where('name', 'branch_id')->count();
            if ($field) {
                $firstMatch[] = ['branch_id' => ['$in' => (array) $this->user->handled_branch_ids]];
            }
        }

        $searchFields = Input::get('searchFields');
        if ($searchFields) {
            $searchFields = $fields->whereIn('_id', $searchFields);
        } else {
            $searchFields = $fields->where('searchKey', true);
        }

        $searchString = Input::get('search');

        $searchAggreggate = [];
        $lookup = [];
        $hide = [];

        foreach ($searchFields as $sf) {
            $sf->load('fieldType');
            if ($sf->fieldType->name == 'picklist') {
                $itemIds = $this->pickListRepository->getList($sf->listName)->listItems->filter(function ($item) use ($searchString) {
                    $str = str_replace('\\', '\\\\\\\\', $searchString);

                    return preg_match('!.*'.strtolower($str).'.*!', strtolower($item));
                })->pluck('_id');

                if ($itemIds) {
                    $searchAggreggate[] = [$sf->name => ['$in' => (array) $itemIds]];
                }
            } elseif ($sf->fieldType->name == 'lookupModel') {

                $fieldNames = (array) $sf->relation->getDisplayFields(false);
                $entity = $sf->relation->relatedEntity;
                $lookup[] = [
                    '$lookup' => [
                        'from' => $entity->collection,
                        'localField' => $sf->name,
                        'foreignField' => ($sf->relation->local_key == '_id') ? 'oid' : $sf->relation->local_key,
                        'as' => $entity->collection,
                    ],
                ];
                $lookup[] = ['$unwind' => '$'.$entity->collection];

                $hide[$entity->collection] = 0;

                foreach ($fieldNames as $fn) {
                    $lookup[] = ['$addFields' => [$sf->name.'_'.$fn => '$'.$entity->collection.'.'.$fn]];
                    $searchAggreggate[] = [$sf->name.'_'.$fn => ['$regex' => $searchString, '$options' => 'i']];
                }
            } else {
                $searchAggreggate[] = [$sf->name => ['$regex' => $searchString, '$options' => 'i']];
            }
        }

        $aggregate = [];
        $aggregate[] = (count($firstMatch) > 1) ? ['$match' => ['$and' => $firstMatch]] : $firstMatch;

        foreach ($lookup as $lu) {
            $aggregate[] = $lu;
        }

        $aggregate[] = ['$project' => $hide];

        $aggregate[] =
             [
                 '$match' => ['$or' => $searchAggreggate],
             ];

        $aggregate[]['$limit'] = 25;
        $aggregate[] = ['$project' => ['account_id' => 1]];

        return SalesOpportunity::raw(function ($collection) use ($aggregate) {
            return $collection->aggregate($aggregate, ['allowDiskUse' => true]);
        });
    }

    public function isMultiSelect($rules)
    {
        return count(array_intersect(array_column($rules, 'name'), ['ms_dropdown', 'ms_list_view', 'checkbox_inline', 'checkbox', 'tab_multi_select', 'ms_pop_up'])) > 0;
    }
}
