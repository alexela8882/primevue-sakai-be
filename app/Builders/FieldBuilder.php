<?php

namespace App\Builders;

use App\Facades\DQBuilder;
use App\Facades\EntityField;

class FieldBuilder extends BaseFieldBuilder
{
    protected $steps = [];

    /*********************************************** COMPOSITE FIELD METHODS **********************************************/

    /**
     * @param  array  $fieldNames
     * @param  string  $groupLabel
     * @param  bool  $concat
     * @param  string  $glue
     * @param  bool  $labelBasedOnType
     * @return $this
     *
     * @throws \Exception
     */
    public function addName($fieldNames = [], $groupLabel = null, $concat = false, $glue = ' ', $labelBasedOnType = true)
    {

        $this->checkLastMethodCalled('on', '', "The method 'on' should be called first before calling compound field definition methods");

        $availableFields = [idify('honorific'), 'lastName', 'firstName', 'middleName', 'academicSuffix'];

        if (! count($fieldNames)) {
            $fieldNames = $availableFields;
        }

        $fieldDetails = $this->checkFieldNames('name', $fieldNames, $availableFields, $labelBasedOnType);

        foreach ($fieldDetails as $key => $fieldDetail) {

            $type = ($fieldDetail['type'] != 'honorific_id') ? 'text' : 'picklist';

            if ($this->lastMethodCalled != 'on') {
                $this->on($this->entity);
            }

            $this->add($type, $fieldDetail['name'], $fieldDetail['label']);

            if ($key == 0) {
                $this->setGroupWith($groupLabel ?: 'Name', $fieldNames, $concat, $glue);
            }

            if ($type == 'picklist') {
                $this->enum('honorifics', ['Mr.', 'Ms.', 'Mrs.', 'Dr.', 'Prof.', 'Engr.']);
            }

            $this->group('name')->save();
        }

        $this->lastMethodCalled = '';

        return $this;

    }

    protected function setGroupWith($groupLabel, $fields, $concat, $glue)
    {

        if (is_numeric(array_keys($fields)[0])) {
            $fields = array_combine($fields, $fields);
        }

        $this->setFieldAttribute('groupWith', $fields);
        $this->setFieldAttribute('concatenated', $concat);

        $this->glue($glue);

        if ($this->fieldRepository->where(['groupLabel' => $groupLabel, 'entity_id' => $this->entity->_id])) {
            throw new \Exception('Error. Cannot add field group label "'.$groupLabel.'" on entity "'.$this->entity->name.'". Group label already exists.');
        }

        $this->setFieldAttribute('groupLabel', $groupLabel);
    }

    /**
     * @param  array  $fieldNames
     * @param  null  $groupLabel
     * @param  bool  $concat
     * @param  string  $glue
     * @param  bool  $labelBasedOnType
     * @return $this
     *
     * @throws \Exception
     */
    public function addAddress($fieldNames = [], $groupLabel = null, $concat = false, $glue = ',', $labelBasedOnType = true)
    {

        $this->checkLastMethodCalled('on', '', "The method 'on' should be called first before calling compound field definition methods");

        $availableFields = ['street', 'zipcode', 'city', 'state', idify('country')];

        $fieldDetails = $this->checkFieldNames('address', $fieldNames, $availableFields, $labelBasedOnType);

        foreach ($fieldDetails as $key => $fieldDetail) {
            $type = 'text';

            if ($this->lastMethodCalled != 'on') {
                $this->on($this->entity);
            }

            $type = ($fieldDetail['type'] != idify('country')) ? $type : 'lookupModel';

            $this->add($type, $fieldDetail['name'], $fieldDetail['label']);

            if ($key == 0) {
                $this->setGroupWith($groupLabel ?: 'Address', $fieldNames, $concat, $glue);
            }

            if ($fieldDetail['type'] == idify('country')) {
                $this->relate('one_from_many')->to('Country', 'name', $fieldDetail['name'])->includeFields('alpha2Code')->ssDropDown();
            }
            $this->group('address')->save();
        }

        $this->lastMethodCalled = '';

        return $this;
    }

    /**
     * @param  null  $groupLabel
     * @param  bool  $concat
     * @param  bool  $labelBasedOnType
     * @return $this
     *
     * @throws \Exception
     */
    public function addCurrency($fieldNames, $groupLabel = null, $concat = false, $labelBasedOnType = true)
    {

        $this->checkLastMethodCalled('on', 'addCurrency', "The method 'on' should be called first before calling compound field definition methods");

        $availableFields = ['currencyName', 'currencySign'];

        $fieldDetails = $this->checkFieldNames('currency', $fieldNames, $availableFields, $labelBasedOnType);

        foreach ($fieldDetails as $key => $fieldDetail) {

            if ($this->lastMethodCalled != 'on') {
                $this->on($this->entity);
            }

            switch ($fieldDetail['type']) {
                case 'currencyName':
                    $this->add('text', $fieldDetail['name'], $fieldDetail['label']);
                    break;
                case 'currencyCode':
                    $this->add('lookupModel', $fieldDetail['name'], $fieldDetail['label'])->relate('one_from_many')->to('currencies', 'code');
                    break;
            }

            if ($key == 0) {
                $this->setGroupWith($groupLabel ?: 'Currency', $fieldNames, $concat, '');
            }

            $this->save();
        }

        $this->lastMethodCalled = '';

        return $this;
    }

    public function addUserStamps($hasSoftDeleteAttrib = true)
    {

        $this->checkLastMethodCalled('on', 'addUserStamps', "The method 'on' should be called first before calling compound field definition methods");

        $this->lastMethodCalled = '';

        $this->on($this->entity)->add('date', 'updated_at', 'Last Modified Date')->hideIn(['create', 'update'])->save();
        $this->on($this->entity)->add('date', 'created_at', 'Created Date')->hideIn(['create', 'update'])->save();

        $this->on($this->entity)->add('lookupModel', 'updated_by', 'Last Modified By')->relate('one_from_many')->to('User', ['firstName', 'lastName'], 'updated_by')->ssPopUp()->hideIn(['create', 'update'])->save();
        $this->on($this->entity)->add('lookupModel', 'created_by', 'Created By')->relate('one_from_many')->to('User', ['firstName', 'lastName'], 'created_by')->ssPopUp()->hideIn(['create', 'update'])->save();

        if ($hasSoftDeleteAttrib) {
            $this->on($this->entity)->add('date', 'deleted_at')->hideIn(['create', 'update'])->save();

            $this->on($this->entity)->add('lookupModel', 'deleted_by', 'Deleted By')->relate('one_from_many')->to('User', ['firstName', 'lastName'], 'deleted_by')->ssPopUp()->hideIn(['create', 'update'])->save();
        }

        $this->lastMethodCalled = '';

        return $this;
    }

