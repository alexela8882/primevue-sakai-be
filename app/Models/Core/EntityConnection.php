<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class EntityConnection extends Base
{
    protected $connection = 'mongodb';

    public function entities()
    {
        return $this->belongsToMany(Entity::class, null, 'connection_ids', 'entity_ids');
    }
}
