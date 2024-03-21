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

    public function rules()
    {
        return $this->belongsToMany('App\Models\Core\Rule', null, 'field_type_ids', 'rule_ids');
    }

    public function defaultRules()
    {
        return $this->embedsMany('App\Models\Core\DefaultRule', 'defaultRule', '_id');
    }
}