    protected function checkFieldNames($group, $fieldNames, $availableFields, $labelBasedOnType = true)
    {
        if (! count($fieldNames)) {
            $fieldDetails = $availableFields;
        } else {
            $fieldDetails = collect([]);
            foreach ($fieldNames as $key => $fieldName) {
                if (is_string($key)) {
                    if (in_array($key, $availableFields)) {
                        $fieldInfo = ['type' => $key];
                        if (is_array($fieldName)) {
                            if (! isset($fieldName['label']) || ! isset($fieldName['name'])) {
                                throw new \Exception('Invalid field name definition "'.$key.'" for group "'.$group.'". Missing label and/or name.');
                            }

                            $fieldInfo['label'] = $fieldName['label'];
                            $fieldInfo['name'] = $fieldName['name'];
                        } else {
                            $fieldInfo['label'] = labelify($labelBasedOnType ? $key : $fieldName);
                            $fieldInfo['name'] = $fieldName;
                        }
                    } else {
                        throw new \Exception('Invalid field name "'.$key.'" for group "'.$group.'"');
                    }
                } else {
                    if (! in_array($fieldName, $availableFields)) {
                        throw new \Exception('Invalid field name "'.$fieldName.'" for group "'.$group.'"');
                    }

                    $fieldInfo = [
                        'type' => $fieldName,
                        'name' => $fieldName,
                        'label' => labelify($fieldName),
                    ];
                }
                $fieldDetails->push($fieldInfo);
            }
        }

        return $fieldDetails;

    }

    /********************************************* ATTRIBUTE-BASED METHODS *******************************************/

    /**
     * @param  string  $name
     * @param  string  $attrib
     * @param  null|string  $default
     * @param  string  $mode
     * @return $this
     *
     * @throws \Exception
     */
    public function asTypeFilter($name, $attrib = 'value', $default = null, $mode = '1-0')
    {
        if (! in_array($this->type->name, ['lookupModel', 'picklist'])) {
            throw new \Exception('Error. Only lookups and picklists can be assigned as a view type');
        }
        $validModes = [
            '1-0',      // single item, empty value allowed, fetch first if empty
            '1-1',      // single item, value required
            '1-0-X',    // single item, empty value allowed, fetch all (no filter)
            'X-1',      // multiple items, value required
            'X-0',      // multiple items, value required, fetch first if empty
            'X-0-X',    // multiple items, value required, fetch all (no filter)
        ];
        if (! in_array($mode, $validModes)) {
            throw new \Exception('Error. Mode "'.$mode.'". Select from the following: "'.implode('","', $validModes).'"');
        }

        if ($this->type->name == 'lookupModel') {
            $entity = $this->relation->relatedEntity;
            $viewAttribField = EntityField::checkEntityFields($entity, $attrib);
            if (! $viewAttribField || $attrib != '_id' && in_array($viewAttribField->fieldType->name, ['lookupModel', 'picklist'])) {
                throw new \Exception('Error. Invalid viewType field attribute "'.$attrib.'". This must exist as a field of '.$entity->name.' and must neither be a lookup or a picklist');
            }

            if ($default) {
                $defaultItem = $entity->getModel()->where($attrib, $default)->first();
                if (! $defaultItem) {
                    throw new \Exception("Error. Unknown item {$default} in entity {$entity->name}");
                } else {
                    $default = $defaultItem->_id;
                }
            }
        } elseif ($this->type->name == 'picklist') {
            $item = $this->pickListRepository->getModel()->where('name', $this->field->listName);

            if ($mode == '1-1') {
                $this->required(true);
            } elseif ($mode == '1-0') {
                $this->required(false);
            } else {
                throw new \Exception("Error. Invalid mode {$mode} for picklist");
            }

            if ($default) {
                $defaultItem = $this->pickListRepository->getIDs($this->field->listName, $default, null, $attrib);
                if (! $defaultItem) {
                    throw new \Exception("Error. Unknown item {$default} in picklist {$this->field->listName}");
                } else {
                    $default = $defaultItem->_id;
                }
            }

            if (! $item) {
                throw new \Exception('Error. Unknown items in picklist '.$this->pickList->name);
            }
        }

        $this->setFieldAttribute([
            'typeFilter' => $name,
            'typeFilterAttrib' => $attrib,
            'typeFilterMode' => $mode,
            'typeFilterDefault' => $default,
        ])->header()->disable();

        return $this;
    }

    /**
     * @param  string  $groupName
     * @return $this
     */
    public function group($groupName)
    {
        $this->setFieldAttribute('group', $groupName);

        return $this;
    }

    /**
     * @param  string  $groupName
     * @return $this
     */
    public function hierarchy($orderNum)
    {
        $this->setFieldAttribute('hierarchy', $orderNum);

        return $this;
    }

    /**
     * @return $this
     */
    public function order($order)
    {
        $this->setFieldAttribute('order', $order);

        return $this;
    }

    /**
     * @param  bool  $active
     */
    public function active($active = true)
    {
        $this->setFieldAttribute('active', $active);
    }

    public function header($h = true)
    {
        $this->setFieldAttribute('header', $h);

        return $this;
    }

    public function title($t = true)
    {
        $this->setFieldAttribute('title', $t);

        return $this;
    }

    public function cloak($cloak = true)
    {
        $this->setFieldAttribute('cloak', $cloak);

        return $this;
    }

    public function onKeys(array $values, $selectionMessage = 'Choose value')
    {
        $this->setFieldAttribute('onKeys', [
            'values' => $values,
            'selectionMessage' => $selectionMessage,
        ]);

        return $this;
    }

    public function quick()
    {
        $this->setFieldAttribute('quick', true);

        return $this;
    }

    public function addable($moduleName, $fieldName = null)
    {
        if ($this->type->name = 'lookupModel') {
            $a = makeObject([
                'moduleName' => $moduleName,
                'parentField' => $fieldName,
            ]);
            $this->setFieldAttribute('addable', $a);
        } else {
            throw new \Exception("Field attribute addable is not allowed in fields with type '".$this->type->name."'");
        }

        return $this;
    }

    /*********************************************** RULE-BASED METHODS ********************************************/

    /**
     * @param  bool  $require
     * @return $this
     */
    public function required($require = true)
    {
        if ($require) {
            $this->quick();
        } // all required are automatically required for quickadd

        $this->rules('required', $require);

        return $this;
    }

    public function requiredExept($require = [])
    {
        $this->rules('required_exept', $require);

        return $this;
    }

