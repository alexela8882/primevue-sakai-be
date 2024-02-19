<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class Module extends Base
{
    protected $connection = 'mongodb';

    public function mainEntity()
    {
        return $this->belongsTo(Entity::class, 'mainEntity', '_id');
    }
}
