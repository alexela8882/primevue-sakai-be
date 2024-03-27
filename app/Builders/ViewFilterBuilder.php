<?php

namespace App\Builders;

use App\Facades\RoleAccess;
use App\Models\Core\ViewFilter;
use App\Models\Module\Module;
use App\Services\PicklistService;
use Illuminate\Http\Request;

class ViewFilterBuilder
{
    protected $viewFilterRepository;

    protected $moduleRepository;

    protected $entityRepository;

    protected $userRepository;

    protected $fieldRepository;

    protected $currentViewFilter;

    protected $builds = [];

    protected $currentEntityName;

    protected $currentModule;

    protected $currentEntity;

    protected $operators;

    public function __construct(private PicklistService $picklistService)
    {

        $this->operators = $this->picklistService->getListItems('filter_operators');
    }

    public function on($moduleName)
    {

        $this->reset(false);

        $this->currentModule = Module::with('queries')->where('name', $moduleName)->first();
        if (! $this->currentModule) {
            throw new \Exception('Error. Unknown module named "'.$moduleName.'"');
        }

        $entity = $this->currentModule->entity;

        if (! $entity) {
            throw new \Exception('Error. Cannot add view filter on a module that has no main entity');
        }

        $this->currentEntity = $entity;
        $this->currentEntityName = $entity->name;

        return $this;
    }

    public function add($name, $fields, $isDefault = false, $owner = null, $isFieldId = false)
    {

        if ($this->currentViewFilter) {
            $this->builds[] = $this->verifyAndGetCurrentViewFilter();
            $this->reset(true);
        }

        if ($owner && strtolower($owner) != 'default') {
            if (! $this->userRepository->find($owner)) {
                throw new \Exception('Error. Unknown user with id: '.$owner);
            }
        }

        $key = $isFieldId ? '_id' : 'name';

        $fieldIds = $this->currentEntity->fields()->whereIn($key, $fields)->pluck($key)->toArray();
        $nonExisting = array_diff($fields, $fieldIds);
        if (count($nonExisting)) {
            throw new \Exception('The following field Ids do not exist: '.implode(',', $nonExisting));
        }

        $fieldIds = $this->currentEntity->fields()->whereIn($key, $fields)->pluck('_id')->toArray();

        $this->currentViewFilter['filterName'] = $name;
        $this->currentViewFilter['moduleName'] = $this->currentModule->name;
        $this->currentViewFilter['fields'] = $fieldIds;
        $this->currentViewFilter['isDefault'] = ($isDefault === true);
        $this->currentViewFilter['owner'] = $owner ?? 'default';

        return $this;
    }

    public function query($query, $sortField = null, $sortOrder = 'ASC', $queryField = 'name')
    {

        if (is_string($query) || is_array($query) && count($query)) {
            $moduleQuery = $this->currentModule->queries->where($queryField, $query)->first();
            if (! $moduleQuery) {
                throw new \Exception('Error. Unknown module query '.$query);
            }

            $this->currentViewFilter['query_id'] = $moduleQuery->_id;

        }
        if ($sortField) {
            $this->sortOrder($sortField, $sortOrder);
        }

        return $this;
    }

    public function ofType($typeName, $key = null)
    {
        if (! $key) {
            $key = strtolower($this->currentEntityName).'_type';
        }

        $this->currentViewFilter[$key] = $typeName;

        return $this;
    }

    public function sortOrder($sortField, $sortOrder)
    {
        $this->currentViewFilter['sortField'] = $sortField;
        $this->currentViewFilter['sortOrder'] = $sortOrder;

        return $this;
    }

    public function filters(array $filters, $logicString = null, $opKey = 'value', $valKey = null)
    {
        $filterValues = [];
        foreach ($filters as $key => $filter) {
            $filterValues[] = $this->checkFilter($filter, $opKey, $valKey);
        }

        $this->currentViewFilter['filters'] = $filterValues;
        $this->currentViewFilter['filterLogic'] = is_string($logicString) ? $logicString : null;

        return $this;
    }

