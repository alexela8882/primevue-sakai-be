<?php

namespace App\Builders;

use App\Models\Core\Entity;
use App\Models\Core\Field;
use App\Models\Core\FieldType;
use App\Models\Core\Relation;
use App\Services\PicklistService;

abstract class BaseFieldBuilder
{
    /**
     * $entity = entity where field will be added
     * $relatedEntity = the other entity where the above entity has a relationship
     */
    protected $entity;

    protected $entityModel;

    protected $entityName = '';

    protected $type = null;

    protected $relatedEntity;

    protected $relationshipMethod;

    protected $field;

    protected $relation;

    protected $fieldRelatable = false;

    protected $pickList = null;

    protected $lookupDisplayField = null;

    protected $lastMethodCalled;

    protected $defaultValue = [];

    protected $list = [];

    protected $inRange = [];

    protected $displayFields = null;

    protected $extraInfo = null;

    protected $dqBuilder;

    protected $formulaParser;

    /**
     * Class names used for testing in 'instanceof'
     */
    protected $entityClass;

    protected $fieldClass;

    protected $relationClass;

    protected $fieldTypeClass;

    protected $rules = [];

    protected $holdRules = [];

    protected $fileTypes = [];

    protected $imageTypes = [];

    protected static $rusTypes = ['count', 'sum', 'min', 'max'];

    public function __construct()
    {

        // $this->fileTypes = pickli->getList('fileTypes');
        // $this->imageTypes = (new PicklistService)->getList('imageTypes');

    }

    public function on($entity)
    {

        $this->checkLastMethodCalled('', 'on', "Method 'on' should be called before all other methods");

        $this->relatedEntity = null;

        $this->type = null;

        $this->pickList = null;

        $this->defaultValue = [];

        $this->displayFields = null;

        $this->list = [];

        $this->rules = [];

        $this->entity = $this->resolveEntity($entity, true);

        return $this;
    }

    public function add($type, $fieldName, $label = null, $uniqueName = null)
    {

        if ($this->lastMethodCalled != 'inRangeWith') {
            $this->checkLastMethodCalled('on', 'add', "Method 'on' should be called before 'add' method");
            $this->inRange = [];
            $this->holdRules = [];
        } else {
            $this->lastMethodCalled = 'add';
        }

        if (Field::where('name', $fieldName)->where('entity_id', $this->entity->_id)->first()) {
            throw new \Exception('Error. Cannot add field named "'.$fieldName.'" on entity "'.$this->entity->name.'". Field already exists.');
        }

        $this->field = $this->resolveField($fieldName, $type, $label, $uniqueName);

        if ($this->type->name == 'autonumber') {
            $this->setFieldAttribute('format', '{0}');
        }

        return $this;
    }

    public function onlyWithin($entityName, $values, $key = '_id')
    {
        $availableEntities = [
            'Branch' => ['prop' => 'managedbranches', 'key' => '_id'],
            'Country' => ['prop' => 'managedbranches', 'key' => 'country_id'],
        ];
        if (in_array($entityName, array_keys($availableEntities))) {
            $entity = Entity::where('name', $entityName)->first();
            if (is_array($values)) {
                $itemId = $entity->getModel()->whereIn($key, $values)->pluck('_id')->toArray();
            } else {
                $itemId = (array) $entity->getModel()->where($key, $values)->first()->_id;
            }
            $this->setFieldAttribute('onlyWithin', ['values' => $itemId, 'reference' => $availableEntities[$entityName]]);
        } else {
            throw new \Exception('Invalid entity named "'.$entityName.'" for method onlyWithin.');
        }

        return $this;
    }

    /**
     * @param  string|array  $attributeOrAttributes
     * @return $this
     */
    public function setFieldAttribute($attributeOrAttributes, $value = null)
    {
        if ($this->field) {
            if (is_array($attributeOrAttributes)) {
                foreach ($attributeOrAttributes as $attribute => $value) {
                    $this->field->{$attribute} = $value;
                }
            } else {
                $this->field->{$attributeOrAttributes} = $value;
            }
        }

        return $this;
    }

    public function controls($fieldName)
    {
        $this->field->controls = $fieldName;

        return $this;
    }