    public function disable($disable = true)
    {
        $this->rules('disable', $disable);

        return $this;
    }

    public function hide($hide = true)
    {
        $this->rules('hide', $hide);

        return $this;
    }

    public function noSelectedLabel($label)
    {
        if (! $label) {
            throw new \Exception('Syntax error. Rule "noSelectedLabel" must have a label.');
        }

        $this->rules('no_selected_label', $label);

        return $this;
    }

    /**
     * @param  string  $anotherField
     * @param  array  $values
     * @return $this
     *
     * @throws \Exception
     */
    public function requiredIf($anotherField, $values = [])
    {

        if (! count($values)) {
            throw new \Exception('Syntax error. Rule "requiredIf" must have specified values');
        }

        $rule = makeObject([
            'anotherField' => $anotherField,
            'values' => $values,
        ]);
        $this->rules('required_if', $rule);

        return $this;
    }

    /**
     * @param  array  $values
     * @return $this
     *
     * @throws \Exception
     */
    public function requiredUnless($anotherField, $values = [])
    {

        if (! count($values)) {
            throw new \Exception('Syntax error. Rule "requiredUnless" must have specified values');
        }

        $rule = makeObject([
            'anotherField' => $anotherField,
            'values' => $values,
        ]);
        $this->rules('required_unless', $rule);

        return $this;
    }

    /**
     * @param  array  $fields
     * @return $this
     *
     * @throws \Exception
     */
    public function requiredWith($fields = [])
    {

        $this->requiresFields($fields, 'required_with');

        return $this;
    }

    // public function requiredWhen($anotherField, $values = []) {
    //
    //   $rule = makeObject([
    //       'anotherField' => $anotherField,
    //       'values' => $values
    //   ]);
    //   $this->rules('requiredWhen', $rule);
    //   return $this;
    // }

    public function setValueDisableIf($expression, $setValue, $type = 'literal', $value = null)
    {
        $this->setValue('set_val_disable_if', $expression, $setValue, $type, $value);

        return $this;
    }

    public function disableIn($pages)
    {
        if (! is_array($pages)) {
            throw new \Exception('Error. Parameter for hideIn function must be of type array.');
        }
        if (array_diff($pages, ['create', 'show', 'update', 'index'])) {
            throw new \Exception('Error. Invalid parameter for hideIn function.');
        }

        $this->rules('disable_in', $pages);

        return $this;
    }

    public function setValueIf($expression, $setValue, $type = 'literal', $value = null)
    {
        $this->setValue('set_val_if', $expression, $setValue, $type, $value);

        return $this;
    }

    /**
     * @param  array  $fields
     * @return $this
     *
     * @throws \Exception
     */
    public function requiredWithAll($fields)
    {
        $this->requiresFields($fields, 'required_with_all');

        return $this;
    }

    /**
     * @return $this
     *
     * @throws \Exception
     */
    public function requiredWithout($fields)
    {
        $this->requiresFields($fields, 'required_without');

        return $this;
    }

    /**
     * @return $this
     *
     * @throws \Exception
     */
    public function requiredWithoutAll($fields)
    {
        $this->requiresFields($fields, 'required_without_all');

        return $this;
    }

    protected function requiresFields($fields, $ruleName)
    {
        if (! count($fields)) {
            throw new \Exception("Rule '".$ruleName."' must have specified fields.");
        }

        $this->rules($ruleName, $fields);
    }

    /**
     * @param  bool  $unique
     * @return $this
     *
     * @throws \Exception
     */
    public function unique($unique = true)
    {
        $this->rules('unique', $this->entity->name);

        return $this;
    }

    public function uniqueWith($fields)
    {
        $obj = makeObject([
            'entity' => $this->entity->name,
            'fields' => $fields,
        ]);

        $this->rules('unique_with', $obj);

        return $this;
    }

    /**
     * @param  bool  $titleCased
     * @return $this
     *
     * @throws \Exception
     */
    public function titleCase($titleCased = true)
    {
        $this->rules('title_case', $titleCased);

        return $this;
    }

    /**
     * @param  bool  $email
     * @return $this
     *
     * @throws \Exception
     */
    public function email($email = true)
    {
        $this->rules('email', $email);

        return $this;
    }

    /**
     * @param  bool  $isString
     * @return $this
     *
     * @throws \Exception
     */
    public function string($isString = true)
    {
        $this->rules('string', $isString);

        return $this;
    }

    public function concatinated($concat = true)
    {
        $this->rules('concatinated', $concat);

        return $this;
    }

    /**
     * @param  bool  $isFax
     * @return $this
     *
     * @throws \Exception
     */
    public function fax($isFax = true)
    {
        $this->rules('fax', $isFax);

        return $this;
    }

    /**
     * @param  bool  $isPhone
     * @return $this
     *
     * @throws \ExceptionBase
     */
    public function phone($isPhone = true)
    {
        $this->rules('phone', $isPhone);

        return $this;
    }

    /**
     * @param  bool  $isValidUrl
     * @return $this
     *
     * @throws \Exception
     */
    public function url($isValidUrl = true)
    {
        $this->rules('url', $isValidUrl);

        return $this;
    }

    /**
     * @param  bool  $isAlpha
     * @return $this
     *
     * @throws \Exception
     */
    public function alpha($isAlpha = true)
    {
        $this->rules('alpha', $isAlpha);

        return $this;
    }

    /**
     * @param  bool  $isAlphaNumeric
     * @return $this
     *
     * @throws \Exception
     */
    public function alphaNumeric($isAlphaNumeric = true)
    {
        $this->rules('alpha_num', $isAlphaNumeric);

        return $this;
    }

    /**
     * @param  bool  $isAlphaDash
     * @return $this
     *
     * @throws \Exception
     */
    public function alphaDash($isAlphaDash = true)
    {
        $this->rules('alpha_dash', $isAlphaDash);

        return $this;
    }

    /**
     * @param  array  $rules
     * @return $this
     */
    public function password()
    {
        $this->rules('password', true);

        return $this;
    }

    /**
     * @param  string  $fieldName
     * @return $this
     *
     * @throws \Exception
     */
    public function same($fieldName)
    {
        $this->rules('same', $fieldName);

        return $this;
    }

    /**
     * @param  string  $expression
     * @return $this
     *
     * @throws \Exception
     */
    public function visibleIf($expression)
    {
        $this->rules('visible_if', $expression);

        return $this;
    }

    /**
     * @param  string  $expression
     * @return $this
     *
     * @throws \Exception
     */
    public function hideIf($expression)
    {
        $this->rules('hide_if', $expression);

        return $this;
    }

