<?php

namespace App\Services;

use App\Builders\DynamicQueryBuilder;
use App\Models\Core\Entity;
use App\Models\Customer\SalesOpportunity;

class SearchService
{
    public function checkSearch($builder, $fields = null, $entityName = null)
    {

        $searchString = trim(request('search', null));

        if (! $searchString) {
            return $builder;
        }

        if (! $fields) {
            $fields = request('searchFields', null);
        }

        if (! $fields) {
            $fields = Entity::where('name', $entityName)->first()->fields()->where('searchKey', true)->limit(3)->get();
        } elseif (is_array($fields) && array_depth($fields) == 1) {
            $fields = Entity::where('name', $entityName)->first()->fields()->whereIn('_id', $fields)->limit(3)->get();
        }

        if (! $fields || ! $searchString) {
            return $builder;
        }

        $searchStrings = [$searchString];

        $dqb = new DynamicQueryBuilder;

        $builder = $builder->where(function ($query) use ($fields, $searchString, $searchStrings, $entityName, $dqb) {
            foreach ($fields as $field) {

                if ($field->fieldType->name == 'lookupModel') {

                    $fieldNames = (array) $field->relation->getDisplayFields(false);

                    $entity = $field->relation->relatedEntity;
                    $whereField = $field->name;

                    $fieldNames = array_values(array_unique($fieldNames));

                    [$criteriaFilters, $filterLogic] = $this->getSearchFilterInfo($fieldNames, $searchString, $searchStrings);

                    $result = $dqb->addFilters($entity, $criteriaFilters)->filterBuild($filterLogic)->get()->pluck('_id');

                    $dqb->resetFilters();

                    $result = ($result) ? $result->toArray() : null;

                    $query->orWhere(function ($q) use ($whereField, $result) {
                        $q->whereIn($whereField, $result);
                    });

                } elseif ($field->fieldType->name == 'picklist') {
                    $itemIds = picklist_items($field->listName)->filter(function ($item) use ($searchString) {
                        $str = str_replace('\\', '\\\\\\\\', $searchString);

                        return preg_match('!.*'.strtolower($str).'.*!', strtolower($item));
                    })->pluck('_id');

                    if ($itemIds) {
                        $query->orWhere(function ($q) use ($field, $itemIds) {
                            $q->whereIn($field->name, $itemIds);
                        });
                    }
                } else {
                    /* Old */
                    $str = '%'.$searchString.'%';
                    $query->orWhere($field->name, 'LIKE', $str);
                }
            }

            // Added by Hobert Mejia
            // September 03, 2021
            // Requested by Irish Bane Batacam
            // Add searching of relation
            if ($entityName == 'DefectReport') {
                $query->orWhereHas('rp', function ($subQuery) use ($searchString) {
                    $subQuery->where('rpNo', 'like', '%'.$searchString.'%');
                });
            }
        });

        return $builder;
    }

    protected function getSearchFilterInfo($fieldNames, $searchString)
    {
        $filters = [];
        $map = [];
        $filterLogic = '';
        $fieldNameCnt = count($fieldNames);

        $str = '%'.$searchString.'%';

        if ($fieldNameCnt == 1) {
            $filters[] = [
                $fieldNames[0], 'LIKE', $str,
            ];
        } else {

            foreach ($fieldNames as $fkey => $fieldName) {
                $map[$fkey] = [];

                $filters[1] = [
                    $fieldName, 'LIKE', $str,
                ];
                $map[$fkey][$searchString] = 1;

            }
        }
        $size = count($filters);
        foreach (range(1, $size) as $i) {
            if ($i > 1) {
                $filterLogic .= ' OR ';
            }
            $filterLogic .= $i;
        }

        return [$filters, $filterLogic];
    }

    public function checkSearchWithRequestFilter(&$builder, $filters, $fields)
    {
        $builder->where(function ($query) use ($filters, $fields) {
            foreach ($fields as $field) {
                $key = array_search($field->_id, array_column($filters, 'field_id'));
                $query->orWhere($field->name, 'LIKE', $filters[$key]['value']);
            }
        });
    }

