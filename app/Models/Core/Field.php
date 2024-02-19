<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class Field extends Base
{
    protected $connection = 'mongodb';

    public function relation()
    {
        return $this->hasOne(Relation::class);
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function fieldType()
    {
        return $this->belongsTo(FieldType::class);
    }

    public function rules()
    {
        return $this->hasMany(Rule::class);
    }
}