    // /**
    //  * @param string $expression
    //  * @throws \Exception
    //  */
    // // public function disableIf($expression) {
    // //     $this->rules('disable_if', $expression);
    // // }

    public function afterOrEqual($date, $type = 'literal', $value = null)
    {
        if ($this->type->name == 'date' || $this->type->name == 'time') {
            $dateData = $this->validateDateTime($type, $value, $date);
        }

        $this->rules('after_or_equal', $dateData);

        return $this;
    }

    public function beforeOrEqual($date, $type = 'literal', $value = null)
    {
        if ($this->type->name == 'date' || $this->type->name == 'time') {
            $dateData = $this->validateDateTime($type, $value, $date);
        }

        if ($this->inRange) {
            $this->holdRules('before_or_equal', $dateData);
        } else {
            $this->rules('before_or_equal', $dateData);
        }

        return $this;
    }

    public function after($date, $type = 'literal', $value = null)
    {
        $dateData = $this->validateDateTime($type, $value, $date);
        $this->rules('after', $dateData);

        return $this;
    }

    public function before($date, $type = 'literal', $value = null)
    {
        $dateData = $this->validateDateTime($type, $value, $date);

        if ($this->inRange) {
            $this->holdRules('before', $dateData);
        } else {
            $this->rules('before', $dateData);
        }

        return $this;
    }

    public function setMinDate($fieldName)
    {
        $this->checkDateControllingField('set_min_date', $fieldName);

        return $this;
    }

    public function setMaxDate($fieldName)
    {
        $this->checkDateControllingField('set_max_date', $fieldName);

        return $this;
    }

    protected function checkDateControllingField($rule, $fieldName)
    {
        $cFieldDetails = $this->entity->fields()->where('name', $fieldName)->first();
        if (! $cFieldDetails) {
            throw new \Exception('Field named "'.$fieldName.'" is not found on entity "'.$this->entity->name.'"');
        }

        $ctype = $cFieldDetails->fieldType->name;

        if ($ctype != 'date') {
            throw new \Exception('Field named "'.$fieldName.'" must be a date field.');
        }

        if ($rule == 'set_max_date' && $this->inRange) {
            $this->holdRules($rule, $fieldName);
        } else {
            $this->rules($rule, $fieldName);
        }

        return $this;
    }

    /**
     * @param  $time
     * @param  string  $type
     * @return $this
     *
     * @throws \Exception
     */
    public function hasSeconds($value = true)
    {
        $this->rules('has_seconds', $value);
        if ($this->inRange) {
            $this->holdRules('set_max_date', $fieldName);
        }

        return $this;
    }

    /**
     * @param  $time
     * @param  string  $type
     * @return $this
     *
     * @throws \Exception
     */
    // public function maxTime($time, $type = 'literal', $value = null) {
    //
    //
    //     return $this;
    // }

    public function setMinTime($fieldName)
    {
        $this->checkTimeControllingField('set_min_time', $fieldName);

        return $this;
    }

    public function setMaxTime($fieldName)
    {
        $this->checkTimeControllingField('set_max_time', $fieldName);

        return $this;
    }

    protected function checkTimeControllingField($rule, $fieldName)
    {

        $cFieldDetails = $this->entity->fields()->where('name', $fieldName)->first();

        if (! $cFieldDetails) {
            throw new \Exception('Field named "'.$fieldName.'" is not found on entity "'.$this->entity->name.'"');
        }

        $ctype = $cFieldDetails->fieldType->name;

        if ($ctype == 'date') {
            $rules = $cFieldDetails->rules->pluck('name')->toArray();
            if (! in_array('date_time', $rules)) {
                throw new \Exception('Field named "'.$fieldName.'" must be a time or datetime field.');
            }
        } elseif ($ctype != 'time') {
            throw new \Exception('Field named "'.$fieldName.'" must be a time or datetime field.');
        }

        if ($rule == 'set_max_time' && $this->inRange) {
            $this->holdRules($rule, $fieldName);
        } else {
            $this->rules($rule, $fieldName);
        }
    }

    // public function checkrules($f1, $f2){
    //   $cFieldDetails = $this->entity->fields()->where('name', $f1)->first();
    //   $rules = $this->fieldRepository->find($cFieldDetails->_id)->rules->pluck('name')->toArray();
    //   dump($rules);
    //   $cFieldDetails = $this->entity->fields()->where('name', $f2)->first();
    //   $rules = $this->fieldRepository->find($cFieldDetails->_id)->rules->pluck('name')->toArray();
    //   dd($rules);
    // }

    public function inRangeWith($type, $fieldName1, $fieldName2, $label1 = null, $label2 = null)
    {

        $this->inRange = [];

        $this->checkLastMethodCalled('on', 'inRangeWith', "Method 'on' should be called before 'add' method");

        if ($type != 'date' && $type != 'time') {
            throw new \Exception('in_range_with rule is only applicable on Date and Time fields.');
        }

        $this->inRange['type'] = $type;
        $this->inRange['name'] = $fieldName2;
        $this->inRange['label'] = $label2;

        $this->add($type, $fieldName1, $label1);

        $obj = makeObject([
            'otherField' => $fieldName2,
            'rangeRole' => 'start',
        ]);

        $this->rules('in_range_with', $obj);

        $obj = makeObject([
            'otherField' => $fieldName1,
            'rangeRole' => 'end',
        ]);
        $this->holdRules('in_range_with', $obj);

        return $this;
    }

    /**
     * @param  string  $type
     * @param  null  $type2
     * @return $this
     */
    public function minmaxTime($minTime, $maxTime, $type = 'literal', $type2 = null)
    {

        $this->minTime($minTime, $type);
        $this->maxTime($maxTime, ($type2) ?: $type);

        return $this;
    }

    protected function validateDateTime($type, $value, $function, $expression = null)
    {
        if ($type == 'literal' || $type == 'computed') {
            if ($type == 'literal') {

                $value = $function;
                $function = null;

                if ($this->type->name == 'date') {
                    $format = 'Y-m-d H:i:s';
                } else {
                    $format = 'H:i:s';
                }

                if ($value != null && $value != date($format, strtotime($value))) {
                    throw new \Exception('String "'.$value."' is not a valid ".$this->type->name.'.');
                }
            } elseif ($type == 'computed') {

                if ($this->type->name == 'date') {
                    $key = ['now', 'addDay', 'subDay', 'addMonth', 'subMonth', 'addYear', 'subYear'];
                } else {
                    $key = ['now', 'addHr', 'subHr', 'addMin', 'subMin'];
                }

                if (! in_array($function, $key)) {
                    throw new \Exception('Error. "'.$function."' is not a valid ".$this->type->name.' function.');
                }

                if ($function != 'now' && ! $value) {
                    throw new \Exception('Error. Value for '.$function.' '.$this->type->name.' function must be greater than zero (0).');
                }

                if ($function == 'now') {
                    $value = null;
                }

            }
            $rule = ['type' => $type, 'function' => $function, 'value' => $value];
            if ($expression) {
                $rule = array_merge($rule, ['expression' => $expression]);
            }
            $data = makeObject($rule);

            return $data;
        } else {
            throw new \Exception('Error. Unrecognize '.$this->type->name.' type value : "'.$type.'".');
        }

    }

