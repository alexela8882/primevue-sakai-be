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

    public function formulaReturnTypes()
    {
        return $this->belongsToMany('App\Models\Core\FieldType', null, 'return_type_ids', 'formula_type_id');
    }
}
