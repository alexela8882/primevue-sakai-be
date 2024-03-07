<?php

namespace App\Models\User;

use App\Models\Model\Base;

class Permission extends Base
{
    protected $connection = 'mongodb';

    public function module()
    {
        return $this->belongsTo('App\Models\Module\Module');
    }

    public function roles()
    {
        return $this->belongsToMany('App\Models\User\Role', null, 'permission_id', 'role_id');
    }
}