    /**
     * @return $this
     *
     * @throws \Exception
     */
    public function dateFormat($formatType)
    {

        if (! in_array($formatType, ['m/d/Y', 'M d, Y', 'F d, Y', 'd-M-y'])) {
            throw new \Exception('Error. Format "'.$formatType."' is not recognized.");
        }

        $this->rules('date_format', $formatType);
        if ($this->inRange) {
            $this->holdRules('date_format', $formatType);
        }

        return $this;
    }

    public function dateSelection($selectionType)
    {
        if (! in_array($selectionType, ['month', 'week', 'day'])) {
            throw new \Exception('Error. Format "'.$selectionType."' is not recognized.");
        }

        $this->rules('date_selection', $selectionType);

        return $this;
    }

    public function dateOnly($isDateOnly = true)
    {
        $this->rules('date_only', $isDateOnly);
        if ($this->inRange) {
            $this->holdRules('date_only', $isDateOnly);
        }

        return $this;
    }

    public function dateTime($isDateTime = true)
    {
        $this->rules('date_time', $isDateTime);
        if ($this->inRange) {
            $this->holdRules('date_time', $isDateTime);
        }

        return $this;
    }

    /**
     * @param  array  $allowedMimeTypes
     * @return $this
     *
     * @throws \Exception
     */
    public function fileType($allowedMimeTypes = [])
    {
        $types = $this->fileTypes->listItems->pluck('value')->toArray();
        $unknownFileTypes = array_diff($allowedMimeTypes, $types);
        if (count($unknownFileTypes)) {
            throw new \Exception('Error. The following file types are not allowed: '.implode(',', $unknownFileTypes));
        }

        $this->rules('mime', $allowedMimeTypes);

        return $this;
    }

    /**
     * @param  int  $min
     * @param  int  $max
     * @return $this
     *
     * @throws \Exception
     */
    public function selectBetween($min = 0, $max = 0)
    {
        $minMax = makeObject([
            'min' => $min,
            'max' => $max,
        ]);
        $this->rules('select_between', $minMax);

        return $this;
    }

    public function hideIn($pages)
    {
        if (! is_array($pages)) {
            throw new \Exception('Error. Parameter for hideIn function must be of type array.');
        }
        if (array_diff($pages, ['create', 'show', 'update', 'index'])) {
            throw new \Exception('Error. Invalid parameter for hideIn function.');
        }

        $this->rules('hide_in', $pages);

        return $this;
    }

    public function showIn($pages)
    {
        if (! is_array($pages)) {
            throw new \Exception('Error. Parameter for showIn function must be of type array.');
        }
        if (array_diff($pages, ['create', 'show', 'update', 'index'])) {
            throw new \Exception('Error. Invalid parameter for showIn function.');
        }

        $this->rules('show_in', $pages);

        return $this;
    }

    /**
     * @param  $sourceField
     * @param  null  $controllingField
     * @return $this
     *
     * @throws \Exception
     */
    public function noRoleFilter($nrf = true)
    {
        if ($this->type->name == 'lookupModel') {
            $this->setFieldAttribute('noRoleFilter', $nrf);
        } else {
            throw new \Exception("Error. Field attribute no_role_filter is not allowed in fields with type '".$this->type->name."'");
        }

        return $this;
    }

    public function filterOption($controllingField)
    {
        if ($this->type->name == 'lookupModel' || $this->type->name == 'picklist') {
            $this->validateFieldProperties();

            $cFieldDetails = $this->checkControllingField($controllingField);
            $ctype = $cFieldDetails->fieldType->name;

            if ($ctype != $this->type->name) {
                throw new \Exception('Error. The given controlling field for filterOption method must be of type '.$this->type->name.'.');
            }

            if ($ctype == 'picklist') {
                if ($cFieldDetails->listName != $this->field->listName) {
                    throw new \Exception("Error. The given controlling field for filterOption method does not match the current field's listname.");
                }
            } elseif ($ctype == 'lookupModel') {
                if ($cFieldDetails->relation->entity_id != $this->relatedEntity->_id) {
                    throw new \Exception("Error. The given controlling field for filterOption method does not match the current field's related entity.");
                }
            }

            $rules = $cFieldDetails->rules->pluck('name')->toArray();
            $mustDisplay = ['tab_multi_select', 'ms_list_view', 'ms_dropdown', 'checkbox_inline', 'checkbox'];

            if (count(array_diff($mustDisplay, $rules)) == count($mustDisplay)) {
                throw new \Exception('Error. The given controlling field for filterOption method must be a multi-select field.');
            }

            $this->rules('filter_option', $controllingField);

        } else {
            throw new \Exception("Error. Field rule filter_option is not allowed in fields with type '".$this->type->name."'");
        }

        return $this;
    }

    public function fillFromList($controllingField)
    {
        if ($this->type->name == 'lookupModel' || $this->type->name == 'picklist') {
            $this->validateFieldProperties();

            if (strrpos($controllingField, '::') === false) {
                throw new \Exception("Error. Controlling field for fillFromList method must be from a connected entity of '".$this->entity->name."' entity.");
            }

            $cFieldDetails = $this->checkControllingField($controllingField);
            $ctype = $cFieldDetails->fieldType->name;

            if ($ctype != $this->type->name) {
                throw new \Exception('Error. Controlling field for fillFromList method must be of type '.$this->type->name.'.');
            }

            if ($ctype == 'picklist') {
                if ($cFieldDetails->listName != $this->field->listName) {
                    throw new \Exception("Error. Controlling field for fillFromList method does not match the current field's listname.");
                }
            } elseif ($ctype == 'lookupModel') {
                if ($cFieldDetails->relation->entity_id != $this->relatedEntity->_id) {
                    throw new \Exception("Error. Controlling field for fillFromList method does not match the current field's related entity.");
                }
            }

            $this->rules('fill_from_list', $controllingField);
            $this->disable();
        } else {
            throw new \Exception("Error. Field rule fillFromList is not allowed in fields with type '".$this->type->name."'");
        }

        return $this;

    }

