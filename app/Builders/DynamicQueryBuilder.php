<?php

namespace App\Builders;

use App\Models\Core\Field;
use App\Services\FieldService;
use App\Services\PicklistService;
use App\Traits\QueryTrait;
use Illuminate\Support\Collection;
use Moloquent\Eloquent\Builder;
use Nette\Tokenizer\Tokenizer;


class DynamicQueryBuilder
{
    use QueryTrait;

    const INDEX_FIELD = 0;

    const INDEX_OPERATOR = 1;

    const INDEX_VALUE = 2;

    const T_PARENTHESIS = 1;

    const T_OPERATOR = 2;

    const T_OPERAND = 3;

    protected $entityRepository;

    protected $fieldRepository;

    protected $pickListRepository;

    protected $query;

    protected $lastEntity;

    protected $entities;

    protected $mainEntity;

    protected $selectedFields = '*';

    protected $entityClass;

    protected $afterQuery;

    protected $lastWhereEntity = null;

    protected $wheres = [];

    protected $orderBy = null;

    protected $entityClasses = [];

    protected $separator = '::';

    protected $queryVarNames;

    protected $returnBuilder = false;

    protected $limit = null;

    protected $filters = [];

    protected $filterLogic = null;

    protected $tokenizer;

    protected $user;

    public function __construct()
    {

        $this->entities = [];
        $this->queryVarNames = collect([]);

        $this->user = \Auth::guard('api')->user();

        $this->tokenizer = new Tokenizer([
            T_WHITESPACE => '\s+',
            self::T_PARENTHESIS => '\(|\)',
            self::T_OPERATOR => 'AND|OR',
            self::T_OPERAND => '[0-9]+',
        ]);
    }

    public function addFilters($entity, $filters = [], $addtlQuery = null)
    {

        if ($addtlQuery) {
            $this->filters = [$addtlQuery];
        }

        $this->mainEntity = (new EntityField)->resolveEntity($entity);
        foreach ($filters as $key => $filter) {
            if (is_array($filter) && count($filter) == 3) {

                $field = Field::where('entity_id', $this->mainEntity->_id)->where(function ($q) use ($filter) {
                    $q->where('_id', $filter[static::INDEX_FIELD])->orWhere('name', $filter[static::INDEX_FIELD]);
                })->first();
                $operator = (new PicklistService)->getOperators($filter[static::INDEX_OPERATOR]);

                if (valid_id($filter[static::INDEX_VALUE]) && $field->fieldType->name == 'lookupModel') {
                    $vField = $field->relation->relatedEntity->getModel()->find($filter[static::INDEX_VALUE]);
                    if (! $vField) {
                        $value = $filter[static::INDEX_VALUE];
                    } else {
                        $value = $vField->_id;
                    }
                } elseif (is_string($filter[static::INDEX_VALUE]) && $field->fieldType->name == 'picklist') {
                    if (valid_id($filter[static::INDEX_VALUE])) {
                        $value = (new PicklistService)->getItemById($field->listName, $filter[static::INDEX_VALUE]);
                        if (! $value) {
                            $value = $filter[static::INDEX_VALUE];
                        } else {
                            $value = $value->_id;
                        }
                    } else {
                        $value = (new PicklistService)->getIDs($field->listName, $filter[static::INDEX_VALUE]);
                        if (! $value) {
                            $value = $filter[static::INDEX_VALUE];
                        }
                    }
                } else {
                    $value = $filter[static::INDEX_VALUE];
                    if ($operator->value == 'in' && ! is_array($filter[static::INDEX_VALUE])) {
                        $operator->value = 'LIKE';
                        $value = '%'.$value.'%';
                    }
                }

                $this->filters[] = $this->createFilterWhere($field, $operator->value, $value);
            } else {
                throw new \Exception('Filter item '.($key + 1).' is not found in filters');
            }
        }

        return $this;
    }

    public function resetFilters()
    {
        $this->filters = [];
    }

