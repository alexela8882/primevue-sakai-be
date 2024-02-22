<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class Module extends Base
{
    protected $connection = 'mongodb';

    public function entity()
    {
        return $this->belongsTo(Entity::class, 'mainEntity', '_id');
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }
}