    public function filteredBy($sourceField, $controllingField)
    {

        if ($this->type->name == 'lookupModel') {

            $this->validateFieldProperties();

            if ($this->field->level) {
                throw new \Exception('Error. Level and sourceFieldFilter attribute can not be applied concurrently on the same field.');
            }

            if (is_array($this->displayFields)) {
                if (! in_array($sourceField, $this->displayFields)) {
                    $this->includeFields($sourceField);
                }
            }

            if (! $sourceField || ! $controllingField) {
                throw new \Exception('Error. filteredBy method must have a filter source field and a controlling field.');
            }

            $sFieldDetails = $this->relatedEntity->fields()->where('name', $sourceField)->first();
            $stype = $sFieldDetails->fieldType->name;

            if ($stype != 'lookupModel' && $stype != 'picklist') {
                throw new \Exception('Error. Source field for lookupModel must only be of type lookupModel or picklist field.');
            }

            $cFieldDetails = $this->checkControllingField($controllingField);
            $ctype = $cFieldDetails->fieldType->name;

            if ($ctype != $stype) {
                throw new \Exception('Error. Controlling field and source field must be of the same type.');
            }

            if ($ctype == 'picklist') {
                if ($cFieldDetails->listName != $sFieldDetails->listName) {
                    throw new \Exception('Error. Picklist controlling field and source field must have the same listname.');
                }
            } elseif ($ctype == 'lookupModel') {
                if ($cFieldDetails->relation->entity_id != $sFieldDetails->relation->entity_id) {
                    throw new \Exception('Error. LookupModel controlling field and source field must have the same related entity.');
                }
            } else {
                throw new \Exception('Error. Controlling field for lookupModel must only be of type lookupModel or picklist field.');
            }

            $this->rules('filtered_by', $controllingField);
            $this->setFieldAttribute('filterSourceField', $sourceField);
        } else {
            throw new \Exception("Field rule filtered_by is not allowed in fields with type '".$this->type->name."'");
        }

        return $this;
    }

    public function level($filterLevel)
    {

        if ($this->type->name = 'lookupModel') {

            $this->validateFieldProperties();

            if ($this->field->filterSourceField) {
                throw new \Exception('Error. Level and sourceFieldFilter attribute can not be applied concurrently on the same field.');
            }

            if (is_array($this->displayFields)) {
                if (! in_array($filterLevel, $this->displayFields)) {
                    $this->includeFields($filterLevel);
                }
            }

            $sFieldDetails = $this->relatedEntity->fields()->where('name', $filterLevel)->first();
            $stype = $sFieldDetails->fieldType->name;

            if ($stype != 'number') {
                throw new \Exception('Error. Level field must only be of type number.');
            }

            $num = $this->relatedEntity->getModel()->where($filterLevel, '<=', 0)->first();

            if (! $num) {
                $this->setFieldAttribute('level', $filterLevel);
            } else {
                throw new \Exception('Error. Data from field "'.$filterLevel.'" must only be positive numbers.');
            }
        } else {
            throw new \Exception("Field attribute level is not allowed in fields with type '".$this->type->name."'");
        }

        return $this;
    }

    public function autoFill($sourceFields, $fillFields)
    {

        if ($this->type->name = 'lookupModel') {
            $this->validateFieldProperties();

            if (! is_array($sourceFields) || ! is_array($fillFields)) {
                throw new \Exception('Values for rule auto_fill must be of type array.');
            }

            if (count($sourceFields) != count($fillFields)) {
                throw new \Exception('Value count for sourceFields and fillFields in rule auto_fill must be equal.');
            }

            $sFields = $this->relatedEntity->fields();
            $fFields = $this->entity->fields();

            $relatedEntityFieldNames = $sFields->pluck('name')->toArray();
            $unknownFieldNames = array_diff($sourceFields, $relatedEntityFieldNames);
            if (count($unknownFieldNames)) {
                throw new \Exception('Error. The following field/s named in entity "'.$this->relatedEntity->name."' are not found: ".implode(', ', $unknownFieldNames));
            }

            $mergeFields = [];
            foreach ($sourceFields as $key => $value) {
                $sDetails = $this->relatedEntity->fields()->where('name', $sourceFields[$key])->first();
                $stype = $sDetails->fieldType->name;

                $fDetails = $this->entity->fields()->where('name', $fillFields[$key])->first();
                $ftype = $fDetails->fieldType->name;

                if ($ftype == 'boolean' && $stype != 'boolean') {
                    throw new \Exception('Error. Source field for field : "'.$fillFields[$key].'" must be of type boolean.');
                }

                if (($ftype == 'number' || $ftype == 'currency') && ($stype != 'number' && $stype != 'currency')) {
                    throw new \Exception('Error. Source field for field : "'.$fillFields[$key].'" must be of type number or currency.');
                }

                if ($ftype == 'date' && $stype != 'date') {
                    throw new \Exception('Error. Source field for field : "'.$fillFields[$key].'" must be of type date.');
                }

                if ($ftype == 'file' || $ftype == 'image' || $ftype == 'rollUpSummary' || $ftype == 'password') {
                    throw new \Exception('Error.Fill field values for auto_fill rule must not contain '.$ftype.' field type.');
                }

                if ($ftype == 'lookupModel' && $stype != 'lookupModel') {
                    throw new \Exception('Error. Source field for field : "'.$fillFields[$key].'" must be of type lookupModel.');
                }

                if ($ftype == 'lookupModel') {
                    if ($fDetails->relation->entity_id != $sDetails->relation->entity_id) {
                        throw new \Exception('Error. Source field for field "'.$fillFields[$key].'" must have the same related entity.');
                    }

                    $ssRules = ['ss_dropdown', 'ss_list_view', 'radiobutton', 'ss_pop_up', 'radiobutton_inline'];
                    $fRules = $fDetails->rules()->whereIn('name', $ssRules)->first();
                    $sRules = $sDetails->rules()->whereIn('name', $ssRules)->first();

                    if (($fRules) && ! ($sRules)) {
                        throw new \Exception('Error. Source field for field "'.$fillFields[$key].'" must be a single-select lookup field.');
                    }

                }

                if ($ftype == 'picklist' && $stype != 'picklist') {
                    throw new \Exception('Error. Source field for field : "'.$fillFields[$key].'" must be of type picklist.');
                }

                if ($ftype == 'picklist') {
                    if ($fDetails->listName != $sDetails->listName) {
                        throw new \Exception('Error. Source field for field "'.$fillFields[$key].'" must have the same listName.');
                    }

                    $ssRules = ['ss_dropdown', 'ss_list_view', 'radiobutton', 'radiobutton_inline'];
                    $fRules = $fDetails->rules()->whereIn('name', $ssRules)->first();
                    $sRules = $sDetails->rules()->whereIn('name', $ssRules)->first();

                    if (($fRules) && ! ($sRules)) {
                        throw new \Exception('Error. Source field for field "'.$fillFields[$key].'" must be a single-select picklist field.');
                    }
                }

                $genType = ['text', 'longText', 'richTextbox', 'label'];
                if (in_array($ftype, $genType) && $stype == 'picklist') {
                    $mergeFields[] = [$sourceFields[$key], $fillFields[$key], $sDetails->listName];
                } else {
                    $mergeFields[] = [$sourceFields[$key], $fillFields[$key]];
                }
            }
            $mergeFields = makeObject($mergeFields);
            $this->rules('auto_fill', $mergeFields);
        } else {
            throw new \Exception("Error. Auto_fill rule is not allowed in fields with type '".$this->type->name."'");
        }

        return $this;
    }