    public function relate($relationshipMethod)
    {

        $this->lastMethodCalled = 'relate';

        $this->relationshipMethod = checkRelationshipMethod($relationshipMethod);

        return $this;
    }

    /**
     * @param  string|array  $displayFieldName
     * @param  null  $foreignKey
     * @param  null  $otherKey
     * @param  string  $glue
     * @return $this
     *
     * @throws \Exception
     */
    public function to($entity, $displayFieldName, $foreignKey = null, $otherKey = null, $glue = ' ')
    {

        $this->checkLastMethodCalled('relate', 'to', "Method 'to' should be called after 'relate' method");

        // field type must be relatable
        if ($this->fieldRelatable) {
            $displayFieldName = (array) $displayFieldName;
            $popupDisplayFieldName = null;
            $this->displayFields = null;
            $this->relatedEntity = $this->resolveEntity($entity);

            $this->displayFields = (array) $displayFieldName;

            $relatedEntityFieldNames = $this->relatedEntity->fields->pluck('name')->toArray();

            if (array_depth($displayFieldName) > 1) {
                $popupDisplayFieldName = $displayFieldName[1];
                $unknownFieldNames = array_diff($popupDisplayFieldName, $relatedEntityFieldNames);
                if (count($unknownFieldNames) && $this->relationshipMethod != 'belongsToMany') {
                    throw new \Exception('Error. The following field/s named in entity "'.$entity."' are not found: ".implode(', ', $unknownFieldNames));
                }
                $displayFieldName = $displayFieldName[0];
                $this->displayFields = $popupDisplayFieldName;
            } else {
                $unknownFieldNames = array_diff($displayFieldName, $relatedEntityFieldNames);
                if (count($unknownFieldNames) && $this->relationshipMethod != 'belongsToMany') {
                    throw new \Exception('Error. The following field/s named in entity "'.$entity."' are not found: ".implode(', ', $unknownFieldNames));
                }
            }

            $className = $this->relatedEntity->model_class;

            if (! $foreignKey) {
                if ($this->relationshipMethod == 'belongsToMany') {
                    $foreignKey = snake_case(class_basename($this->entity->model_class).'_ids');
                } else {
                    $foreignKey = snake_case(idify($this->field->name));
                }
            }

            if (! $otherKey) {
                if ($this->relationshipMethod == 'belongsToMany') {
                    $otherKey = $this->field->name;
                } else {
                    $otherKey = '_id';
                }
            }

            // create field relations
            $relationData = [
                'method' => $this->relationshipMethod,
                'class' => $className,
                'displayFieldName' => $displayFieldName,
                'foreign_key' => $foreignKey,
                'local_key' => $otherKey,
                'entity_id' => $this->relatedEntity->_id,
            ];
            if ($popupDisplayFieldName) {
                $relationData['popupDisplayFieldName'] = $popupDisplayFieldName;
            }
            if ($this->extraInfo) {
                $relationData['extraInfo'] = $this->extraInfo;
            }

            $this->glue($glue);
            $this->relation = new Relation($relationData);
        } else {
            throw new \Exception('Error. Either relating field is not yet defined or its type is not relatable');
        }

        return $this;
    }

    public function glue($glue)
    {
        $this->setFieldAttribute('fieldGlue', $glue);
    }

    public function format($format)
    {

        if ($this->type->name == 'autonumber') {
            $this->setFieldAttribute('format', $format);
        } else {
            throw new \Exception('Error. Format attribute is only applicable for fields with autonumber fieldtype.');
        }

        return $this;
    }

    public function includeFields($fieldNames)
    {

        if (! $this->relatedEntity) {
            throw new \Exception('Error. Cannot call includeField method with defining relation');
        }

        $relatedEntityFieldNames = $this->relatedEntity->fields->pluck('name')->toArray();
        $unknownFieldNames = array_diff((array) $fieldNames, $relatedEntityFieldNames);
        if (count($unknownFieldNames) && $this->relationshipMethod != 'belongsToMany') {
            throw new \Exception('Error. The following field/s named in entity "'.$this->relatedEntity->name."' are not found: ".implode(', ', $unknownFieldNames));
        }

        $this->setFieldAttribute('extraInfo', (array) $fieldNames);

        return $this;
    }