    public function buildFilterWheres($logicString = null, $returnQuery = false)
    {
        $query = '';
        if ($logicString) {
            $tokens = $this->tokenizer->tokenize($logicString);
            foreach ($tokens as $token) {
                if ($token->type == self::T_PARENTHESIS) {
                    if ($token->value == '(') {
                        $query .= 'where( function($query) { $query->';
                    } else {
                        $query .= '; })';
                    }
                } elseif ($token->type == self::T_OPERAND) {
                    $operand = (int) $token->value;
                    $query .= $this->filters[$operand - 1];
                } elseif ($token->type == self::T_OPERATOR) {
                    if (strtolower($token->value) == 'or') {
                        $query .= '->or';
                    } else {
                        $query .= '->';
                    }
                }
            }
            $query .= '->';
        } else {
            foreach ($this->filters as $filter) {
                $query .= $filter.'->';
            }
        }

        $query = str_replace('orwhere', 'orWhere', $query);

        if (! $returnQuery) {
            return $query.';';
        } else {
            return substr($query, 0, -2).';';
        }
    }

    protected function extractWheres(Builder $builder)
    {
        //        preg_match('/where(.)*/', $builder->toSql(), $matches);
        // , $builder->getQuery()->getBindings()

        $matches = explode('where ', $builder->toSql(), 2);

        return [
            $matches[1],
            $builder->getQuery()->getBindings(),
        ];
    }

    public function filterBuild($logicString = null, $returnQuery = false, ?Builder $prependQuery = null)
    {

        $query = $this->buildFilterWheres($logicString, $returnQuery);

        $query = '$this->mainEntity->getModel()->'.$query;

        $query = str_replace('->;', ';', $query);
        $query = str_replace('->get()', '', $query);

        try {
            $query = eval('return '.$query.';');
        } catch (\Throwable $e) {
            dd('Error query', $query);
        }

        if ($prependQuery) {

            $query = $this->mergeQueries($query, $prependQuery);

            if (! $returnQuery) {
                $query = $query->get();
            }

            return $query;
        } else {
            return $query;
        }

    }

    public function buildRus($field, $idsOnly = false)
    {

        $result = $this->addFilters($field->rusEntity, $field->filters)
            ->filterBuild($field->filterLogic);

        if ($idsOnly) {
            $result = $result->pluck('_id');
        }

        return $result;
    }

    public function setUser($user)
    {
        (new EntityField)->setUser($user);
        $this->user = $user;

        return $this;
    }

    protected function createFilterWhere($field, $operator, $value)
    {

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            $value = '["'.implode('","', $value).'"]';
            $operator = 'in';
        } elseif ($field->fieldType->name == 'picklist' && ! valid_id($value)) {
            $value = (new PicklistService)->getIDs($field->listName, $value);
        } else {
            $value = (is_numeric($value) || in_array($value, ['true', 'false'])) ? $value : '"'.$value.'"';
        }

        if (! $value) {
            $value = 'null';
        }