    public function autoFillValue($sourceFields, $fillFields)
    {

        if ($this->type->name = 'lookupModel') {
            $this->validateFieldProperties();

            if (! is_array($sourceFields) || ! is_array($fillFields)) {
                throw new \Exception('Parameters for rule auto_fill_value must be of type array.');
            }

            if (count($sourceFields) != count($fillFields)) {
                throw new \Exception('Value count for sourceFields and fillFields in rule auto_fill_value must be equal.');
            }

            $sFields = $this->relatedEntity->fields();
            $fFields = $this->entity->fields();

            $relatedEntityFieldNames = $sFields->pluck('name')->toArray();
            $unknownFieldNames = array_diff($sourceFields, $relatedEntityFieldNames);

            if (count($unknownFieldNames)) {
                throw new \Exception('Error. The following field/s named in entity "'.$this->relatedEntity->name."' are not found: ".implode(', ', $unknownFieldNames));
            }

            $entityFieldNames = $fFields->pluck('name')->toArray();
            $unknownFieldNames = array_diff($fillFields, $entityFieldNames);

            if (count($unknownFieldNames)) {
                throw new \Exception('Error. The following field/s named in entity "'.$this->entity->name."' are not found: ".implode(', ', $unknownFieldNames));
            }

            $mergeFields = [];

            foreach ($sourceFields as $key => $value) {

                $sDetails = $this->relatedEntity->fields()->where('name', $sourceFields[$key])->first();
                $stype = $sDetails->fieldType->name;

                $fDetails = $this->entity->fields()->where('name', $fillFields[$key])->first();
                $ftype = $fDetails->fieldType->name;

                if ($ftype != 'readOnly') {
                    throw new \Exception('Error. Field "'.$fillFields[$key].'" must be of type readOnly.');
                }

                if ($stype == 'picklist') {
                    $mergeFields[] = [$sourceFields[$key], $fillFields[$key], $sDetails->listName];
                } else {
                    $mergeFields[] = [$sourceFields[$key], $fillFields[$key]];
                }

            }
            $mergeFields = makeObject($mergeFields);
            $this->rules('auto_fill_value', $mergeFields);
        } else {
            throw new \Exception("Error. Auto_fill_value rule is not applicable in fields with type '".$this->type->name."'");
        }

        return $this;
    }

    /**
     * @param  bool  $isCheckbox
     * @return $this
     *
     * @throws \Exception
     */
    public function checkbox($isCheckbox = true)
    {
        $this->rules('checkbox', $isCheckbox);

        return $this;
    }

    public function switchDisplay($isSwitch = true)
    {
        $this->rules('switch', $isSwitch);

        return $this;
    }

    public function checkboxInline($isCheckbox = true)
    {
        $this->rules('checkbox_inline', $isCheckbox);

        return $this;
    }

    /**
     * @param  bool  $isRadio
     * @return $this
     *
     * @throws \Exception
     */
    public function radioButton($isRadio = true)
    {
        $this->rules('radiobutton', $isRadio);

        return $this;
    }

    public function radioButtonInline($isRadio = true)
    {
        $this->rules('radiobutton_inline', $isRadio);

        return $this;
    }

    /**
     * @param  bool  $isDropDown
     * @return $this
     *
     * @throws \Exception
     */
    public function ssDropDown($isDropDown = true)
    {
        $this->rules('ss_dropdown', $isDropDown);

        return $this;
    }
    //
    // public function arbitraryTime($isArbit = true) {
    //     $this->rules('arbitrary_time', $isArbit);
    //     return $this;
    // }
    //
    // public function intervalTime($isInter = true) {
    //     $this->rules('interval_time', $isInter);
    //     return $this;
    // }

    /**
     * @param  bool  $isMulti
     * @return $this
     *
     * @throws \Exception
     */
    public function msDropdown($isMulti = true)
    {
        $this->rules('ms_dropdown', $isMulti);

        return $this;
    }

    public function ssListView($isListView = true)
    {
        $this->rules('ss_list_view', $isListView);

        return $this;
    }

    public function msListView($isListView = true)
    {
        $this->rules('ms_list_view', $isListView);

        return $this;
    }

    public function ssPopUp($isSSpopUp = true)
    {
        $this->rules('ss_pop_up', $isSSpopUp);

        return $this;
    }

    public function msPopUp($isMSpopUp = true)
    {
        $this->rules('ms_pop_up', $isMSpopUp);

        return $this;
    }

    public function min($min = 0)
    {
        $this->rules('min', $min);

        return $this;
    }

    public function max($max = 0)
    {
        $this->rules('max', $max);

        return $this;
    }

    public function tabMultiSelect()
    {
        if ($this->type->name == 'picklist') {
            if ($this->field->listName) {
                if (is_string($this->pickList)) {
                    $currentList = $this->pickListRepository->getList($this->field->listName, false);
                } else {
                    $currentList = $this->pickList;
                }

                if ($currentList && ! (($currentList->catSrcListName) ?? null)) {
                    //if ($currentList && $currentList->catSrcListName)
                    throw new \Exception('Error. tab_multi_select display rule is only applicable to picklist field with categorized list items.');
                }
            } else {
                throw new \Exception("Error. Please specify field's listname first before calling tabMultiSelect method.");
            }
        } elseif ($this->type->name == 'lookupModel' && ! $this->field->filterSourceField) {
            throw new \Exception('Error. tab_multi_select display rule is only applicable to lookupModel field with filterSourceField attribute.');
        }

        $this->rules('tab_multi_select', true);

        return $this;
    }

    public function between($min = 0, $max = 0)
    {

        $minMax = makeObject([
            'min' => $min,
            'max' => $max,
        ]);

        $this->rules('between', $minMax);

        return $this;
    }

