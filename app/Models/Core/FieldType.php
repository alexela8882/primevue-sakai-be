<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class FieldType extends Base
{
    protected $connection = 'mongodb';

    public function fields()
    {
        return $this->hasMany(Field::class, 'field_type_id', '_id');
    }
}