    public function checkControllingField($controllingField)
    {
        if (strrpos($controllingField, '::') !== false) {
            $fDetails = explode('::', $controllingField);
            $entity = $this->entity->getModel()->where('name', $fDetails[0])->first();
            if (! $entity) {
                throw new \Exception('Entity named '.$fDetails[0].' does not exist');
            }
            $con = $entity->connectedEntities()->where('name', $this->entityName)->first();
            if (! $con) {
                throw new \Exception("Error. Entity '".$fDetails[0]."' is not connected to entity ".$this->entity->name.'.');
            }

            $controllingField = $fDetails[1];
            $con = $this->entity->getByname($fDetails[0]);
        } else {
            $con = $this->entity;
        }

        $cFieldDetails = $con->fields()->where('name', $controllingField)->first();
        if (! $cFieldDetails) {
            throw new \Exception('Field named "'.$controllingField.'" is not found on entity "'.$con->name.'"');
        }

        return $cFieldDetails;
    }

    protected function getControllingField($controllingField)
    {
        $cFieldDetails = $this->checkControllingField($controllingField);
        if ($cFieldDetails->fieldType->name != 'picklist') {
            throw new \Exception('Error. Controlling field of picklist must only be of picklist type.');
        }

        return $cFieldDetails;
    }

    public function enum($listName, $list = [], $controllingField = null, $catSrcListName = null, $useIfExisting = true)
    {

        $flatItems = [];

        $currentList = (new PicklistService)->getList($listName, false);
        $cFieldDetails = $controllingField ? $this->getControllingField($controllingField) : null;

        if ($currentList) {
            if (! $useIfExisting) {
                throw new \Exception('Error. Cannot add Picklist named "'.$listName."'. Data already exists.");
            }

            if ($cFieldDetails && $cFieldDetails->listName != $currentList->catSrcListName) {
                throw new \Exception('PickList with list name "'.$listName.'" did not match controlling field category source list name');
            }

            if ($cFieldDetails && $cFieldDetails->listName != $currentList->catSrcListName) {
                throw new \Exception('PickList with list name "'.$listName.'" did not match controlling field category source list name');
            }

            $this->pickList = $currentList->_id;
            if ($cFieldDetails && $cFieldDetails->controls) {
                throw new \Exception('The set controlling field for "'.$cFieldDetails->name.'" is already binded to another field.');
            }

            $this->list = array_column(is_array($currentList->items) ? $currentList->items : $currentList->items->toArray(), 'value');
            /* Added February 02, 2020 - HSM */
            if ($cFieldDetails) {
                $this->rules('filtered_by', $controllingField);
                $catSrcListName = $cFieldDetails->listName;
            }
            /* End  */
        } elseif (! count($list)) {
            throw new \Exception('Error. No items defined for Picklist named "'.$listName.'"');
        } else {

            $this->list = [];

            if (array_depth($list) == 2) {
                if (! $controllingField && ! $catSrcListName) {
                    throw new \Exception('Error. Categorized field requires category source list name. Either define the list name or define a controlling field');
                } elseif ($cFieldDetails) {
                    $this->rules('filtered_by', $controllingField);
                    $catSrcListName = $cFieldDetails->listName;
                }

                $ids = (new PicklistService)->getListItems($catSrcListName, true, true, true);

                foreach ($list as $category_key => $items) {

                    if (! isset($ids[$category_key])) {
                        throw new \Exception('Error. "'.$category_key.'" is not found on list named "'.$catSrcListName.'"');
                    }

                    foreach ($items as $item) {

                        $flatItems[] = ['value' => $item, 'category_key' => $ids[$category_key]];
                        $this->list[] = $item;
                    }
                }
                $list = $flatItems;
            } else {
                $this->list = $list;
            }

            // if no list is enumerated, it is assumed that the list already exists
            $this->checkLastMethodCalled('add', 'enum', "Method 'enum' should be called after 'add' method");

            if ($this->type->name != 'picklist') {
                throw new \Exception("Syntax error. Method 'enum' can only be used for fields with type 'picklist'");
            }

            $this->pickList = (new PicklistService)->buildList($listName, $list, true, $catSrcListName);
        }

        $this->field->listName = $listName;

        return $this;
    }

