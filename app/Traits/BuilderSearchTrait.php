<?php

namespace App\Traits;

use App\Models\Customer\SalesOpportunity;
use App\Models\Customer\SalesQuote;
use Illuminate\Support\Facades\Input;

trait BuilderSearchTrait
{
    public function checkSearch(&$builder, $fields, $entityName = null)
    {

        // check if search string is defined
        $searchString = Input::get('search', null);

        if ($searchString && strlen(trim($searchString))) {
            $searchString = trim($searchString);

            //  dump($searchString);
            $searchStrings = [$searchString]; //explode(' ', $searchString);

            //   dump($builder->toSql());

            $builder = $builder->where(function ($query) use ($fields, $searchString, $searchStrings, $entityName) {
                foreach ($fields as $field) {

                    if ($field->fieldType->name == 'lookupModel') { // || $field->groupWith
                        //if($field->fieldType->name == 'lookupModel') {

                        // if (in_array($field->entity->name,['ServiceSchedule', 'ServiceJob', 'BreakdownLog']) && in_array($field->name,['account_id', 'end_user_id'])) {
                        //     $fieldNames = (array) $field->relation->getDisplayFields(false);
                        // } elseif ($field->entity->name == 'SalesOpportunity' || $field->entity->name == 'SalesQuote') {
                        //     $fieldNames = (array) $field->relation->getDisplayFields(false);
                        // } else
                        $fieldNames = (array) $field->relation->getDisplayFields(false);

                        $entity = $field->relation->relatedEntity;
                        $whereField = $field->name;
                        // }
                        // else {
                        //     $fieldNames = array_merge((array)$field->name, array_values($field->groupWith));
                        //     $entity = $field->entity;
                        //     $whereField = '_id';
                        // }
                        $fieldNames = array_values(array_unique($fieldNames));

                        [$criteriaFilters, $filterLogic] = $this->getSearchFilterInfo($fieldNames, $searchString, $searchStrings);

                        $result = $this->dqBuilder->addFilters($entity, $criteriaFilters)->filterBuild($filterLogic)->get()->pluck('_id');

                        $this->dqBuilder->resetFilters();

                        $result = ($result) ? $result->toArray() : null;

                        //if ($result && count($result)) {
                        $query->orWhere(function ($q) use ($whereField, $result) {
                            $q->whereIn($whereField, $result);
                        });
                        // }

                    } elseif ($field->fieldType->name == 'picklist') {
                        $itemIds = $this->pickListRepository->getList($field->listName)->listItems->filter(function ($item) use ($searchString) {
                            $str = str_replace('\\', '\\\\\\\\', $searchString);

                            // if (substr($str, -1) == '+')
                            //     $str = substr($str, 1, str_len($str)-1);
                            //
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
        }
    }

    protected function getSearchFilterInfo($fieldNames, $searchString, $searchStrings)
    {
        $filters = [];
        $map = [];
        $filterLogic = '';
        $fieldNameCnt = count($fieldNames);
        $searchStringsCnt = count($searchStrings);

        $str = '%'.$searchString.'%';

        if ($fieldNameCnt == 1) {
            $filters[] = [
                $fieldNames[0], 'LIKE', $str,
            ];
        } else {
            $ctr = 0;
            foreach ($fieldNames as $fkey => $fieldName) {
                $map[$fkey] = [];
                foreach ($searchStrings as $skey => $string) {
                    $filters[$ctr++] = [
                        $fieldName, 'LIKE', $str,
                    ];
                    $map[$fkey][$skey] = $ctr;
                }
            }
        }
        $size = count($filters);
        if (count($searchStrings) == 1 || count($fieldNames) == 1) {
            foreach (range(1, $size) as $i) {
                if ($i > 1) {
                    $filterLogic .= ' OR ';
                }

                $filterLogic .= $i;
            }
        } elseif (count($map)) {
            foreach ($map as $fkey => $mapItem) {
                foreach ($mapItem as $skey => $filterNum) {
                    $filterLogic .= '( '.$filterNum.' AND ';
                    $y = $fkey;
                    $x = $skey;
                    for ($i = 1; $i < $searchStringsCnt; $i++) {
                        $y++;
                        if ($y > $fieldNameCnt - 1) {
                            $y = 0;
                        }
                        $x++;
                        if ($x > $searchStringsCnt - 1) {
                            $x = 0;
                        }

                        $filterLogic .= $map[$y][$x];

                        if ($i != $searchStringsCnt - 1) {
                            $filterLogic .= ' AND ';
                        }
                    }
                    $filterLogic .= ' )';

                    if ($skey != $searchStringsCnt - 1) {
                        $filterLogic .= ' OR ';
                    }
                }

                if ($fkey != $fieldNameCnt - 1) {
                    $filterLogic .= ' OR ';
                }
            }
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

        $collectionFieldNames = ($this->module->hasViewFilter) ? $this->currentViewFilterFields->pluck('name')->toArray() : $this->fields->pluck('name')->toArray();
        $project = [];
        foreach ($collectionFieldNames as $cf) {
            $project[$cf] = '1';
        }

        $tfField = null;
        $firstMatch = [];
        $firstMatch[] = ['deleted_at' => null];
        if (Input::get('type')) {
            $tfField = $this->fields->where('typeFilter', '!=', null)->first();
            $firstMatch[] = [$tfField->name => Input::get('type')];
        }

        $ignorefilter = ($this->module->hasViewFilter && $this->currentViewFilter) ? $this->currentViewFilter->ignorePermission ?? false : false;
        $mainEntity = $this->entityRepository->where(['name' => $this->mainEntityName]);
        if (! $ignorefilter) {
            $field = $mainEntity->fields()->where('name', 'branch_id')->count();
            if ($field) {
                $firstMatch[] = ['branch_id' => ['$in' => (array) $this->user->handled_branch_ids]];
            }
        }

        $searchFields = Input::get('searchFields');
        if ($searchFields) {
            $searchFields = $this->fields->whereIn('_id', $searchFields);
        } else {
            $searchFields = $this->fields->where('searchKey', true);
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
                $lookup[] = ['$lookup' => [
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

    public function tokenize($query)
    {
        $queries = explode('->', $query);
        foreach ($queries as $q) {
            $q = str_replace($this->mainEntityName.'::', '', $q);
            $q = str_replace('currentUser::', '$this->user->', $q);
            $q = str_replace('where(', '', $q);
            $q = str_replace(')', '', $q);
            $q = str_replace(' ', '', $q);
            $q = str_replace('"', '', $q);
            $filter[] = explode(',', $q);
        }

        return $filter;
    }

    public function isMultiSelect($rules)
    {
        return count(array_intersect(array_column($rules, 'name'), ['ms_dropdown', 'ms_list_view', 'checkbox_inline', 'checkbox', 'tab_multi_select', 'ms_pop_up'])) > 0;
    }
}
