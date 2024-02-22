<?php

namespace App\Models\Customer;

use App\Models\Employee\Employee;
use App\Models\Model\Base;

class Lead extends Base
{
    protected $connection = 'mongodb';

    public function owner()
    {
        return $this->belongsTo(Employee::class, 'owner_id');
    }
}