    public function summarize($summarizedEntity, $rusType, $aggregateFieldName = null, $filters = null, $filterLogic = null, $entityKey = 'name')
    {

        if ($this->type->name != 'rollUpSummary') {
            throw new \Exception("Syntax error. Method 'summarize' can only be used for fields with type 'rollUpSummary'");
        }

        $entity = $this->entity->connectedEntities()->where('name', $summarizedEntity)->first();
        if (! $entity) {
            throw new \Exception('Error. Entity "'.$this->entityName.'" does not have a connected entity named "'.$summarizedEntity.'"');
        }

        $fieldData = [];
        if (! in_array($rusType, static::$rusTypes)) {
            throw new \Exception('Error. Invalid roll-up summary type "'.$rusType.'". Choose only among the following: '.implode(',', static::$rusTypes));
        } elseif ($rusType != 'count') {
            $fieldData = ['aggregateField' => $aggregateFieldName];

            $aggregateField = $entity->fields()->where('name', $aggregateFieldName)->first();
            if (! $aggregateField) {
                throw new \Exception('Error. Unknown field named "'.$aggregateFieldName.'" in entity "'.$entity->name.'"');
            }

            $type = $aggregateField->fieldType->name;

            if ($type == 'date' && ! in_array($rusType, ['sum', 'min']) || ! in_array($type, ['number', 'currency', 'percentage', 'formula', 'rollUpSummary']) || ($type == 'formula' && ! in_array($aggregateField->formulaType, ['number', 'currency', 'percentage', 'rollUpSummary']))) {
                throw new \Exception('Error. Invalid roll-up summary type '.$rusType.' for aggregate field with type '.$type);
            }
        }

        if ($filters) {
            $this->dqBuilder->addFilters($entity, $filters)->filterBuild($filterLogic);
        }

        $data = [
            'rusEntity' => $entity->name,
            'rusType' => $rusType,
            'filters' => $filters,
            'filterLogic' => $filterLogic,
        ];

        $this->setFieldAttribute(array_merge($data, (array) $fieldData));

        return $this;
    }

    public function hasRUS()
    {
        if ($this->type->name != 'formula') {
            throw new \Exception("Syntax error. Method 'formulize' can only be used for fields with type 'formula'");
        }
        $this->setFieldAttribute(['hasRUS' => true]);

        return $this;
    }

    public function connectingField($field)
    {
        if ($this->type->name != 'rollUpSummary') {
            throw new \Exception("Syntax error. Method 'connectingField' can only be used for fields with type 'rollUpSummary'");
        }
        $this->setFieldAttribute(['connectingField' => $field]);

        return $this;
    }

    public function convertedField($field)
    {
        if ($this->type->name != 'currency' && $this->type->name != 'formula' && $this->type->name != 'rollUpSummary') {
            throw new \Exception("Syntax error. Method 'convertedField' can only be used for fields with type 'currency'");
        }
        $this->setFieldAttribute(['convertedField' => $field]);

        return $this;
    }

    public function formulize($formulaType, $strFormula, $decimalPlace = null)
    {

        if ($this->type->name != 'formula') {
            throw new \Exception("Syntax error. Method 'formulize' can only be used for fields with type 'formula'");
        }

        $returnType = $this->type->formulaReturnTypes()->where('name', $formulaType)->first();
        if (! $returnType) {
            throw new \Exception('Error. Invalid formula type "'.$formulaType.'" for field '.$this->field->name);
        }

        $data = [
            'formulaType' => $formulaType,
            'formulaExpression' => $strFormula,
        ];

        if ($decimalPlace && ! $returnType->isNumeric()) {
            throw new \Exception('Error. You cannot set decimal places for formula with type '.$formulaType);
        }
        if ($returnType->isNumeric()) {
            $data['decimalPlace'] = $decimalPlace ?: 2;
        }

        $this->setFieldAttribute($data);

        return $this;
    }

