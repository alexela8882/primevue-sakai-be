<?php

namespace App\Models\Module;

use App\Models\Core\Entity;
use App\Models\Core\ModuleQuery;
use App\Models\Core\Permission;
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

    public function queries()
    {
        return $this->hasMany(ModuleQuery::class);
    }
}
