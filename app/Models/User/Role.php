<?php

namespace App\Models\User;

use App\Models\Model\Base;

class Role extends Base
{
    protected $connection = 'mongodb';

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, null, 'role_id', 'permission_id');
    }

    public function filters()
    {
        return $this->hasMany(RoleFilter::class);
    }

    public function underRole()
    {
        return $this->belongsToMany('App\Models\Core\Role', null, 'handled_role_id', 'under_role_id');
    }

    public function handledRole()
    {
        return $this->belongsToMany('App\Models\Core\Role', null, 'under_role_id', 'handled_role_id');
    }
}
