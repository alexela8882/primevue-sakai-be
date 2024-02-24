<?php

namespace App\Models\Auth;

use App\Models\Model\Base;
use App\Models\User;

class LoginHistory extends Base
{
    protected $connection = 'mongodb';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