        if ($operator == 'in') {
            return "whereIn('".$field->name."', ".$value.')';
        } elseif ($operator == 'not_in') {
            return "whereNotIn('".$field->name."', ".$value.')';
        } else {
            return "where('".$field->name."', '".$operator."', ".$value.')';
        }
    }

    public function selectFrom($fields, $entity, $returnBuilder = false, $limit = null)
    {
        $this->wheres = [];
        $this->entityClasses = [];

        $entity = (new EntityField)->resolveEntity($entity);

        if (is_array($fields)) {
            (new EntityField)->checkEntityFields($entity, $fields);
            $this->selectedFields = "['".implode("','", $fields)."']";
        } elseif (is_string($fields)) {
            if ($fields == '*') {
                // get all fields from the entity
                $this->selectedFields = "['*']";
            } else {
                (new EntityField)->checkEntityFields($entity, $fields);
                $this->selectedFields = [$fields];
            }
        }

        $this->mainEntity = $entity;

        $this->returnBuilder = $returnBuilder;

        $this->entityClasses[] = $entity->model_class;

        if ($limit) {
            $this->limit = $limit;
        }

        return $this;
    }

    public function filterGet($filterQuery, $returnQuery = null)
    {

        $rq = ($returnQuery) ? 'true' : '';

        if ($filterQuery) {
            $query = '$this->'.$filterQuery.'->getResult('.$rq.');';
        } else {
            $query = '$this->getResult('.$rq.');';
        }

        if (preg_match('/picklist/', $query)) {
            $query = str_replace('picklist', '$this->picklist', $query);
        }

        return eval('return '.$query);
    }

    /**
     * @param  string  $listName
     * @param  array  $values
     * @param  string  $key
     * @return mixed
     */
    public function picklist($listName, $values, $key = 'value')
    {
        return (new PicklistService)->getList($listName)->listItems()->whereIn($key, (array) $values)->pluck('_id')->toArray();
    }

    public function lookup($value, $entity, $fieldName)
    {
        $entity = (new EntityField)->resolveEntity($entity);
        $field = $entity->fields()->where('name', $fieldName)->first();
        if (! $field) {
            throw new \Exception('Error. The field named '.$fieldName.' is not found in entity '.$entity->name);
        }
        $result = $entity->getModel()->where($fieldName, $value)->pluck('_id');
        if ((new FieldService)->hasMultipleValues($field)) {
            return $result->get();
        } else {
            return $result->first();
        }
    }

    protected function resolveIfDeepField($fieldName, $prevEntity = null)
    {

        if (is_string($fieldName) && strpos($fieldName, '.') !== false) {

            if (! $prevEntity) {
                [$entityName, $fieldName] = explode('::', $fieldName, 2);
                $prevEntity = (new EntityField)->resolveEntity($entityName);
            }
            [$fieldName, $remToken] = explode('.', $fieldName, 2);

            $entityField = $prevEntity->getFieldsByType('lookupModel')->where('name', $fieldName)->first();
            if (! $entityField) {
                throw new \Exception('Error. field name '.$fieldName.' does not exist in entity '.$prevEntity->name);
            }

            $rEntity = $entityField->relation->relatedEntity;

            $operator = ((new FieldService)->hasMultipleValues($entityField) || $prevEntity->name != $rEntity->name) ? 'in' : '=';

            //            dd($prevEntity->name . '::' . $entityField->name, $operator, $rEntity->name . '::_id' );

            $this->where($prevEntity->name.'::'.$entityField->name, $operator, $rEntity->name.'::_id');

            if (strpos($remToken, '.') !== false) {
                return $this->resolveIfDeepField($remToken, $rEntity);
            }

            return $rEntity->name.'::'.$remToken;
        }

        return $fieldName;
    }

    public function where($field, $operator, $valueOrField)
    {

        $field = $this->resolveIfDeepField($field);

        if (is_string($valueOrField) && (starts_with($valueOrField, 'picklist') || starts_with($valueOrField, 'lookup'))) {
            $valueOrField = eval('return $this->'.$valueOrField.';');
        }

        if (is_array($valueOrField)) {
            $valueOrField = "['".implode("','", $valueOrField)."']";
        }

        $verifiableClass = [];
        $operand1 = (new EntityField)->extractEntityAndField($field, $this->separator);

        $className1 = $operand1['entity']->model_class;

        $verifiableClass[] = $className1;

        if ($this->isField($valueOrField)) {
            $operand2 = (new EntityField)->extractEntityAndField($valueOrField, $this->separator);

            if ($operand2['entity']->isCurrentUser) {

                $grpIds = '';
                // if (preg_match('/owner_id/', $field)) {
                //     $memberIds = $this->getPublicGroupMembers(true, $this->user);
                //     $grpIds = implode('","', $memberIds);
                //     if (strlen($grpIds)) {
                //         $operator = 'in';
                //     }
                // }

                if (is_array($operand2['field'])) {
                    $valueOrField = '["'.$grpIds.implode('","', collect($operand2['field'])->flatten()->toArray()).'"]';
                } elseif (is_string($operand2['field'])) {
                    $valueOrField = $operand2['field'];
                } else {
                    $valueOrField = strlen($grpIds) ? '["'.$grpIds.'","'.$operand2['entity']->{$operand2['field']['name']}.'"]' : $operand2['entity']->{$operand2['field']['name']};
                }
            } else {
                $className2 = $operand2['entity']->model_class;
                $verifiableClass[] = $className2;

                // Check if the order should be reversed
                $reverse = false;
                foreach ($this->entityClasses as $entityClass) {
                    if ($entityClass == $className1) {
                        $reverse = false;
                        break;
                    }
                    if ($entityClass == $className2) {
                        $reverse = true;
                        break;
                    }
                }
                if ($reverse) {
                    $temp = $operand1;
                    $operand1 = $operand2;
                    $operand2 = $temp;

                    $temp = $className1;
                    $className1 = $className2;
                    $className2 = $temp;
                }
            }
        }

        // Verify that any of the operands is in the resolved wheres or is the main entity
        $whereEntitiesValid = false;
        foreach ($verifiableClass as $class) {
            if (in_array($class, $this->entityClasses)) {
                $whereEntitiesValid = true;
                break;
            }
        }
        if (! $whereEntitiesValid) {
            throw new \Exception('The entities ('.implode(',', $verifiableClass).') in the where clause of your query are irrelevant to previous queries');
        }

        // Add the given entities to the list
        $this->entityClasses = array_unique(array_merge($this->entityClasses, $verifiableClass));

        $query = $this->resolveWhereOperator($operator, $operand1['field'], $valueOrField);

        if (strpos($query, 'function(') === false && $this->isField($valueOrField)) {
            $this->pushWhere($className1, $operand1['field']['name'], $query, $operator, $className2, $operand2['field']['name']);
        } else {
            if (! (new FieldService)->hasMultipleValues($operand1['field'])) {
                if (! starts_with($valueOrField, '[')) {
                    if (is_bool($valueOrField)) {
                        $query .= ''.(($valueOrField) ? 'true' : 'else').')';
                    } else {
                        $query .= "'".$valueOrField."')";
                    }
                } else {
                    $query .= $valueOrField.')';
                }
            }
            // Push closed where....

            $this->pushWhere($className1, $operand1['field']['name'], $query, $operator, 'LITERAL', null, true);
        }

        return $this;
    }

    public function orderBy($fieldName = 'updated_at', $order = 'desc')
    {
        (new EntityField)->checkEntityFields($this->mainEntity, $fieldName);
    }

    protected function pushWhere($operand1Class, $operand1Field, $whereQuery, $operator, $operand2Class, $operand2Value = null, $isComplete = false)
    {
        // If there isn't any where's yet with the specific entity, create new collection
        if (! array_key_exists($operand1Class, $this->wheres)) {
            $this->wheres[$operand1Class] = new Collection();
        }
        $whereItem = $this->createWhereItem($whereQuery, $operand1Field, $operator, $operand2Class, $operand2Value, $isComplete);

        $this->wheres[$operand1Class]->push($whereItem);
    }

    protected function createWhereItem($whereQuery, $operand1Field, $operator, $operand2Class, $operand2Value, $isComplete)
    {
        $whereItem = new \StdClass();

        if (is_string($operand2Value) && in_array($operand2Value, ['true', 'false'])) {
            $operand2Value = ($operand2Value == 'true');
        }

        $whereItem->whereQuery = $whereQuery;
        $whereItem->operand1Field = $operand1Field;
        $whereItem->operand2Class = $operand2Class;
        $whereItem->operator = $operator;
        $whereItem->operand2Value = $operand2Value;
        $whereItem->isComplete = $isComplete;

        return $whereItem;
    }

    protected function resolveWhereOperator($operator, $field, $otherField = null)
    {

        $strQuery = 'where';

        $fieldName = is_array($field) ? $field['name'] : (is_object($field) ? $field->name : $field);
        //if($field->hasMultipleValues()) {
        //    dd($fieldName, $operator, $otherField, $this->isField($otherField));
        //}
        if (is_object($field) && (new FieldService)->hasMultipleValues($field) && $otherField) {
            $prepend = '(';
            if (! $this->isField($otherField) && ! starts_with($otherField, '[')) {
                $otherField = '["'.$otherField.'"]';
            } elseif (starts_with($otherField, '[') && ends_with($otherField, ']')) {
                $operator = '=';
            } else {
                $entityField = (new EntityField)->extractEntityAndField($otherField);

                $otherField = $entityField['entity']->getModel()->pluck($entityField['field']->name)->toArray();
                if (count($otherField)) {
                    $otherField = '["'.implode('","', $otherField).'"]';
                } else {
                    $otherField = '[]';
                }

                $operator = '=';
            }
            $whereStr = 'orWhere';
            if (strpos($otherField, ',') === false) {
                $whereStr = 'where';
            }
            $strQuery = $strQuery.$prepend.'function($q) { foreach('.$otherField.' as $item) $q->'.$whereStr.'("'.$fieldName.'", "'.$operator.'", $item);} )';
        } elseif (in_array($operator, ['=', '!=', '<', '>', '<=', '>='])) {
            $strQuery .= "('".$fieldName."', '".$operator."', ";
        } elseif (strtolower($operator) == 'in') {
            $strQuery .= "In('".$fieldName."', ";
        } elseif (strtolower($operator) == 'not_in') {
            $strQuery .= "NotIn('".$fieldName."', ";
        }

        return $strQuery;
    }

    protected function isField($val)
    {
        return is_string($val) && strpos($val, '::') !== false;
    }

    protected function buildQuery()
    {

        $entityClasses = collect($this->entityClasses);
        $queryEntities = [];

        if (! count($this->wheres)) {
            $queries = $entityClasses->first();
            if ($this->returnBuilder) {
                $queries .= '::where("_id", "!=", null)';
            } else {
                $queries .= (($this->limit) ? '::paginate('.$this->limit.', ' : '::get(').$this->selectedFields.')';
            }

            return 'return '.$queries.';';
        }

        foreach ($entityClasses as $entity) {
            $entityQuery = new \StdClass();
            $entityQuery->startQuery = collect([]);
            $entityQuery->endQuery = '';
            $queryEntities[$entity] = $entityQuery;
        }
        $iterator = 0;
        do {
            // Get an entity from the stack
            $entity = $entityClasses->pop();
            $appendText = ($entity != $this->mainEntity->model_class && ! $this->returnBuilder) ? 'Query' : '';
            if (! array_key_exists($entity, $this->wheres)) {
                continue;
            }

            // If it's the entity's start of query
            if ($queryEntities[$entity]->startQuery->isEmpty()) {
                $startQuery = $entity.'::';    // Entity query starts with the entity class, appended with class property symbol (::)
            }

            foreach ($this->wheres[$entity] as $whereKey => $where) {

                // If whereQuery is complete
                if ($where->isComplete) {

                    if (ends_with($startQuery, '::')) {
                        $queryVarName = $this->generateQueryVarName($entity);
                        if (! $this->queryVarNames->contains($queryVarName)) {
                            $this->queryVarNames->push($queryVarName);
                        }
                        $startQuery = $queryVarName.' = '.$startQuery;

                        $queryEntities[$entity]->startQuery->push($startQuery);
                    }
                    // If where is first in entity query...
                    if ($queryEntities[$entity]->endQuery == '') {
                        $queryEntities[$entity]->endQuery .= $where->whereQuery;
                    } else {
                        $queryEntities[$entity]->endQuery = $where->whereQuery.'->'.$queryEntities[$entity]->endQuery;
                    }
                } else {
                    // if whereQuery is open yet it's the first to pop, close it
                    if ($iterator == 0) {
                        // Convert where operator to 'in'
                        if ($where->operator == '=') {
                            $this->convertWhereOperator($where, 'in');
                        } elseif ($where->operator == '!=') {
                            $this->convertWhereOperator($where, 'not_in');
                        }

                        if (ends_with($startQuery, '::')) {
                            $queryVarName = $this->generateQueryVarName($entity, $appendText);
                            if (! $this->queryVarNames->contains($queryVarName)) {
                                $this->queryVarNames->push($queryVarName);
                            }
                            $startQuery = $queryVarName.' = '.$startQuery;
                            $startQuery .= $where->whereQuery;
                        } else {
                            $startQuery .= '->'.$where->whereQuery;
                        }

                        $operand2Query = $where->operand2Class."::pluck('".$where->operand2Value."')->flatten()";

                        if ($where->operator != 'in' && $where->operator != 'not_in') {
                            $operand2Query .= 'first()';
                        }
                        $operand2Query .= ' )';

                        $startQuery .= $operand2Query;
                    } else {

                        if (ends_with($startQuery, '::')) {
                            $queryVarName = $this->generateQueryVarName($entity, $appendText);
                            if (! $this->queryVarNames->contains($queryVarName)) {
                                $this->queryVarNames->push($queryVarName);
                            }
                            $startQuery = $queryVarName.' = '.$startQuery;
                        }
                        // If where is first in entity query...
                        // transformToFirstIfOne
                        $queryVarName = $this->generateQueryVarName($where->operand2Class);
                        if (! $this->queryVarNames->contains($queryVarName)) {
                            $this->queryVarNames->push($queryVarName);
                        }

                        $whereQuery = $where->whereQuery.$queryVarName."->pluck('".$where->operand2Value."')->flatten()->toArray()";
                        // All of these operators cannot be used in an array result so force "first()"

                        if (in_array($where->operator, ['<', '>', '<=', '>='])) {
                            $whereQuery .= '->first()';
                        } elseif (in_array($where->operator, ['in', 'not_in'])) {    // mark pluck() method of whereIn and whereNotIn as non-appendable
                            $whereQuery .= ')';
                        } else {
                            $whereQuery .= ' )';
                        }

                        if ($queryEntities[$entity]->startQuery->isEmpty()) {
                            $startQuery .= $whereQuery;
                        } else {
                            $startQuery .= '->'.$whereQuery;
                        }
                        preg_match('/where\(\'[[:alpha:],_]*\', [\',\"]=[\',\"], \$[[:alpha:]]*Query\->pluck\(.*\)\->flatten\(\)\->toArray\(\)/', $startQuery, $matches);
                        if (count($matches)) {
                            $startQuery = str_replace("'=',", '', $startQuery);
                            $startQuery = str_replace('where', 'whereIn', $startQuery);
                        }

                        //                        if($entity == $this->mainEntity->model_class && $whereKey == 0 ) {
                        //                            if($queryEntities[$entity]->endQuery == '')
                        //                                $queryEntities[$entity]->endQuery .= '->';
                        //
                        //                            $queryEntities[$entity]->endQuery .= (($this->limit) ? 'paginate(' . $this->limit . ', '  :  'get(' ) . $this->selectedFields . ')';
                        //                        }
                    }
                    $queryEntities[$entity]->startQuery->push($startQuery);
                }

                if (! $this->returnBuilder && $entity == $this->mainEntity->model_class && count($this->wheres[$entity]) == $whereKey + 1) {
                    $queryEntities[$entity]->endQuery = $queryEntities[$entity]->endQuery.'->'.(($this->limit) ? 'paginate('.$this->limit.', ' : 'get(').$this->selectedFields.')';
                }

                $startQuery = '';
            }

            $iterator++;
        } while ($entityClasses->count());

        $queryEntities = collect($queryEntities);
        $queries = '';
        $iterator = 0;
        do {
            $queryEntity = $queryEntities->take(-1);
            $key = $queryEntity->keys()[0];
            $queryEntity = $queryEntity->first();
            $query = '';
            // if the collection is unnecessary
            if (! count($queryEntity->startQuery) && $queryEntity->endQuery == '') {
                $queryEntities->pop();

                continue;
            } else {    // If it is the bottom collection

                // If there is no startQuery, begin query with key (i.e., namespaced class name)...
                if (! count($queryEntity->startQuery)) {
                    $query .= $key;
                } else {
                    // It is expected that all startQueries are closed, so just append them all...
                    foreach ($queryEntity->startQuery as $startQuery) {
                        $query .= $startQuery;
                    }
                }
                // Then append endQuery
                if (! ends_with($query, '::') && ! ends_with($query, '->') && $queryEntity->endQuery != '' && ! starts_with($queryEntity->endQuery, '->')) {
                    $query .= '->';
                }
                $query .= $queryEntity->endQuery.'; ';

                if ($iterator) {   // if it is not the first subquery
                    $query = $this->transformToFirstIfOne($query, $queries);
                }

                $queries .= $query;
                $queryEntities->pop();
            }

            $iterator++;
        } while ($queryEntities->isNotEmpty());
        $queries .= 'return '.$this->queryVarNames->pop().';';

        return $queries;
    }

    protected function checkIfKeyIsId($id, $entityName)
    {
        // if it is an id and the key exists in the entity as a field, keep it, else return '_id'
        return ($id != '_id' && (ends_with($id, '_id') || ends_with($id, '_ids')) && (new EntityField)->fieldExistsInEntity($id, $entityName)) ? '_id' : $id;
    }

    protected function generateQueryVarName($className, $endsWith = 'Query')
    {
        return '$'.camel_case((new \ReflectionClass($className))->getShortName()).$endsWith;
    }

    protected function convertWhereOperator(&$where, $operator = 'in')
    {
        $where->operator = $operator;
        $where->whereQuery = $this->resolveWhereOperator($operator, $where->operand1Field);
    }

    protected function transformToFirstIfOne($queryString, $prependedQuery = '')
    {

        // extract pluck query
        $matches = [];
        preg_match('/\$[[:alpha:]]*Query\->pluck\(.*\)\->flatten\(\) /', $queryString, $matches);
        if (! count($matches)) {
            //            $value = @eval('return ' . $prependedQuery . $queryString . ";");
            return $queryString;
        } else {
            $testQuery = $matches[0];
        }

        eval($prependedQuery);
        $value = eval('return '.$testQuery.';');

        if (! $value || $value->isEmpty()) {
            throw new \Exception('Error. Query "'.$prependedQuery.$testQuery.'" results to null.');
        } else {
            if (count($value) == 1) {
                $queryString = str_replace($testQuery, trim($testQuery).'->first() ', $queryString);
            } else {
                // Transform query to whereIn if operator is '='
                $matches = [];
                preg_match('/where\(\'[a-zA-Z_]*\', \'=\', \$[[:alpha:]]*/', $queryString, $matches);
                if (count($matches)) {
                    foreach ($matches as $matched) {
                        $strMatched = str_replace("'=',", '', $matched);
                        $strMatched = str_replace('where', 'whereIn', $strMatched);
                        $queryString = str_replace($matched, $strMatched, $queryString);
                    }
                }
            }
        }

        return $queryString;
    }

    public function getResult($returnQuery = false)
    {

        $query = $this->buildQuery();
        //ddd($query);
        if ($returnQuery) {
            return $query;
        }

        $query = str_replace('::->', '::', $query);

        return eval($query);
    }

    // Dynamically replaces query element with parameter
    public function replaceQueryPatterns($filterQuery, $entity, $requireParams = true)
    {
        $matches = [];
        $fields = [];
        $item = null;
        //        preg_match('/(foo)(bar)(baz)/', 'foobarbaz', $matches);
        //        dd($matches);
        preg_match_all('/%[A-Za-z_\:\$]+%/', $filterQuery, $matches);
        $matches = collect($matches)->flatten()->toArray();
        if (count($matches)) {
            $entity = (new EntityField)->resolveEntity($entity);

            if ($requireParams && request('itemId', null)) {
                $itemId = request('itemId');
                $item = $entity->getModel()->find($itemId);
                if (! $item) {
                    throw new \Exception('Error. Unknown itemId '.$itemId.' in entity '.$entity->name);
                }
            }

            foreach ($matches as $match) {
                $fieldName = str_replace('%', '', $match);

                if ($requireParams) {
                    if ($item) {
                        $filterQuery = str_replace($match, $item->{$fieldName}, $filterQuery);
                    } else {
                        $param = request($fieldName);
                        if (! $param) {
                            // Resolve if wildcard is current user
                            if (starts_with($fieldName, '$currentUser::')) {
                                [$user, $userField] = explode('::', $fieldName);
                                $param = $this->user->{$userField};
                            } else {
                                $param = request($entity->name.'::'.$fieldName);
                            }
                        }

                        if ($param) {
                            if (is_string($param) && strpos((strtr($filterQuery, [' ' => ''])), ',"in",')) {
                                $param = explode(',', $param);
                            } //not the best solution :( -cha

                            if (is_array($param)) {
                                $param = "['".implode("' ,'", $param)."']";
                            }

                            $filterQuery = str_replace($match, $param, $filterQuery);
                        } else {
                            throw new \Exception('Error. Missing itemId or parameter. Expected field param for this lookup: '.$fieldName.'');
                        }
                    }
                }
            }

            $existingFields = $entity->fields()->whereIn('name', $fields)->pluck('name');
            $nonExisting = collect($fields)->diff($existingFields);
            if ($nonExisting->count()) {
                throw new \Exception('Error. The following fields in entity "'.$entity->name.'" are missing: '.$nonExisting->implode(','));
            }

            if (! $requireParams) {
                return $fields;
            }
        }

        return $filterQuery;
    }
}