    public function aggregateSearch()
    {

        //         $collectionFieldNames = ($this->module->hasViewFilter) ? $this->currentViewFilterFields->pluck('name')->toArray() : $this->fields->pluck('name')->toArray();
        //         $project = [];
        //         foreach ($collectionFieldNames as $cf) {
        //             $project[$cf] = '1';
        //         }

        //         $tfField = null;
        //         $firstMatch = [];
        //         $firstMatch[] = ['deleted_at' => null];
        //         if (request('type')) {
        //             $tfField = $this->fields->where('typeFilter', '!=', null)->first();
        //             $firstMatch[] = [$tfField->name => request('type')];
        //         }

        //         $ignorefilter = ($this->module->hasViewFilter && $this->currentViewFilter) ? $this->currentViewFilter->ignorePermission ?? false : false;
        //         $mainEntity = $this->entityRepository->where(['name' => $this->mainEntityName]);
        //         if (! $ignorefilter) {
        //             $field = $mainEntity->fields()->where('name', 'branch_id')->count();
        //             if ($field) {
        //                 $firstMatch[] = ['branch_id' => ['$in' => (array) $this->user->handled_branch_ids]];
        //             }
        //         }

        //         $searchFields = request('searchFields');
        //         if ($searchFields) {
        //             $searchFields = $this->fields->whereIn('_id', $searchFields);
        //         } else {
        //             $searchFields = $this->fields->where('searchKey', true);
        //         }

        //         $searchString = request('search');

        //         $searchAggreggate = [];
        //         $lookup = [];
        //         $hide = [];

        //         foreach ($searchFields as $sf) {
        //             $sf->load('fieldType');
        //             if ($sf->fieldType->name == 'picklist') {
        //                 $itemIds = $this->pickListRepository->getList($sf->listName)->listItems->filter(function ($item) use ($searchString) {
        //                     $str = str_replace('\\', '\\\\\\\\', $searchString);

        //                     return preg_match('!.*'.strtolower($str).'.*!', strtolower($item));
        //                 })->pluck('_id');

        //                 if ($itemIds) {
        //                     $searchAggreggate[] = [$sf->name => ['$in' => (array) $itemIds]];
        //                 }
        //             } elseif ($sf->fieldType->name == 'lookupModel') {

        //                 $fieldNames = (array) $sf->relation->getDisplayFields(false);
        //                 $entity = $sf->relation->relatedEntity;
        //                 $lookup[] = ['$lookup' => [
        //                     'from' => $entity->collection,
        //                     'localField' => $sf->name,
        //                     'foreignField' => ($sf->relation->local_key == '_id') ? 'oid' : $sf->relation->local_key,
        //                     'as' => $entity->collection,
        //                 ],
        //                 ];
        //                 $lookup[] = ['$unwind' => '$'.$entity->collection];

        //                 $hide[$entity->collection] = 0;

        //                 foreach ($fieldNames as $fn) {
        //                     $lookup[] = ['$addFields' => [$sf->name.'_'.$fn => '$'.$entity->collection.'.'.$fn]];
        //                     $searchAggreggate[] = [$sf->name.'_'.$fn => ['$regex' => $searchString, '$options' => 'i']];

        //                 }

        //             } else {
        //                 $searchAggreggate[] = [$sf->name => ['$regex' => $searchString, '$options' => 'i']];
        //             }
        //         }

        //         $aggregate = [];
        //         $aggregate[] = (count($firstMatch) > 1) ? ['$match' => ['$and' => $firstMatch]] : $firstMatch;

        //         foreach ($lookup as $lu) {
        //             $aggregate[] = $lu;
        //         }

        //         $aggregate[] = ['$project' => $hide];

        //         $aggregate[] =
        //             [
        //                 '$match' => ['$or' => $searchAggreggate],
        //             ];

        //         $aggregate[]['$limit'] = 25;
        //         $aggregate[] = ['$project' => ['account_id' => 1]];

        //         return SalesOpportunity::raw(function ($collection) use ($aggregate) {
        //             return $collection->aggregate($aggregate, ['allowDiskUse' => true]);
        //         });
        //     }

        //     public function tokenize($query)
        //     {
        //         $queries = explode('->', $query);
        //         foreach ($queries as $q) {
        //             $q = str_replace($this->mainEntityName.'::', '', $q);
        //             $q = str_replace('currentUser::', '$this->user->', $q);
        //             $q = str_replace('where(', '', $q);
        //             $q = str_replace(')', '', $q);
        //             $q = str_replace(' ', '', $q);
        //             $q = str_replace('"', '', $q);
        //             $filter[] = explode(',', $q);
        //         }

        //         return $filter;
    }

    public function isMultiSelect($rules)
    {
        return count(array_intersect(array_column($rules, 'name'), ['ms_dropdown', 'ms_list_view', 'checkbox_inline', 'checkbox', 'tab_multi_select', 'ms_pop_up'])) > 0;
    }
}