    protected function validateFieldProperties()
    {

        $type = $this->type->name;

        if ($type == 'lookupModel' && ! $this->relatedEntity) {
            throw new \Exception('Error. Defined field named "'.$this->field->name.'" with field type lookupModel has no defined relationship. Methods "relate" and "to" must not have been called');
        }
        if ($type == 'picklist' && ! $this->field->listName) {
            throw new \Exception('Error. Defined field named "'.$this->field->name.'" with field type picklist has no defined list. Method "enum" must not have been called');
        }
        if ($type == 'rollupSummary' && ! $this->field->rollupSummaryInfo) {
            throw new \Exception('Error. Defined field named "'.$this->field->name.'" with field type rollupSummary has no defined summary info. Method "summarize" must not have been called');
        }
    }

    public function save()
    {

        // entity and field must have already been resolved
        if (! $this->entity || ! $this->field) {
            throw new \Exception('Error. Either entity or field is missing');
        }

        $this->validateRules();
        $this->validateFieldProperties();

        // If field 'active' is not set, set it to true by default
        if (! $this->field->active) {
            $this->field->active = true;
        }

        $this->entity->fields()->save($this->field);

        // Save rules on the fields

        if (count($this->rules)) {
            $rules = [];
            foreach ($this->rules as $rule) {
                $rules[] = new Field($rule);
            }

            $this->field->find($this->field->_id)->rules()->saveMany($rules);

            $key = array_search('filtered_by', array_column($this->rules, 'name'));
            if ($key !== false) {
                $cFieldDetails = $this->checkControllingField($this->rules[$key]['value']);
                $cFieldDetails->controls = $this->field->name;
                $cFieldDetails->save();
            }
        }

        // If there's a relationship, related field must have already been defined
        if ($this->relatedEntity) {
            // create relationship
            $field = $this->field->find($this->field->_id);
            $field->relation()->save($this->relation);
        } elseif ($this->type->name == 'picklist' && $this->pickList) {
            // save new picklist data
            if (! is_string($this->pickList)) {
                (new PicklistService)->saveBuilt($this->pickList->name, $this->pickList->options, ($this->pickList->catSrcListName) ?? null);
                if ($this->defaultValue) {
                    $addtoRules = $this->field->find($this->field->_id)->rules();

                    foreach ($this->defaultValue as $key => $value) {
                        if (is_array($this->defaultValue[$key]['value']) && array_key_exists('value', $this->defaultValue[$key]['value'])) {
                            $this->defaultValue[$key]['value']['value'] = (new PicklistService)->getIDs($this->field->listName, $this->defaultValue[$key]['value']['value']);
                            $this->defaultValue[$key]['value'] = makeObject($this->defaultValue[$key]['value']);
                        } else {
                            $this->defaultValue[$key]['value'] = (new PicklistService)->getIDs($this->field->listName, $this->defaultValue[$key]['value']);
                        }

                        $addtoRules->create($this->defaultValue[$key]);
                    }
                }
            }
        }

        $this->lastMethodCalled = '';

        if ($this->inRange && ($this->type->name == 'date' || $this->type->name == 'time')) {
            $this->on($this->entity);
            $this->rules = $this->holdRules;
            $this->add($this->inRange['type'], $this->inRange['name'], $this->inRange['label']);
            $this->inRange = [];
            $this->holdRules = [];
            $this->save();
        }

        return $this->field;
    }

    private function validateRules()
    {
        if ($this->type->defaultRule) {
            $this->setDefault();
        }

        if ($this->defaultValue) {
            foreach ($this->defaultValue as $key => $value) {
                $this->defaultValue[$key] = $this->checkValue($value);
            }
            $this->defaultValue = array_filter($this->defaultValue);
        }

        return $this;
    }

    protected function setDefault()
    {
        $default = $this->type->defaultRule;

        foreach ($default as $key => $value) {

            $newRule = $this->addNewRule($default[$key]['rule_id'], $default[$key]['value'], '_id');
            $skey = false;

            if ($newRule['groupName']) {
                $skey = array_search($newRule['groupName'], array_column($this->rules, 'groupName'));
            } else {
                $skey = array_search($newRule['name'], array_column($this->rules, 'name'));
            }

            if ($skey === false) {
                $this->rules[] = $newRule;
            }
        }

        return $this;
    }

