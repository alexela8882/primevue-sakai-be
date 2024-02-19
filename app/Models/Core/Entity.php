<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class Entity extends Base
{
    protected $connection = 'mongodb';

    public function fields()
    {
        return $this->hasMany(Field::class, 'entity_id', '_id');
    }

    public function panels()
    {
        return $this->hasMany(Panel::class, 'entity_id', '_id');
    }

    public function mainModule()
    {
        return $this->hasOne(Module::class, 'mainEntity', '_id');
    }
}
