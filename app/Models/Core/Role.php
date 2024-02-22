<?php

namespace App\Models\Core;

use App\Models\Model\Base;

class Role extends Base
{
    protected $connection = 'mongodb';

    public function filters()
    {
        return $this->hasMany(RoleFilter::class);
    }
}