    public function checkValue($valueToConvert)
    {
        $isMS = false;
        $hasDepth = false;
        $mustDisplay = ['tab_multi_select', 'ms_list_view', 'ms_dropdown', 'ms_pop_up', 'checkbox_inline', 'checkbox'];

        if (count(array_diff($mustDisplay, array_column($this->rules, 'name'))) != count($mustDisplay)) {
            $isMS = true;
        }

        if (is_array($valueToConvert['value']) && array_key_exists('value', $valueToConvert['value'])) {
            $hasDepth = true;
            $values = $valueToConvert['value']['value'];
        } else {
            $values = $valueToConvert['value'];
        }

        if (is_string($values) && $isMS) {
            throw new \Exception('Given value/s for multi-select display must be an array');
        } elseif (is_array($values) && ! $isMS) {
            throw new \Exception('Multiple value must have a multi-select display');
        }

        if ($values === null) {
            $this->rules[] = $valueToConvert;

            return null;
        }

        $listIDs = null;
        if (is_string($this->pickList) || $this->type->name == 'lookupModel') {
            if ($this->type->name == 'picklist') {
                $listIDs = (new PicklistService)->getIDs($this->field->listName, $values);
                $errMsg = 'Error. Unable to find value/s set in "'.$this->field->listName.'" list of values.';
            } else {
                $listIDs = $this->getLookupID($values, $valueToConvert['value']['field']);
                $errMsg = 'Error. Unable to find set value/s on "'.$this->relatedEntity->name.'".';
            }

            if (count($listIDs) != count($values)) {
                throw new \Exception($errMsg);
            } else {
                if ($hasDepth) {
                    unset($valueToConvert['value']['field']);
                    if (count($valueToConvert['value']) > 1) {
                        $valueToConvert['value']['value'] = $listIDs;
                    } else {
                        $hasDepth = false;
                    }
                }
                if (! $hasDepth) {
                    $valueToConvert['value'] = $listIDs;
                }
                $this->rules[] = $valueToConvert;

                return null;
            }
        } elseif ($this->type->name == 'picklist') {
            if (is_string($values)) {
                if (! in_array($values, $this->list)) {
                    throw new \Exception('Error. Unable to find "'.$values.'" in "'.$this->field->listName.'" list of values.');
                }
            } else {
                foreach ($values as $value) {
                    if (! in_array($value, $this->list)) {
                        throw new \Exception('Error. Unable to find "'.$value.'" in "'.$this->field->listName.'" list of values.');
                    }
                }
            }

            return $valueToConvert;
        } else {
            return null;
        }
    }

    /**
     * @param  mixed  $value
     * @return $this
     *
     * @throws \Exception
     */
    public function rules($ruleName, $value = true, $convertValue = false, $reverse = false)
    {

        $newRule = $this->addNewRule($ruleName, $value);

        if ($convertValue) {
            return $this->defaultValue[] = $newRule;
        }

        if ($newRule['groupName']) {
            $key = array_search($newRule['groupName'], array_column($this->rules, 'groupName'));
            if ($key !== false && ! $reverse) {
                return $this->rules[$key] = $newRule;
            }

            if ($key !== false && $reverse) {
                return $this->rules;
            }
        } else {
            $key = array_search($newRule['name'], array_column($this->rules, 'name'));
            if ($key !== false && ! $reverse) {
                return $this->rules[$key] = $newRule;
            }

            if ($key !== false && $reverse) {
                return $this->rules;
            }
        }

        return $this->rules[] = $newRule;
    }

    public function holdRules($ruleName, $value = true)
    {

        $newRule = $this->addNewRule($ruleName, $value);

        if ($newRule['groupName']) {
            $key = array_search($newRule['groupName'], array_column($this->holdRules, 'groupName'));
            if ($key !== false) {
                return $this->holdRules[$key] = $newRule;
            }
        }

        return $this->holdRules[] = $newRule;
    }

    protected function addNewRule($ruleName, $value = true, $field = 'ruleName')
    {
        $rule = $this->type->rules()->where($field, $ruleName)->first();
        if (! $rule) {
            throw new \Exception('Field rule "'.$ruleName."' is not allowed in fields with type '".$this->type->name."'");
        }

        return [
            'name' => $rule->ruleName,
            'event' => $rule->event,
            'groupName' => $rule->groupName,
            'value' => $value,
        ];
    }