    protected function checkFilter($filter, $opKey = null, $valKey = null)
    {
        if (is_object($filter)) {
            $filter = $filter->toArray();
        }

        if (count($filter) != 3) {
            throw new \Exception('Error. Invalid filter: ['.implode(',', $filter).']');
        }

        $field = Field::where('entity_id', $this->currentEntity->_id)->where(function ($q) use ($filter) {
            $q->where('_id', $filter[0])->orWhere('name', $filter[0]);
        })->first();

        $opKey = $opKey ?: '_id';
        $operator = $this->operators->where($opKey, $filter[1])->first();
        if (! $operator) {
            throw new \Exception('Error. Operator idetifier '.$filter[1].' unrecognized');
        }

        $value = $filter[2];
        if ($field->fieldType->name == 'lookupModel') {
            $valKey = $valKey ?: '_id';
            $value = $field->relation->entity->getModel()->whereIn($valKey, $filter[2])->pluck('_id')->toArray();
        }

        return [
            $field->_id,
            $operator->_id,
            $value,
        ];

    }

    protected function reset($currentOnly = true)
    {
        $this->currentViewFilter = null;
        if (! $currentOnly) {
            $this->currentModule = null;
            $this->currentEntityName = null;
            $this->builds = [];
        }
    }

    protected function verifyAndGetCurrentViewFilter()
    {
        $requiredKeys = ['filterName', 'moduleName', 'fields', 'isDefault'];
        $missingKeys = array_diff($requiredKeys, array_keys($this->currentViewFilter));
        if (count($missingKeys)) {
            throw new \Exception('Error. Missing required viewFilter fields ['.implode(',', $missingKeys).']. Method add is probably not called');
        }

        $this->currentViewFilter = array_merge([
            'query_id' => null,
            'sortField' => null,
            'sortOrder' => null,
            'filters' => [],
            'filterLogic' => null,
        ], $this->currentViewFilter);

        return new ViewFilter($this->currentViewFilter);
    }

    public function save()
    {
        if ($this->currentViewFilter) {
            $this->builds[] = $this->verifyAndGetCurrentViewFilter();
        }

        $this->currentModule->viewFilters()->saveMany($this->builds);
    }

    public function executeThruRequest(Request $request, $userId, $id = null)
    {

        if (! $id) {
            $entity = $this->moduleRepository->getMain($request['moduleName'], 'name');

            $this->on($request['moduleName'], $entity);

            $this->add($request['filterName'], $request['fields'], $request['isDefault'], $userId, true)
                ->query($request['query_id'], $request['sortField'], $request['sortOrder'], '_id');

            if (count($request['filters'])) {
                $this->filters($request['filters'], $request['filterLogic'], '_id');
            }

            if ($request['isDefault'] === true || $request['isDefault'] === 'true') {
                $this->viewFilterRepository->getModel()->where(['owner' => $userId, 'moduleName' => $request['moduleName']])->update(['isDefault' => false]);
            }

            $this->save();

            return $this->viewFilterRepository->getModel()->where(['filterName' => $request['filterName'], 'owner' => $userId])->first();
        } else {
            $curViewFilter = $this->viewFilterRepository->find($id);
            if (! $curViewFilter) {
                throw new \Exception('Error. Unknown view filter with id '.$id);
            }

            $queryId = $request['query_id'];

            if ($userId != 'default') {
                $userModuleQueries = RoleAccess::getUserModuleQueries($userId, $curViewFilter->moduleName.'.index');
                if ($queryId && count($userModuleQueries) && ! in_array($queryId, $userId)) {
                    throw new \Exception('Error. Unknown query id '.$queryId);
                }
            }

            $sortField = $request['sortField'];
            if ($sortField) {
                $sField = $curViewFilter->module->main->fields()->where('name', $sortField)->first();
                if (! $sField) {
                    throw new \Exception('Error. Unknown field "'.$sortField.'" for sorting');
                }
            }

            $isDefault = ($request['isDefault'] === true || $request['isDefault'] === 'true');
            $data = [
                'filterName' => $request['filterName'],
                'fields' => $request['fields'],
                'owner' => $userId,
                'query_id' => $queryId,
                'isDefault' => ($request['isDefault'] === true || $request['isDefault'] === 'true'),
                'sortField' => $sortField,
                'sortOrder' => in_array($request['sortOrder'], ['ASC', 'DESC']) ? $request['sortOrder'] : null,
                'filters' => $request['filters'],
                'filterLogic' => $request['filterLogic'],
            ];

            if (! $curViewFilter->isDefault && $isDefault) {
                $this->viewFilterRepository->getModel()->where(['owner' => $userId, 'moduleName' => $request['moduleName']])->where('_id', '!=', $curViewFilter)->update(['isDefault' => false]);
            }

            $curViewFilter->update($data);

            return $curViewFilter;
        }

    }

    public function deleteByModule($moduleName)
    {
        $this->viewFilterRepository->getModel()->where('moduleName', $moduleName)->delete();
    }
}
