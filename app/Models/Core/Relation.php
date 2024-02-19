<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class Relation extends Base
{
    protected $connection = 'mongodb';

    public function field()
    {
        return $this->belongsTo(Field::class);
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
