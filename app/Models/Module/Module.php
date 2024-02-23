<?php

namespace App\Models\Module;

use App\Models\Core\Entity;
use App\Models\Model\Base;

class Module extends Base
{
    protected $connection = 'mongodb';

    public function entity()
    {
        return $this->belongsTo(Entity::class, 'mainEntity', '_id');
    }
}
