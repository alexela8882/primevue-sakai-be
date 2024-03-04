<?php

namespace App\Builders;

use App\Models\Core\Entity;
use App\Models\Core\Field;

class EntityField
{
    protected $user;

    public function __construct()
    {

        $this->user = \Auth::guard('api')->user();
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    public function pair($entity, $field)
    {

        if ($entity instanceof Entity && $field instanceof Field) {
            $obj = new \StdClass();

            $obj->entity = $entity;
            $obj->field = $field;

            return $obj;
        } else {
            throw new \Exception('Error. Either entity or field given in "make" method is not vaid');
        }
    }

    public function resolveEntity($entity, $currentUserAllowed = true)
    {

        if (is_string($entity)) {
            // check if entity is currentUser
            if (strtolower($entity) == 'currentuser' && $currentUserAllowed) {
                $entityModel = $this->user;
                if ($this->user) {
                    $entityModel->isCurrentUser = true;
                }
            } else {
                // get the model class name associated with the entity
                $entityModel = Entity::where(['name' => $entity])->first();
            }
            if (! $entityModel) {
                throw new \Exception('Entity named "'.$entity."' is not recognized");
            }

            return $entityModel;
        } elseif (is_object($entity) && $entity instanceof Entity) {
            return $entity;
        } else {
            throw new \Exception('Error. Entity given is of unrecognized type');
        }

        return $this;
    }

    public function resolveField($field, $entityName, $returnBoolean = false)
    {

        $entity = $this->resolveEntity($entityName);

        $fieldModel = null;

        if (is_string($field)) {
            if ($field == '_id') {
                $fieldModel = new Field(['name' => '_id']);
            } else {
                if ($entityName == 'currentUser') {

                    if ($field == 'people') {
                        $fieldModel = $this->user->getPeople();
                    } else {
                        $fieldModel = $this->user->{$field};
                    }

                    if ($fieldModel instanceof Collection) {
                        $fieldModel = $fieldModel->toArray();
                        $fieldModel = "['".implode("','", $fieldModel)."']";
                    }
                } else {
                    $fieldModel = Field::where(['name' => $field, 'entity_id' => $entity->_id])->first();
                }
                if (! $fieldModel) {
                    throw new \Exception('Field named "'.$field.'" is not found in entity "'.$entity->name.'"');
                }
            }
        } elseif (is_object($field) && $field instanceof Field) {
            $fieldModel = $field;
        } else {
            throw new \Exception('Error. Field given is of unrecognized type');
        }
        if (! $returnBoolean) {
            return $fieldModel;
        } else {
            return $fieldModel != null;
        }
    }

    public function checkEntityFields($entity, $fieldNames, $checked = false)
    {
        if ($checked) {
            $entity = $this->resolveEntity($entity);
        }

        // if field name to check is only one
        if (is_string($fieldNames)) {
            if ($fieldNames == '_id') {
                $field = new Field(['name' => '_id']);
            } else {

                $field = $entity->fields()->where('name', $fieldNames)->first();
                if (! $field) {
                    throw new \Exception('Field named "'.$fieldNames."' is not recognized in entity '".$entity->name."'.");
                }
            }

            return $field;
        }

        $entityFieldNames = $entity->fields()->pluck('name')->toArray();
        $entityFieldNames[] = '_id';
        $unknownFields = array_diff($fieldNames, $entityFieldNames);
        if ($unknownFields) {
            throw new \Exception('Error. The following fields are not recognized in entity '.$entity->name.': '.implode(',', $unknownFields));
        }
    }

    /**
     * @param  null  $requireFieldType
     * @param  bool|string  $checked
     * @return $this
     *
     * @throws \Exception
     */
    public function checkInstanceFieldValue($entity, $entityInstance, $smartFieldName, $requireFieldType = null, $checked = false)
    {

        if ($checked) {
            $entity = $this->resolveEntity($entity);
        }

        $smartFieldNameElems = explode('.', $smartFieldName);

        $currentEntity = $entity;
        $fieldName = $entity->name;
        $value = $entityInstance;

        // foreach($smartFieldNameElems as $smartFieldNameElem) {
        $smartFieldNameElem = $smartFieldNameElems[0];
        $field = $this->checkEntityFields($currentEntity, $smartFieldNameElem);
        $currentEntity = $field->entity;
        $fieldName .= '.'.$smartFieldNameElem;
        try {
            if ($field->rusType) {

                $val = $value->{$smartFieldNameElem} ?? null;

                if ($val === null || $val === '') {
                    RusResolver::setEntity($currentEntity);
                    $value = RusResolver::resolve($value, $field);
                } else {
                    $value = $value->{$smartFieldNameElem};
                }
            } // if RUS

            elseif ($field->formulaType) {

                $val = $value->{$smartFieldNameElem} ?? null;

                if ($val === null || $val === '') {
                    FormulaParser::setEntity($currentEntity);
                    $value = FormulaParser::parseField($field, $value);
                } else {
                    $value = $value->{$smartFieldNameElem};
                }
            } elseif ($field->fieldType->name == 'lookupModel') {
                $lookupEntity = $field->relation();
                $value = $value->{$smartFieldNameElem};
                $lookupVal = $field->relation->relatedEntity->getModel()->find($value);
                if ($lookupVal) {
                    $value = $lookupVal[$smartFieldNameElems[1]];
                }
            } elseif ($field->fieldType->name == 'number' || $field->fieldType->name == 'currency') {
                if ($value->{$smartFieldNameElem}) {
                    $value = $value->{$smartFieldNameElem};
                } else {
                    $value = 0;
                }
            } else {
                $value = $value->{$smartFieldNameElem};
            }
        } catch (\Exception $e) {
            throw new \Exception('Error. Unidentified field named '.$fieldName);
        }
        // }

        if ($requireFieldType && $field->fieldType->name != $requireFieldType) {
            throw new \Exception('Error. Field '.$field->name.' must be of type '.$requireFieldType);
        } elseif ($requireFieldType && $field->fieldType->name == 'picklist' && $value) {
            $value = picklist_item($field->listName, $value);
            if ($value) {
                $value = $value->value;
            }
        }

        return $value;
    }

    public function extractEntityAndField($coupledData, $separator = '::')
    {
        $elements = explode($separator, $coupledData);
        if (count($elements) != 2) {
            throw new \Exception('Invalid fieldName "'.$coupledData.'". Expected syntax is {Entity_NAME}'.$separator.'{FIELD_NAME}');
        }

        $entityName = $elements[0];
        $fieldName = $elements[1];

        //        if(starts_with($coupledData, 'current')) {
        //            dd($entityName);
        //        }

        $entity = $this->resolveEntity($entityName);

        $field = $this->resolveField($fieldName, $entityName);

        return [
            'entity' => $entity,
            'field' => $field,
        ];
    }

    /**
     * @return $this|bool|null
     *
     * @throws \Exception
     */
    public function fieldExistsInEntity($fieldName, $entityName)
    {
        $entityName = collect(explode('\\', $entityName))->pop();

        return $this->resolveField($fieldName, $entityName, true);
    }
}
