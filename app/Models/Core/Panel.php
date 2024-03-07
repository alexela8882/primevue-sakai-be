<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class Panel extends Base
{
    protected $connection = 'mongodb';

    public function sections()
    {
        return $this->hasMany(Section::class, 'panel_id', '_id');
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class, 'entity_id', '_id');
    }
}
