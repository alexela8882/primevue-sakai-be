<?php

namespace App\Models\User;

use App\Models\Model\Base;

class Role extends Base
{
    protected $connection = 'mongodb';

    public function permissions()
    {
        return $this->belongsToMany('App\Models\User\Permission', null, 'role_id', 'permission_id');
    }
}