    public function digitsBetween($min = 0, $max = 0)
    {

        $minMax = makeObject([
            'min' => $min,
            'max' => $max,
        ]);

        $this->rules('digits_between', $minMax);

        return $this;

    }

    protected function verifyField($field)
    {
        // operators: <, >, <=, >=, =, !=, in
        if (is_string($field)) {
            $referenceField = $this->fieldRepository->where(['name' => $field, 'module_id' => $this->module->_id]);
            if (! $referenceField) {
                throw new \Exception('Error. Field "'.$field."' does not yet exist in this module.");
            }
        } elseif ($field instanceof $this->fieldClass) {
            $referenceField = $field;
        }

        return $referenceField;
    }

    protected function verifyOperator($operator)
    {
        if (! in_array($operator, ['=', '!=', '<', '>', '<=', '>=', 'IN', 'NOT_IN', 'LIKE'])) {
            throw new \Exception('Error. Operator "'.$operator."' is not recognized.");
        }

        return $operator;
    }

    public function defaultValue($defaultValue, $type = 'literal', $value = null)
    {

        if ($this->type->name == 'picklist') {
            $this->rules('default_value', $defaultValue, true);
        } elseif ($this->type->name == 'lookupModel') {
            if ($type == 'literal') {
                $type = '_id';
            }
            $rule = ['value' => $defaultValue, 'field' => $type];
            $this->rules('default_value', $rule, true);
        } //$type = fieldsource

        else {
            if (is_array($defaultValue)) {
                throw new \Exception("Default value of type array is not allowed in fields with type '".$this->type->name."'");
            } elseif ($this->type->name == 'date' || $this->type->name == 'time') {
                $dateData = $this->validateDateTime($type, $value, $defaultValue);
                $this->rules('default_value', $dateData);
            } else {
                $this->rules('default_value', $defaultValue);
            }
        }

        return $this;
    }

    public function currencySource($type, $concat, $field = null)
    {

        $source = [];

        if ($this->type->name != 'currency' && $this->type->name != 'formula' && $this->type->name != 'rollUpSummary') {
            throw new \Exception('Error. Currency source field attribute is applicable in currency and formula fieldtypes only.');
        }

        if ($this->type->name == 'formula' && $this->field->formulaType != 'currency') {
            throw new \Exception("Error. Currency source field attribute is only applicable in formula fieldtype with 'Currency' formulaType.");
        }

        if (! in_array($concat, ['code', 'symbol'])) {
            throw new \Exception("Error. Undefined 'concat' parameter for currencySource method.");
        }

        if (! in_array($type, ['lookupModel', 'company', 'country'])) {
            throw new \Exception("Error. Undefined 'type' parameter for currencySource method.");
        }

        $source = ['type' => $type,
            'concat' => $concat];

        if ($type == 'lookupModel') {
            if (! $field) {
                throw new \Exception('Error. Missing field parameter for currencySource method.');
            }

            $field = $this->checkControllingField($field);

            if (! $field || $field->fieldType->name != 'lookupModel' || $field->relation->relatedEntity->name != 'Currency') {
                throw new \Exception('Error. Invalid field parameter for currencySource method. Field must be a lookupModel field of Currency Entity.');
            }

            $source['field'] = $field->name;
            if ($this->entity->_id != $field->entity_id) {
                $source['entity_id'] = $field->entity_id;
            }
        }

        $this->setFieldAttribute('currencySource', $source);

        return $this;
    }

    public function setValue($ruleName, $expression, $setValue, $type = 'literal', $value = null)
    {

        if ($this->type->name == 'picklist') {
            $rule = ['expression' => $expression, 'value' => $setValue];
            $this->rules($ruleName, $rule, true);
        } elseif ($this->type->name == 'lookupModel') {
            if ($type == 'literal') {
                $type = '_id';
            }

            $rule = ['expression' => $expression, 'value' => $setValue, 'field' => $type];
            $this->rules($ruleName, $rule, true);
        } else {
            if (is_array($setValue)) {
                throw new \Exception("Value of type array is not allowed in fields with type '".$this->type->name."'");
            }

            if ($this->type->name == 'date' || $this->type->name == 'time') {
                $dateData = $this->validateDateTime($type, $value, $setValue, $expression);
                $this->rules($ruleName, $dateData);
            } else {
                $rule = makeObject([
                    'expression' => $expression,
                    'value' => $setValue,
                ]);
                $this->rules($ruleName, $rule);
            }
        }

        return $this;
    }

    /****added number rules****/

    /**
     * @param  int  $places
     * @return $this
     *
     * @throws \Exception
     */
    public function decimal($places = 2)
    {
        $this->rules('decimal', $places);

        return $this;
    }

    public function commaSeparated($isSeparated = true)
    {
        $this->rules('comma_separated', $isSeparated);

        return $this;
    }

    public function roundOff($toNearest = 'tens')
    {

        if (! in_array($toNearest, ['tens', 'hundreds', 'thousands', 'millions'])) {
            throw new \Exception('Error. Field can only be rounded off to the nearest tens, hundreds, thousands or millions');
        }
        $this->rules('round_off', $toNearest);

        return $this;
    }

    /*********************************************** FILTER METHODS **********************************************/

    /**
     * @return $this
     */
    public function filterQuery($query)
    {
        if (! starts_with($query, '>>')) {
            $queryOrFields = DQBuilder::replaceQueryPatterns($query, $this->entity, false);
            if (is_array($queryOrFields)) {
                $this->setFieldAttribute('filterQueryParams', $queryOrFields);
            }
        }
        $this->setFieldAttribute('filterQuery', $query);

        return $this;
    }

    public function acceptsMultiple($acceptsMultiple = true)
    {
        if ($this->type->name == 'file') {
            $this->setFieldAttribute('acceptsMultiple', $acceptsMultiple);
        } else {
            throw new \Exception('Field attribute "acceptsMultiple" is not allowed in fields with type '.$this->type->name.'.');
        }

        return $this;
    }

    public function allowFileDeletion($allowFileDeletion = true)
    {
        if ($this->type->name == 'file') {
            $this->setFieldAttribute('allowFileDeletion', $allowFileDeletion);
        } else {
            throw new \Exception('Field attribute "allowFileDeletion" is not allowed in fields with type '.$this->type->name.'.');
        }

        return $this;
    }

    public function flagWith($fieldName = null)
    {
        $field = $this->entity->fields()->where('name', $fieldName)->first();

        if (! $field) {
            throw new \Exception('Error. Unable to find "'.$fieldName.'" from the list of '.$this->entity->name.' fields.');
        }

        $this->rules('flag_with', $fieldName);

        return $this;
    }
}