    protected function resolveEntity($entity, $continuous = false)
    {
        if (is_string($entity)) {
            if ($continuous) {
                if ($this->entityName != $entity) {
                    // different entity from the previous one, or new
                    $this->entityName = $entity;
                } else {
                    return $this->entity;
                }
            }
            $mod = Entity::where('name', $entity)->first();
            if (! $mod) {
                throw new \Exception('Entity named "'.$entity.'" is undefined');
            }

            $this->entityModel = $mod->getModel();

            return $mod;

        } elseif ($entity instanceof Entity) {
            $this->entityModel = $entity->getModel();

            return $entity;
        } else {
            throw new \Exception('Entity given is undefined');
        }
    }

    protected function resolveExistingField($field, $fieldName = 'name')
    {
        if (is_string($field)) {
            return Field::where($fieldName, $field)->first();
        } elseif ($field instanceof $this->fieldClass) {
            return $field;
        }
    }

    protected function resolveType($type, $fieldName = 'name')
    {
        if (is_string($type)) {
            $type = FieldType::where($fieldName, $type)->first();

            if (! $type) {
                throw new \Exception('Field type "'.$type.'" is undefined');
            }

            return $type;
        } elseif ($type instanceof $this->fieldTypeClass) {
            return $type;
        } else {
            throw new \Exception('Field type given is not recognized as a valid field');
        }
    }

    /**
     * @return mixed
     *
     * @throws \Exception
     */
    protected function resolveField($fieldName, $type, $label, $uniqueName)
    {

        $this->type = $this->resolveType($type);

        if ($this->type->name == 'lookupModel') {
            $this->fieldRelatable = true;
        }

        return new Field([
            'name' => $fieldName,
            'label' => ($label) ?: labelify($fieldName),
            'uniqueName' => $this->generateUniqueName($fieldName, $uniqueName),
            'field_type_id' => $this->type->_id,
        ]);
    }

    protected function generateUniqueName($fieldName, $uniqueName = null)
    {
        $a = 0;

        $name = snake_case(($uniqueName) ?: strtolower($this->entity->name).'_'.$fieldName);
        do {
            $newName = $name.($a++ ?: '');
            $field = Field::where('uniqueName', $newName)->count();
        } while ($field);

        return $newName;
    }

    protected function checkLastMethodCalled($expectedLastMethod, $method, $message)
    {

        if ($this->lastMethodCalled != $expectedLastMethod) {
            throw new \Exception('Syntax error. '.$message);
        } else {
            $this->lastMethodCalled = $method;
        }
    }

    public function getLastMethodCalled()
    {
        return $this->lastMethodCalled;
    }

    public function getField()
    {
        return $this->field;
    }

    public function deleteEntityFields($entityName)
    {
        $entity = Entity::where('name', $entityName);

        $entity->fields()->delete();
    }

    public function onField($entity, $field)
    {

        $this->rules = [];

        $this->checkLastMethodCalled('', 'onField', "Method 'onField' should be called before all other methods");

        $this->entity = $this->resolveEntity($entity, true);

        $this->field = $this->entity->fields()->where('name', $field)->first();

        $this->type = FieldType::find($this->field->field_type_id);

        if ($this->type->name == 'lookupModel') {
            $this->fieldRelatable = true;
        }

        if (! $this->field) {
            throw new \Exception('Field named "'.$field.'" is not found on entity "'.$entity.'"');
        }

        return $this;
    }

    public function update($merge = false)
    {
        $this->lastMethodCalled = '';

        if ($merge === true) {
            foreach ($this->field->rules as $r) {

                $this->rules($r->name, $r->value, false, true);
            }
        }

        $this->field->rules = $this->rules;

        if ($this->relatedEntity) {
            // create relationship
            $field = Field::find($this->field->_id);
            $field->relation()->update($this->relation->toarray());
        }

        $this->field->save();
    }

    public static function getRusTypes()
    {
        return static::$rusTypes;
    }

    private function getLookupID($value, $fieldSource = '_id')
    {
        $model = $this->relation->entity->getModel();
        if (is_array($value)) {
            $ID = $model->whereIn($fieldSource, $value)->pluck('_id')->toArray();
        } else {
            $ID = $model->where($fieldSource, $value)->pluck('_id')->first();
        }

        return $ID;
    }
}
