<?php

namespace App\Services;

use App\Models\Core\Entity;
use App\Models\Core\Field;
use App\Models\Model\Base as Model;

class RollUpSummaryResolver
{
    protected $entity;

    protected $fields;

    protected static $value;

    public function setEntity(Entity $entity)
    {
        $this->entity = $entity;
        $this->fields = $entity->fields()->with('fieldType')->get();
    }

    public function resolve(Model $model, Field $field)
    {

        if ($model->{$field->name}) {
            $value = $model->{$field->name};
        } else {

            $rusEntity = Entity::where('name', $field->rusEntity)->first();

            if ($rusEntity) {

                $rusValue = $rusEntity->getModel()->where($field->connectingField, $model->_id);

                switch ($field->rusType) {
                    case 'count':
                        $value = $rusValue->count();
                        break;
                    case 'min':
                        $value = $rusValue->min($field->aggregateField);
                        break;
                    case 'max':
                        $value = $rusValue->max($field->aggregateField);
                        break;
                    case 'sum':
                        $value = $rusValue->sum($field->aggregateField);
                        break;
                }
            } else {
                $value = 0;
            }
        }

        return $value;
    }

    public function resolveToken(Model $model, Field $field)
    {
        $tvals = Config::get('constants.TYPE_VAL')['T_LITERAL_NUMBER'];
        $value = $this->resolve($model, $field);

        return [$value, 0, $tvals];
    }
}
